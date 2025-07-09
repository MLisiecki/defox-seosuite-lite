<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Sitemap;

use Defox\SEOSuite\Model\Sitemap\Analytics\StatisticsManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap dashboard controller
 * 
 * Displays comprehensive analytics and statistics for sitemap generation including:
 * - KPI cards with key metrics
 * - Charts for trends and performance
 * - Recent generation history
 * - Performance analytics
 */
class Dashboard extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Defox_SEOSuite::sitemap_dashboard';

    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var StatisticsManager
     */
    private StatisticsManager $statisticsManager;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param StatisticsManager $statisticsManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        StatisticsManager $statisticsManager,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->statisticsManager = $statisticsManager;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if ($this->getRequest()->isAjax()) {
            return $this->getAjaxData();
        }

        return $this->showDashboard();
    }

    /**
     * Show dashboard page
     *
     * @return ResultInterface
     */
    private function showDashboard(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Defox_SEOSuite::sitemap_dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Sitemap Management - Analytics'));
        
        return $resultPage;
    }

    /**
     * Get AJAX data for dashboard
     *
     * @return ResultInterface
     */
    private function getAjaxData(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $action = $this->getRequest()->getParam('ajax_action');
            
            switch ($action) {
                case 'kpi_data':
                    return $result->setData($this->getKpiData());
                    
                case 'chart_data':
                    return $result->setData($this->getChartData());
                    
                case 'recent_history':
                    return $result->setData($this->getRecentHistory());
                    
                case 'performance_stats':
                    return $result->setData($this->getPerformanceStats());
                    
                case 'provider_comparison':
                    return $result->setData($this->getProviderComparison());
                    
                case 'store_performance':
                    return $result->setData($this->getStorePerformance());
                    
                case 'export_data':
                    return $this->handleExport();
                    
                case 'benchmark_data':
                    return $result->setData($this->getBenchmarkData());
                    
                case 'filtered_stats':
                    return $result->setData($this->getFilteredStats());
                    
                default:
                    return $result->setData([
                        'error' => true,
                        'message' => __('Invalid AJAX action')
                    ]);
            }
            
        } catch (\Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => __('Error loading dashboard data: %1', $e->getMessage())
            ]);
        }
    }

    /**
     * Get KPI data for dashboard cards
     *
     * @return array
     */
    private function getKpiData(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeIds = array_map(function($store) {
                return (int)$store->getId();
            }, $stores);

            // Get statistics for last 30 days
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify('-30 days');
            
            $stats = $this->statisticsManager->getStatisticsByDateRange(
                $startDate,
                $endDate,
                $storeIds
            );

            // Calculate KPIs
            $totalGenerations = count($stats);
            $successfulGenerations = array_filter($stats, function($stat) {
                return $stat['success'] ?? false;
            });
            
            $successRate = $totalGenerations > 0 
                ? round((count($successfulGenerations) / $totalGenerations) * 100, 1)
                : 0;

            $totalFiles = array_sum(array_column($stats, 'files_generated'));
            $totalUrls = array_sum(array_column($stats, 'total_urls'));
            
            $avgDuration = $totalGenerations > 0
                ? round(array_sum(array_column($stats, 'duration_seconds')) / $totalGenerations, 2)
                : 0;

            // Get last generation info
            $lastGeneration = $this->statisticsManager->getLastGenerationByStore();
            $lastGenerationTime = null;
            $lastGenerationStatus = null;
            
            if (!empty($lastGeneration)) {
                $lastGenerationTime = $lastGeneration['generation_time'] ?? null;
                $lastGenerationStatus = $lastGeneration['success'] ?? false ? 'success' : 'error';
            }

            return [
                'success' => true,
                'data' => [
                    'total_generations' => $totalGenerations,
                    'success_rate' => $successRate,
                    'total_files' => $totalFiles,
                    'total_urls' => $totalUrls,
                    'avg_duration' => $avgDuration,
                    'last_generation_time' => $lastGenerationTime,
                    'last_generation_status' => $lastGenerationStatus,
                    'active_stores' => count($stores)
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get chart data for trends
     *
     * @return array
     */
    private function getChartData(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeIds = array_map(function($store) {
                return (int)$store->getId();
            }, $stores);

            // Get daily statistics for last 30 days
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify('-30 days');
            
            $dailyStats = $this->statisticsManager->getDailyStatistics(
                $startDate,
                $endDate,
                $storeIds
            );

            // Prepare timeline chart data
            $timelineData = [];
            $performanceData = [];
            
            for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                $dateStr = $date->format('Y-m-d');
                $dayStats = $dailyStats[$dateStr] ?? [];
                
                $timelineData[] = [
                    'date' => $dateStr,
                    'generations' => count($dayStats),
                    'success_rate' => $this->calculateDaySuccessRate($dayStats),
                    'total_urls' => array_sum(array_column($dayStats, 'total_urls')),
                    'avg_duration' => $this->calculateAvgDuration($dayStats)
                ];
                
                if (!empty($dayStats)) {
                    $performanceData[] = [
                        'date' => $dateStr,
                        'avg_duration' => $this->calculateAvgDuration($dayStats),
                        'total_files' => array_sum(array_column($dayStats, 'files_generated')),
                        'errors_count' => array_sum(array_column($dayStats, 'errors_count'))
                    ];
                }
            }

            // Get provider performance data
            $providerStats = $this->statisticsManager->getProviderPerformanceStats($storeIds);

            return [
                'success' => true,
                'data' => [
                    'timeline' => $timelineData,
                    'performance' => $performanceData,
                    'providers' => $providerStats
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get recent generation history
     *
     * @return array
     */
    private function getRecentHistory(): array
    {
        try {
            // Check if this is a request for specific stat details
            $statId = $this->getRequest()->getParam('stat_id');
            $details = $this->getRequest()->getParam('details');
            
            if ($statId && $details) {
                return $this->getStatDetails((int)$statId);
            }
            
            $page = (int)$this->getRequest()->getParam('page', 1);
            $limit = (int)$this->getRequest()->getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $stores = $this->storeManager->getStores();
            $storeNames = [];
            foreach ($stores as $store) {
                $storeNames[$store->getId()] = $store->getName();
            }

            $recentStats = $this->statisticsManager->getRecentStatistics($limit, $offset);
            
            // Format data for display
            $formattedStats = [];
            foreach ($recentStats as $stat) {
                $formattedStats[] = [
                    'id' => $stat['stat_id'],
                    'store_name' => $storeNames[$stat['store_id']] ?? 'Unknown Store',
                    'store_id' => $stat['store_id'],
                    'generation_time' => $stat['generation_time'],
                    'duration' => round((float)$stat['duration_seconds'], 2),
                    'total_urls' => $stat['total_urls'],
                    'files_generated' => $stat['files_generated'],
                    'success' => (bool)$stat['success'],
                    'errors_count' => $stat['errors_count'] ?? 0,
                    'status_class' => $stat['success'] ? 'grid-severity-notice' : 'grid-severity-critical',
                    'status_text' => $stat['success'] ? __('Success') : __('Error'),
                    'file_size_mb' => round((float)$stat['total_file_size'] / 1024 / 1024, 2)
                ];
            }

            return [
                'success' => true,
                'data' => $formattedStats,
                'pagination' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'has_more' => count($recentStats) === $limit
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get detailed statistics for specific stat ID
     *
     * @param int $statId
     * @return array
     */
    private function getStatDetails(int $statId): array
    {
        try {
            $connection = $this->statisticsManager->getResourceConnection()->getConnection();
            $tableName = $this->statisticsManager->getResourceConnection()->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->where('stat_id = ?', $statId)
                ->limit(1);
                
            $stat = $connection->fetchRow($select);
            
            if (!$stat) {
                return [
                    'success' => false,
                    'error' => 'Statistics record not found'
                ];
            }
            
            $stores = $this->storeManager->getStores();
            $storeNames = [];
            foreach ($stores as $store) {
                $storeNames[$store->getId()] = $store->getName();
            }
            
            $formattedStat = [
                'id' => $stat['stat_id'],
                'store_name' => $storeNames[$stat['store_id']] ?? 'Unknown Store',
                'store_id' => $stat['store_id'],
                'generation_time' => $stat['generation_time'],
                'duration' => round((float)$stat['duration_seconds'], 2),
                'total_urls' => $stat['total_urls'],
                'files_generated' => $stat['files_generated'],
                'success' => (bool)$stat['success'],
                'errors_count' => $stat['errors_count'] ?? 0,
                'status_class' => $stat['success'] ? 'grid-severity-notice' : 'grid-severity-critical',
                'status_text' => $stat['success'] ? __('Success') : __('Error'),
                'file_size_mb' => round((float)$stat['total_file_size'] / 1024 / 1024, 2),
                'provider_stats' => $stat['provider_stats'] ? json_decode($stat['provider_stats'], true) : [],
                'performance_metrics' => $stat['performance_metrics'] ? json_decode($stat['performance_metrics'], true) : []
            ];
            
            return [
                'success' => true,
                'data' => $formattedStat
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get performance statistics
     *
     * @return array
     */
    private function getPerformanceStats(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeIds = array_map(function($store) {
                return (int)$store->getId();
            }, $stores);

            $performanceMetrics = $this->statisticsManager->getPerformanceMetrics($storeIds);
            
            return [
                'success' => true,
                'data' => $performanceMetrics
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get provider comparison data
     *
     * @return array
     */
    private function getProviderComparison(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeIds = array_map(function($store) {
                return (int)$store->getId();
            }, $stores);

            $dateRange = $this->getDateRangeFromRequest();
            $providerStats = $this->statisticsManager->getProviderPerformanceStats(
                $storeIds,
                $dateRange['start'],
                $dateRange['end']
            );

            // Calculate provider efficiency metrics
            $comparison = [];
            foreach ($providerStats as $provider => $stats) {
                $comparison[] = [
                    'provider' => $provider,
                    'total_urls' => $stats['total_urls'] ?? 0,
                    'avg_processing_time' => $stats['avg_processing_time'] ?? 0,
                    'success_rate' => $stats['success_rate'] ?? 0,
                    'efficiency_score' => $this->calculateEfficiencyScore($stats),
                    'trend' => $this->getProviderTrend($provider, $storeIds, $dateRange)
                ];
            }

            // Sort by efficiency score
            usort($comparison, function($a, $b) {
                return $b['efficiency_score'] <=> $a['efficiency_score'];
            });

            return [
                'success' => true,
                'data' => [
                    'providers' => $comparison,
                    'summary' => [
                        'total_providers' => count($comparison),
                        'best_performer' => $comparison[0] ?? null,
                        'avg_efficiency' => array_sum(array_column($comparison, 'efficiency_score')) / count($comparison)
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get store performance comparison
     *
     * @return array
     */
    private function getStorePerformance(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $dateRange = $this->getDateRangeFromRequest();
            
            $storePerformance = [];
            foreach ($stores as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $storeId = (int)$store->getId();
                $stats = $this->statisticsManager->getStatisticsByDateRange(
                    $dateRange['start'],
                    $dateRange['end'],
                    [$storeId]
                );

                $totalGenerations = count($stats);
                $successfulGenerations = array_filter($stats, function($stat) {
                    return $stat['success'] ?? false;
                });

                $avgDuration = $totalGenerations > 0
                    ? array_sum(array_column($stats, 'duration_seconds')) / $totalGenerations
                    : 0;

                $totalUrls = array_sum(array_column($stats, 'total_urls'));
                $avgUrlsPerGeneration = $totalGenerations > 0 ? $totalUrls / $totalGenerations : 0;

                $storePerformance[] = [
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'store_code' => $store->getCode(),
                    'website_name' => $store->getWebsite()->getName(),
                    'total_generations' => $totalGenerations,
                    'success_rate' => $totalGenerations > 0 ? (count($successfulGenerations) / $totalGenerations) * 100 : 0,
                    'avg_duration' => $avgDuration,
                    'total_urls' => $totalUrls,
                    'avg_urls_per_generation' => $avgUrlsPerGeneration,
                    'performance_score' => $this->calculateStorePerformanceScore($stats),
                    'status' => $this->getStoreHealthStatus($stats)
                ];
            }

            // Sort by performance score
            usort($storePerformance, function($a, $b) {
                return $b['performance_score'] <=> $a['performance_score'];
            });

            return [
                'success' => true,
                'data' => [
                    'stores' => $storePerformance,
                    'summary' => [
                        'total_stores' => count($storePerformance),
                        'avg_success_rate' => array_sum(array_column($storePerformance, 'success_rate')) / count($storePerformance),
                        'total_urls_all_stores' => array_sum(array_column($storePerformance, 'total_urls'))
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle export requests
     *
     * @return ResultInterface
     */
    private function handleExport(): ResultInterface
    {
        $exportType = $this->getRequest()->getParam('export_type', 'csv');
        $dataType = $this->getRequest()->getParam('data_type', 'recent_history');
        
        try {
            switch ($dataType) {
                case 'recent_history':
                    $data = $this->getRecentHistoryForExport();
                    break;
                case 'performance_stats':
                    $data = $this->getPerformanceStatsForExport();
                    break;
                case 'store_comparison':
                    $data = $this->getStoreComparisonForExport();
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid data type for export');
            }

            return $this->createExportResponse($data, $exportType, $dataType);
            
        } catch (\Exception $e) {
            $result = $this->resultJsonFactory->create();
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get benchmark data
     *
     * @return array
     */
    private function getBenchmarkData(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeIds = array_map(function($store) {
                return (int)$store->getId();
            }, $stores);

            // Get last 90 days for benchmarking
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify('-90 days');
            
            $stats = $this->statisticsManager->getStatisticsByDateRange(
                $startDate,
                $endDate,
                $storeIds
            );

            $benchmarks = [
                'duration' => [
                    'excellent' => 30, // seconds
                    'good' => 60,
                    'average' => 120,
                    'poor' => 300
                ],
                'success_rate' => [
                    'excellent' => 98, // percent
                    'good' => 95,
                    'average' => 90,
                    'poor' => 80
                ],
                'urls_per_minute' => [
                    'excellent' => 1000,
                    'good' => 500,
                    'average' => 250,
                    'poor' => 100
                ]
            ];

            $currentMetrics = $this->calculateCurrentMetrics($stats);
            $recommendations = $this->generateRecommendations($currentMetrics, $benchmarks);

            return [
                'success' => true,
                'data' => [
                    'benchmarks' => $benchmarks,
                    'current_metrics' => $currentMetrics,
                    'recommendations' => $recommendations,
                    'benchmark_score' => $this->calculateBenchmarkScore($currentMetrics, $benchmarks)
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get filtered statistics based on request parameters
     *
     * @return array
     */
    private function getFilteredStats(): array
    {
        try {
            $filters = $this->getFiltersFromRequest();
            $dateRange = $this->getDateRangeFromRequest();
            
            $storeIds = !empty($filters['store_ids']) 
                ? $filters['store_ids'] 
                : array_map(function($store) { return (int)$store->getId(); }, $this->storeManager->getStores());

            $stats = $this->statisticsManager->getStatisticsByDateRange(
                $dateRange['start'],
                $dateRange['end'],
                $storeIds
            );

            // Apply additional filters
            if (!empty($filters['success_only'])) {
                $stats = array_filter($stats, function($stat) {
                    return $stat['success'] ?? false;
                });
            }

            if (!empty($filters['min_duration'])) {
                $stats = array_filter($stats, function($stat) use ($filters) {
                    return ($stat['duration_seconds'] ?? 0) >= $filters['min_duration'];
                });
            }

            if (!empty($filters['min_urls'])) {
                $stats = array_filter($stats, function($stat) use ($filters) {
                    return ($stat['total_urls'] ?? 0) >= $filters['min_urls'];
                });
            }

            // Group by requested grouping
            $groupBy = $filters['group_by'] ?? 'date';
            $groupedData = $this->groupStatistics($stats, $groupBy);

            return [
                'success' => true,
                'data' => [
                    'grouped_data' => $groupedData,
                    'summary' => [
                        'total_records' => count($stats),
                        'date_range' => [
                            'start' => $dateRange['start']->format('Y-m-d'),
                            'end' => $dateRange['end']->format('Y-m-d')
                        ],
                        'applied_filters' => $filters
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate success rate for a day's statistics
     *
     * @param array $dayStats
     * @return float
     */
    private function calculateDaySuccessRate(array $dayStats): float
    {
        if (empty($dayStats)) {
            return 0.0;
        }
        
        $successful = array_filter($dayStats, function($stat) {
            return $stat['success'] ?? false;
        });
        
        return round((count($successful) / count($dayStats)) * 100, 1);
    }

    /**
     * Calculate average duration for day's statistics
     *
     * @param array $dayStats
     * @return float
     */
    private function calculateAvgDuration(array $dayStats): float
    {
        if (empty($dayStats)) {
            return 0.0;
        }
        
        $totalDuration = array_sum(array_column($dayStats, 'duration_seconds'));
        return round($totalDuration / count($dayStats), 2);
    }

    /**
     * Calculate efficiency score for provider
     *
     * @param array $stats
     * @return float
     */
    private function calculateEfficiencyScore(array $stats): float
    {
        $baseScore = 100.0;
        
        // Reduce score based on processing time (higher is worse)
        $avgTime = $stats['avg_processing_time'] ?? 0;
        if ($avgTime > 1.0) {
            $baseScore -= min(50, ($avgTime - 1.0) * 10);
        }
        
        // Reduce score based on success rate
        $successRate = $stats['success_rate'] ?? 100;
        $baseScore -= (100 - $successRate) * 0.5;
        
        return max(0, round($baseScore, 1));
    }

    /**
     * Get provider trend
     *
     * @param string $provider
     * @param array $storeIds
     * @param array $dateRange
     * @return string
     */
    private function getProviderTrend(string $provider, array $storeIds, array $dateRange): string
    {
        // Get recent vs older performance for trend calculation
        $midDate = new \DateTime($dateRange['start']->format('Y-m-d'));
        $midDate->add(new \DateInterval('P15D')); // Middle of range
        
        $olderStats = $this->statisticsManager->getProviderPerformanceStats(
            $storeIds,
            $dateRange['start'],
            $midDate
        );
        
        $recentStats = $this->statisticsManager->getProviderPerformanceStats(
            $storeIds,
            $midDate,
            $dateRange['end']
        );
        
        $olderEfficiency = isset($olderStats[$provider]) ? $this->calculateEfficiencyScore($olderStats[$provider]) : 0;
        $recentEfficiency = isset($recentStats[$provider]) ? $this->calculateEfficiencyScore($recentStats[$provider]) : 0;
        
        if ($recentEfficiency > $olderEfficiency + 5) {
            return 'improving';
        } elseif ($recentEfficiency < $olderEfficiency - 5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Calculate store performance score
     *
     * @param array $stats
     * @return float
     */
    private function calculateStorePerformanceScore(array $stats): float
    {
        if (empty($stats)) {
            return 0.0;
        }
        
        $score = 100.0;
        
        // Calculate averages
        $avgDuration = array_sum(array_column($stats, 'duration_seconds')) / count($stats);
        $avgErrors = array_sum(array_column($stats, 'errors_count')) / count($stats);
        $successRate = (array_sum(array_column($stats, 'success')) / count($stats)) * 100;
        
        // Reduce score based on duration (penalty after 60 seconds)
        if ($avgDuration > 60) {
            $score -= min(30, ($avgDuration - 60) / 10);
        }
        
        // Reduce score based on errors
        $score -= $avgErrors * 5;
        
        // Reduce score based on success rate
        $score -= (100 - $successRate) * 0.5;
        
        return max(0, round($score, 1));
    }

    /**
     * Get store health status
     *
     * @param array $stats
     * @return string
     */
    private function getStoreHealthStatus(array $stats): string
    {
        $score = $this->calculateStorePerformanceScore($stats);
        
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 75) {
            return 'good';
        } elseif ($score >= 60) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get recent history for export
     *
     * @return array
     */
    private function getRecentHistoryForExport(): array
    {
        return $this->statisticsManager->getRecentStatistics(1000, 0);
    }

    /**
     * Get performance stats for export
     *
     * @return array
     */
    private function getPerformanceStatsForExport(): array
    {
        $stores = $this->storeManager->getStores();
        $storeIds = array_map(function($store) {
            return (int)$store->getId();
        }, $stores);
        
        return $this->statisticsManager->getPerformanceMetrics($storeIds);
    }

    /**
     * Get store comparison for export
     *
     * @return array
     */
    private function getStoreComparisonForExport(): array
    {
        $storePerformance = $this->getStorePerformance();
        return $storePerformance['data']['stores'] ?? [];
    }

    /**
     * Create export response
     *
     * @param array $data
     * @param string $exportType
     * @param string $dataType
     * @return ResultInterface
     */
    private function createExportResponse(array $data, string $exportType, string $dataType): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        
        switch ($exportType) {
            case 'csv':
                $csvContent = $this->convertToCSV($data, $dataType);
                return $result->setData([
                    'success' => true,
                    'content' => $csvContent,
                    'filename' => $dataType . '_' . date('Y-m-d_H-i-s') . '.csv',
                    'content_type' => 'text/csv'
                ]);
                
            case 'json':
                return $result->setData([
                    'success' => true,
                    'data' => $data,
                    'filename' => $dataType . '_' . date('Y-m-d_H-i-s') . '.json'
                ]);
                
            default:
                return $result->setData([
                    'success' => false,
                    'error' => 'Unsupported export type'
                ]);
        }
    }

    /**
     * Convert data to CSV format
     *
     * @param array $data
     * @param string $dataType
     * @return string
     */
    private function convertToCSV(array $data, string $dataType): string
    {
        if (empty($data)) {
            return '';
        }
        
        $csv = '';
        
        // Add headers based on data type
        switch ($dataType) {
            case 'recent_history':
                $csv = "Date,Store ID,Duration,URLs,Files,Success,Errors\n";
                foreach ($data as $record) {
                    $csv .= sprintf(
                        "%s,%d,%.2f,%d,%d,%s,%d\n",
                        $record['generation_time'],
                        $record['store_id'],
                        $record['duration_seconds'],
                        $record['total_urls'],
                        $record['files_generated'],
                        $record['success'] ? 'Yes' : 'No',
                        $record['errors_count']
                    );
                }
                break;
                
            default:
                // Generic CSV export
                if (is_array($data[0])) {
                    $headers = array_keys($data[0]);
                    $csv = implode(',', $headers) . "\n";
                    
                    foreach ($data as $record) {
                        $csv .= implode(',', array_values($record)) . "\n";
                    }
                }
                break;
        }
        
        return $csv;
    }

    /**
     * Calculate current metrics
     *
     * @param array $stats
     * @return array
     */
    private function calculateCurrentMetrics(array $stats): array
    {
        if (empty($stats)) {
            return [
                'avg_duration' => 0,
                'success_rate' => 0,
                'urls_per_minute' => 0
            ];
        }
        
        $totalGenerations = count($stats);
        $avgDuration = array_sum(array_column($stats, 'duration_seconds')) / $totalGenerations;
        $successCount = array_sum(array_column($stats, 'success'));
        $successRate = ($successCount / $totalGenerations) * 100;
        
        $totalUrls = array_sum(array_column($stats, 'total_urls'));
        $totalDuration = array_sum(array_column($stats, 'duration_seconds'));
        $urlsPerMinute = $totalDuration > 0 ? ($totalUrls / $totalDuration) * 60 : 0;
        
        return [
            'avg_duration' => $avgDuration,
            'success_rate' => $successRate,
            'urls_per_minute' => $urlsPerMinute
        ];
    }

    /**
     * Generate recommendations based on metrics
     *
     * @param array $currentMetrics
     * @param array $benchmarks
     * @return array
     */
    private function generateRecommendations(array $currentMetrics, array $benchmarks): array
    {
        $recommendations = [];
        
        // Duration recommendations
        if ($currentMetrics['avg_duration'] > $benchmarks['duration']['poor']) {
            $recommendations[] = [
                'type' => 'critical',
                'category' => 'performance',
                'message' => 'Generation duration is critically high. Consider optimization or server upgrade.',
                'action' => 'optimize_generation'
            ];
        } elseif ($currentMetrics['avg_duration'] > $benchmarks['duration']['average']) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'performance',
                'message' => 'Generation duration could be improved.',
                'action' => 'review_performance'
            ];
        }
        
        // Success rate recommendations
        if ($currentMetrics['success_rate'] < $benchmarks['success_rate']['poor']) {
            $recommendations[] = [
                'type' => 'critical',
                'category' => 'reliability',
                'message' => 'Success rate is critically low. Immediate attention required.',
                'action' => 'fix_errors'
            ];
        } elseif ($currentMetrics['success_rate'] < $benchmarks['success_rate']['average']) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'reliability',
                'message' => 'Success rate could be improved.',
                'action' => 'review_errors'
            ];
        }
        
        // URLs per minute recommendations
        if ($currentMetrics['urls_per_minute'] < $benchmarks['urls_per_minute']['poor']) {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'efficiency',
                'message' => 'URL processing rate is low. Consider batch size optimization.',
                'action' => 'optimize_batch_size'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Calculate benchmark score
     *
     * @param array $currentMetrics
     * @param array $benchmarks
     * @return float
     */
    private function calculateBenchmarkScore(array $currentMetrics, array $benchmarks): float
    {
        $score = 0;
        $maxScore = 300; // 100 points per metric
        
        // Duration score (lower is better)
        if ($currentMetrics['avg_duration'] <= $benchmarks['duration']['excellent']) {
            $score += 100;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['good']) {
            $score += 80;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['average']) {
            $score += 60;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['poor']) {
            $score += 40;
        } else {
            $score += 20;
        }
        
        // Success rate score
        if ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['excellent']) {
            $score += 100;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['good']) {
            $score += 80;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['average']) {
            $score += 60;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['poor']) {
            $score += 40;
        } else {
            $score += 20;
        }
        
        // URLs per minute score
        if ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['excellent']) {
            $score += 100;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['good']) {
            $score += 80;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['average']) {
            $score += 60;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['poor']) {
            $score += 40;
        } else {
            $score += 20;
        }
        
        return round(($score / $maxScore) * 100, 1);
    }
}

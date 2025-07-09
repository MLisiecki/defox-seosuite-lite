<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPL v3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\Sitemap;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Sitemap\Analytics\StatisticsManager;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap dashboard block
 * 
 * Provides data and methods for sitemap analytics dashboard including:
 * - KPI metrics preparation
 * - Chart data configuration
 * - Store information
 * - AJAX endpoints URLs
 */
class Dashboard extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Defox_SEOSuite::sitemap/dashboard.phtml';

    /**
     * @var Config
     */
    private Config $config;

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
     * @param Config $config
     * @param StatisticsManager $statisticsManager
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        StatisticsManager $statisticsManager,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->statisticsManager = $statisticsManager;
        $this->storeManager = $storeManager;
    }

    /**
     * Get AJAX URLs for dashboard data loading
     *
     * @return array
     */
    public function getAjaxUrls(): array
    {
        return [
            'kpi_data' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'kpi_data']),
            'chart_data' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'chart_data']),
            'recent_history' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'recent_history']),
            'performance_stats' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'performance_stats']),
            'provider_comparison' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'provider_comparison']),
            'store_performance' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'store_performance']),
            'export_data' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'export_data']),
            'benchmark_data' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'benchmark_data']),
            'filtered_stats' => $this->getUrl('defox_seosuite/sitemap/dashboard', ['ajax_action' => 'filtered_stats'])
        ];
    }

    /**
     * Get generate sitemap URL
     *
     * @return string
     */
    public function getGenerateSitemapUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/generate');
    }

    /**
     * Get validate sitemap URL
     *
     * @return string
     */
    public function getValidateSitemapUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/validate');
    }

    /**
     * Get settings URL
     *
     * @return string
     */
    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/defox_seosuite');
    }

    /**
     * Check if sitemap is globally enabled
     *
     * @return bool
     */
    public function isSitemapEnabled(): bool
    {
        return $this->config->isSitemapEnabled();
    }

    /**
     * Get active stores count
     *
     * @return int
     */
    public function getActiveStoresCount(): int
    {
        $stores = $this->storeManager->getStores();
        $activeCount = 0;
        
        foreach ($stores as $store) {
            if ($store->getIsActive()) {
                $activeCount++;
            }
        }
        
        return $activeCount;
    }

    /**
     * Get stores with sitemap enabled
     *
     * @return array
     */
    public function getStoresWithSitemapEnabled(): array
    {
        $stores = [];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $enabled = (bool)$this->config->getValue('defox_seosuite/sitemap/enabled', (int)$store->getId());
                if ($enabled) {
                    $stores[] = [
                        'id' => $store->getId(),
                        'name' => $store->getName(),
                        'code' => $store->getCode(),
                        'website' => $store->getWebsite()->getName()
                    ];
                }
            }
        }
        
        return $stores;
    }

    /**
     * Get dashboard refresh interval in seconds
     *
     * @return int
     */
    public function getDashboardRefreshInterval(): int
    {
        return (int)$this->config->getValue('defox_seosuite/sitemap_ui/dashboard_refresh_interval') ?: 300; // 5 minutes default
    }

    /**
     * Check if real-time progress is enabled
     *
     * @return bool
     */
    public function isRealTimeProgressEnabled(): bool
    {
        return (bool)$this->config->getValue('defox_seosuite/sitemap_ui/enable_realtime_progress');
    }

    /**
     * Get maximum dashboard records to display
     *
     * @return int
     */
    public function getMaxDashboardRecords(): int
    {
        return (int)$this->config->getValue('defox_seosuite/sitemap_ui/max_dashboard_records') ?: 50;
    }

    /**
     * Get Chart.js configuration
     *
     * @return array
     */
    public function getChartConfig(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom'
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false
                ]
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Date'
                    ]
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Count'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get color scheme for charts
     *
     * @return array
     */
    public function getChartColors(): array
    {
        return [
            'primary' => '#1979c3',
            'success' => '#5cb85c',
            'warning' => '#f0ad4e',
            'danger' => '#d9534f',
            'info' => '#5bc0de',
            'secondary' => '#6c757d',
            'gradient_primary' => 'linear-gradient(90deg, #1979c3, #006bb4)',
            'gradient_success' => 'linear-gradient(90deg, #5cb85c, #449d44)',
            'gradient_warning' => 'linear-gradient(90deg, #f0ad4e, #ec971f)'
        ];
    }

    /**
     * Get initial KPI data (server-side rendered)
     *
     * @return array
     */
    public function getInitialKpiData(): array
    {
        try {
            // Get basic statistics without heavy processing
            $stores = $this->storeManager->getStores();
            $enabledStores = count($this->getStoresWithSitemapEnabled());
            
            // Get last generation info if available
            $lastGeneration = $this->statisticsManager->getLastGenerationByStore();
            
            return [
                'total_stores' => count($stores),
                'enabled_stores' => $enabledStores,
                'last_generation_time' => $lastGeneration['generation_time'] ?? null,
                'last_generation_status' => isset($lastGeneration['success']) 
                    ? ($lastGeneration['success'] ? 'success' : 'error') 
                    : null,
                'sitemap_enabled' => $this->isSitemapEnabled()
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get dashboard warnings
     *
     * @return array
     */
    public function getDashboardWarnings(): array
    {
        $warnings = [];
        
        if (!$this->isSitemapEnabled()) {
            $warnings[] = [
                'type' => 'error',
                'message' => __('Sitemap generation is globally disabled.'),
                'action_url' => $this->getSettingsUrl(),
                'action_text' => __('Enable in Settings')
            ];
        }
        
        $enabledStores = $this->getStoresWithSitemapEnabled();
        if (empty($enabledStores)) {
            $warnings[] = [
                'type' => 'warning',
                'message' => __('No stores have sitemap enabled.'),
                'action_url' => $this->getSettingsUrl(),
                'action_text' => __('Configure Stores')
            ];
        }
        
        // Check if statistics table exists
        try {
            $this->statisticsManager->getLastGenerationByStore();
        } catch (\Exception $e) {
            $warnings[] = [
                'type' => 'info',
                'message' => __('No sitemap generation history available. Generate your first sitemap to see statistics.'),
                'action_url' => $this->getGenerateSitemapUrl(),
                'action_text' => __('Generate Now')
            ];
        }
        
        return $warnings;
    }

    /**
     * Check if dashboard should show welcome message
     *
     * @return bool
     */
    public function shouldShowWelcomeMessage(): bool
    {
        try {
            $lastGeneration = $this->statisticsManager->getLastGenerationByStore();
            return empty($lastGeneration);
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get advanced chart configuration
     *
     * @return array
     */
    public function getAdvancedChartConfig(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20
                    ]
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(0,0,0,0.8)',
                    'titleColor' => '#fff',
                    'bodyColor' => '#fff',
                    'borderColor' => '#1979c3',
                    'borderWidth' => 1,
                    'cornerRadius' => 6,
                    'displayColors' => true
                ],
                'zoom' => [
                    'zoom' => [
                        'wheel' => [
                            'enabled' => true
                        ],
                        'pinch' => [
                            'enabled' => true
                        ],
                        'mode' => 'x'
                    ],
                    'pan' => [
                        'enabled' => true,
                        'mode' => 'x'
                    ]
                ]
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Date'
                    ],
                    'grid' => [
                        'color' => '#f0f0f0'
                    ]
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Count'
                    ],
                    'grid' => [
                        'color' => '#f0f0f0'
                    ]
                ]
            ],
            'elements' => [
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6
                ],
                'line' => [
                    'tension' => 0.4
                ]
            ]
        ];
    }

    /**
     * Get date range options for filters
     *
     * @return array
     */
    public function getDateRangeOptions(): array
    {
        return [
            '7days' => __('Last 7 Days'),
            '30days' => __('Last 30 Days'),
            '90days' => __('Last 90 Days'),
            '1year' => __('Last Year'),
            'custom' => __('Custom Range')
        ];
    }

    /**
     * Get filter options for dashboard
     *
     * @return array
     */
    public function getFilterOptions(): array
    {
        return [
            'store_filter' => $this->getStoreFilterOptions(),
            'success_filter' => [
                'all' => __('All Generations'),
                'success_only' => __('Successful Only'),
                'errors_only' => __('Errors Only')
            ],
            'group_by' => [
                'date' => __('By Date'),
                'week' => __('By Week'),
                'month' => __('By Month'),
                'store' => __('By Store'),
                'hour' => __('By Hour')
            ]
        ];
    }

    /**
     * Get store filter options
     *
     * @return array
     */
    private function getStoreFilterOptions(): array
    {
        $options = [['value' => '', 'label' => __('All Stores')]];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $options[] = [
                    'value' => $store->getId(),
                    'label' => sprintf('%s (%s)', $store->getName(), $store->getCode())
                ];
            }
        }
        
        return $options;
    }

    /**
     * Get export options
     *
     * @return array
     */
    public function getExportOptions(): array
    {
        return [
            'formats' => [
                'csv' => __('CSV (Excel)'),
                'json' => __('JSON'),
                'xml' => __('XML')
            ],
            'data_types' => [
                'recent_history' => __('Recent History'),
                'performance_stats' => __('Performance Statistics'),
                'store_comparison' => __('Store Comparison')
            ]
        ];
    }

    /**
     * Get benchmark configuration
     *
     * @return array
     */
    public function getBenchmarkConfig(): array
    {
        return [
            'thresholds' => [
                'duration' => [
                    'excellent' => 30,
                    'good' => 60,
                    'average' => 120,
                    'poor' => 300
                ],
                'success_rate' => [
                    'excellent' => 98,
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
            ],
            'colors' => [
                'excellent' => '#28a745',
                'good' => '#6cb2eb',
                'average' => '#ffc107',
                'poor' => '#dc3545'
            ]
        ];
    }

    /**
     * Check if real-time features are enabled
     *
     * @return bool
     */
    public function isRealTimeFeaturesEnabled(): bool
    {
        return (bool)$this->config->getValue('defox_seosuite/sitemap_ui/enable_realtime_features');
    }

    /**
     * Get WebSocket configuration for real-time updates
     *
     * @return array
     */
    public function getWebSocketConfig(): array
    {
        if (!$this->isRealTimeFeaturesEnabled()) {
            return [];
        }
        
        return [
            'enabled' => true,
            'url' => $this->config->getValue('defox_seosuite/sitemap_ui/websocket_url') ?: 'ws://localhost:8080',
            'reconnect_interval' => 5000,
            'max_reconnect_attempts' => 10
        ];
    }

    /**
     * Get dashboard performance settings
     *
     * @return array
     */
    public function getPerformanceSettings(): array
    {
        return [
            'max_data_points' => (int)$this->config->getValue('defox_seosuite/sitemap_ui/max_data_points') ?: 100,
            'enable_data_compression' => (bool)$this->config->getValue('defox_seosuite/sitemap_ui/enable_compression'),
            'lazy_load_charts' => (bool)$this->config->getValue('defox_seosuite/sitemap_ui/lazy_load_charts'),
            'debounce_interval' => (int)$this->config->getValue('defox_seosuite/sitemap_ui/debounce_interval') ?: 300
        ];
    }

    /**
     * Check if advanced features are enabled
     *
     * @return bool
     */
    public function isAdvancedFeaturesEnabled(): bool
    {
        return (bool)$this->config->getValue('defox_seosuite/sitemap_ui/enable_advanced_features');
    }
}

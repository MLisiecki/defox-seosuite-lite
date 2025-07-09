<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Analytics;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Sitemap generation statistics and analytics manager
 * 
 * Tracks and analyzes sitemap generation performance, providing insights for optimization
 */
class StatisticsManager
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param DateTime $dateTime
     * @param Json $jsonSerializer
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        Json $jsonSerializer,
        Config $config,
        Logger $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->jsonSerializer = $jsonSerializer;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Record generation statistics
     *
     * @param array $stats
     * @return void
     */
    public function recordGenerationStats(array $stats): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');

            $data = [
                'store_id' => $stats['store_id'] ?? 0,
                'generation_time' => $this->dateTime->gmtDate(),
                'duration_seconds' => $stats['duration'] ?? 0,
                'total_urls' => $stats['total_urls'] ?? 0,
                'files_generated' => count($stats['files'] ?? []),
                'total_file_size' => $this->calculateTotalFileSize($stats['files'] ?? []),
                'errors_count' => count($stats['errors'] ?? []),
                'provider_stats' => $this->jsonSerializer->serialize($this->extractProviderStats($stats)),
                'performance_metrics' => $this->jsonSerializer->serialize($this->extractPerformanceMetrics($stats)),
                'success' => empty($stats['errors']) ? 1 : 0
            ];

            $connection->insert($tableName, $data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record sitemap generation stats: ' . $e->getMessage());
        }
    }

    /**
     * Get generation statistics for dashboard
     *
     * @param int $storeId
     * @param int $days
     * @return array
     */
    public function getGenerationStatistics(int $storeId = 0, int $days = 30): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->where('generation_time >= ?', $this->dateTime->gmtDate('Y-m-d H:i:s', time() - ($days * 24 * 3600)))
                ->order('generation_time DESC');

            if ($storeId > 0) {
                $select->where('store_id = ?', $storeId);
            }

            $records = $connection->fetchAll($select);

            return [
                'summary' => $this->calculateSummaryStats($records),
                'timeline' => $this->prepareTimelineData($records),
                'performance_trends' => $this->calculatePerformanceTrends($records),
                'error_analysis' => $this->analyzeErrors($records),
                'provider_performance' => $this->analyzeProviderPerformance($records)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get generation statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate summary statistics
     *
     * @param array $records
     * @return array
     */
    private function calculateSummaryStats(array $records): array
    {
        if (empty($records)) {
            return [
                'total_generations' => 0,
                'success_rate' => 0,
                'avg_duration' => 0,
                'avg_urls' => 0,
                'total_urls_generated' => 0,
                'avg_file_size' => 0,
                'last_generation' => null
            ];
        }

        $totalGenerations = count($records);
        $successfulGenerations = array_sum(array_column($records, 'success'));
        $totalDuration = array_sum(array_column($records, 'duration_seconds'));
        $totalUrls = array_sum(array_column($records, 'total_urls'));
        $totalFileSize = array_sum(array_column($records, 'total_file_size'));

        return [
            'total_generations' => $totalGenerations,
            'success_rate' => round(($successfulGenerations / $totalGenerations) * 100, 2),
            'avg_duration' => round($totalDuration / $totalGenerations, 2),
            'avg_urls' => round($totalUrls / $totalGenerations, 0),
            'total_urls_generated' => $totalUrls,
            'avg_file_size' => round($totalFileSize / $totalGenerations / 1024 / 1024, 2), // MB
            'last_generation' => $records[0]['generation_time'] ?? null
        ];
    }

    /**
     * Prepare timeline data for charts
     *
     * @param array $records
     * @return array
     */
    private function prepareTimelineData(array $records): array
    {
        $timeline = [];
        
        foreach ($records as $record) {
            $date = date('Y-m-d', strtotime($record['generation_time']));
            
            if (!isset($timeline[$date])) {
                $timeline[$date] = [
                    'date' => $date,
                    'generations' => 0,
                    'total_duration' => 0,
                    'total_urls' => 0,
                    'total_errors' => 0,
                    'success_count' => 0
                ];
            }
            
            $timeline[$date]['generations']++;
            $timeline[$date]['total_duration'] += $record['duration_seconds'];
            $timeline[$date]['total_urls'] += $record['total_urls'];
            $timeline[$date]['total_errors'] += $record['errors_count'];
            $timeline[$date]['success_count'] += $record['success'];
        }

        // Calculate averages and add derived metrics
        foreach ($timeline as &$day) {
            $day['avg_duration'] = round($day['total_duration'] / $day['generations'], 2);
            $day['avg_urls'] = round($day['total_urls'] / $day['generations'], 0);
            $day['success_rate'] = round(($day['success_count'] / $day['generations']) * 100, 2);
        }

        return array_values($timeline);
    }

    /**
     * Calculate performance trends
     *
     * @param array $records
     * @return array
     */
    private function calculatePerformanceTrends(array $records): array
    {
        if (count($records) < 2) {
            return [];
        }

        $recentRecords = array_slice($records, 0, 10);
        $olderRecords = array_slice($records, -10);

        $recentAvgDuration = array_sum(array_column($recentRecords, 'duration_seconds')) / count($recentRecords);
        $olderAvgDuration = array_sum(array_column($olderRecords, 'duration_seconds')) / count($olderRecords);

        $recentAvgUrls = array_sum(array_column($recentRecords, 'total_urls')) / count($recentRecords);
        $olderAvgUrls = array_sum(array_column($olderRecords, 'total_urls')) / count($olderRecords);

        $recentSuccessRate = (array_sum(array_column($recentRecords, 'success')) / count($recentRecords)) * 100;
        $olderSuccessRate = (array_sum(array_column($olderRecords, 'success')) / count($olderRecords)) * 100;

        return [
            'duration_trend' => $this->calculateTrendPercentage($olderAvgDuration, $recentAvgDuration),
            'urls_trend' => $this->calculateTrendPercentage($olderAvgUrls, $recentAvgUrls),
            'success_rate_trend' => $this->calculateTrendPercentage($olderSuccessRate, $recentSuccessRate),
            'performance_score' => $this->calculatePerformanceScore($recentRecords)
        ];
    }

    /**
     * Analyze errors from generation statistics
     *
     * @param array $records
     * @return array
     */
    private function analyzeErrors(array $records): array
    {
        $errorPatterns = [];
        $totalErrors = 0;

        foreach ($records as $record) {
            $totalErrors += $record['errors_count'];
            
            // This would be expanded to analyze actual error messages
            // For now, we'll track error frequency
            if ($record['errors_count'] > 0) {
                $errorLevel = $this->categorizeErrorLevel($record['errors_count']);
                if (!isset($errorPatterns[$errorLevel])) {
                    $errorPatterns[$errorLevel] = 0;
                }
                $errorPatterns[$errorLevel]++;
            }
        }

        return [
            'total_errors' => $totalErrors,
            'error_patterns' => $errorPatterns,
            'error_rate' => count($records) > 0 ? round(($totalErrors / count($records)), 2) : 0,
            'most_recent_errors' => $this->getMostRecentErrors($records)
        ];
    }

    /**
     * Analyze provider performance
     *
     * @param array $records
     * @return array
     */
    private function analyzeProviderPerformance(array $records): array
    {
        $providerStats = [];

        foreach ($records as $record) {
            if (!empty($record['provider_stats'])) {
                try {
                    $stats = $this->jsonSerializer->unserialize($record['provider_stats']);
                    
                    foreach ($stats as $provider => $data) {
                        if (!isset($providerStats[$provider])) {
                            $providerStats[$provider] = [
                                'total_items' => 0,
                                'total_time' => 0,
                                'generations' => 0,
                                'errors' => 0
                            ];
                        }
                        
                        $providerStats[$provider]['total_items'] += $data['items'] ?? 0;
                        $providerStats[$provider]['total_time'] += $data['time'] ?? 0;
                        $providerStats[$provider]['generations']++;
                        $providerStats[$provider]['errors'] += $data['errors'] ?? 0;
                    }
                } catch (\Exception $e) {
                    // Skip invalid provider stats
                    continue;
                }
            }
        }

        // Calculate averages
        foreach ($providerStats as $provider => &$stats) {
            $stats['avg_items'] = round($stats['total_items'] / $stats['generations'], 0);
            $stats['avg_time'] = round($stats['total_time'] / $stats['generations'], 3);
            $stats['items_per_second'] = $stats['avg_time'] > 0 
                ? round($stats['avg_items'] / $stats['avg_time'], 0) 
                : 0;
        }

        return $providerStats;
    }

    /**
     * Get recommendations based on statistics
     *
     * @param array $stats
     * @return array
     */
    public function getRecommendations(array $stats): array
    {
        $recommendations = [];

        // Check success rate
        if (isset($stats['summary']['success_rate']) && $stats['summary']['success_rate'] < 90) {
            $recommendations[] = [
                'type' => 'reliability',
                'priority' => 'high',
                'message' => sprintf('Success rate is %.1f%%. Investigate recurring errors.', $stats['summary']['success_rate']),
                'action' => 'review_error_logs'
            ];
        }

        // Check average duration
        if (isset($stats['summary']['avg_duration']) && $stats['summary']['avg_duration'] > 300) { // 5 minutes
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => sprintf('Average generation time is %.1f seconds. Consider optimization.', $stats['summary']['avg_duration']),
                'action' => 'optimize_generation'
            ];
        }

        // Check trends
        if (isset($stats['performance_trends']['duration_trend']) && $stats['performance_trends']['duration_trend'] > 20) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Generation time is increasing. Monitor system resources.',
                'action' => 'monitor_resources'
            ];
        }

        return $recommendations;
    }

    /**
     * Extract provider statistics from generation stats
     *
     * @param array $stats
     * @return array
     */
    private function extractProviderStats(array $stats): array
    {
        $providerStats = [];
        
        if (isset($stats['provider_performance'])) {
            foreach ($stats['provider_performance'] as $provider => $data) {
                $providerStats[$provider] = [
                    'items' => $data['items'] ?? 0,
                    'time' => $data['duration'] ?? 0,
                    'errors' => $data['errors'] ?? 0
                ];
            }
        }

        return $providerStats;
    }

    /**
     * Extract performance metrics from generation stats
     *
     * @param array $stats
     * @return array
     */
    private function extractPerformanceMetrics(array $stats): array
    {
        return [
            'memory_peak' => $stats['memory_peak'] ?? 0,
            'memory_usage' => $stats['memory_usage'] ?? 0,
            'cpu_time' => $stats['cpu_time'] ?? 0,
            'batch_size' => $stats['batch_size'] ?? 1000,
            'compression_ratio' => $stats['compression_ratio'] ?? 0
        ];
    }

    /**
     * Calculate total file size from files array
     *
     * @param array $files
     * @return int
     */
    private function calculateTotalFileSize(array $files): int
    {
        $totalSize = 0;
        
        foreach ($files as $file) {
            if (isset($file['size'])) {
                $totalSize += $file['size'];
            } elseif (is_string($file) && file_exists($file)) {
                $totalSize += filesize($file);
            }
        }

        return $totalSize;
    }

    /**
     * Calculate trend percentage
     *
     * @param float $oldValue
     * @param float $newValue
     * @return float
     */
    private function calculateTrendPercentage(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Calculate performance score
     * @param array $records
     * @return float
     */
    private function calculatePerformanceScore(array $records): float
    {
        if (empty($records)) {
            return 0;
        }

        $score = 100.0;
        $avgDuration = array_sum(array_column($records, 'duration_seconds')) / count($records);
        $avgErrors = array_sum(array_column($records, 'errors_count')) / count($records);
        $successRate = (array_sum(array_column($records, 'success')) / count($records)) * 100;

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
     * Categorize error level
     *
     * @param int $errorCount
     * @return string
     */
    private function categorizeErrorLevel(int $errorCount): string
    {
        if ($errorCount >= 10) {
            return 'critical';
        } elseif ($errorCount >= 5) {
            return 'high';
        } elseif ($errorCount >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get most recent errors
     *
     * @param array $records
     * @return array
     */
    private function getMostRecentErrors(array $records): array
    {
        $recentErrors = [];
        
        foreach (array_slice($records, 0, 5) as $record) {
            if ($record['errors_count'] > 0) {
                $recentErrors[] = [
                    'date' => $record['generation_time'],
                    'store_id' => $record['store_id'],
                    'error_count' => $record['errors_count']
                ];
            }
        }

        return $recentErrors;
    }

    /**
     * Clean old statistics
     *
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function cleanOldStatistics(int $daysToKeep = 90): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $cutoffDate = $this->dateTime->gmtDate('Y-m-d H:i:s', time() - ($daysToKeep * 24 * 3600));
            
            $deletedRows = $connection->delete(
                $tableName,
                ['generation_time < ?' => $cutoffDate]
            );

            $this->logger->info(sprintf('Cleaned %d old sitemap statistics records', $deletedRows));
            
            return $deletedRows;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clean old statistics: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get last generation by store (for backward compatibility)
     *
     * @param int|null $storeId
     * @return array|null
     */
    public function getLastGenerationByStore(?int $storeId = null): ?array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->order('generation_time DESC')
                ->limit(1);

            if ($storeId !== null) {
                $select->where('store_id = ?', $storeId);
            }

            $record = $connection->fetchRow($select);
            
            if (!$record) {
                return null;
            }
            
            return [
                'generation_time' => $record['generation_time'],
                'success' => (bool)$record['success'],
                'duration_seconds' => $record['duration_seconds'],
                'total_urls' => $record['total_urls'],
                'errors_count' => $record['errors_count'],
                'store_id' => $record['store_id']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get last generation by store: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get statistics by date range
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param array $storeIds
     * @return array
     */
    public function getStatisticsByDateRange(\DateTime $startDate, \DateTime $endDate, array $storeIds = []): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->where('generation_time >= ?', $startDate->format('Y-m-d H:i:s'))
                ->where('generation_time <= ?', $endDate->format('Y-m-d H:i:s'))
                ->order('generation_time DESC');

            if (!empty($storeIds)) {
                $select->where('store_id IN (?)', $storeIds);
            }

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get statistics by date range: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily statistics
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param array $storeIds
     * @return array
     */
    public function getDailyStatistics(\DateTime $startDate, \DateTime $endDate, array $storeIds = []): array
    {
        $stats = $this->getStatisticsByDateRange($startDate, $endDate, $storeIds);
        $dailyStats = [];
        
        foreach ($stats as $stat) {
            $date = date('Y-m-d', strtotime($stat['generation_time']));
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [];
            }
            $dailyStats[$date][] = $stat;
        }
        
        return $dailyStats;
    }

    /**
     * Get provider performance stats
     *
     * @param array $storeIds
     * @param \DateTime|null $startDate
     * @param \DateTime|null $endDate
     * @return array
     */
    public function getProviderPerformanceStats(array $storeIds = [], ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        if ($startDate === null) {
            $startDate = new \DateTime('-30 days');
        }
        if ($endDate === null) {
            $endDate = new \DateTime();
        }
        
        $stats = $this->getStatisticsByDateRange($startDate, $endDate, $storeIds);
        return $this->analyzeProviderPerformance($stats);
    }

    /**
     * Get recent statistics
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getRecentStatistics(int $limit = 20, int $offset = 0): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->order('generation_time DESC')
                ->limit($limit, $offset);

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get recent statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get performance metrics
     *
     * @param array $storeIds
     * @return array
     */
    public function getPerformanceMetrics(array $storeIds = []): array
    {
        try {
            $startDate = new \DateTime('-30 days');
            $endDate = new \DateTime();
            $stats = $this->getStatisticsByDateRange($startDate, $endDate, $storeIds);
            
            if (empty($stats)) {
                return [
                    'avg_duration' => 0,
                    'avg_urls_per_generation' => 0,
                    'avg_file_size' => 0,
                    'success_rate' => 0,
                    'total_generations' => 0,
                    'error_rate' => 0
                ];
            }
            
            $totalGenerations = count($stats);
            $successfulGenerations = array_filter($stats, function($stat) {
                return $stat['success'];
            });
            
            return [
                'avg_duration' => array_sum(array_column($stats, 'duration_seconds')) / $totalGenerations,
                'avg_urls_per_generation' => array_sum(array_column($stats, 'total_urls')) / $totalGenerations,
                'avg_file_size' => array_sum(array_column($stats, 'total_file_size')) / $totalGenerations,
                'success_rate' => (count($successfulGenerations) / $totalGenerations) * 100,
                'total_generations' => $totalGenerations,
                'error_rate' => (array_sum(array_column($stats, 'errors_count')) / $totalGenerations)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get performance metrics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get resource connection (for dashboard details)
     *
     * @return ResourceConnection
     */
    public function getResourceConnection(): ResourceConnection
    {
        return $this->resourceConnection;
    }

    /*
     * @param int $storeId
     * @param int $days
     * @return string CSV content
     */
    public function exportStatisticsCSV(int $storeId = 0, int $days = 30): string
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            $select = $connection->select()
                ->from($tableName)
                ->where('generation_time >= ?', $this->dateTime->gmtDate('Y-m-d H:i:s', time() - ($days * 24 * 3600)))
                ->order('generation_time DESC');

            if ($storeId > 0) {
                $select->where('store_id = ?', $storeId);
            }

            $records = $connection->fetchAll($select);

            // Create CSV content
            $csv = "Date,Store ID,Duration (seconds),Total URLs,Files Generated,File Size (MB),Errors,Success\n";
            
            foreach ($records as $record) {
                $csv .= sprintf(
                    "%s,%d,%.2f,%d,%d,%.2f,%d,%s\n",
                    $record['generation_time'],
                    $record['store_id'],
                    $record['duration_seconds'],
                    $record['total_urls'],
                    $record['files_generated'],
                    round($record['total_file_size'] / 1024 / 1024, 2),
                    $record['errors_count'],
                    $record['success'] ? 'Yes' : 'No'
                );
            }

            return $csv;
        } catch (\Exception $e) {
            $this->logger->error('Failed to export statistics: ' . $e->getMessage());
            return '';
        }
    }
}

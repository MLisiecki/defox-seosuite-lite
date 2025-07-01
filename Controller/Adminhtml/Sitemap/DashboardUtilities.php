<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Sitemap;

/**
 * Dashboard utility methods trait
 * 
 * Contains helper methods for advanced dashboard functionality including:
 * - Date range parsing
 * - Filter handling  
 * - Export functionality
 * - Performance calculations
 * - Benchmark scoring
 */
trait DashboardUtilities
{
    /**
     * Get date range from request parameters
     *
     * @return array
     */
    private function getDateRangeFromRequest(): array
    {
        $startDate = $this->getRequest()->getParam('start_date');
        $endDate = $this->getRequest()->getParam('end_date');
        $period = $this->getRequest()->getParam('period', '30days');
        
        if ($startDate && $endDate) {
            return [
                'start' => new \DateTime($startDate),
                'end' => new \DateTime($endDate)
            ];
        }
        
        // Default periods
        $endDate = new \DateTime();
        switch ($period) {
            case '7days':
                $startDate = (clone $endDate)->modify('-7 days');
                break;
            case '30days':
                $startDate = (clone $endDate)->modify('-30 days');
                break;
            case '90days':
                $startDate = (clone $endDate)->modify('-90 days');
                break;
            case '1year':
                $startDate = (clone $endDate)->modify('-1 year');
                break;
            default:
                $startDate = (clone $endDate)->modify('-30 days');
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    /**
     * Get filters from request parameters
     *
     * @return array
     */
    private function getFiltersFromRequest(): array
    {
        return [
            'store_ids' => $this->getRequest()->getParam('store_ids', []),
            'success_only' => (bool)$this->getRequest()->getParam('success_only', false),
            'min_duration' => (float)$this->getRequest()->getParam('min_duration', 0),
            'min_urls' => (int)$this->getRequest()->getParam('min_urls', 0),
            'group_by' => $this->getRequest()->getParam('group_by', 'date'),
            'provider_filter' => $this->getRequest()->getParam('provider_filter', ''),
            'exclude_errors' => (bool)$this->getRequest()->getParam('exclude_errors', false)
        ];
    }

    /**
     * Calculate efficiency score for provider
     *
     * @param array $stats
     * @return float
     */
    private function calculateEfficiencyScore(array $stats): float
    {
        $urlsPerSecond = isset($stats['avg_processing_time']) && $stats['avg_processing_time'] > 0
            ? ($stats['total_urls'] ?? 0) / $stats['avg_processing_time']
            : 0;
            
        $successRate = $stats['success_rate'] ?? 0;
        
        // Combined score: 70% success rate, 30% speed
        return ($successRate * 0.7) + ($urlsPerSecond * 0.3);
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
        // This would compare current period with previous period
        // For now, return a placeholder
        $trends = ['improving', 'stable', 'declining'];
        return $trends[array_rand($trends)];
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
            return 0;
        }
        
        $successRate = count(array_filter($stats, function($s) { return $s['success'] ?? false; })) / count($stats);
        $avgDuration = array_sum(array_column($stats, 'duration_seconds')) / count($stats);
        $totalUrls = array_sum(array_column($stats, 'total_urls'));
        
        // Scoring algorithm
        $successScore = $successRate * 40; // 40 points for success rate
        $speedScore = max(0, 30 - ($avgDuration / 10)); // 30 points for speed (penalty for slow)
        $volumeScore = min(30, $totalUrls / 1000); // 30 points for volume (up to 30k URLs)
        
        return $successScore + $speedScore + $volumeScore;
    }

    /**
     * Get store health status
     *
     * @param array $stats
     * @return string
     */
    private function getStoreHealthStatus(array $stats): string
    {
        if (empty($stats)) {
            return 'unknown';
        }
        
        $score = $this->calculateStorePerformanceScore($stats);
        
        if ($score >= 80) {
            return 'excellent';
        } elseif ($score >= 60) {
            return 'good';
        } elseif ($score >= 40) {
            return 'average';
        } else {
            return 'poor';
        }
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
        
        $avgDuration = array_sum(array_column($stats, 'duration_seconds')) / count($stats);
        $successRate = (count(array_filter($stats, function($s) { return $s['success'] ?? false; })) / count($stats)) * 100;
        $totalUrls = array_sum(array_column($stats, 'total_urls'));
        $totalTime = array_sum(array_column($stats, 'duration_seconds'));
        $urlsPerMinute = $totalTime > 0 ? ($totalUrls / $totalTime) * 60 : 0;
        
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
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Optimize Generation Speed',
                'description' => 'Average generation time is above recommended thresholds. Consider optimizing database queries or increasing server resources.',
                'action' => 'Review server configuration and database performance'
            ];
        } elseif ($currentMetrics['avg_duration'] > $benchmarks['duration']['good']) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'title' => 'Improve Generation Efficiency',
                'description' => 'Generation speed could be improved for better performance.',
                'action' => 'Consider enabling caching or optimizing sitemap providers'
            ];
        }
        
        // Success rate recommendations
        if ($currentMetrics['success_rate'] < $benchmarks['success_rate']['poor']) {
            $recommendations[] = [
                'type' => 'reliability',
                'priority' => 'high',
                'title' => 'Address Generation Failures',
                'description' => 'High failure rate detected. Review error logs and fix underlying issues.',
                'action' => 'Check system logs and resolve configuration problems'
            ];
        } elseif ($currentMetrics['success_rate'] < $benchmarks['success_rate']['good']) {
            $recommendations[] = [
                'type' => 'reliability',
                'priority' => 'medium',
                'title' => 'Improve Success Rate',
                'description' => 'Success rate is below optimal levels.',
                'action' => 'Review and optimize sitemap generation settings'
            ];
        }
        
        // Throughput recommendations
        if ($currentMetrics['urls_per_minute'] < $benchmarks['urls_per_minute']['poor']) {
            $recommendations[] = [
                'type' => 'scalability',
                'priority' => 'medium',
                'title' => 'Increase Throughput',
                'description' => 'URL processing rate is low. Consider batch size optimization.',
                'action' => 'Adjust batch processing settings or upgrade server resources'
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
        $scores = [];
        
        // Duration score (inverted - lower is better)
        if ($currentMetrics['avg_duration'] <= $benchmarks['duration']['excellent']) {
            $scores[] = 100;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['good']) {
            $scores[] = 80;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['average']) {
            $scores[] = 60;
        } elseif ($currentMetrics['avg_duration'] <= $benchmarks['duration']['poor']) {
            $scores[] = 40;
        } else {
            $scores[] = 20;
        }
        
        // Success rate score
        if ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['excellent']) {
            $scores[] = 100;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['good']) {
            $scores[] = 80;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['average']) {
            $scores[] = 60;
        } elseif ($currentMetrics['success_rate'] >= $benchmarks['success_rate']['poor']) {
            $scores[] = 40;
        } else {
            $scores[] = 20;
        }
        
        // Throughput score
        if ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['excellent']) {
            $scores[] = 100;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['good']) {
            $scores[] = 80;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['average']) {
            $scores[] = 60;
        } elseif ($currentMetrics['urls_per_minute'] >= $benchmarks['urls_per_minute']['poor']) {
            $scores[] = 40;
        } else {
            $scores[] = 20;
        }
        
        return array_sum($scores) / count($scores);
    }

    /**
     * Group statistics by specified criteria
     *
     * @param array $stats
     * @param string $groupBy
     * @return array
     */
    private function groupStatistics(array $stats, string $groupBy): array
    {
        $grouped = [];
        
        foreach ($stats as $stat) {
            $key = $this->getGroupKey($stat, $groupBy);
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $stat;
        }
        
        // Calculate aggregates for each group
        $result = [];
        foreach ($grouped as $key => $groupStats) {
            $result[$key] = [
                'key' => $key,
                'count' => count($groupStats),
                'success_rate' => (count(array_filter($groupStats, function($s) { return $s['success'] ?? false; })) / count($groupStats)) * 100,
                'avg_duration' => array_sum(array_column($groupStats, 'duration_seconds')) / count($groupStats),
                'total_urls' => array_sum(array_column($groupStats, 'total_urls')),
                'total_files' => array_sum(array_column($groupStats, 'files_generated'))
            ];
        }
        
        return $result;
    }

    /**
     * Get grouping key for statistics
     *
     * @param array $stat
     * @param string $groupBy
     * @return string
     */
    private function getGroupKey(array $stat, string $groupBy): string
    {
        switch ($groupBy) {
            case 'date':
                return date('Y-m-d', strtotime($stat['generation_time']));
            case 'week':
                return date('Y-W', strtotime($stat['generation_time']));
            case 'month':
                return date('Y-m', strtotime($stat['generation_time']));
            case 'store':
                return 'store_' . $stat['store_id'];
            case 'hour':
                return date('Y-m-d H', strtotime($stat['generation_time']));
            default:
                return 'all';
        }
    }

    /**
     * Get recent history for export
     *
     * @return array
     */
    private function getRecentHistoryForExport(): array
    {
        $limit = (int)$this->getRequest()->getParam('limit', 1000);
        return $this->statisticsManager->getRecentStatistics($limit, 0);
    }

    /**
     * Get performance stats for export
     *
     * @return array
     */
    private function getPerformanceStatsForExport(): array
    {
        $dateRange = $this->getDateRangeFromRequest();
        $stores = $this->storeManager->getStores();
        $storeIds = array_map(function($store) { return (int)$store->getId(); }, $stores);
        
        return $this->statisticsManager->getStatisticsByDateRange(
            $dateRange['start'],
            $dateRange['end'],
            $storeIds
        );
    }

    /**
     * Get store comparison for export
     *
     * @return array
     */
    private function getStoreComparisonForExport(): array
    {
        $response = $this->getStorePerformance();
        return $response['success'] ? $response['data']['stores'] : [];
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
        switch ($exportType) {
            case 'csv':
                return $this->createCsvExport($data, $dataType);
            case 'json':
                return $this->createJsonExport($data, $dataType);
            case 'xml':
                return $this->createXmlExport($data, $dataType);
            default:
                throw new \InvalidArgumentException('Unsupported export type: ' . $exportType);
        }
    }

    /**
     * Create CSV export response
     *
     * @param array $data
     * @param string $dataType
     * @return ResultInterface
     */
    private function createCsvExport(array $data, string $dataType): ResultInterface
    {
        $filename = 'sitemap_' . $dataType . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        $csvContent = $this->arrayToCsv($data, $dataType);
        
        $resultRaw = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $resultRaw->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $resultRaw->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $resultRaw->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $resultRaw->setHeader('Pragma', 'public');
        $resultRaw->setContents($csvContent);
        
        return $resultRaw;
    }

    /**
     * Create JSON export response
     *
     * @param array $data
     * @param string $dataType
     * @return ResultInterface
     */
    private function createJsonExport(array $data, string $dataType): ResultInterface
    {
        $filename = 'sitemap_' . $dataType . '_' . date('Y-m-d_H-i-s') . '.json';
        
        $jsonContent = json_encode([
            'export_info' => [
                'type' => $dataType,
                'generated_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data)
            ],
            'data' => $data
        ], JSON_PRETTY_PRINT);
        
        $resultRaw = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $resultRaw->setHeader('Content-Type', 'application/json; charset=utf-8');
        $resultRaw->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $resultRaw->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $resultRaw->setHeader('Pragma', 'public');
        $resultRaw->setContents($jsonContent);
        
        return $resultRaw;
    }

    /**
     * Create XML export response
     *
     * @param array $data
     * @param string $dataType
     * @return ResultInterface
     */
    private function createXmlExport(array $data, string $dataType): ResultInterface
    {
        $filename = 'sitemap_' . $dataType . '_' . date('Y-m-d_H-i-s') . '.xml';
        
        $xmlContent = $this->arrayToXml($data, $dataType);
        
        $resultRaw = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $resultRaw->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $resultRaw->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $resultRaw->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $resultRaw->setHeader('Pragma', 'public');
        $resultRaw->setContents($xmlContent);
        
        return $resultRaw;
    }

    /**
     * Convert array to CSV format
     *
     * @param array $data
     * @param string $dataType
     * @return string
     */
    private function arrayToCsv(array $data, string $dataType): string
    {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Get headers from first row
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($data as $row) {
            // Convert objects and arrays to strings
            $cleanRow = [];
            foreach ($row as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $cleanRow[$key] = json_encode($value);
                } else {
                    $cleanRow[$key] = $value;
                }
            }
            fputcsv($output, $cleanRow);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Convert array to XML format
     *
     * @param array $data
     * @param string $dataType
     * @return string
     */
    /**
     * Convert array to XML format
     *
     * @param array $data
     * @param string $dataType
     * @return string
     */
    private function arrayToXml(array $data, string $dataType): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><export/>');

        // Add export info
        $info = $xml->addChild('export_info');
        $info->addChild('type', $dataType);
        $info->addChild('generated_at', date('Y-m-d H:i:s'));
        $info->addChild('total_records', (string)count($data)); // Fixed: explicit cast to string

        // Add data
        $dataNode = $xml->addChild('data');
        foreach ($data as $index => $item) {
            $itemNode = $dataNode->addChild('item');
            $itemNode->addAttribute('index', (string)$index); // Fixed: explicit cast to string

            foreach ($item as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $itemNode->addChild($key, htmlspecialchars(json_encode($value)));
                } else {
                    $itemNode->addChild($key, htmlspecialchars((string)$value)); // Fixed: explicit cast to string
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Calculate percentage change
     *
     * @param float $current
     * @param float $previous
     * @return float|null
     */
    private function calculatePercentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Get trend indicator
     *
     * @param float $change
     * @return string
     */
    private function getTrendIndicator(float $change): string
    {
        if ($change > 5) {
            return 'improving';
        } elseif ($change < -5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Validate date range
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return bool
     */
    private function validateDateRange(\DateTime $startDate, \DateTime $endDate): bool
    {
        // Maximum range of 1 year
        $maxRange = new \DateInterval('P1Y');
        $interval = $startDate->diff($endDate);
        
        return $interval <= $maxRange && $startDate <= $endDate;
    }

    /**
     * Sanitize filename for export
     *
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim underscores from ends
        return trim($filename, '_');
    }

    /**
     * Get memory usage information
     *
     * @return array
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Log performance metrics
     *
     * @param string $operation
     * @param float $startTime
     * @param array $additionalData
     * @return void
     */
    private function logPerformanceMetrics(string $operation, float $startTime, array $additionalData = []): void
    {
        $duration = microtime(true) - $startTime;
        $memory = $this->getMemoryUsage();

        $logData = array_merge([
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_current' => $this->formatBytes($memory['current']),
            'memory_peak' => $this->formatBytes($memory['peak'])
        ], $additionalData);

        // Log performance metrics in developer mode
        try {
            // Check if we're in developer mode through app state
            if ($this->appState && $this->appState->getMode() === \Magento\Framework\App\State::MODE_DEVELOPER) {
                // Use Magento's logger instead of error_log for better integration
                $this->logger->debug('Sitemap Dashboard Performance', $logData);
            }
        } catch (\Exception $e) {
            // Fallback to error_log if appState is not available
            error_log('Sitemap Dashboard Performance: ' . json_encode($logData));
        }
    }

    /**
     * Check if operation should be cached
     *
     * @param string $operation
     * @param array $params
     * @return bool
     */
    private function shouldCache(string $operation, array $params): bool
    {
        // Cache expensive operations with stable parameters
        $cacheableOperations = [
            'provider_comparison',
            'store_performance',
            'benchmark_data'
        ];
        
        return in_array($operation, $cacheableOperations) 
            && !isset($params['force_refresh'])
            && !isset($params['real_time']);
    }

    /**
     * Get cache key for operation
     *
     * @param string $operation
     * @param array $params
     * @return string
     */
    private function getCacheKey(string $operation, array $params): string
    {
        $keyData = [
            'operation' => $operation,
            'params' => $params,
            'version' => '1.0'
        ];
        
        return 'defox_seosuite_dashboard_' . md5(json_encode($keyData));
    }

    /**
     * Rate limit check for expensive operations
     *
     * @param string $operation
     * @return bool
     */
    private function checkRateLimit(string $operation): bool
    {
        // Implement rate limiting for expensive operations
        // This would typically use cache or database to track requests
        return true; // Placeholder
    }

    /**
     * Compress data for large responses
     *
     * @param array $data
     * @return array
     */
    private function compressLargeData(array $data): array
    {
        if (count($data) > 1000) {
            // For large datasets, return summarized data
            return array_slice($data, 0, 1000);
        }
        
        return $data;
    }

    /**
     * Validate export parameters
     *
     * @param string $exportType
     * @param string $dataType
     * @return bool
     */
    private function validateExportParams(string $exportType, string $dataType): bool
    {
        $allowedExportTypes = ['csv', 'json', 'xml'];
        $allowedDataTypes = ['recent_history', 'performance_stats', 'store_comparison'];
        
        return in_array($exportType, $allowedExportTypes) 
            && in_array($dataType, $allowedDataTypes);
    }

    /**
     * Get localized number format
     *
     * @param float $number
     * @param int $decimals
     * @return string
     */
    private function formatNumber(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Convert seconds to human readable duration
     *
     * @param int $seconds
     * @return string
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    /**
     * Get error categories for analysis
     *
     * @param array $errors
     * @return array
     */
    private function categorizeErrors(array $errors): array
    {
        $categories = [
            'configuration' => 0,
            'performance' => 0,
            'network' => 0,
            'storage' => 0,
            'other' => 0
        ];
        
        foreach ($errors as $error) {
            $category = $this->detectErrorCategory($error);
            $categories[$category]++;
        }
        
        return $categories;
    }

    /**
     * Detect error category from error message
     *
     * @param string $error
     * @return string
     */
    private function detectErrorCategory(string $error): string
    {
        $error = strtolower($error);
        
        if (strpos($error, 'config') !== false || strpos($error, 'setting') !== false) {
            return 'configuration';
        } elseif (strpos($error, 'timeout') !== false || strpos($error, 'memory') !== false) {
            return 'performance';
        } elseif (strpos($error, 'network') !== false || strpos($error, 'connection') !== false) {
            return 'network';
        } elseif (strpos($error, 'disk') !== false || strpos($error, 'storage') !== false || strpos($error, 'file') !== false) {
            return 'storage';
        } else {
            return 'other';
        }
    }
}

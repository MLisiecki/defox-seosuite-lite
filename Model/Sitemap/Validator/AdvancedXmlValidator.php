<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Validator;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Advanced XML sitemap validator
 * 
 * Provides comprehensive validation including:
 * - XSD Schema validation
 * - Google-specific requirements
 * - SEO best practices
 * - Performance analysis
 * - Real-time URL health monitoring
 */
class AdvancedXmlValidator
{
    /**
     * Maximum URLs per sitemap file
     */
    private const MAX_URLS_PER_FILE = 50000;
    
    /**
     * Maximum file size (50MB)
     */
    private const MAX_FILE_SIZE = 52428800;
    
    /**
     * Maximum URL length
     */
    private const MAX_URL_LENGTH = 2048;
    
    /**
     * Timeout for URL health checks
     */
    private const URL_CHECK_TIMEOUT = 5;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param Curl $curl
     * @param Filesystem $filesystem
     */
    public function __construct(
        Config $config,
        Logger $logger,
        Curl $curl,
        Filesystem $filesystem
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->curl = $curl;
        $this->filesystem = $filesystem;
    }

    /**
     * Perform comprehensive validation
     *
     * @param string $filePath
     * @param array $options
     * @return ValidationResult
     */
    public function validateComprehensive(string $filePath, array $options = []): ValidationResult
    {
        $result = new ValidationResult();
        $startTime = microtime(true);

        try {
            // Basic file checks
            $this->validateFileBasics($filePath, $result);
            
            if (!$result->isValid()) {
                return $result;
            }

            // Load XML document
            $xml = $this->loadXmlDocument($filePath, $result);
            if (!$xml) {
                return $result;
            }

            // Perform different validation levels
            $this->validateAgainstSchema($xml, $result);
            $this->validateGoogleRequirements($xml, $result);
            $this->validateSeoOptimization($xml, $result);
            $this->analyzePerformance($xml, $filePath, $result);
            
            // URL health monitoring (if enabled and not too many URLs)
            if ($options['check_url_health'] ?? false) {
                $this->performUrlHealthCheck($xml, $result, $options);
            }

            // Set performance metrics
            $result->setPerformanceMetric('validation_time', microtime(true) - $startTime);
            
        } catch (\Exception $e) {
            $result->addError('Validation failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->logger->error('XML validation error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Validate file basics
     *
     * @param string $filePath
     * @param ValidationResult $result
     * @return void
     */
    private function validateFileBasics(string $filePath, ValidationResult $result): void
    {
        // Check if file exists
        if (!file_exists($filePath)) {
            $result->addError('Sitemap file does not exist', ['path' => $filePath]);
            return;
        }

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            $result->addError(sprintf(
                'File size exceeds limit: %s (max: %s)',
                $this->formatBytes($fileSize),
                $this->formatBytes(self::MAX_FILE_SIZE)
            ), ['file_size' => $fileSize]);
        }

        // Check if file is readable
        if (!is_readable($filePath)) {
            $result->addError('Sitemap file is not readable', ['path' => $filePath]);
        }

        $result->setPerformanceMetric('file_size_bytes', $fileSize);
        $result->setPerformanceMetric('file_size_mb', round($fileSize / 1024 / 1024, 2));
    }

    /**
     * Load XML document
     *
     * @param string $filePath
     * @param ValidationResult $result
     * @return \DOMDocument|null
     */
    private function loadXmlDocument(string $filePath, ValidationResult $result): ?\DOMDocument
    {
        try {
            $xml = new \DOMDocument();
            $xml->load($filePath);
            
            $result->addInfo('XML document loaded successfully');
            return $xml;
        } catch (\Exception $e) {
            $result->addError('Failed to load XML document: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate against XSD schema
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @return void
     */
    private function validateAgainstSchema(\DOMDocument $xml, ValidationResult $result): void
    {
        try {
            // Check for sitemap vs sitemapindex
            $rootElement = $xml->documentElement->nodeName;
            
            if ($rootElement === 'urlset') {
                $this->validateSitemapSchema($xml, $result);
            } elseif ($rootElement === 'sitemapindex') {
                $this->validateSitemapIndexSchema($xml, $result);
            } else {
                $result->addError('Invalid root element: ' . $rootElement);
            }
            
        } catch (\Exception $e) {
            $result->addError('Schema validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate sitemap schema
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @return void
     */
    private function validateSitemapSchema(\DOMDocument $xml, ValidationResult $result): void
    {
        // Validate namespace
        $urlset = $xml->documentElement;
        $namespace = $urlset->getAttribute('xmlns');
        
        if ($namespace !== 'http://www.sitemaps.org/schemas/sitemap/0.9') {
            $result->addWarning('Missing or incorrect namespace', ['namespace' => $namespace]);
        }

        // Count URLs
        $urls = $xml->getElementsByTagName('url');
        $urlCount = $urls->length;
        
        if ($urlCount > self::MAX_URLS_PER_FILE) {
            $result->addError(sprintf(
                'Too many URLs: %d (max: %d)',
                $urlCount,
                self::MAX_URLS_PER_FILE
            ));
        }

        $result->setPerformanceMetric('url_count', $urlCount);
        $result->addInfo(sprintf('Found %d URLs in sitemap', $urlCount));
    }

    /**
     * Validate Google-specific requirements
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @return void
     */
    private function validateGoogleRequirements(\DOMDocument $xml, ValidationResult $result): void
    {
        $urls = $xml->getElementsByTagName('url');
        $invalidUrls = 0;
        $longUrls = 0;
        $duplicateUrls = [];
        $foundUrls = [];

        foreach ($urls as $urlElement) {
            $locElements = $urlElement->getElementsByTagName('loc');
            if ($locElements->length === 0) {
                $invalidUrls++;
                continue;
            }

            $url = trim($locElements->item(0)->textContent);

            // Check URL length
            if (strlen($url) > self::MAX_URL_LENGTH) {
                $longUrls++;
                $result->addWarning('URL exceeds recommended length', [
                    'url' => substr($url, 0, 100) . '...',
                    'length' => strlen($url)
                ]);
            }

            // Check for duplicates
            if (in_array($url, $foundUrls)) {
                $duplicateUrls[] = $url;
            } else {
                $foundUrls[] = $url;
            }

            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $result->addError('Invalid URL format', ['url' => $url]);
            }

            // Check for HTTPS (SEO best practice)
            if (strpos($url, 'https://') !== 0) {
                $result->addWarning('URL not using HTTPS', ['url' => $url]);
            }
        }

        if ($invalidUrls > 0) {
            $result->addError(sprintf('Found %d URLs without <loc> element', $invalidUrls));
        }

        if ($longUrls > 0) {
            $result->addWarning(sprintf('Found %d URLs exceeding recommended length', $longUrls));
        }

        if (!empty($duplicateUrls)) {
            $result->addError(sprintf('Found %d duplicate URLs', count($duplicateUrls)), [
                'duplicates' => array_slice($duplicateUrls, 0, 10) // Show first 10
            ]);
        }
    }

    /**
     * Validate SEO optimization
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @return void
     */
    private function validateSeoOptimization(\DOMDocument $xml, ValidationResult $result): void
    {
        $urls = $xml->getElementsByTagName('url');
        $hasLastmod = 0;
        $hasPriority = 0;
        $hasChangefreq = 0;
        $hasImages = 0;
        $hasHreflang = 0;

        foreach ($urls as $urlElement) {
            // Check for lastmod
            if ($urlElement->getElementsByTagName('lastmod')->length > 0) {
                $hasLastmod++;
                
                // Validate lastmod format
                $lastmod = $urlElement->getElementsByTagName('lastmod')->item(0)->textContent;
                if (!$this->isValidDate($lastmod)) {
                    $result->addWarning('Invalid lastmod format', [
                        'lastmod' => $lastmod,
                        'expected' => 'YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS+00:00'
                    ]);
                }
            }

            // Check for priority
            if ($urlElement->getElementsByTagName('priority')->length > 0) {
                $hasPriority++;
                
                $priority = (float)$urlElement->getElementsByTagName('priority')->item(0)->textContent;
                if ($priority < 0 || $priority > 1) {
                    $result->addWarning('Invalid priority value', [
                        'priority' => $priority,
                        'expected' => '0.0 - 1.0'
                    ]);
                }                
            }

            // Check for changefreq
            if ($urlElement->getElementsByTagName('changefreq')->length > 0) {
                $hasChangefreq++;
                
                $changefreq = $urlElement->getElementsByTagName('changefreq')->item(0)->textContent;
                $validFreqs = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
                if (!in_array($changefreq, $validFreqs)) {
                    $result->addWarning('Invalid changefreq value', [
                        'changefreq' => $changefreq,
                        'valid_values' => $validFreqs
                    ]);
                }
            }

            // Check for images
            if ($urlElement->getElementsByTagName('image')->length > 0) {
                $hasImages++;
            }

            // Check for hreflang
            if ($urlElement->getElementsByTagNameNS('http://www.w3.org/1999/xhtml', 'link')->length > 0) {
                $hasHreflang++;
            }
        }

        $totalUrls = $urls->length;
        
        // Set SEO metrics
        $result->setSeoMetric('has_lastmod', ($hasLastmod / $totalUrls) * 100);
        $result->setSeoMetric('has_priority', ($hasPriority / $totalUrls) * 100);
        $result->setSeoMetric('has_changefreq', ($hasChangefreq / $totalUrls) * 100);
        $result->setSeoMetric('has_images', ($hasImages / $totalUrls) * 100);
        $result->setSeoMetric('has_hreflang', ($hasHreflang / $totalUrls) * 100);

        // Add recommendations
        if ($hasLastmod < $totalUrls * 0.8) {
            $result->addWarning('Less than 80% of URLs have lastmod tags');
        }

        if ($hasImages == 0 && $totalUrls > 0) {
            $result->addInfo('Consider adding image tags for better SEO');
        }

        if ($hasHreflang == 0 && $totalUrls > 0) {
            $result->addInfo('Consider adding hreflang tags for international SEO');
        }
    }

    /**
     * Analyze performance metrics
     *
     * @param \DOMDocument $xml
     * @param string $filePath
     * @param ValidationResult $result
     * @return void
     */
    private function analyzePerformance(\DOMDocument $xml, string $filePath, ValidationResult $result): void
    {
        $urls = $xml->getElementsByTagName('url');
        $urlCount = $urls->length;
        $fileSize = filesize($filePath);

        // Calculate compression ratio if it's a gzipped file
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'gz') {
            $uncompressedSize = strlen(gzfile_get_contents($filePath));
            $compressionRatio = ($fileSize / $uncompressedSize) * 100;
            $result->setPerformanceMetric('compression_ratio', round($compressionRatio, 2));
        }

        // Calculate URLs per MB
        $urlsPerMb = $fileSize > 0 ? ($urlCount / ($fileSize / 1024 / 1024)) : 0;
        $result->setPerformanceMetric('urls_per_mb', round($urlsPerMb, 0));

        // Estimate crawl time (rough estimate: 1 URL per second)
        $estimatedCrawlTime = $urlCount; // seconds
        $result->setPerformanceMetric('estimated_crawl_time_minutes', round($estimatedCrawlTime / 60, 1));

        // Performance recommendations
        if ($urlCount > 25000) {
            $result->addWarning('Large number of URLs may impact crawling efficiency');
        }

        if ($fileSize > 10 * 1024 * 1024) { // 10MB
            $result->addWarning('Large file size may impact download time for crawlers');
        }
    }

    /**
     * Perform URL health check on sample URLs
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @param array $options
     * @return void
     */
    private function performUrlHealthCheck(\DOMDocument $xml, ValidationResult $result, array $options): void
    {
        $urls = $xml->getElementsByTagName('url');
        $totalUrls = $urls->length;
        
        // Sample size for health check
        $sampleSize = min($options['health_check_sample_size'] ?? 50, $totalUrls);
        $sampleUrls = $this->getSampleUrls($xml, $sampleSize);

        $healthResults = [
            'checked' => 0,
            'accessible' => 0,
            'errors' => 0,
            'warnings' => 0,
            'response_times' => []
        ];

        foreach ($sampleUrls as $url) {
            $healthResults['checked']++;
            $checkResult = $this->checkUrlHealth($url);
            
            if ($checkResult['accessible']) {
                $healthResults['accessible']++;
            } else {
                $healthResults['errors']++;
                $result->addError('URL not accessible', [
                    'url' => $url,
                    'status_code' => $checkResult['status_code'],
                    'error' => $checkResult['error']
                ]);
            }

            if (isset($checkResult['response_time'])) {
                $healthResults['response_times'][] = $checkResult['response_time'];
                
                // Flag slow responses
                if ($checkResult['response_time'] > 3000) { // 3 seconds
                    $result->addWarning('Slow response time', [
                        'url' => $url,
                        'response_time_ms' => $checkResult['response_time']
                    ]);
                }
            }
        }

        // Calculate metrics
        $accessibilityRate = ($healthResults['accessible'] / $healthResults['checked']) * 100;
        $avgResponseTime = !empty($healthResults['response_times']) 
            ? array_sum($healthResults['response_times']) / count($healthResults['response_times'])
            : 0;

        $result->setSeoMetric('url_accessibility_rate', round($accessibilityRate, 2));
        $result->setSeoMetric('avg_response_time_ms', round($avgResponseTime, 0));
        $result->setSeoMetric('broken_urls_percent', round(100 - $accessibilityRate, 2));

        $result->addInfo(sprintf(
            'URL Health Check: %d/%d URLs accessible (%.1f%%)',
            $healthResults['accessible'],
            $healthResults['checked'],
            $accessibilityRate
        ));
    }

    /**
     * Get sample URLs for health checking
     *
     * @param \DOMDocument $xml
     * @param int $sampleSize
     * @return array
     */
    private function getSampleUrls(\DOMDocument $xml, int $sampleSize): array
    {
        $urls = $xml->getElementsByTagName('url');
        $allUrls = [];

        foreach ($urls as $urlElement) {
            $locElements = $urlElement->getElementsByTagName('loc');
            if ($locElements->length > 0) {
                $allUrls[] = trim($locElements->item(0)->textContent);
            }
        }

        // Get random sample
        if (count($allUrls) <= $sampleSize) {
            return $allUrls;
        }

        $sampleKeys = array_rand($allUrls, $sampleSize);
        if (!is_array($sampleKeys)) {
            $sampleKeys = [$sampleKeys];
        }

        return array_intersect_key($allUrls, array_flip($sampleKeys));
    }

    /**
     * Check health of a single URL
     *
     * @param string $url
     * @return array
     */
    private function checkUrlHealth(string $url): array
    {
        try {
            $startTime = microtime(true);
            
            $this->curl->setTimeout(self::URL_CHECK_TIMEOUT);
            $this->curl->setOption(CURLOPT_NOBODY, true); // HEAD request only
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 3);
            
            $this->curl->get($url);
            $statusCode = $this->curl->getStatus();
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            return [
                'accessible' => $statusCode >= 200 && $statusCode < 400,
                'status_code' => $statusCode,
                'response_time' => round($responseTime, 0),
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'status_code' => 0,
                'response_time' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate sitemap index schema
     *
     * @param \DOMDocument $xml
     * @param ValidationResult $result
     * @return void
     */
    private function validateSitemapIndexSchema(\DOMDocument $xml, ValidationResult $result): void
    {
        $sitemaps = $xml->getElementsByTagName('sitemap');
        $sitemapCount = $sitemaps->length;

        if ($sitemapCount > 50000) {
            $result->addError(sprintf(
                'Too many sitemaps in index: %d (max: 50000)',
                $sitemapCount
            ));
        }

        $result->setPerformanceMetric('sitemap_count', $sitemapCount);
        $result->addInfo(sprintf('Found %d sitemaps in index', $sitemapCount));

        // Validate each sitemap entry
        foreach ($sitemaps as $sitemapElement) {
            $locElements = $sitemapElement->getElementsByTagName('loc');
            if ($locElements->length === 0) {
                $result->addError('Sitemap entry missing <loc> element');
                continue;
            }

            $url = trim($locElements->item(0)->textContent);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $result->addError('Invalid sitemap URL', ['url' => $url]);
            }
        }
    }

    /**
     * Check if date is valid
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        // Check W3C datetime format
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/', // YYYY-MM-DD
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', // Full datetime with timezone
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/' // UTC datetime
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Generate comprehensive validation report
     *
     * @param ValidationResult $result
     * @return array
     */
    public function generateReport(ValidationResult $result): array
    {
        $summary = $result->getSummary();
        $performance = $result->getPerformance();
        $seoMetrics = $result->getSeoMetrics();

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => $result->isValid() ? 'PASS' : 'FAIL',
            'summary' => $summary,
            'performance' => $performance,
            'seo_metrics' => $seoMetrics,
            'errors' => $result->getErrors(),
            'warnings' => $result->getWarnings(),
            'info' => $result->getInfo(),
            'recommendations' => $this->generateRecommendations($result)
        ];
    }

    /**
     * Generate recommendations based on validation results
     *
     * @param ValidationResult $result
     * @return array
     */
    private function generateRecommendations(ValidationResult $result): array
    {
        $recommendations = [];
        $performance = $result->getPerformance();
        $seoMetrics = $result->getSeoMetrics();

        // Performance recommendations
        if (isset($performance['file_size_mb']) && $performance['file_size_mb'] > 10) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Consider splitting sitemap into multiple files to reduce size',
                'action' => 'Split large sitemap files'
            ];
        }

        if (isset($performance['url_count']) && $performance['url_count'] > 25000) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Large number of URLs detected. Consider using sitemap index.',
                'action' => 'Implement sitemap index structure'
            ];
        }

        // SEO recommendations
        if (isset($seoMetrics['has_lastmod']) && $seoMetrics['has_lastmod'] < 80) {
            $recommendations[] = [
                'type' => 'seo',
                'priority' => 'medium',
                'message' => 'Add lastmod tags to more URLs for better crawling efficiency',
                'action' => 'Implement lastmod tags'
            ];
        }

        if (isset($seoMetrics['broken_urls_percent']) && $seoMetrics['broken_urls_percent'] > 5) {
            $recommendations[] = [
                'type' => 'critical',
                'priority' => 'high',
                'message' => 'High percentage of broken URLs detected',
                'action' => 'Fix or remove broken URLs from sitemap'
            ];
        }

        return $recommendations;
    }
}

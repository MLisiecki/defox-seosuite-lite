<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Generator;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Defox\SEOSuite\Model\Sitemap\SitemapGeneratorInterface;
use Defox\SEOSuite\Model\Sitemap\SitemapProviderPool;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

/**
 * XML sitemap generator
 * 
 * Generates XML sitemaps with support for:
 * - Multiple files (50,000 URLs limit per file)
 * - Gzip compression
 * - Sitemap index
 * - Hreflang tags
 * - Image sitemaps
 */
class XmlGenerator implements SitemapGeneratorInterface
{
    /**
     * Maximum URLs per sitemap file
     */
    private const MAX_URLS_PER_FILE = 50000;
    
    /**
     * Maximum file size (50MB uncompressed)
     */
    private const MAX_FILE_SIZE = 52428800; // 50MB
    
    /**
     * XML namespace for sitemap
     */
    private const XML_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    
    /**
     * XML namespace for image sitemap
     */
    private const XML_NAMESPACE_IMAGE = 'http://www.google.com/schemas/sitemap-image/1.1';
    
    /**
     * XML namespace for hreflang
     */
    private const XML_NAMESPACE_XHTML = 'http://www.w3.org/1999/xhtml';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @var SitemapProviderPool
     */
    private SitemapProviderPool $providerPool;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var EventManager
     */
    private EventManager $eventManager;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var WriteInterface
     */
    private ?WriteInterface $directory = null;

    /**
     * @var array
     */
    private array $generationStats = [];
    
    /**
     * @var array
     */
    private array $generationOptions = [];

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param CacheManager $cacheManager
     * @param SitemapProviderPool $providerPool
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param EventManager $eventManager
     * @param DateTime $dateTime
     * @param Curl $curl
     */
    public function __construct(
        Config $config,
        Logger $logger,
        CacheManager $cacheManager,
        SitemapProviderPool $providerPool,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        EventManager $eventManager,
        DateTime $dateTime,
        Curl $curl
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
        $this->providerPool = $providerPool;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->eventManager = $eventManager;
        $this->dateTime = $dateTime;
        $this->curl = $curl;
    }

    /**
     * @inheritDoc
     */
    public function generate(int $storeId, array $options = []): array
    {
        $startTime = microtime(true);
        $this->generationOptions = $options;
        $this->generationStats = [
            'start_time' => $this->dateTime->gmtDate(),
            'store_id' => $storeId,
            'files' => [],
            'total_urls' => 0,
            'errors' => []
        ];
        
        try {
            $this->logger->info(sprintf('Starting sitemap generation for store %d', $storeId));
            
            // Clean old files first (or if force_regeneration is enabled)
            if (isset($options['force_regeneration']) && $options['force_regeneration']) {
                $this->logger->info('Force regeneration enabled - cleaning all old files');
                $this->cleanOldFiles($storeId);
            } else {
                // Normal cleanup logic - could check if files exist and are recent
                $this->cleanOldFiles($storeId);
            }
            
            // Get all items from enabled providers
            $allItems = $this->getAllItems($storeId, $options);
            
            if (empty($allItems)) {
                throw new \Exception('No items found for sitemap generation');
            }
            
            // Generate sitemap files
            $generatedFiles = $this->generateSitemapFiles($storeId, $allItems);
            
            // Generate sitemap index if multiple files
            if (count($generatedFiles) > 1) {
                $indexFile = $this->generateIndex($storeId, $generatedFiles);
                array_unshift($generatedFiles, $indexFile);
            }
            
            // Clear sitemap cache
            $this->cacheManager->clean(['defox_seosuite_sitemap']);
            
            // Validate sitemaps if enabled
            if (isset($options['validate_after_generation']) && $options['validate_after_generation']) {
                $this->validateGeneratedFiles($generatedFiles);
            }
            
            // Ping search engines if enabled (check options first, then config)
            $shouldPing = isset($options['ping_search_engines']) 
                ? $options['ping_search_engines'] 
                : $this->config->getValue('defox_seosuite/sitemap/ping_search_engines', $storeId);
                
            if ($shouldPing) {
                $mainSitemapUrl = $this->getSitemapUrl($storeId, basename($generatedFiles[0]));
                $this->pingSearchEngines($mainSitemapUrl, $storeId);
            }
            
            $this->generationStats['end_time'] = $this->dateTime->gmtDate();
            $this->generationStats['duration'] = microtime(true) - $startTime;
            
            $this->logger->info(sprintf(
                'Sitemap generation completed for store %d. Generated %d files with %d URLs in %.2f seconds',
                $storeId,
                count($generatedFiles),
                $this->generationStats['total_urls'],
                $this->generationStats['duration']
            ));
            
            return $generatedFiles;
        } catch (\Exception $e) {
            $this->logger->error('Error generating sitemap: ' . $e->getMessage());
            $this->generationStats['errors'][] = $e->getMessage();
            
            // Send error notification if enabled
            if ($this->config->getValue('defox_seosuite/sitemap/generation_error_notification', $storeId)) {
                $this->sendErrorNotification($storeId, $e);
            }
            
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function generateIndex(int $storeId, array $sitemapFiles): string
    {
        try {
            $indexContent = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
            $indexContent .= '<sitemapindex xmlns="' . self::XML_NAMESPACE . '">' . PHP_EOL;
            
            foreach ($sitemapFiles as $file) {
                $url = $this->getSitemapUrl($storeId, basename($file));
                $lastmod = $this->dateTime->gmtDate('Y-m-d\TH:i:s\Z');
                
                $indexContent .= '  <sitemap>' . PHP_EOL;
                $indexContent .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
                $indexContent .= '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
                $indexContent .= '  </sitemap>' . PHP_EOL;
            }
            
            $indexContent .= '</sitemapindex>';
            
            // Write index file
            $indexFileName = 'sitemap_index.xml';
            $this->writeFile($indexFileName, $indexContent);
            
            // Compress if enabled
            $compressionEnabled = $this->config->getValue('defox_seosuite/sitemap/enable_compression', $storeId);
            
            if ($compressionEnabled) {
                $this->compressFile($indexFileName);
                $indexFileName .= '.gz';
            }
            
            return $this->getFilePath($storeId, 0, $indexFileName);
        } catch (\Exception $e) {
            $this->logger->error('Error generating sitemap index: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function pingSearchEngines(string $sitemapUrl, int $storeId = 0): array
    {
        $results = [];
        
        // Google
        if ($this->config->getValue('defox_seosuite/sitemap/ping_search_engines', $storeId)) {
            $googleUrl = sprintf(
                'https://www.google.com/ping?sitemap=%s',
                urlencode($sitemapUrl)
            );
            $results['google'] = $this->pingUrl($googleUrl, 'Google');
        }
        
        // Bing
        if ($this->config->getValue('defox_seosuite/sitemap/ping_search_engines', $storeId)) {
            $bingUrl = sprintf(
                'https://www.bing.com/ping?sitemap=%s',
                urlencode($sitemapUrl)
            );
            $results['bing'] = $this->pingUrl($bingUrl, 'Bing');
        }
        
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getFilePath(int $storeId, int $fileNumber = 0, string $customFileName = ''): string
    {
        $directory = $this->getDirectory();
        $path = $this->config->getValue('defox_seosuite/sitemap/path', $storeId);
        
        // Ensure path ends with slash
        $path = rtrim($path, '/') . '/';
        
        if ($customFileName) {
            $fileName = $customFileName;
        } else {
            $fileName = $fileNumber > 0 
                ? sprintf('sitemap_%d.xml', $fileNumber)
                : 'sitemap.xml';
        }
        
        return $directory->getAbsolutePath($path . $fileName);
    }

    /**
     * @inheritDoc
     */
    public function getSitemapUrl(int $storeId, string $fileName): string
    {
        $store = $this->storeManager->getStore($storeId);
        $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        $path = $this->config->getValue('defox_seosuite/sitemap/path', $storeId);
        
        // Ensure path ends with slash
        $path = rtrim($path, '/') . '/';
        
        return $baseUrl . ltrim($path, '/') . $fileName;
    }

    /**
     * @inheritDoc
     */
    public function cleanOldFiles(int $storeId): int
    {
        $deletedCount = 0;
        
        try {
            $directory = $this->getDirectory();
            $path = $this->config->getValue('defox_seosuite/sitemap/path', $storeId);
            
            // Ensure path ends with slash
            $path = rtrim($path, '/') . '/';
            
            // Pattern to match sitemap files
            $patterns = [
                'sitemap*.xml',
                'sitemap*.xml.gz',
                'sitemap_index.xml',
                'sitemap_index.xml.gz'
            ];
            
            foreach ($patterns as $pattern) {
                $files = $directory->search($path . $pattern);
                foreach ($files as $file) {
                    if ($directory->isFile($file)) {
                        $directory->delete($file);
                        $deletedCount++;
                    }
                }
            }
            
            $this->logger->info(sprintf('Cleaned %d old sitemap files', $deletedCount));
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning old sitemap files: ' . $e->getMessage());
        }
        
        return $deletedCount;
    }

    /**
     * @inheritDoc
     */
    public function validateSitemap(string $filePath): array
    {
        $result = ['valid' => false, 'errors' => []];
        
        try {
            if (!file_exists($filePath)) {
                $result['errors'][] = 'File does not exist';
                return $result;
            }
            
            // Load XML
            $xml = new \DOMDocument();
            $xml->load($filePath);
            
            // Validate against schema
            $schemaPath = __DIR__ . '/../../../etc/sitemap.xsd';
            if (file_exists($schemaPath)) {
                if (!$xml->schemaValidate($schemaPath)) {
                    $result['errors'][] = 'XML schema validation failed';
                }
            }
            
            // Check URL count
            $urls = $xml->getElementsByTagName('url');
            if ($urls->length > self::MAX_URLS_PER_FILE) {
                $result['errors'][] = sprintf(
                    'Too many URLs: %d (max: %d)',
                    $urls->length,
                    self::MAX_URLS_PER_FILE
                );
            }
            
            // Check file size
            $fileSize = filesize($filePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                $result['errors'][] = sprintf(
                    'File too large: %s (max: %s)',
                    $this->formatBytes($fileSize),
                    $this->formatBytes(self::MAX_FILE_SIZE)
                );
            }
            
            if (empty($result['errors'])) {
                $result['valid'] = true;
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Validation error: ' . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Get all items from providers
     *
     * @param int $storeId
     * @param array $options
     * @return array
     */
    private function getAllItems(int $storeId, array $options = []): array
    {
        $allItems = [];
        $batchSize = 1000; // Process in batches to manage memory

        $enabledProviders = $this->providerPool->getEnabledProviders($storeId);
        
        if (empty($enabledProviders)) {
            $this->logger->warning(sprintf(
                'XmlGenerator: No enabled providers found for store %d',
                $storeId
            ));
            return [];
        }
        
        foreach ($enabledProviders as $providerCode => $provider) {
            try {
                
                $offset = 0;
                $hasMore = true;
                $providerItemsCount = 0;
                
                while ($hasMore) {
                    $items = $provider->getItems($storeId, $batchSize, $offset);
                    
                    if (empty($items)) {
                        $hasMore = false;
                    } else {
                        $allItems = array_merge($allItems, $items);
                        $providerItemsCount += count($items);
                        $offset += $batchSize;
                        
                        if (count($items) < $batchSize) {
                            $hasMore = false;
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'XmlGenerator: Error getting items from provider "%s" for store %d: %s',
                    $providerCode,
                    $storeId,
                    $e->getMessage()
                ));
                $this->logger->error('Stack trace: ' . $e->getTraceAsString());
                $this->generationStats['errors'][] = sprintf(
                    'Provider %s: %s',
                    $providerCode,
                    $e->getMessage()
                );
            }
        }
        
        return $allItems;
    }

    /**
     * Generate sitemap files
     *
     * @param int $storeId
     * @param array $items
     * @return array Generated file paths
     */
    private function generateSitemapFiles(int $storeId, array $items): array
    {
        $generatedFiles = [];
        $fileNumber = 1;
        $currentFileUrls = 0;
        $currentFileSize = 0;
        
        // Start first file
        $xmlWriter = $this->startSitemapFile();
        
        foreach ($items as $item) {
            if (!$item->isIncluded()) {
                continue;
            }
            
            // Check if we need to start a new file
            if ($currentFileUrls >= self::MAX_URLS_PER_FILE || 
                $currentFileSize >= self::MAX_FILE_SIZE * 0.9) { // 90% of max size
                
                // Close current file
                $filePath = $this->finishSitemapFile($xmlWriter, $storeId, $fileNumber);
                $generatedFiles[] = $filePath;
                
                // Start new file
                $fileNumber++;
                $currentFileUrls = 0;
                $currentFileSize = 0;
                $xmlWriter = $this->startSitemapFile();
            }
            
            // Write URL
            $urlXml = $this->generateUrlXml($item);
            $xmlWriter->writeRaw($urlXml);
            
            $currentFileUrls++;
            $currentFileSize += strlen($urlXml);
            $this->generationStats['total_urls']++;
        }
        
        // Close last file
        if ($currentFileUrls > 0) {
            $filePath = $this->finishSitemapFile($xmlWriter, $storeId, $fileNumber);
            $generatedFiles[] = $filePath;
        }
        
        return $generatedFiles;
    }

    /**
     * Start a new sitemap file
     *
     * @return \XMLWriter
     */
    private function startSitemapFile(): \XMLWriter
    {
        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString('  ');
        
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->startElement('urlset');
        $xmlWriter->writeAttribute('xmlns', self::XML_NAMESPACE);
        $xmlWriter->writeAttribute('xmlns:image', self::XML_NAMESPACE_IMAGE);
        $xmlWriter->writeAttribute('xmlns:xhtml', self::XML_NAMESPACE_XHTML);
        
        return $xmlWriter;
    }

    /**
     * Finish and save sitemap file
     *
     * @param \XMLWriter $xmlWriter
     * @param int $storeId
     * @param int $fileNumber
     * @return string File path
     */
    private function finishSitemapFile(\XMLWriter $xmlWriter, int $storeId, int $fileNumber): string
    {
        $xmlWriter->endElement(); // urlset
        $xmlWriter->endDocument();
        
        $content = $xmlWriter->outputMemory();
        $fileName = $fileNumber > 1 
            ? sprintf('sitemap_%d.xml', $fileNumber)
            : 'sitemap.xml';
        
        $this->writeFile($fileName, $content);
        
        // Compress if enabled
        $compressionEnabled = $this->config->getValue('defox_seosuite/sitemap/enable_compression', $storeId);
        
        if ($compressionEnabled) {
            $this->compressFile($fileName);
            $fileName .= '.gz';
        }
        
        $filePath = $this->getFilePath($storeId, $fileNumber, $fileName);
        
        $this->generationStats['files'][] = [
            'name' => $fileName,
            'size' => strlen($content),
            'urls' => substr_count($content, '<url>')
        ];
        
        return $filePath;
    }

    /**
     * Generate XML for a single URL
     *
     * @param \Defox\SEOSuite\Model\Sitemap\SitemapItemInterface $item
     * @return string
     */
    private function generateUrlXml($item): string
    {
        $xml = '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($item->getUrl()) . '</loc>' . PHP_EOL;
        
        if ($item->getLastmod()) {
            $xml .= '    <lastmod>' . $item->getLastmod() . '</lastmod>' . PHP_EOL;
        }
        
        if ($item->getChangefreq()) {
            $xml .= '    <changefreq>' . $item->getChangefreq() . '</changefreq>' . PHP_EOL;
        }
        
        if ($item->getPriority() !== null) {
            $xml .= '    <priority>' . number_format($item->getPriority(), 1) . '</priority>' . PHP_EOL;
        }
        
        // Add alternate language versions (if enabled in options)
        $includeHreflang = isset($this->generationOptions['include_hreflang']) 
        ? $this->generationOptions['include_hreflang'] 
        : $this->config->getValue('defox_seosuite/sitemap/enable_hreflang', $this->generationStats['store_id']);
        
        if ($includeHreflang) {
        foreach ($item->getAlternates() as $alternate) {
                $xml .= sprintf(
                    '    <xhtml:link rel="alternate" hreflang="%s" href="%s" />' . PHP_EOL,
                    htmlspecialchars($alternate['lang']),
                    htmlspecialchars($alternate['url'])
            );
        }
        }
        
        // Add images (if enabled in options)
        $includeImages = isset($this->generationOptions['include_images']) 
        ? $this->generationOptions['include_images'] 
        : $this->config->getValue('defox_seosuite/sitemap/product/include_images', $this->generationStats['store_id']);
        
        if ($includeImages) {
        foreach ($item->getImages() as $image) {
            $xml .= '    <image:image>' . PHP_EOL;
                $xml .= '      <image:loc>' . htmlspecialchars($image['url']) . '</image:loc>' . PHP_EOL;
                    
                    if (!empty($image['title'])) {
                        $xml .= '      <image:title>' . htmlspecialchars($image['title']) . '</image:title>' . PHP_EOL;
                    }
                    
                    if (!empty($image['caption'])) {
                        $xml .= '      <image:caption>' . htmlspecialchars($image['caption']) . '</image:caption>' . PHP_EOL;
                    }
                    
                    $xml .= '    </image:image>' . PHP_EOL;
                }
            }
        
        $xml .= '  </url>' . PHP_EOL;
        
        return $xml;
    }

    /**
     * Write file
     *
     * @param string $fileName
     * @param string $content
     * @return void
     */
    private function writeFile(string $fileName, string $content): void
    {
        $directory = $this->getDirectory();
        $path = $this->config->getValue('defox_seosuite/sitemap/path');
        
        // Ensure path ends with slash
        $path = rtrim($path, '/') . '/';
        
        $fullPath = $path . $fileName;

        // Ensure directory exists
        $directory->create($path);
        
        $directory->writeFile($fullPath, $content);
    }

    /**
     * Compress file using gzip
     *
     * @param string $fileName
     * @return void
     */
    private function compressFile(string $fileName): void
    {
        $directory = $this->getDirectory();
        $path = $this->config->getValue('defox_seosuite/sitemap/path');
        
        // Ensure path ends with slash
        $path = rtrim($path, '/') . '/';
        $filePath = $path . $fileName;
        
        $content = $directory->readFile($filePath);
        $compressed = gzencode($content, 9);
        
        $directory->writeFile($filePath . '.gz', $compressed);
        $directory->delete($filePath);
    }

    /**
     * Ping URL
     *
     * @param string $url
     * @param string $engineName
     * @return array
     */
    private function pingUrl(string $url, string $engineName): array
    {
        try {
            $this->curl->get($url);
            $status = $this->curl->getStatus();
            
            $result = [
                'success' => $status >= 200 && $status < 300,
                'status' => $status,
                'message' => $status >= 200 && $status < 300 
                    ? 'Successfully pinged ' . $engineName
                    : 'Failed to ping ' . $engineName . '. HTTP status: ' . $status
            ];

        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'status' => 0,
                'message' => 'Error pinging ' . $engineName . ': ' . $e->getMessage()
            ];
            
            $this->logger->error('Error pinging ' . $engineName . ': ' . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Send error notification
     *
     * @param int $storeId
     * @param \Exception $exception
     * @return void
     */
    private function sendErrorNotification(int $storeId, \Exception $exception): void
    {
        try {
            $this->eventManager->dispatch('defox_seosuite_sitemap_generation_error', [
                'store_id' => $storeId,
                'exception' => $exception,
                'stats' => $this->generationStats
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error sending error notification: ' . $e->getMessage());
        }
    }

    /**
     * Get directory
     *
     * @return WriteInterface
     */
    private function getDirectory(): WriteInterface
    {
        if ($this->directory === null) {
            $this->directory = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);
        }
        
        return $this->directory;
    }

    /**
     * Validate generated files
     *
     * @param array $generatedFiles
     * @return void
     */
    private function validateGeneratedFiles(array $generatedFiles): void
    {
        foreach ($generatedFiles as $filePath) {
            $validation = $this->validateSitemap($filePath);
            if (!$validation['valid']) {
                $this->logger->warning(sprintf(
                    'Sitemap validation failed for %s: %s',
                    basename($filePath),
                    implode(', ', $validation['errors'])
                ));
                $this->generationStats['errors'][] = sprintf(
                    'Validation failed for %s: %s',
                    basename($filePath),
                    implode(', ', $validation['errors'])
                );
            } else {
                $this->logger->info(sprintf(
                    'Sitemap validation passed for %s',
                    basename($filePath)
                ));
            }
        }
    }

    /**
     * Format bytes to human readable size
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
}

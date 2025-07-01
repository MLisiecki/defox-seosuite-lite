<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Provider;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Defox\SEOSuite\Model\Sitemap\SitemapProviderInterface;
use Defox\SEOSuite\Model\Sitemap\SitemapItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Abstract base class for sitemap providers
 * 
 * Provides common functionality for all sitemap data providers
 */
abstract class AbstractProvider implements SitemapProviderInterface
{
    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var CacheManager
     */
    protected CacheManager $cacheManager;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * Cache lifetime in seconds (1 hour)
     */
    protected const CACHE_LIFETIME = 3600;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Config $config,
        Logger $logger,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId): bool
    {
        // Check if the main SEO Suite is enabled
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }
        
        // Check if sitemap functionality is enabled
        if (!$this->config->getValue('defox_seosuite/sitemap/enabled', $storeId)) {
            return false;
        }
        
        // Check if this specific provider is enabled
        $configPath = 'sitemap/' . $this->getCode() . '/enabled';
        $providerEnabled = $this->config->getValue($configPath, $storeId);
        
        $this->logger->debug(sprintf(
            'Provider %s: Checking config path "defox_seosuite/%s" for store %d, value: %s',
            $this->getCode(),
            $configPath,
            $storeId,
            var_export($providerEnabled, true)
        ));
        
        // Fallback to true if configuration doesn't exist (for backwards compatibility)
        if ($providerEnabled === null) {
            $this->logger->warning(sprintf(
                'Provider configuration "sitemap/%s/enabled" not found for store %d, defaulting to enabled',
                $this->getCode(),
                $storeId
            ));
            return true;
        }
        
        $isEnabled = (bool)$providerEnabled;
        return $isEnabled;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultChangefreq(): string
    {
        $changefreq = $this->config->getValue('defox_seosuite/sitemap/' . $this->getCode() . '/changefreq');
        return $changefreq ?: 'daily';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultPriority(): float
    {
        $priority = $this->config->getValue('defox_seosuite/sitemap/' . $this->getCode() . '/priority');
        if ($priority !== null && $priority !== '') {
            $priorityValue = (float)$priority;
            // Ensure priority is within valid range (0.0 to 1.0)
            return max(0.0, min(1.0, $priorityValue));
        }
        
        // Default priorities based on provider type
        $defaults = [
            'product' => 0.8,
            'category' => 0.7,
            'cms_page' => 0.6,
            'additional_links' => 0.5
        ];
        
        return $defaults[$this->getCode()] ?? 0.5;
    }

    /**
     * @inheritDoc
     */
    public function supportsHreflang(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsImages(): bool
    {
        return false;
    }

    /**
     * Get cache key for items
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function getCacheKey(int $storeId, int $limit, int $offset): string
    {
        return sprintf(
            'defox_seosuite_sitemap_%s_items_%d_%d_%d',
            $this->getCode(),
            $storeId,
            $limit,
            $offset
        );
    }

    /**
     * Get cache key for count
     *
     * @param int $storeId
     * @return string
     */
    protected function getCountCacheKey(int $storeId): string
    {
        return sprintf(
            'defox_seosuite_sitemap_%s_count_%d',
            $this->getCode(),
            $storeId
        );
    }

    /**
     * Get cache tags
     *
     * @return array
     */
    protected function getCacheTags(): array
    {
        return [
            'defox_seosuite_sitemap',
            'defox_seosuite_sitemap_' . $this->getCode()
        ];
    }

    /**
     * Load items from cache
     *
     * @param string $cacheKey
     * @return array|null
     */
    protected function loadFromCache(string $cacheKey): ?array
    {
        try {
            $data = $this->cacheManager->load($cacheKey);
            if ($data) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Convert arrays back to SitemapItem objects if needed
                    return $this->unserializeDataFromCache($decoded);
                }
                $this->logger->warning('Invalid JSON data in cache for key: ' . $cacheKey);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error loading sitemap data from cache: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Save items to cache
     *
     * @param string $cacheKey
     * @param array $data
     * @return void
     */
    protected function saveToCache(string $cacheKey, array $data): void
    {
        try {
            // Convert SitemapItem objects to arrays for JSON serialization
            $serializedData = $this->serializeDataForCache($data);
            
            $jsonData = json_encode($serializedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonData === false) {
                $this->logger->error('Failed to encode data to JSON for cache key: ' . $cacheKey);
                return;
            }
            
            $this->cacheManager->save(
                $jsonData,
                $cacheKey,
                $this->getCacheTags(),
                self::CACHE_LIFETIME
            );
        } catch (\Exception $e) {
            $this->logger->error('Error saving sitemap data to cache: ' . $e->getMessage());
        }
    }

    /**
     * Get alternate URLs for multi-language support
     *
     * @param string $entityId
     * @param string $entityType
     * @return array
     */
    protected function getAlternateUrls(string $entityId, string $entityType): array
    {
        $alternates = [];
        
        try {
            $stores = $this->storeManager->getStores();
            $storeCount = count($stores);
            
            // Only generate alternates if there are multiple stores
            if ($storeCount <= 1) {
                return $alternates;
            }
            
            foreach ($stores as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }
                
                $langCode = $this->config->getValue(
                    'sitemap/hreflang/store_' . $store->getId(),
                    $store->getId()
                );
                
                if (!$langCode) {
                    // Try to auto-detect from locale
                    $locale = $this->config->getValue('general/locale/code', $store->getId());
                    $langCode = substr($locale, 0, 2);
                }
                
                if ($langCode) {
                    $url = $this->getEntityUrl($entityId, $entityType, (int)$store->getId());
                    if ($url) {
                        $alternates[] = [
                            'lang' => $langCode,
                            'url' => $url
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error generating alternate URLs: ' . $e->getMessage());
        }
        
        return $alternates;
    }

    /**
     * Get entity URL for specific store
     *
     * @param string $entityId
     * @param string $entityType
     * @param int $storeId
     * @return string|null
     */
    abstract protected function getEntityUrl(string $entityId, string $entityType, int $storeId): ?string;

    /**
     * Check if entity is excluded from sitemap
     *
     * @param string $entityId
     * @param string $entityType
     * @param int $storeId
     * @return bool
     */
    protected function isExcluded(string $entityId, string $entityType, int $storeId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('defox_seosuite_sitemap_exclusion');
        
        $select = $connection->select()
            ->from($table, 'entity_id')
            ->where('entity_type = ?', $entityType)
            ->where('entity_id = ?', $entityId)
            ->where('store_id IN (?)', [0, $storeId])
            ->limit(1);
        
        return (bool)$connection->fetchOne($select);
    }

    /**
     * Serialize data for cache storage (convert objects to arrays)
     *
     * @param array $data
     * @return array
     */
    private function serializeDataForCache(array $data): array
    {
        $serialized = [];
        
        foreach ($data as $item) {
            if ($item instanceof SitemapItemInterface) {
                $serialized[] = [
                    'url' => $item->getUrl(),
                    'lastmod' => $item->getLastmod(),
                    'changefreq' => $item->getChangefreq(),
                    'priority' => $item->getPriority(),
                    'alternates' => $item->getAlternates(),
                    'images' => $item->getImages(),
                    'included' => $item->isIncluded()
                ];
            } else {
                // For primitive data (like count)
                $serialized[] = $item;
            }
        }
        
        return $serialized;
    }

    /**
     * Unserialize data from cache (convert arrays back to objects if needed)
     *
     * @param array $data
     * @return array
     */
    private function unserializeDataFromCache(array $data): array
    {
        $unserialized = [];
        
        foreach ($data as $item) {
            if (is_array($item) && isset($item['url'])) {
                // This is a serialized SitemapItem
                $sitemapItem = new \Defox\SEOSuite\Model\Sitemap\SitemapItem(
                    $item['url'],
                    $item['lastmod'] ?? null,
                    $item['changefreq'] ?? null,
                    $item['priority'] ?? null,
                    $item['alternates'] ?? [],
                    $item['images'] ?? [],
                    $item['included'] ?? true
                );
                $unserialized[] = $sitemapItem;
            } else {
                // Primitive data (like count)
                $unserialized[] = $item;
            }
        }
        
        return $unserialized;
    }
}

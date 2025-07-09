<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Provider;

use Defox\SEOSuite\Model\Sitemap\SitemapItem;
use Defox\SEOSuite\Model\Sitemap\SitemapItemInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;

/**
 * Category sitemap data provider
 * 
 * Provides category URLs for sitemap generation with support for:
 * - Category hierarchy
 * - Active categories only
 * - Multi-language support (hreflang)
 * - Category images
 */
class CategoryProvider extends AbstractProvider
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $categoryCollectionFactory;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        Config $config,
        Logger $logger,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        CollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct($config, $logger, $cacheManager, $storeManager, $resourceConnection);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'category';
    }

    /**
     * @inheritDoc
     */
    public function supportsImages(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getItems(int $storeId, int $limit = 0, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey($storeId, $limit, $offset);
        $cachedData = $this->loadFromCache($cacheKey);
        
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        try {
            $items = [];
            $collection = $this->getCategoryCollection($storeId, $limit, $offset);
            
            foreach ($collection as $category) {
                if ($this->isExcluded((string)$category->getId(), 'category', $storeId)) {
                    continue;
                }
                
                $item = $this->createSitemapItem($category, $storeId);
                if ($item && $item->isIncluded()) {
                    $items[] = $item;
                }
            }
            
            $this->saveToCache($cacheKey, $items);
            
            return $items;
        } catch (\Exception $e) {
            $this->logger->error('Error generating category sitemap items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getItemsCount(int $storeId): int
    {
        $cacheKey = $this->getCountCacheKey($storeId);
        $cachedCount = $this->loadFromCache($cacheKey);
        
        if ($cachedCount !== null) {
            return (int)$cachedCount;
        }
        
        try {
            $collection = $this->getCategoryCollection($storeId);
            $count = $collection->getSize();
            
            // Subtract excluded items
            $connection = $this->resourceConnection->getConnection();
            $excludedCount = $connection->fetchOne(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('defox_seosuite_sitemap_exclusion'),
                        'COUNT(*)'
                    )
                    ->where('entity_type = ?', 'category')
                    ->where('store_id IN (?)', [0, $storeId])
            );
            
            $totalCount = max(0, $count - (int)$excludedCount);
            $this->saveToCache($cacheKey, [$totalCount]);
            
            return $totalCount;
        } catch (\Exception $e) {
            $this->logger->error('Error getting category count for sitemap: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get category collection
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private function getCategoryCollection(int $storeId, int $limit = 0, int $offset = 0)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addIsActiveFilter();
        
        // Add URL rewrite data
        $collection->addUrlRewriteToResult();
        
        // Add required attributes
        $collection->addAttributeToSelect(['name', 'image', 'updated_at', 'include_in_menu']);
        
        // Exclude root category
        $store = $this->storeManager->getStore($storeId);
        $rootCategoryId = $store->getRootCategoryId();
        $collection->addFieldToFilter('entity_id', ['neq' => $rootCategoryId]);
        
        // Exclude categories based on level if configured
        $minLevel = (int)$this->config->getValue('defox_seosuite/sitemap/category/min_level', $storeId) ?: 2;
        $collection->addFieldToFilter('level', ['gteq' => $minLevel]);
        
        // Optionally exclude categories not in menu
        if ($this->config->getValue('defox_seosuite/sitemap/category/only_in_menu', $storeId)) {
            $collection->addAttributeToFilter('include_in_menu', 1);
        }
        
        // Apply pagination
        if ($limit > 0) {
            $collection->setPageSize($limit);
            if ($offset > 0) {
                $collection->setCurPage((int)floor($offset / $limit) + 1);
            }
        }
        
        return $collection;
    }

    /**
     * Create sitemap item from category
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param int $storeId
     * @return SitemapItemInterface|null
     */
    private function createSitemapItem($category, int $storeId): ?SitemapItemInterface
    {
        try {
            $url = $this->getEntityUrl((string)$category->getId(), 'category', $storeId);
            if (!$url) {
                return null;
            }
            
            $item = new SitemapItem($url);
            
            // Set last modification date
            $updatedAt = $category->getUpdatedAt();
            if ($updatedAt) {
                $item->setLastmod(date('Y-m-d', strtotime($updatedAt)));
            }
            
            // Set change frequency and priority based on category level
            $item->setChangefreq($this->getCategoryChangefreq($category));
            $item->setPriority($this->getCategoryPriority($category));
            
            // Add alternate URLs for multi-language
            if ($this->supportsHreflang()) {
                $alternates = $this->getAlternateUrls((string)$category->getId(), 'category');
                $item->setAlternates($alternates);
            }
            
            // Add category image if available
            if ($this->config->getValue('defox_seosuite/sitemap/category/include_images', $storeId)) {
                $images = $this->getCategoryImages($category, $storeId);
                $item->setImages($images);
            }
            
            return $item;
        } catch (\Exception $e) {
            $this->logger->error('Error creating sitemap item for category ' . $category->getId() . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function getEntityUrl(string $entityId, string $entityType, int $storeId): ?string
    {
        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addFieldToFilter('entity_id', $entityId);
            $collection->addUrlRewriteToResult();
            $collection->setPageSize(1);
            
            $category = $collection->getFirstItem();
            if ($category->getId()) {
                return $category->getUrl();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting category URL: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get change frequency for category
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return string
     */
    private function getCategoryChangefreq($category): string
    {
        // Higher level categories change less frequently
        $level = (int)$category->getLevel();
        
        if ($level <= 2) {
            return 'monthly';
        } elseif ($level <= 3) {
            return 'weekly';
        }
        
        return $this->getDefaultChangefreq();
    }

    /**
     * Get priority for category
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return float
     */
    private function getCategoryPriority($category): float
    {
        // Higher level categories have higher priority
        $level = (int)$category->getLevel();
        
        if ($level <= 2) {
            return 0.8;
        } elseif ($level <= 3) {
            return 0.6;
        }
        
        return $this->getDefaultPriority();
    }

    /**
     * Get category images
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param int $storeId
     * @return array
     */
    private function getCategoryImages($category, int $storeId): array
    {
        $images = [];
        
        try {
            if ($category->getImage()) {
                $store = $this->storeManager->getStore($storeId);
                $mediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                
                $images[] = [
                    'url' => $mediaUrl . 'catalog/category/' . $category->getImage(),
                    'title' => $category->getName(),
                    'caption' => null
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting category images: ' . $e->getMessage());
        }
        
        return $images;
    }
}

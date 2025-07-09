<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Provider;

use Defox\SEOSuite\Model\Sitemap\SitemapItem;
use Defox\SEOSuite\Model\Sitemap\SitemapItemInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;

/**
 * Product sitemap data provider
 * 
 * Provides product URLs for sitemap generation with support for:
 * - Multiple product types
 * - Stock status filtering
 * - Visibility filtering
 * - Multi-language support (hreflang)
 * - Product images
 */
class ProductProvider extends AbstractProvider
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $productCollectionFactory
     * @param StockHelper $stockHelper
     */
    public function __construct(
        Config $config,
        Logger $logger,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        CollectionFactory $productCollectionFactory,
        StockHelper $stockHelper
    ) {
        parent::__construct($config, $logger, $cacheManager, $storeManager, $resourceConnection);
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper = $stockHelper;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'product';
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
        $this->logger->info(sprintf(
            'ProductProvider: Starting getItems for store %d, limit=%d, offset=%d',
            $storeId,
            $limit,
            $offset
        ));
        
        $cacheKey = $this->getCacheKey($storeId, $limit, $offset);
        $cachedData = $this->loadFromCache($cacheKey);
        
        if ($cachedData !== null) {
            $this->logger->info(sprintf(
                'ProductProvider: Returning %d items from cache for store %d',
                count($cachedData),
                $storeId
            ));
            return $cachedData;
        }
        
        try {
            $items = [];
            $collection = $this->getProductCollection($storeId, $limit, $offset);
            
            $this->logger->info(sprintf(
                'ProductProvider: Product collection size: %d for store %d',
                $collection->getSize(),
                $storeId
            ));
            
            $processedCount = 0;
            $excludedCount = 0;
            
            foreach ($collection as $product) {
                $processedCount++;
                
                if ($this->isExcluded((string)$product->getId(), 'product', $storeId)) {
                    $excludedCount++;
                    continue;
                }
                
                $item = $this->createSitemapItem($product, $storeId);
                if ($item && $item->isIncluded()) {
                    $items[] = $item;
                    
                    if (count($items) <= 3) { // Log first few items
                    }
                }
            }
            
            $this->logger->info(sprintf(
                'ProductProvider: Processed %d products, excluded %d, final items: %d for store %d',
                $processedCount,
                $excludedCount,
                count($items),
                $storeId
            ));
            
            $this->saveToCache($cacheKey, $items);
            
            return $items;
        } catch (\Exception $e) {
            $this->logger->error('Error generating product sitemap items: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
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
            $collection = $this->getProductCollection($storeId);
            $count = $collection->getSize();
            
            // Subtract excluded items
            $connection = $this->resourceConnection->getConnection();
            $excludedCount = $connection->fetchOne(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('defox_seosuite_sitemap_exclusion'),
                        'COUNT(*)'
                    )
                    ->where('entity_type = ?', 'product')
                    ->where('store_id IN (?)', [0, $storeId])
            );
            
            $totalCount = max(0, $count - (int)$excludedCount);
            $this->saveToCache($cacheKey, [$totalCount]);
            
            return $totalCount;
        } catch (\Exception $e) {
            $this->logger->error('Error getting product count for sitemap: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get product collection
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductCollection(int $storeId, int $limit = 0, int $offset = 0)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        
        // Add basic filters
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_BOTH
            ]
        ]);
        
        // Add URL rewrite data
        $collection->addUrlRewrite();
        
        // Add required attributes
        $collection->addAttributeToSelect(['name', 'image', 'small_image', 'updated_at']);
        
        // Filter out of stock products if configured
        if ($this->config->getValue('defox_seosuite/sitemap/product/exclude_out_of_stock', $storeId)) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }
        
        // Filter product types if configured
        $excludedTypes = $this->config->getValue('defox_seosuite/sitemap/product/excluded_types', $storeId);
        if ($excludedTypes) {
            $excludedTypes = explode(',', $excludedTypes);
            $collection->addAttributeToFilter('type_id', ['nin' => $excludedTypes]);
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
     * Create sitemap item from product
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return SitemapItemInterface|null
     */
    private function createSitemapItem(ProductInterface $product, int $storeId): ?SitemapItemInterface
    {
        try {
            $url = $this->getEntityUrl((string)$product->getId(), 'product', $storeId);
            if (!$url) {
                return null;
            }
            
            $item = new SitemapItem($url);
            
            // Set last modification date
            $updatedAt = $product->getUpdatedAt();
            if ($updatedAt) {
                $item->setLastmod(date('Y-m-d', strtotime($updatedAt)));
            }
            
            // Set change frequency and priority
            $item->setChangefreq($this->getProductChangefreq($product));
            $item->setPriority($this->getProductPriority($product));
            
            // Add alternate URLs for multi-language
            if ($this->supportsHreflang()) {
                $alternates = $this->getAlternateUrls((string)$product->getId(), 'product');
                $item->setAlternates($alternates);
            }
            
            // Add product images
            if ($this->config->getValue('defox_seosuite/sitemap/product/include_images', $storeId)) {
                $images = $this->getProductImages($product, $storeId);
                $item->setImages($images);
            }
            
            return $item;
        } catch (\Exception $e) {
            $this->logger->error('Error creating sitemap item for product ' . $product->getId() . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function getEntityUrl(string $entityId, string $entityType, int $storeId): ?string
    {
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addFieldToFilter('entity_id', $entityId);
            $collection->addUrlRewrite();
            $collection->setPageSize(1);
            
            $product = $collection->getFirstItem();
            if ($product->getId()) {
                return $product->getProductUrl(false);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting product URL: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get change frequency for product
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductChangefreq(ProductInterface $product): string
    {
        // Could be enhanced to use product-specific logic
        // For example, new products might have 'daily', older ones 'weekly'
        return $this->getDefaultChangefreq();
    }

    /**
     * Get priority for product
     *
     * @param ProductInterface $product
     * @return float
     */
    private function getProductPriority(ProductInterface $product): float
    {
        // Could be enhanced with custom logic
        // For example, featured products might have higher priority
        return $this->getDefaultPriority();
    }

    /**
     * Get product images
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array
     */
    private function getProductImages(ProductInterface $product, int $storeId): array
    {
        $images = [];
        
        try {
            $store = $this->storeManager->getStore($storeId);
            $mediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            
            // Main image
            if ($product->getImage() && $product->getImage() !== 'no_selection') {
                $images[] = [
                    'url' => $mediaUrl . 'catalog/product' . $product->getImage(),
                    'title' => $product->getName(),
                    'caption' => null
                ];
            }
            
            // Small image (if different from main)
            if ($product->getSmallImage() && 
                $product->getSmallImage() !== 'no_selection' && 
                $product->getSmallImage() !== $product->getImage()) {
                $images[] = [
                    'url' => $mediaUrl . 'catalog/product' . $product->getSmallImage(),
                    'title' => $product->getName(),
                    'caption' => null
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting product images: ' . $e->getMessage());
        }
        
        return $images;
    }
}

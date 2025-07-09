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
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;

/**
 * CMS page sitemap data provider
 * 
 * Provides CMS page URLs for sitemap generation with support for:
 * - Active pages only
 * - Multi-language support (hreflang)
 * - Page exclusion rules
 */
class CmsPageProvider extends AbstractProvider
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $pageCollectionFactory;

    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $pageCollectionFactory
     * @param PageRepositoryInterface $pageRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Config $config,
        Logger $logger,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        CollectionFactory $pageCollectionFactory,
        PageRepositoryInterface $pageRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($config, $logger, $cacheManager, $storeManager, $resourceConnection);
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->pageRepository = $pageRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'cms_page';
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
            $collection = $this->getPageCollection($storeId, $limit, $offset);
            
            foreach ($collection as $page) {
                if ($this->isExcluded((string)$page->getId(), 'cms_page', $storeId)) {
                    continue;
                }
                
                // Skip excluded pages by identifier
                if ($this->isPageExcludedByIdentifier($page->getIdentifier(), $storeId)) {
                    continue;
                }
                
                $item = $this->createSitemapItem($page, $storeId);
                if ($item && $item->isIncluded()) {
                    $items[] = $item;
                }
            }
            
            $this->saveToCache($cacheKey, $items);
            
            return $items;
        } catch (\Exception $e) {
            $this->logger->error('Error generating CMS page sitemap items: ' . $e->getMessage());
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
            $collection = $this->getPageCollection($storeId);
            $count = $collection->getSize();
            
            // Subtract excluded items
            $connection = $this->resourceConnection->getConnection();
            $excludedCount = $connection->fetchOne(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('defox_seosuite_sitemap_exclusion'),
                        'COUNT(*)'
                    )
                    ->where('entity_type = ?', 'cms_page')
                    ->where('store_id IN (?)', [0, $storeId])
            );
            
            // Also subtract pages excluded by identifier
            $excludedIdentifiers = $this->getExcludedIdentifiers($storeId);
            if (!empty($excludedIdentifiers)) {
                $excludedByIdentifier = $connection->fetchOne(
                    $connection->select()
                        ->from(
                            $this->resourceConnection->getTableName('cms_page'),
                            'COUNT(*)'
                        )
                        ->where('identifier IN (?)', $excludedIdentifiers)
                        ->where('is_active = ?', 1)
                );
                $excludedCount += (int)$excludedByIdentifier;
            }
            
            $totalCount = max(0, $count - (int)$excludedCount);
            $this->saveToCache($cacheKey, [$totalCount]);
            
            return $totalCount;
        } catch (\Exception $e) {
            $this->logger->error('Error getting CMS page count for sitemap: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get CMS page collection
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return \Magento\Cms\Model\ResourceModel\Page\Collection
     */
    private function getPageCollection(int $storeId, int $limit = 0, int $offset = 0)
    {
        $collection = $this->pageCollectionFactory->create();
        
        // Filter by store
        $collection->addStoreFilter($storeId);
        
        // Only active pages
        $collection->addFieldToFilter('is_active', 1);
        
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
     * Create sitemap item from CMS page
     *
     * @param \Magento\Cms\Model\Page $page
     * @param int $storeId
     * @return SitemapItemInterface|null
     */
    private function createSitemapItem($page, int $storeId): ?SitemapItemInterface
    {
        try {
            $url = $this->getEntityUrl((string)$page->getId(), 'cms_page', $storeId);
            if (!$url) {
                return null;
            }
            
            $item = new SitemapItem($url);
            
            // Set last modification date
            $updatedAt = $page->getUpdateTime();
            if ($updatedAt) {
                $item->setLastmod(date('Y-m-d', strtotime($updatedAt)));
            }
            
            // Set change frequency and priority
            $item->setChangefreq($this->getPageChangefreq($page));
            $item->setPriority($this->getPagePriority($page));
            
            // Add alternate URLs for multi-language
            if ($this->supportsHreflang()) {
                $alternates = $this->getAlternateUrls($page->getIdentifier(), 'cms_page');
                $item->setAlternates($alternates);
            }
            
            return $item;
        } catch (\Exception $e) {
            $this->logger->error('Error creating sitemap item for CMS page ' . $page->getId() . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function getEntityUrl(string $entityId, string $entityType, int $storeId): ?string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $baseUrl = $store->getBaseUrl();
            
            // For CMS pages, we need to get the page by ID and then use its identifier
            if ($entityType === 'cms_page' && is_numeric($entityId)) {
                $page = $this->pageRepository->getById((int)$entityId);
                $identifier = $page->getIdentifier();
            } else {
                // If entityId is already an identifier (for alternates)
                $identifier = $entityId;
            }
            
            // Special case for home page
            if ($identifier === 'home') {
                return $baseUrl;
            }
            
            return $baseUrl . $identifier;
        } catch (\Exception $e) {
            $this->logger->error('Error getting CMS page URL: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Check if page is excluded by identifier
     *
     * @param string $identifier
     * @param int $storeId
     * @return bool
     */
    private function isPageExcludedByIdentifier(string $identifier, int $storeId): bool
    {
        $excludedIdentifiers = $this->getExcludedIdentifiers($storeId);
        return in_array($identifier, $excludedIdentifiers);
    }

    /**
     * Get excluded page identifiers
     *
     * @param int $storeId
     * @return array
     */
    private function getExcludedIdentifiers(int $storeId): array
    {
        $excluded = $this->config->getValue('defox_seosuite/sitemap/cms_page/excluded_pages', $storeId);
        if (!$excluded) {
            // Default excluded pages
            return ['no-route', 'enable-cookies', 'privacy-policy-cookie-restriction-mode'];
        }
        
        return array_map('trim', explode(',', $excluded));
    }

    /**
     * Get change frequency for CMS page
     *
     * @param \Magento\Cms\Model\Page $page
     * @return string
     */
    private function getPageChangefreq($page): string
    {
        // Home page changes more frequently
        if ($page->getIdentifier() === 'home') {
            return 'daily';
        }
        
        // Policy pages change less frequently
        $policyPages = ['privacy-policy', 'terms-conditions', 'shipping-policy', 'return-policy'];
        if (in_array($page->getIdentifier(), $policyPages)) {
            return 'yearly';
        }
        
        return $this->getDefaultChangefreq();
    }

    /**
     * Get priority for CMS page
     *
     * @param \Magento\Cms\Model\Page $page
     * @return float
     */
    private function getPagePriority($page): float
    {
        // Home page has highest priority
        if ($page->getIdentifier() === 'home') {
            return 1.0;
        }
        
        // Important pages have higher priority
        $importantPages = ['about-us', 'contact', 'customer-service'];
        if (in_array($page->getIdentifier(), $importantPages)) {
            return 0.8;
        }
        
        return $this->getDefaultPriority();
    }
}

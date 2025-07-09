<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Canonical;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\Type\Canonical as CanonicalCache;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category as CatalogCategory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class to handle canonical URLs for category pages
 */
class Category
{
    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Exclusion
     */
    private Exclusion $exclusion;
    
    /**
     * @var CanonicalCache
     */
    private CanonicalCache $canonicalCache;

    /**
     * @param Http $request
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     * @param Exclusion $exclusion
     * @param CanonicalCache $canonicalCache
     */
    public function __construct(
        Http $request,
        Config $config,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger,
        Exclusion $exclusion,
        CanonicalCache $canonicalCache
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
        $this->exclusion = $exclusion;
        $this->canonicalCache = $canonicalCache;
    }

    /**
     * Get canonical URL for category
     *
     * @param CatalogCategory|null $category
     * @return string|null
     */
    public function getCanonicalUrl(?CatalogCategory $category = null): ?string
    {
        // Return null if canonical URLs are disabled for categories
        if (!$this->config->isCategoryCanonicalEnabled()) {
            return null;
        }

        try {
            if (!$category) {
                $categoryId = (int)$this->request->getParam('id');
                if (!$categoryId) {
                    return null;
                }
                
                // Try to get from cache first
                $cacheKey = 'category_canonical_' . $categoryId . '_' . $this->storeManager->getStore()->getId();
                $cachedUrl = $this->canonicalCache->load($cacheKey);
                
                if ($cachedUrl !== false) {
                    return $cachedUrl;
                }
                
                $category = $this->categoryRepository->get(
                    $categoryId,
                    $this->storeManager->getStore()->getId()
                );
            }

            // Check if category should be excluded from canonical
            if (!$category || !$category->getId() || $this->exclusion->isCategoryExcluded($category)) {
                return null;
            }

            // Get canonical URL 
            $canonicalUrl = $this->getCategoryCanonicalUrl($category);
            
            // Save to cache
            if ($canonicalUrl) {
                $cacheKey = 'category_canonical_' . $category->getId() . '_' . $this->storeManager->getStore()->getId();
                $this->canonicalCache->save($canonicalUrl, $cacheKey, ['category_canonical']);
            }
            
            return $canonicalUrl;
        } catch (NoSuchEntityException $e) {
            $this->logger->error(
                'Error generating canonical URL for category: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
    }

    /**
     * Get canonical URL for category
     *
     * @param CatalogCategory $category
     * @return string|null
     */
    private function getCategoryCanonicalUrl(CatalogCategory $category): ?string
    {
        // Handle pagination if needed
        $currentPage = (int)$this->request->getParam('p');
        
        if ($currentPage > 1 && $this->config->shouldUseCanonicalForCategoryPagination()) {
            return $this->getCategoryPaginationCanonicalUrl($category, $currentPage);
        }
        
        // Handle filters if present
        if ($this->hasActiveFilters() && !$this->config->shouldUseCanonicalForCategoryFilters()) {
            // For filtered pages, canonical should point to unfiltered category page
            return $category->getUrl();
        }
        
        // Default case - clean category URL
        return $category->getUrl();
    }

    /**
     * Get canonical URL for category pagination
     *
     * @param CatalogCategory $category
     * @param int $currentPage
     * @return string
     */
    private function getCategoryPaginationCanonicalUrl(CatalogCategory $category, int $currentPage): string
    {
        $paginationCanonicalType = $this->config->getCategoryPaginationCanonicalType();
        
        switch ($paginationCanonicalType) {
            case 'first_page':
                // Point to the first page
                return $category->getUrl();
            case 'self':
                // Point to current page
                return $category->getUrl() . '?p=' . $currentPage;
            default:
                // Default to first page
                return $category->getUrl();
        }
    }

    /**
     * Check if there are active filters
     *
     * @return bool
     */
    private function hasActiveFilters(): bool
    {
        $params = $this->request->getParams();
        
        // Exclude standard parameters
        $standardParams = ['id', 'p', 'q', '___store', '___from_store'];
        
        foreach ($params as $key => $value) {
            if (!in_array($key, $standardParams) && strpos($key, 'SID') === false) {
                return true;
            }
        }
        
        return false;
    }
}

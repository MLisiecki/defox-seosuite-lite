<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Canonical;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\Type\Canonical as CanonicalCache;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class to handle canonical URLs for product pages
 */
class Product
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
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    
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
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param Exclusion $exclusion
     * @param CanonicalCache $canonicalCache
     */
    public function __construct(
        Http $request,
        Config $config,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        Exclusion $exclusion,
        CanonicalCache $canonicalCache
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->exclusion = $exclusion;
        $this->canonicalCache = $canonicalCache;
    }

    /**
     * Get canonical URL for product
     *
     * @param CatalogProduct|null $product
     * @return string|null
     */
    public function getCanonicalUrl(?CatalogProduct $product = null): ?string
    {
        // Return null if canonical URLs are disabled for products
        if (!$this->config->isProductCanonicalEnabled()) {
            return null;
        }

        try {
            if (!$product) {
                $productId = (int)$this->request->getParam('id');
                if (!$productId) {
                    return null;
                }
                
                // Try to get from cache first
                $cacheKey = 'product_canonical_' . $productId . '_' . $this->storeManager->getStore()->getId();
                $cachedUrl = $this->canonicalCache->load($cacheKey);
                
                if ($cachedUrl !== false) {
                    return $cachedUrl;
                }
                
                $product = $this->productRepository->getById(
                    $productId,
                    false,
                    $this->storeManager->getStore()->getId()
                );
            }

            // Check if product should be excluded from canonical
            if (!$product || !$product->getId() || $this->exclusion->isProductExcluded($product)) {
                return null;
            }

            // Get canonical URL according to configuration
            $canonicalUrl = $this->getProductCanonicalUrl($product);
            
            // Save to cache
            if ($canonicalUrl) {
                $cacheKey = 'product_canonical_' . $product->getId() . '_' . $this->storeManager->getStore()->getId();
                $this->canonicalCache->save($canonicalUrl, $cacheKey, ['product_canonical']);
            }
            
            return $canonicalUrl;
        } catch (NoSuchEntityException $e) {
            $this->logger->error(
                'Error generating canonical URL for product: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
    }

    /**
     * Get canonical URL for product according to configuration
     *
     * @param CatalogProduct $product
     * @return string|null
     */
    private function getProductCanonicalUrl(CatalogProduct $product): ?string
    {
        $canonicalUrlType = $this->config->getProductCanonicalUrlType();
        
        switch ($canonicalUrlType) {
            case 'product_url':
                return $this->getProductUrlWithoutParams($product);
            case 'with_category':
                return $this->getProductUrlWithPrimaryCategory($product);
            case 'shortest_category':
                return $this->getProductUrlWithShortestCategory($product);
            case 'longest_category':
                return $this->getProductUrlWithLongestCategory($product);
            default:
                return $this->getProductUrlWithoutParams($product);
        }
    }

    /**
     * Get product URL without any parameters
     *
     * @param CatalogProduct $product
     * @return string
     */
    private function getProductUrlWithoutParams(CatalogProduct $product): string
    {
        return $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
    }

    /**
     * Get product URL with primary category
     *
     * @param CatalogProduct $product
     * @return string
     */
    private function getProductUrlWithPrimaryCategory(CatalogProduct $product): string
    {
        $categoryId = $product->getCategoryId();
        
        if (!$categoryId && !empty($product->getCategoryIds())) {
            $categoryId = $product->getCategoryIds()[0];
        }
        
        if ($categoryId) {
            return $product->getUrlModel()->getUrl($product, ['_ignore_category' => false, 'category' => $categoryId]);
        }
        
        return $this->getProductUrlWithoutParams($product);
    }

    /**
     * Get product URL with shortest category path
     *
     * @param CatalogProduct $product
     * @return string
     */
    private function getProductUrlWithShortestCategory(CatalogProduct $product): string
    {
        $categoryIds = $product->getCategoryIds();
        
        if (empty($categoryIds)) {
            return $this->getProductUrlWithoutParams($product);
        }
        
        $shortestCategoryId = null;
        $shortestLength = PHP_INT_MAX;
        
        foreach ($categoryIds as $categoryId) {
            $category = $this->getCategoryById($categoryId);
            
            if ($category && $category->getLevel()) {
                $pathLength = $category->getLevel();
                
                if ($pathLength < $shortestLength) {
                    $shortestLength = $pathLength;
                    $shortestCategoryId = $categoryId;
                }
            }
        }
        
        if ($shortestCategoryId) {
            return $product->getUrlModel()->getUrl(
                $product,
                ['_ignore_category' => false, 'category' => $shortestCategoryId]
            );
        }
        
        return $this->getProductUrlWithoutParams($product);
    }

    /**
     * Get product URL with longest category path
     *
     * @param CatalogProduct $product
     * @return string
     */
    private function getProductUrlWithLongestCategory(CatalogProduct $product): string
    {
        $categoryIds = $product->getCategoryIds();
        
        if (empty($categoryIds)) {
            return $this->getProductUrlWithoutParams($product);
        }
        
        $longestCategoryId = null;
        $longestLength = 0;
        
        foreach ($categoryIds as $categoryId) {
            $category = $this->getCategoryById($categoryId);
            
            if ($category && $category->getLevel()) {
                $pathLength = $category->getLevel();
                
                if ($pathLength > $longestLength) {
                    $longestLength = $pathLength;
                    $longestCategoryId = $categoryId;
                }
            }
        }
        
        if ($longestCategoryId) {
            return $product->getUrlModel()->getUrl(
                $product,
                ['_ignore_category' => false, 'category' => $longestCategoryId]
            );
        }
        
        return $this->getProductUrlWithoutParams($product);
    }

    /**
     * Get category by ID
     *
     * @param int $categoryId
     * @return \Magento\Catalog\Model\Category|null
     */
    private function getCategoryById(int $categoryId): ?\Magento\Catalog\Model\Category
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $category = $objectManager->create(\Magento\Catalog\Model\Category::class);
            $category->load($categoryId);
            
            return $category->getId() ? $category : null;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error loading category: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
    }
}

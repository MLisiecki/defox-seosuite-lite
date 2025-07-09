<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Observer;

use Defox\SEOSuite\Model\MetaTag\Manager;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * Enhanced observer for applying meta tag templates
 *
 * This observer listens to the 'layout_generate_blocks_after' event
 * and applies meta tag templates to the page with improved entity detection
 */
class ApplyMetaTagTemplates implements ObserverInterface
{
    /**
     * @var Manager
     */
    private Manager $metaTagManager;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PageConfig
     */
    private PageConfig $pageConfig;

    /**
     * @var array
     */
    private static array $appliedRequests = [];

    /**
     * @var array
     */
    private array $entityCache = [];

    /**
     * @param Manager $metaTagManager
     * @param Registry $registry
     * @param RequestInterface $request
     * @param PageRepositoryInterface $pageRepository
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     * @param PageConfig $pageConfig
     */
    public function __construct(
        Manager $metaTagManager,
        Registry $registry,
        RequestInterface $request,
        PageRepositoryInterface $pageRepository,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger,
        PageConfig $pageConfig
    ) {
        $this->metaTagManager = $metaTagManager;
        $this->registry = $registry;
        $this->request = $request;
        $this->pageRepository = $pageRepository;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
        $this->pageConfig = $pageConfig;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $requestKey = $this->request->getRequestUri();

        // Prevent multiple executions per request
        if (isset(self::$appliedRequests[$requestKey])) {
            return;
        }

        try {
            $entity = $this->getCurrentEntity();
            $entityType = $this->getEntityType($entity);

            if (!$entity || !$entityType) {
                return;
            }

            // Apply meta tag templates
            $metaTags = $this->metaTagManager->applyTemplates($entity, $entityType);

            if (empty($metaTags)) {
                return;
            }

            // Apply meta tags to page config
            $this->applyMetaTagsToPageConfig($metaTags);

            // Mark as applied
            self::$appliedRequests[$requestKey] = true;

        } catch (\Exception $e) {
        $this->logger->error('[Defox_SEOSuite] ApplyMetaTagTemplates: Error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    }

    /**
     * Apply meta tags to page config
     *
     * @param array $metaTags
     * @return void
     */
    private function applyMetaTagsToPageConfig(array $metaTags): void
    {
        // Apply meta title
        if (isset($metaTags['meta_title']) && !empty($metaTags['meta_title'])) {
            $this->pageConfig->getTitle()->set($metaTags['meta_title']);
        }

        // Apply meta description
        if (isset($metaTags['meta_description']) && !empty($metaTags['meta_description'])) {
            $this->pageConfig->setDescription($metaTags['meta_description']);
        }

        // Apply meta keywords
        if (isset($metaTags['meta_keywords']) && !empty($metaTags['meta_keywords'])) {
            $this->pageConfig->setKeywords($metaTags['meta_keywords']);
        }

        // Apply meta robots
        if (isset($metaTags['meta_robots']) && !empty($metaTags['meta_robots'])) {
            $this->pageConfig->setRobots($metaTags['meta_robots']);
        }

        // Apply Open Graph tags
        if (isset($metaTags['og_title']) && !empty($metaTags['og_title'])) {
            $this->pageConfig->setMetadata('og:title', $metaTags['og_title']);
        }

        if (isset($metaTags['og_description']) && !empty($metaTags['og_description'])) {
            $this->pageConfig->setMetadata('og:description', $metaTags['og_description']);
        }

        if (isset($metaTags['og_type']) && !empty($metaTags['og_type'])) {
            $this->pageConfig->setMetadata('og:type', $metaTags['og_type']);
        }

        if (isset($metaTags['og_image']) && !empty($metaTags['og_image'])) {
            $this->pageConfig->setMetadata('og:image', $metaTags['og_image']);
        }
    }

    /**
     * Get current entity with improved detection
     *
     * @return mixed|null
     */
    private function getCurrentEntity()
    {
        $fullActionName = $this->request->getFullActionName();
        $cacheKey = $fullActionName . '_' . serialize($this->request->getParams());

        // Check cache first
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $entity = null;

        // Try to get product
        $entity = $this->getProductEntity($fullActionName);
        if ($entity) {
            $this->entityCache[$cacheKey] = $entity;
            return $entity;
        }

        // Try to get category
        $entity = $this->getCategoryEntity($fullActionName);
        if ($entity) {
            $this->entityCache[$cacheKey] = $entity;
            return $entity;
        }

        // Try to get CMS page
        $entity = $this->getCmsPageEntity($fullActionName);
        if ($entity) {
            $this->entityCache[$cacheKey] = $entity;
            return $entity;
        }

        $this->entityCache[$cacheKey] = null;
        return null;
    }

    /**
     * Get product entity
     *
     * @param string $fullActionName
     * @return Product|null
     */
    private function getProductEntity(string $fullActionName): ?Product
    {
        // Try registry first
        $product = $this->registry->registry('current_product');
        if ($product instanceof Product && $product->getId()) {
            return $product;
        }

        // Check if this is a product-related action
        if ($fullActionName === 'catalog_product_view' ||
            strpos($fullActionName, 'product') !== false) {

            $productId = $this->request->getParam('id');
            if ($productId) {
                try {
                    $product = $this->productRepository->getById($productId);
                    return $product;
                } catch (\Exception $e) {
                    $this->logger->debug('[Defox_SEOSuite] ApplyMetaTagTemplates: Could not load product', [
                        'product_id' => $productId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * Get category entity
     *
     * @param string $fullActionName
     * @return Category|null
     */
    private function getCategoryEntity(string $fullActionName): ?Category
    {
        // Try registry first
        $category = $this->registry->registry('current_category');
        if ($category instanceof Category && $category->getId()) {
            return $category;
        }

        // Check if this is a category-related action
        if ($fullActionName === 'catalog_category_view' ||
            strpos($fullActionName, 'category') !== false) {

            $categoryId = $this->request->getParam('id');
            if ($categoryId) {
                try {
                    $category = $this->categoryRepository->get($categoryId);
                    return $category;
                } catch (\Exception $e) {
                    $this->logger->debug('[Defox_SEOSuite] ApplyMetaTagTemplates: Could not load category', [
                        'category_id' => $categoryId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * Get CMS page entity
     *
     * @param string $fullActionName
     * @return Page|null
     */
    private function getCmsPageEntity(string $fullActionName): ?Page
    {
        // Try registry first
        $page = $this->registry->registry('cms_page');
        if ($page instanceof Page && $page->getId()) {
            return $page;
        }

        // Check if this is a CMS page action
        if ($fullActionName === 'cms_page_view' || $fullActionName === 'cms_index_index') {
            $pageId = $this->request->getParam('page_id') ?: $this->request->getParam('id');

            if ($pageId) {
                try {
                    $page = $this->pageRepository->getById($pageId);
                    return $page;
                } catch (\Exception $e) {
                    $this->logger->debug('[Defox_SEOSuite] ApplyMetaTagTemplates: Could not load CMS page', [
                        'page_id' => $pageId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * Get entity type
     *
     * @param mixed $entity
     * @return string|null
     */
    private function getEntityType($entity): ?string
    {
        if ($entity instanceof Product) {
            return 'product';
        }

        if ($entity instanceof Category) {
            return 'category';
        }

        if ($entity instanceof Page) {
            return 'cms_page';
        }

        return null;
    }
}

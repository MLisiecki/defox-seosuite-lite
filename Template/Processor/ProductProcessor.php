<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Template\Processor;

use Defox\SEOSuite\Helper\Data as SeoHelper;
use Defox\SEOSuite\Template\AbstractVariableProcessor;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enhanced product variable processor
 *
 * Processes variables in templates related to product entities with improved performance
 */
class ProductProcessor extends AbstractVariableProcessor
{
    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @var CategoryRepositoryInterface
     */
    protected CategoryRepositoryInterface $categoryRepository;

    /**
     * @var array
     */
    private array $categoryCache = [];

    /**
     * @param SeoHelper $seoHelper
     * @param StoreManagerInterface $storeManager
     * @param Escaper $escaper
     * @param CacheInterface $cache
     * @param Registry $registry
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        SeoHelper $seoHelper,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        CacheInterface $cache,
        Registry $registry,
        CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct($seoHelper, $storeManager, $escaper, $cache);
        $this->registry = $registry;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritdoc
     */
    public function canProcess($entity): bool
    {
        return $entity instanceof ProductInterface || $entity instanceof Product;
    }

    /**
     * @inheritdoc
     */
    protected function isEntityPrefix(string $prefix): bool
    {
        return in_array($prefix, ['product', 'category']);
    }

    /**
     * @inheritdoc
     */
    protected function getEntitySpecificVariables(): array
    {
        return [
            'product.name' => 'Product Name',
            'product.sku' => 'Product SKU',
            'product.url_key' => 'Product URL Key',
            'product.description' => 'Product Description',
            'product.short_description' => 'Product Short Description',
            'product.price' => 'Product Price',
            'product.final_price' => 'Product Final Price (after discounts)',
            'product.special_price' => 'Product Special Price',
            'product.manufacturer' => 'Product Manufacturer',
            'product.brand' => 'Product Brand',
            'product.weight' => 'Product Weight',
            'product.status' => 'Product Status',
            'product.visibility' => 'Product Visibility',
            'product.type_id' => 'Product Type ID',
            'product.attribute_set_id' => 'Product Attribute Set ID',
            'product.meta_title' => 'Product Meta Title',
            'product.meta_description' => 'Product Meta Description',
            'product.meta_keyword' => 'Product Meta Keywords',
            'product.categories' => 'Product Categories (comma separated)',
            'product.main_category' => 'Product Main Category Name',
            'product.category_names' => 'All Category Names (comma separated)',
            'product.image' => 'Product Base Image URL',
            'product.small_image' => 'Product Small Image URL',
            'product.thumbnail' => 'Product Thumbnail URL',
            'product.gallery_images' => 'Product Gallery Images (comma separated URLs)',
            'product.url' => 'Product URL',
            'product.created_at' => 'Product Creation Date',
            'product.updated_at' => 'Product Update Date',
            'product.in_stock' => 'Product In Stock Status',
            'product.qty' => 'Product Quantity',
            'product.stock_status' => 'Product Stock Status',
            'category.name' => 'Current Category Name',
            'category.description' => 'Current Category Description',
            'category.url' => 'Current Category URL',
            'category.image' => 'Current Category Image URL',
            'category.meta_title' => 'Current Category Meta Title',
            'category.meta_description' => 'Current Category Meta Description',
            'category.path' => 'Current Category Path',
            'category.level' => 'Current Category Level',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getEntityValue($entity, array $propertyPath): string
    {
        if (empty($propertyPath)) {
            return '';
        }

        $prefix = $propertyPath[0];
        $property = $propertyPath[1] ?? null;

        // Handle category variables (get from product's current category)
        if ($prefix === 'category' && ($entity instanceof ProductInterface || $entity instanceof Product)) {
            $currentCategory = $this->getCurrentCategoryForProduct($entity);

            if ($currentCategory && $currentCategory->getId()) {
                return $this->getCategoryPropertyValue($currentCategory, $property);
            }
            return '';
        }

        // Handle product variables
        if ($prefix === 'product' && ($entity instanceof ProductInterface || $entity instanceof Product)) {
            return $this->getProductPropertyValue($entity, $property);
        }

        return '';
    }

    /**
     * Get product property value
     *
     * @param ProductInterface|Product $product
     * @param string|null $property
     * @return string
     */
    protected function getProductPropertyValue(ProductInterface|Product $product, ?string $property): string
    {
        if (!$property) {
            return '';
        }

        switch ($property) {
            case 'name':
                return (string)$product->getName();

            case 'sku':
                return (string)$product->getSku();

            case 'url_key':
                return (string)$product->getUrlKey();

            case 'description':
                $description = $product->getDescription();
                return $this->cleanHtmlContent((string)$description);

            case 'short_description':
                $shortDescription = $product->getShortDescription();
                return $this->cleanHtmlContent((string)$shortDescription);

            case 'price':
                return (string)$product->getPrice();

            case 'final_price':
                if (method_exists($product, 'getFinalPrice')) {
                    return (string)$product->getFinalPrice();
                }
                return (string)$product->getPrice();

            case 'special_price':
                return (string)$product->getSpecialPrice();

            case 'weight':
                return (string)$product->getWeight();

            case 'status':
                return (string)$product->getStatus();

            case 'visibility':
                return (string)$product->getVisibility();

            case 'type_id':
                return (string)$product->getTypeId();

            case 'attribute_set_id':
                return (string)$product->getAttributeSetId();

            case 'meta_title':
                return (string)$product->getMetaTitle();

            case 'meta_description':
                return (string)$product->getMetaDescription();

            case 'meta_keyword':
                return (string)$product->getMetaKeyword();

            case 'categories':
            case 'category_names':
                return $this->getProductCategoryNames($product);

            case 'main_category':
                $currentCategory = $this->getCurrentCategoryForProduct($product);
                return $currentCategory ? (string)$currentCategory->getName() : '';

            case 'image':
                return $this->getProductImageUrl($product, 'image');

            case 'small_image':
                return $this->getProductImageUrl($product, 'small_image');

            case 'thumbnail':
                return $this->getProductImageUrl($product, 'thumbnail');

            case 'gallery_images':
                return $this->getProductGalleryImages($product);

            case 'url':
                return $this->getProductUrl($product);

            case 'created_at':
                return (string)$product->getCreatedAt();

            case 'updated_at':
                return (string)$product->getUpdatedAt();

            case 'in_stock':
            case 'stock_status':
                return $this->getProductStockStatus($product);

            case 'qty':
                return $this->getProductQty($product);

            default:
                // Try custom attributes
                return $this->getProductCustomAttribute($product, $property);
        }
    }

    /**
     * Get category property value
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @param string|null $property
     * @return string
     */
    protected function getCategoryPropertyValue($category, ?string $property): string
    {
        if (!$property) {
            return '';
        }

        switch ($property) {
            case 'name':
                return (string)$category->getName();

            case 'description':
                $description = $category->getDescription();
                return $this->cleanHtmlContent((string)$description);

            case 'url':
                if (method_exists($category, 'getUrl')) {
                    return (string)$category->getUrl();
                }
                // Fallback: build URL from URL path
                $urlPath = $category->getUrlPath();
                if ($urlPath) {
                    return $this->storeManager->getStore()->getBaseUrl() . $urlPath;
                }
                return '';

            case 'image':
                return $this->getCategoryImageUrl($category);

            case 'meta_title':
                return (string)$category->getMetaTitle();

            case 'meta_description':
                return (string)$category->getMetaDescription();

            case 'path':
                return (string)$category->getPath();

            case 'level':
                return (string)$category->getLevel();

            default:
                // Try getData for other properties
                if (method_exists($category, 'getData')) {
                    $value = $category->getData($property);
                    return $value !== null ? (string)$value : '';
                }
                return '';
        }
    }

    /**
     * Get current category for product with optimized fallback
     *
     * @param ProductInterface|Product $product
     * @return \Magento\Catalog\Api\Data\CategoryInterface|null
     */
    protected function getCurrentCategoryForProduct(ProductInterface|Product $product): ?\Magento\Catalog\Api\Data\CategoryInterface
    {
        $productId = $product->getId();
        
        // Check cache first
        if (isset($this->categoryCache[$productId])) {
            return $this->categoryCache[$productId];
        }
        
        // Method 1: Try to get current category from registry (most reliable for frontend)
        $currentCategory = $this->registry->registry('current_category');
        if ($currentCategory && $currentCategory->getId()) {
            // Verify this category actually contains this product
            if ($this->isCategoryAssignedToProduct((int)$currentCategory->getId(), $product)) {
                $this->categoryCache[$productId] = $currentCategory;
                return $currentCategory;
            }
        }
        
        // Method 2: Get from product's category IDs (most direct)
        $categoryIds = [];
        if (method_exists($product, 'getCategoryIds')) {
            $categoryIds = $product->getCategoryIds();
        } elseif (method_exists($product, 'getData')) {
            $categoryIds = $product->getData('category_ids') ?: [];
        }
        
        if (!empty($categoryIds)) {
            // Find the deepest non-root category (highest level number)
            $bestCategory = null;
            $bestLevel = 0;

            foreach ($categoryIds as $categoryId) {
                if ($categoryId <= 2) { // Skip root categories
                    continue;
                }

                try {
                    $category = $this->categoryRepository->get($categoryId);
                    if ($category && $category->getId()) {
                        $level = (int)$category->getLevel();
                        if ($level > $bestLevel) {
                            $bestCategory = $category;
                            $bestLevel = $level;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next category
                    continue;
                }
            }            
            if ($bestCategory) {
                $this->categoryCache[$productId] = $bestCategory;
                return $bestCategory;
            }
        }
        
        // No category found
        $this->categoryCache[$productId] = null;
        return null;
    }

    /**
     * Check if category is assigned to product
     *
     * @param int|string $categoryId
     * @param ProductInterface|Product $product
     * @return bool
     */
    protected function isCategoryAssignedToProduct(int|string $categoryId, ProductInterface|Product $product): bool
    {
        $productCategoryIds = [];
        if (method_exists($product, 'getCategoryIds')) {
            $productCategoryIds = $product->getCategoryIds();
        } elseif (method_exists($product, 'getData')) {
            $productCategoryIds = $product->getData('category_ids') ?: [];
        }
        
        return in_array((int)$categoryId, array_map('intval', $productCategoryIds));
    }

    /**
     * Get product category names
     *
     * @param ProductInterface|Product $product
     * @return string
     */
    protected function getProductCategoryNames(ProductInterface|Product $product): string
    {
        $categoryIds = [];
        if (method_exists($product, 'getCategoryIds')) {
            $categoryIds = $product->getCategoryIds();
        } elseif (method_exists($product, 'getData')) {
            $categoryIds = $product->getData('category_ids') ?: [];
        }
        
        $categoryNames = [];
        foreach ($categoryIds as $categoryId) {
            if ($categoryId <= 2) { // Skip root categories
                continue;
            }
            
            try {
                $category = $this->categoryRepository->get($categoryId);
                if ($category && $category->getName()) {
                    $categoryNames[] = $category->getName();
                }
            } catch (\Exception $e) {
                // Continue to next category
                continue;
            }
        }
        
        return implode(', ', $categoryNames);
    }

    /**
     * Get product image URL
     *
     * @param ProductInterface|Product $product
     * @param string $imageType
     * @return string
     */
    protected function getProductImageUrl(ProductInterface|Product $product, string $imageType): string
    {
        $methodName = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $imageType)));
        
        if (method_exists($product, $methodName)) {
            $image = $product->$methodName();
            if ($image && $image !== 'no_selection') {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                return rtrim($baseUrl, '/') . '/catalog/product' . $image;
            }
        }
        
        return '';
    }

    /**
     * Get product gallery images
     *
     * @param ProductInterface|Product $product
     * @return string
     */
    protected function getProductGalleryImages(ProductInterface|Product $product): string
    {
        $images = [];
        
        if (method_exists($product, 'getMediaGalleryImages')) {
            $galleryImages = $product->getMediaGalleryImages();
            if ($galleryImages) {
                foreach ($galleryImages as $image) {
                    if (method_exists($image, 'getUrl')) {
                        $images[] = $image->getUrl();
                    }
                }
            }
        }
        
        return implode(', ', $images);
    }

    /**
     * Get product URL
     *
     * @param ProductInterface|Product $product
     * @return string
     */
    protected function getProductUrl(ProductInterface|Product $product): string
    {
        if (method_exists($product, 'getProductUrl')) {
            return (string)$product->getProductUrl();
        }
        
        if (method_exists($product, 'getUrlKey')) {
            $urlKey = $product->getUrlKey();
            if ($urlKey) {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                return rtrim($baseUrl, '/') . '/' . $urlKey . '.html';
            }
        }
        
        return '';
    }

    /**
     * Get product stock status
     *
     * @param ProductInterface|Product $product
     * @return string
     */
    protected function getProductStockStatus(ProductInterface|Product $product): string
    {
        if (method_exists($product, 'getQuantityAndStockStatus')) {
            $stockData = $product->getQuantityAndStockStatus();
            if (isset($stockData['is_in_stock'])) {
                return $stockData['is_in_stock'] ? 'In Stock' : 'Out of Stock';
            }
        }
        
        if (method_exists($product, 'isSalable')) {
            return $product->isSalable() ? 'In Stock' : 'Out of Stock';
        }
        
        // Try extension attributes for stock status
        if (method_exists($product, 'getExtensionAttributes')) {
            $extensionAttributes = $product->getExtensionAttributes();
            if ($extensionAttributes && method_exists($extensionAttributes, 'getStockItem')) {
                $stockItem = $extensionAttributes->getStockItem();
                if ($stockItem && method_exists($stockItem, 'getIsInStock')) {
                    return $stockItem->getIsInStock() ? 'In Stock' : 'Out of Stock';
                }
            }
        }
        
        return 'Unknown';
    }

    /**
     * Get product quantity
     *
     * @param ProductInterface|Product $product
     * @return string
     */
    protected function getProductQty(ProductInterface|Product $product): string
    {
        if (method_exists($product, 'getQuantityAndStockStatus')) {
            $stockData = $product->getQuantityAndStockStatus();
            if (isset($stockData['qty'])) {
                return (string)$stockData['qty'];
            }
        }
        
        // Try extension attributes for quantity
        if (method_exists($product, 'getExtensionAttributes')) {
            $extensionAttributes = $product->getExtensionAttributes();
            if ($extensionAttributes && method_exists($extensionAttributes, 'getStockItem')) {
                $stockItem = $extensionAttributes->getStockItem();
                if ($stockItem && method_exists($stockItem, 'getQty')) {
                    return (string)$stockItem->getQty();
                }
            }
        }
        
        return '0';
    }

    /**
     * Get product custom attribute
     *
     * @param ProductInterface|Product $product
     * @param string $attributeCode
     * @return string
     */
    protected function getProductCustomAttribute(ProductInterface|Product $product, string $attributeCode): string
    {
        // Try getter method first
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $attributeCode)));
        if (method_exists($product, $method)) {
            $value = $product->$method();
            return $value !== null ? (string)$value : '';
        }
        
        // Try custom attribute
        if (method_exists($product, 'getCustomAttribute')) {
            $attribute = $product->getCustomAttribute($attributeCode);
            if ($attribute) {
                return (string)$attribute->getValue();
            }
        }
        
        // Try getData
        if (method_exists($product, 'getData')) {
            $value = $product->getData($attributeCode);
            return $value !== null ? (string)$value : '';
        }
        
        return '';
    }

    /**
     * Get category image URL
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return string
     */
    protected function getCategoryImageUrl(\Magento\Catalog\Api\Data\CategoryInterface $category): string
    {
        $image = null;
        
        // Method 1: getImage()
        if (method_exists($category, 'getImage') && $category->getImage()) {
            $image = $category->getImage();
        }
        
        // Method 2: getData('image')
        if (!$image && method_exists($category, 'getData')) {
            $image = $category->getData('image');
        }
        
        // Method 3: getImageUrl() - return directly if available
        if (!$image && method_exists($category, 'getImageUrl')) {
            $imageUrl = $category->getImageUrl();
            if ($imageUrl && $imageUrl !== 'no_selection') {
                return (string)$imageUrl;
            }
        }
        
        // Build full URL if we have image path
        if ($image && $image !== 'no_selection') {
            // Clean up the image path
            $image = str_replace('/media/catalog/category/', '', $image);
            $image = ltrim($image, '/');
            
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            return rtrim($baseUrl, '/') . '/catalog/category/' . $image;
        }
        
        return '';
    }

    /**
     * Clean HTML content safely
     *
     * @param string $content
     * @return string
     */
    protected function cleanHtmlContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }
        
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove HTML tags but preserve some content structure
        $content = strip_tags($content);
        
        // Remove Page Builder artifacts
        $content = preg_replace('/\[data-pb-style=[^\]]*\]/', '', $content);
        $content = preg_replace('/#html-body\s*/', '', $content);
        $content = preg_replace('/\{[^}]*\}/', '', $content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Template\Processor;

use Defox\SEOSuite\Helper\Data as SeoHelper;
use Defox\SEOSuite\Template\AbstractVariableProcessor;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enhanced category variable processor
 * 
 * Processes variables in templates related to category entities with proper dependency injection
 */
class CategoryProcessor extends AbstractVariableProcessor
{
    /**
     * @var ProductCollectionFactory
     */
    protected ProductCollectionFactory $productCollectionFactory;

    /**
     * @param SeoHelper $seoHelper
     * @param StoreManagerInterface $storeManager
     * @param Escaper $escaper
     * @param CacheInterface $cache
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        SeoHelper $seoHelper,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        CacheInterface $cache,
        ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($seoHelper, $storeManager, $escaper, $cache);
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function canProcess($entity): bool
    {
        return $entity instanceof CategoryInterface || $entity instanceof Category;
    }

    /**
     * @inheritdoc
     */
    protected function isEntityPrefix(string $prefix): bool
    {
        return in_array($prefix, ['category', 'parent_category']);
    }

    /**
     * @inheritdoc
     */
    protected function getEntitySpecificVariables(): array
    {
        return [
            'category.name' => 'Category Name',
            'category.url_key' => 'Category URL Key',
            'category.url_path' => 'Category URL Path',
            'category.url' => 'Category URL',
            'category.description' => 'Category Description',
            'category.image' => 'Category Image URL',
            'category.meta_title' => 'Category Meta Title',
            'category.meta_description' => 'Category Meta Description',
            'category.meta_keywords' => 'Category Meta Keywords',
            'category.children_count' => 'Category Children Count',
            'category.level' => 'Category Level',
            'category.parent' => 'Parent Category Name',
            'category.path' => 'Category Path',
            'category.product_count' => 'Category Product Count',
            'category.is_active' => 'Category Active Status',
            'category.is_anchor' => 'Category Anchor Status',
            'category.include_in_menu' => 'Category Include in Menu Status',
            'category.created_at' => 'Category Creation Date',
            'category.updated_at' => 'Category Update Date',
            'parent_category.name' => 'Parent Category Name',
            'parent_category.url' => 'Parent Category URL',
            'parent_category.path' => 'Parent Category Path',
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

        // Ensure entity is properly loaded
        $entity = $this->ensureEntityLoaded($entity);

        $prefix = $propertyPath[0];
        $property = $propertyPath[1] ?? null;
        
        // Handle parent category variables
        if ($prefix === 'parent_category' && ($entity instanceof CategoryInterface || $entity instanceof Category)) {
            $parentCategory = $this->getParentCategory($entity);
            
            if ($parentCategory && $parentCategory->getId()) {
                return $this->getCategoryPropertyValue($parentCategory, $property);
            }
            return '';
        }
        
        // Handle category variables
        if ($prefix === 'category' && ($entity instanceof CategoryInterface || $entity instanceof Category)) {
            return $this->getCategoryPropertyValue($entity, $property);
        }

        return '';
    }

    /**
     * Get category property value
     *
     * @param CategoryInterface|Category $category
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
            
            case 'url_key':
                return (string)$category->getUrlKey();
            
            case 'url_path':
                return (string)$category->getUrlPath();
            
            case 'url':
                return $this->getCategoryUrl($category);
            
            case 'description':
                return $this->getCategoryDescription($category);
            
            case 'image':
                return $this->getCategoryImageUrl($category);
            
            case 'meta_title':
                return (string)$category->getMetaTitle();
            
            case 'meta_description':
                return (string)$category->getMetaDescription();
            
            case 'meta_keywords':
                return (string)$category->getMetaKeywords();
            
            case 'children_count':
                return (string)$category->getChildrenCount();
            
            case 'level':
                return (string)$category->getLevel();
            
            case 'parent':
                $parentCategory = $this->getParentCategory($category);
                return $parentCategory ? (string)$parentCategory->getName() : '';
            
            case 'path':
                return (string)$category->getPath();
            
            case 'product_count':
                return $this->getCategoryProductCount($category);
            
            case 'is_active':
                return $category->getIsActive() ? 'Yes' : 'No';
            
            case 'is_anchor':
                return $category->getIsAnchor() ? 'Yes' : 'No';
            
            case 'include_in_menu':
                return $category->getIncludeInMenu() ? 'Yes' : 'No';
            
            case 'created_at':
                return (string)$category->getCreatedAt();
            
            case 'updated_at':
                return (string)$category->getUpdatedAt();
            
            default:
                // Try custom attributes
                return $this->getCategoryCustomAttribute($category, $property);
        }
    }

    /**
     * Get category URL
     *
     * @param CategoryInterface|Category $category
     * @return string
     */
    protected function getCategoryUrl($category): string
    {
        if (method_exists($category, 'getUrl')) {
            return (string)$category->getUrl();
        }
        
        // Fallback: build URL from URL path
        $urlPath = $category->getUrlPath();
        if ($urlPath) {
            return $this->storeManager->getStore()->getBaseUrl() . $urlPath;
        }
        
        return '';
    }

    /**
     * Get category description with proper HTML cleaning
     *
     * @param CategoryInterface|Category $category
     * @return string
     */
    protected function getCategoryDescription($category): string
    {
        $description = $category->getDescription();
        
        if (empty($description)) {
            return '';
        }
        
        // Clean HTML content safely
        $cleanDescription = $this->cleanHtmlContent((string)$description);
        
        return $cleanDescription;
    }

    /**
     * Get category image URL with proper fallbacks
     *
     * @param CategoryInterface|Category $category
     * @return string
     */
    protected function getCategoryImageUrl($category): string
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
            // Clean up the image path - remove any existing base URL parts
            $image = str_replace('/media/catalog/category/', '', $image);
            $image = ltrim($image, '/');
            
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            return rtrim($baseUrl, '/') . '/catalog/category/' . $image;
        }
        
        return '';
    }

    /**
     * Get category product count using proper collection
     *
     * @param CategoryInterface|Category $category
     * @return string
     */
    protected function getCategoryProductCount($category): string
    {
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addCategoryFilter($category);
            $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);
            
            $count = $collection->getSize();
            return (string)$count;
        } catch (\Exception $e) {
            // Fallback: try to get from category data
            if (method_exists($category, 'getProductCount')) {
                return (string)$category->getProductCount();
            }
            
            return '0';
        }
    }

    /**
     * Get parent category
     *
     * @param CategoryInterface|Category $category
     * @return CategoryInterface|Category|null
     */
    protected function getParentCategory($category)
    {
        if (method_exists($category, 'getParentCategory')) {
            $parentCategory = $category->getParentCategory();
            if ($parentCategory && $parentCategory->getId() && $parentCategory->getId() > 1) {
                return $parentCategory;
            }
        }
        
        if (method_exists($category, 'getParentId')) {
            $parentId = $category->getParentId();
            if ($parentId && $parentId > 1) {
                try {
                    // Load parent category using same class as current category
                    $parentCategory = clone $category;
                    $parentCategory->load($parentId);
                    if ($parentCategory->getId()) {
                        return $parentCategory;
                    }
                } catch (\Exception $e) {
                    // Continue with fallback
                }
            }
        }
        
        return null;
    }

    /**
     * Get category custom attribute
     *
     * @param CategoryInterface|Category $category
     * @param string $attributeCode
     * @return string
     */
    protected function getCategoryCustomAttribute($category, string $attributeCode): string
    {
        // Try getter method first
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $attributeCode)));
        if (method_exists($category, $method)) {
            $value = $category->$method();
            return $value !== null ? (string)$value : '';
        }
        
        // Try custom attribute
        if (method_exists($category, 'getCustomAttribute')) {
            $attribute = $category->getCustomAttribute($attributeCode);
            if ($attribute) {
                return (string)$attribute->getValue();
            }
        }
        
        // Try getData
        if (method_exists($category, 'getData')) {
            $value = $category->getData($attributeCode);
            return $value !== null ? (string)$value : '';
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

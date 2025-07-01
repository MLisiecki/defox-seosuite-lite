<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Canonical;

use Defox\SEOSuite\Helper\Config;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;

/**
 * Class to handle canonicals exclusions
 */
class Exclusion
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @param Config $config
     * @param Http $request
     * @param Registry $registry
     */
    public function __construct(
        Config $config,
        Http $request,
        Registry $registry
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->registry = $registry;
    }

    /**
     * Check if page should be excluded based on URL patterns
     *
     * @return bool
     */
    public function isPageExcluded(): bool
    {
        $currentUrl = $this->request->getRequestUri();
        $excludePatterns = $this->getExcludePatterns();
        
        if (empty($excludePatterns)) {
            return false;
        }
        
        foreach ($excludePatterns as $pattern) {
            if (empty($pattern)) {
                continue;
            }
            
            // Convert wildcard pattern to regex
            $pattern = $this->wildcardToRegex($pattern);
            
            if (preg_match($pattern, $currentUrl)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if product should be excluded from canonical
     *
     * @param Product $product
     * @return bool
     */
    public function isProductExcluded(Product $product): bool
    {
        // Check if canonical is disabled globally for this product
        if ($product->hasData('seo_canonical_disabled') && $product->getData('seo_canonical_disabled')) {
            return true;
        }
        
        // Check if we should exclude based on URL pattern
        if ($this->isPageExcluded()) {
            return true;
        }
        
        // Check excluded product types
        $excludedTypes = $this->config->getExcludedProductTypes();
        if (in_array($product->getTypeId(), $excludedTypes)) {
            return true;
        }
        
        // Check if product visibility is excluded
        if ($this->config->shouldExcludeInvisibleProducts() && 
            $product->getVisibility() == \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if category should be excluded from canonical
     *
     * @param Category $category
     * @return bool
     */
    public function isCategoryExcluded(Category $category): bool
    {
        // Check if canonical is disabled globally for this category
        if ($category->hasData('seo_canonical_disabled') && $category->getData('seo_canonical_disabled')) {
            return true;
        }
        
        // Check if we should exclude based on URL pattern
        if ($this->isPageExcluded()) {
            return true;
        }
        
        // Check if it's an anchor category and we should exclude those
        if ($this->config->shouldExcludeAnchorCategories() && $category->getIsAnchor()) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if CMS page should be excluded from canonical
     *
     * @param string $pageId
     * @return bool
     */
    public function isCmsPageExcluded(string $pageId): bool
    {
        // Check if canonical is disabled for this CMS page
        $excludedCmsPages = $this->config->getExcludedCmsPages();
        if (in_array($pageId, $excludedCmsPages)) {
            return true;
        }
        
        // Check if we should exclude based on URL pattern
        if ($this->isPageExcluded()) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if the current page should have a noindex tag
     *
     * @return bool
     */
    public function hasNoindexTag(): bool
    {
        // Check if robots tag is set to NOINDEX
        $robotsTag = $this->registry->registry('robots_tag');
        
        if ($robotsTag && stripos($robotsTag, 'noindex') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Get exclude patterns from configuration
     *
     * @return array
     */
    private function getExcludePatterns(): array
    {
        $patterns = $this->config->getExcludedUrlPatterns();
        
        if (empty($patterns)) {
            return [];
        }
        
        return array_map('trim', explode(PHP_EOL, $patterns));
    }

    /**
     * Convert wildcard pattern to regex
     *
     * @param string $pattern
     * @return string
     */
    private function wildcardToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return '/^' . $pattern . '$/i';
    }
}

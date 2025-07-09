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
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Page;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enhanced CMS Page variable processor
 *
 * Processes variables in templates related to CMS Page entities with improved content handling
 */
class CmsPageProcessor extends AbstractVariableProcessor
{
    /**
     * @param SeoHelper $seoHelper
     * @param StoreManagerInterface $storeManager
     * @param Escaper $escaper
     * @param CacheInterface $cache
     */
    public function __construct(
        SeoHelper $seoHelper,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        CacheInterface $cache
    ) {
        parent::__construct($seoHelper, $storeManager, $escaper, $cache);
    }

    /**
     * @inheritdoc
     */
    public function canProcess($entity): bool
    {
        return $entity instanceof PageInterface || $entity instanceof Page;
    }

    /**
     * @inheritdoc
     */
    protected function isEntityPrefix(string $prefix): bool
    {
        return in_array($prefix, ['cms_page', 'page']);
    }

    /**
     * @inheritdoc
     */
    protected function getEntitySpecificVariables(): array
    {
        return [
            'cms_page.title' => 'Page Title',
            'cms_page.identifier' => 'Page Identifier (URL Key)',
            'cms_page.content' => 'Page Content (stripped of HTML)',
            'cms_page.content_raw' => 'Page Content (raw HTML)',
            'cms_page.content_excerpt' => 'Page Content Excerpt (first 160 chars)',
            'cms_page.content_heading' => 'Page Content Heading',
            'cms_page.meta_title' => 'Page Meta Title',
            'cms_page.meta_description' => 'Page Meta Description',
            'cms_page.meta_keywords' => 'Page Meta Keywords',
            'cms_page.creation_time' => 'Page Creation Time',
            'cms_page.update_time' => 'Page Update Time',
            'cms_page.is_active' => 'Page Active Status',
            'cms_page.page_layout' => 'Page Layout',
            'cms_page.layout_update_xml' => 'Page Layout Update XML',
            'cms_page.custom_theme' => 'Page Custom Theme',
            'cms_page.custom_theme_from' => 'Page Custom Theme From Date',
            'cms_page.custom_theme_to' => 'Page Custom Theme To Date',
            'cms_page.sort_order' => 'Page Sort Order',
            'page.title' => 'Page Title (alias)',
            'page.identifier' => 'Page Identifier (alias)',
            'page.content' => 'Page Content (alias)',
            'page.url' => 'Page URL',
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

        // Handle both cms_page and page prefixes
        if (in_array($prefix, ['cms_page', 'page']) && ($entity instanceof PageInterface || $entity instanceof Page)) {
            return $this->getPagePropertyValue($entity, $property);
        }

        return '';
    }

    /**
     * Get page property value
     *
     * @param PageInterface|Page $page
     * @param string|null $property
     * @return string
     */
    protected function getPagePropertyValue($page, ?string $property): string
    {
        if (!$property) {
            return '';
        }

        switch ($property) {
            case 'title':
                return (string)$page->getTitle();

            case 'identifier':
                return (string)$page->getIdentifier();

            case 'content':
                return $this->getCleanContent($page);

            case 'content_raw':
                return (string)$page->getContent();

            case 'content_excerpt':
                return $this->getContentExcerpt($page, 160);

            case 'content_heading':
                return (string)$page->getContentHeading();

            case 'meta_title':
                return (string)$page->getMetaTitle();

            case 'meta_description':
                return (string)$page->getMetaDescription();

            case 'meta_keywords':
                return (string)$page->getMetaKeywords();

            case 'creation_time':
                return (string)$page->getCreationTime();

            case 'update_time':
                return (string)$page->getUpdateTime();

            case 'is_active':
                return $page->isActive() ? 'Yes' : 'No';

            case 'page_layout':
                return (string)$page->getPageLayout();

            case 'layout_update_xml':
                return (string)$page->getLayoutUpdateXml();

            case 'custom_theme':
                return (string)$page->getCustomTheme();

            case 'custom_theme_from':
                return (string)$page->getCustomThemeFrom();

            case 'custom_theme_to':
                return (string)$page->getCustomThemeTo();

            case 'sort_order':
                return (string)$page->getSortOrder();

            case 'url':
                return $this->getPageUrl($page);

            default:
                // Try custom attributes
                return $this->getPageCustomAttribute($page, $property);
        }
    }

    /**
     * Get clean content with improved HTML processing
     *
     * @param PageInterface|Page $page
     * @return string
     */
    protected function getCleanContent($page): string
    {
        $content = $page->getContent();

        if (empty($content)) {
            return '';
        }

        return $this->cleanHtmlContent((string)$content);
    }

    /**
     * Get content excerpt
     *
     * @param PageInterface|Page $page
     * @param int $length
     * @return string
     */
    protected function getContentExcerpt($page, int $length = 160): string
    {
        $cleanContent = $this->getCleanContent($page);

        if (empty($cleanContent)) {
            return '';
        }

        if (mb_strlen($cleanContent) <= $length) {
            return $cleanContent;
        }        
        return mb_substr($cleanContent, 0, $length) . '...';
    }

    /**
     * Get page URL
     *
     * @param PageInterface|Page $page
     * @return string
     */
    protected function getPageUrl($page): string
    {
        $identifier = $page->getIdentifier();
        if ($identifier) {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            return rtrim($baseUrl, '/') . '/' . $identifier;
        }
        
        return '';
    }

    /**
     * Get page custom attribute
     *
     * @param PageInterface|Page $page
     * @param string $attributeCode
     * @return string
     */
    protected function getPageCustomAttribute($page, string $attributeCode): string
    {
        // Try getter method first
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $attributeCode)));
        if (method_exists($page, $method)) {
            $value = $page->$method();
            return $value !== null ? (string)$value : '';
        }
        
        // Try custom attribute
        if (method_exists($page, 'getCustomAttribute')) {
            $attribute = $page->getCustomAttribute($attributeCode);
            if ($attribute) {
                return (string)$attribute->getValue();
            }
        }
        
        // Try getData
        if (method_exists($page, 'getData')) {
            $value = $page->getData($attributeCode);
            return $value !== null ? (string)$value : '';
        }
        
        return '';
    }

    /**
     * Clean HTML content with improved processing
     *
     * @param string $content
     * @return string
     */
    protected function cleanHtmlContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }
        
        // First, handle Page Builder content and directives
        $content = $this->processPageBuilderContent($content);
        
        // Decode HTML entities carefully
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove script and style tags with their content
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        
        // Convert some HTML elements to readable text
        $content = preg_replace('/<br\s*\/?>/i', ' ', $content);
        $content = preg_replace('/<\/p>/i', ' ', $content);
        $content = preg_replace('/<\/div>/i', ' ', $content);
        $content = preg_replace('/<\/h[1-6]>/i', ' ', $content);
        
        // Remove all remaining HTML tags
        $content = strip_tags($content);
        
        // Clean up whitespace and special characters
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/[^\w\s\-.,!?;:()\[\]"\'\/&]/', '', $content);
        $content = trim($content);
        
        return $content;
    }

    /**
     * Process Page Builder specific content
     *
     * @param string $content
     * @return string
     */
    protected function processPageBuilderContent(string $content): string
    {
        // Remove Page Builder style attributes
        $content = preg_replace('/\[data-pb-style=[^\]]*\]/', '', $content);
        
        // Remove Page Builder wrapper divs
        $content = preg_replace('/<div[^>]*data-content-type[^>]*>/', '', $content);
        $content = preg_replace('/<div[^>]*data-element[^>]*>/', '', $content);
        
        // Remove empty data attributes
        $content = preg_replace('/data-[a-zA-Z0-9\-]+=["\'"][^"\']*["\'"]/', '', $content);
        
        // Clean up HTML body references from Page Builder
        $content = preg_replace('/#html-body\s*/', '', $content);
        
        // Remove Page Builder CSS class references
        $content = preg_replace('/pagebuilder-[a-zA-Z0-9\-]*/', '', $content);
        
        // Remove widget directives
        $content = preg_replace('/\{\{[^}]*\}\}/', '', $content);
        
        return $content;
    }
}

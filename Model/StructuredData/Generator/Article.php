<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Generator;

use Magento\Cms\Model\Page;

/**
 * Article structured data generator
 *
 * Generates schema.org Article/BlogPosting structured data for CMS pages that are articles or blog posts.
 */
class Article extends WebPage
{
    /**
     * Keywords that identify article/blog pages
     * @var array
     */
    private array $articleKeywords = [
        'blog', 'article', 'news', 'post', 'story',
        'aktualnosci', 'artykul', 'wpis', 'nowosci'
    ];

    /**
     * Generate structured data for article
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        /** @var Page $page */
        $page = $entity;

        // Get base WebPage data
        $data = parent::doGenerate($entity, $context);

        // Change type to Article or BlogPosting
        $data['@type'] = $this->getArticleType($page);

        // Add headline (required for Article)
        $data['headline'] = $page->getTitle();

        // Add author
        $author = $this->getAuthor($page, $context);
        if ($author) {
            $data['author'] = $author;
        }

        // Add article body with HTML cleaning
        $data['articleBody'] = $this->cleanContent($page->getContent());

        // Add word count using cleaned content
        $cleanContent = $this->cleanContent($page->getContent());
        $data['wordCount'] = str_word_count($cleanContent);

        // Add images from content
        $images = $this->extractImagesFromContent($page->getContent());
        if (!empty($images)) {
            $data['image'] = count($images) === 1 ? $images[0] : $images;
        }

        // Add keywords from meta keywords (cleaned)
        if ($page->getMetaKeywords()) {
            $data['keywords'] = $this->cleanTextField($page->getMetaKeywords());
        }

        // Add article section if available
        if (isset($context['section'])) {
            $data['articleSection'] = $context['section'];
        }

        return $data;
    }

    /**
     * Check if generator can handle entity
     *
     * @param mixed $entity
     * @return bool
     */
    public function canHandle($entity): bool
    {
        if (!parent::canHandle($entity)) {
            return false;
        }

        // Check if page is an article/blog post
        return $this->isArticlePage($entity);
    }

    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'Article';
    }

    /**
     * Check if page is an article/blog post
     *
     * @param Page $page
     * @return bool
     */
    private function isArticlePage(Page $page): bool
    {
        $identifier = strtolower($page->getIdentifier());
        $title = strtolower($page->getTitle());

        foreach ($this->articleKeywords as $keyword) {
            if (strpos($identifier, $keyword) !== false || strpos($title, $keyword) !== false) {
                return true;
            }
        }

        // Check if page has a specific layout that indicates it's an article
        $layout = $page->getPageLayout();
        if ($layout && strpos($layout, 'article') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get article type (Article or BlogPosting)
     *
     * @param Page $page
     * @return string
     */
    private function getArticleType(Page $page): string
    {
        $identifier = strtolower($page->getIdentifier());

        if (strpos($identifier, 'blog') !== false) {
            return 'BlogPosting';
        }

        return 'Article';
    }

    /**
     * Get author information
     *
     * @param Page $page
     * @param array $context
     * @return array|null
     */
    private function getAuthor(Page $page, array $context): ?array
    {
        // Check context for author information
        if (isset($context['author'])) {
            if (is_array($context['author'])) {
                return $context['author'];
            } else {
                return [
                    '@type' => 'Person',
                    'name' => $context['author']
                ];
            }
        }

        // Try to extract author from content
        $content = $page->getContent();
        if (preg_match('/(?:by|autor:|author:)\s*([^<\
]+)/i', $content, $matches)) {
            return [
                '@type' => 'Person',
                'name' => trim($matches[1])
            ];
        }

        // Default to organization
        $organizationName = $this->configHelper->getStructuredDataOrganizationName();
        if ($organizationName) {
            return [
                '@type' => 'Organization',
                'name' => $organizationName
            ];
        }

        return null;
    }

    /**
     * Extract images from content
     *
     * @param string $content
     * @return array
     */
    private function extractImagesFromContent(string $content): array
    {
        $images = [];

        // Extract img tags
        if (preg_match_all('/<img[^>]+src=[\"\']([^\"\']+)[\"\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $imageUrl) {
                // Convert relative URLs to absolute
                if (strpos($imageUrl, 'http') !== 0) {
                    $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                    $imageUrl = rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
                }

                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $images[] = $imageUrl;
                }
            }
        }
        return array_unique($images);
    }
}

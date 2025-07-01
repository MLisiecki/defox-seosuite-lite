<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap;

/**
 * Interface for sitemap data providers
 * 
 * Provides items for inclusion in the sitemap from various sources
 * (products, categories, CMS pages, etc.)
 */
interface SitemapProviderInterface
{
    /**
     * Get items for sitemap
     *
     * @param int $storeId
     * @param int $limit Limit number of items (for batch processing)
     * @param int $offset Offset for pagination
     * @return SitemapItemInterface[]
     */
    public function getItems(int $storeId, int $limit = 0, int $offset = 0): array;

    /**
     * Get total count of items
     *
     * @param int $storeId
     * @return int
     */
    public function getItemsCount(int $storeId): int;

    /**
     * Get provider code
     *
     * @return string Unique identifier for this provider
     */
    public function getCode(): string;

    /**
     * Check if provider is enabled
     *
     * @param int $storeId
     * @return bool
     */
    public function isEnabled(int $storeId): bool;

    /**
     * Get default change frequency for this provider's items
     *
     * @return string
     */
    public function getDefaultChangefreq(): string;

    /**
     * Get default priority for this provider's items
     *
     * @return float
     */
    public function getDefaultPriority(): float;

    /**
     * Check if provider supports hreflang
     *
     * @return bool
     */
    public function supportsHreflang(): bool;

    /**
     * Check if provider supports image sitemap
     *
     * @return bool
     */
    public function supportsImages(): bool;
}

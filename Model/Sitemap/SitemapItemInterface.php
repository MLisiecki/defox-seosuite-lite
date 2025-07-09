<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap;

/**
 * Interface for sitemap item
 * 
 * Represents a single item (URL) in the sitemap with all necessary attributes
 * according to the sitemap protocol specification
 */
interface SitemapItemInterface
{
    /**
     * Get URL of the item
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Get last modification date
     *
     * @return string|null Date in W3C format (YYYY-MM-DD)
     */
    public function getLastmod(): ?string;

    /**
     * Get change frequency
     *
     * @return string|null One of: always, hourly, daily, weekly, monthly, yearly, never
     */
    public function getChangefreq(): ?string;

    /**
     * Get priority
     *
     * @return float|null Value between 0.0 and 1.0
     */
    public function getPriority(): ?float;

    /**
     * Get alternate language versions (hreflang)
     *
     * @return array Array of ['lang' => 'language_code', 'url' => 'alternate_url']
     */
    public function getAlternates(): array;

    /**
     * Get images associated with this URL
     *
     * @return array Array of ['url' => 'image_url', 'title' => 'image_title', 'caption' => 'image_caption']
     */
    public function getImages(): array;

    /**
     * Check if item should be included in sitemap
     *
     * @return bool
     */
    public function isIncluded(): bool;
}

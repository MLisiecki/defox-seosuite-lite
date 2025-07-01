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
 * Interface for sitemap generators
 * 
 * Handles the generation of sitemap files in various formats
 */
interface SitemapGeneratorInterface
{
    /**
     * Generate sitemap
     *
     * @param int $storeId
     * @param array $options Additional options for generation
     * @return array Array of generated file paths
     * @throws \Exception
     */
    public function generate(int $storeId, array $options = []): array;

    /**
     * Generate sitemap index file
     *
     * @param int $storeId
     * @param array $sitemapFiles Array of sitemap file URLs
     * @return string Path to generated index file
     * @throws \Exception
     */
    public function generateIndex(int $storeId, array $sitemapFiles): string;

    /**
     * Ping search engines about sitemap update
     *
     * @param string $sitemapUrl
     * @param int $storeId
     * @return array Results of ping operations
     */
    public function pingSearchEngines(string $sitemapUrl, int $storeId = 0): array;

    /**
     * Get sitemap file path
     *
     * @param int $storeId
     * @param int $fileNumber File number for multiple files
     * @return string
     */
    public function getFilePath(int $storeId, int $fileNumber = 0): string;

    /**
     * Get sitemap URL
     *
     * @param int $storeId
     * @param string $fileName
     * @return string
     */
    public function getSitemapUrl(int $storeId, string $fileName): string;

    /**
     * Clean old sitemap files
     *
     * @param int $storeId
     * @return int Number of deleted files
     */
    public function cleanOldFiles(int $storeId): int;

    /**
     * Validate sitemap file
     *
     * @param string $filePath
     * @return array Validation results ['valid' => bool, 'errors' => array]
     */
    public function validateSitemap(string $filePath): array;
}

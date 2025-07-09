<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap;

use Defox\SEOSuite\Logger\Logger;

/**
 * Pool of sitemap providers
 * 
 * Manages all registered sitemap data providers
 */
class SitemapProviderPool
{
    /**
     * @var SitemapProviderInterface[]
     */
    private array $providers;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor
     *
     * @param Logger $logger
     * @param array $providers
     */
    public function __construct(
        Logger $logger,
        array $providers = []
    ) {
        $this->logger = $logger;
        $this->providers = [];
        
        foreach ($providers as $code => $provider) {
            if ($provider instanceof SitemapProviderInterface) {
                $this->providers[$code] = $provider;
            } else {
                $this->logger->error(sprintf(
                    'Invalid sitemap provider for code "%s". Must implement SitemapProviderInterface.',
                    $code
                ));
            }
        }
    }

    /**
     * Get all providers
     *
     * @return SitemapProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get enabled providers for store
     *
     * @param int $storeId
     * @return SitemapProviderInterface[]
     */
    public function getEnabledProviders(int $storeId): array
    {
        $enabledProviders = [];
        
        foreach ($this->providers as $code => $provider) {
            $isEnabled = $provider->isEnabled($storeId);
            
            if ($isEnabled) {
                $enabledProviders[$code] = $provider;
            }
        }
        
        $this->logger->info(sprintf(
            'SitemapProviderPool: Found %d enabled providers for store %d: %s',
            count($enabledProviders),
            $storeId,
            implode(', ', array_keys($enabledProviders))
        ));
        
        return $enabledProviders;
    }

    /**
     * Get provider by code
     *
     * @param string $code
     * @return SitemapProviderInterface|null
     */
    public function getProvider(string $code): ?SitemapProviderInterface
    {
        return $this->providers[$code] ?? null;
    }

    /**
     * Check if provider exists
     *
     * @param string $code
     * @return bool
     */
    public function hasProvider(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * Add provider to pool
     *
     * @param string $code
     * @param SitemapProviderInterface $provider
     * @return void
     */
    public function addProvider(string $code, SitemapProviderInterface $provider): void
    {
        $this->providers[$code] = $provider;
    }

    /**
     * Remove provider from pool
     *
     * @param string $code
     * @return void
     */
    public function removeProvider(string $code): void
    {
        unset($this->providers[$code]);
    }

    /**
     * Get total items count for all enabled providers
     *
     * @param int $storeId
     * @return int
     */
    public function getTotalItemsCount(int $storeId): int
    {
        $totalCount = 0;
        
        foreach ($this->getEnabledProviders($storeId) as $provider) {
            try {
                $totalCount += $provider->getItemsCount($storeId);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error getting items count from provider "%s": %s',
                    $provider->getCode(),
                    $e->getMessage()
                ));
            }
        }
        
        return $totalCount;
    }

    /**
     * Get all items from all enabled providers
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return array Array of ['provider' => string, 'items' => SitemapItemInterface[]]
     */
    public function getAllItems(int $storeId, int $limit = 0, int $offset = 0): array
    {
        $allItems = [];
        
        foreach ($this->getEnabledProviders($storeId) as $provider) {
            try {
                $items = $provider->getItems($storeId, $limit, $offset);
                if (!empty($items)) {
                    $allItems[] = [
                        'provider' => $provider->getCode(),
                        'items' => $items
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error getting items from provider "%s": %s',
                    $provider->getCode(),
                    $e->getMessage()
                ));
            }
        }
        
        return $allItems;
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Provider;

use Defox\SEOSuite\Model\Sitemap\SitemapItem;
use Defox\SEOSuite\Model\Sitemap\SitemapItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Cache\CacheManager;

/**
 * Additional links provider for sitemap
 * 
 * Provides custom links that are manually added by administrators
 */
class AdditionalLinksProvider extends AbstractProvider
{
    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'additional_links';
    }

    /**
     * @inheritDoc
     */
    public function supportsHreflang(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getItems(int $storeId, int $limit = 0, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey($storeId, $limit, $offset);
        $cachedData = $this->loadFromCache($cacheKey);
        
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        try {
            $items = [];
            $links = $this->getAdditionalLinks($storeId, $limit, $offset);
            
            foreach ($links as $link) {
                $item = new SitemapItem($link['url']);
                $item->setChangefreq($link['changefreq']);
                $item->setPriority((float)$link['priority']);
                
                // Set last modification to current date if not specified
                $item->setLastmod(date('Y-m-d'));
                
                $items[] = $item;
            }
            
            $this->saveToCache($cacheKey, $items);
            
            return $items;
        } catch (\Exception $e) {
            $this->logger->error('Error generating additional links sitemap items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getItemsCount(int $storeId): int
    {
        $cacheKey = $this->getCountCacheKey($storeId);
        $cachedCount = $this->loadFromCache($cacheKey);
        
        if ($cachedCount !== null) {
            return (int)$cachedCount;
        }
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('defox_seosuite_sitemap_additional_links');
            
            $select = $connection->select()
                ->from($table, 'COUNT(*)')
                ->where('store_id IN (?)', [0, $storeId])
                ->where('is_active = ?', 1);
            
            $count = (int)$connection->fetchOne($select);
            
            $this->saveToCache($cacheKey, [$count]);
            
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Error getting additional links count for sitemap: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get additional links from database
     *
     * @param int $storeId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function getAdditionalLinks(int $storeId, int $limit = 0, int $offset = 0): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('defox_seosuite_sitemap_additional_links');
        
        $select = $connection->select()
            ->from($table)
            ->where('store_id IN (?)', [0, $storeId])
            ->where('is_active = ?', 1)
            ->order('link_id ASC');
        
        if ($limit > 0) {
            $select->limit($limit, $offset);
        }
        
        return $connection->fetchAll($select);
    }

    /**
     * @inheritDoc
     */
    protected function getEntityUrl(string $entityId, string $entityType, int $storeId): ?string
    {
        // Not applicable for additional links
        return null;
    }

    /**
     * Get additional links grouped by group code for HTML sitemap
     *
     * @param int $storeId
     * @return array
     */
    public function getGroupedLinks(int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('defox_seosuite_sitemap_additional_links');
        
        $select = $connection->select()
            ->from($table)
            ->where('store_id IN (?)', [0, $storeId])
            ->where('is_active = ?', 1)
            ->order(['group_code ASC', 'title ASC']);
        
        $links = $connection->fetchAll($select);
        
        $grouped = [];
        foreach ($links as $link) {
            $groupCode = $link['group_code'] ?: 'other';
            if (!isset($grouped[$groupCode])) {
                $grouped[$groupCode] = [];
            }
            $grouped[$groupCode][] = $link;
        }
        
        return $grouped;
    }
}

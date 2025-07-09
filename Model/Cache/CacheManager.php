<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Cache;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Cache manager class
 */
class CacheManager
{
    /**
     * Cache id prefix
     */
    private const CACHE_ID_PREFIX = 'defox_seosuite_';

    /**
     * Cache tag prefix
     */
    private const CACHE_TAG_PREFIX = 'DEFOX_SEOSUITE_';

    /**
     * Cache table name
     */
    private const CACHE_TABLE_NAME = 'defox_seosuite_cache';

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var string
     */
    private string $cacheBackend;

    /**
     * @var AdapterInterface|null
     */
    private ?AdapterInterface $connection = null;

    /**
     * @param CacheInterface $cache
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param Logger $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        CacheInterface $cache,
        ResourceConnection $resourceConnection,
        Config $config,
        Logger $logger,
        SerializerInterface $serializer
    ) {
        $this->cache = $cache;
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->cacheBackend = $this->config->getCacheBackend();
    }

    /**
     * Load data from cache
     *
     * @param string $key
     * @return mixed|null
     */
    public function load(string $key): mixed
    {
        if (!$this->config->isCacheEnabled()) {
            return null;
        }

        $cacheKey = $this->prepareCacheKey($key);

        try {
            if ($this->cacheBackend === 'db') {
                return $this->loadFromDb($cacheKey);
            }

            // Default to file or redis (handled by Magento's cache)
            return $this->cache->load($cacheKey);
        } catch (\Exception $e) {
            $this->logger->error('Error loading from cache: ' . $e->getMessage(), [
                'key' => $key,
                'backend' => $this->cacheBackend
            ]);
            return null;
        }
    }

    /**
     * Save data to cache
     *
     * @param mixed $data
     * @param string $key
     * @param array $tags
     * @param int|null $lifetime
     * @return bool
     */
    public function save(mixed $data, string $key, array $tags = [], ?int $lifetime = null): bool
    {
        if (!$this->config->isCacheEnabled()) {
            return false;
        }

        if ($lifetime === null) {
            $lifetime = $this->config->getCacheLifetime();
        }

        $cacheKey = $this->prepareCacheKey($key);
        $cacheTags = $this->prepareCacheTags($tags);

        try {
            if ($this->cacheBackend === 'db') {
                return $this->saveToDb($cacheKey, $data, $cacheTags, $lifetime);
            }

            // Default to file or redis (handled by Magento's cache)
            return $this->cache->save($data, $cacheKey, $cacheTags, $lifetime);
        } catch (\Exception $e) {
            $this->logger->error('Error saving to cache: ' . $e->getMessage(), [
                'key' => $key,
                'backend' => $this->cacheBackend,
                'tags' => $tags
            ]);
            return false;
        }
    }

    /**
     * Remove data from cache
     *
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        if (!$this->config->isCacheEnabled()) {
            return false;
        }

        $cacheKey = $this->prepareCacheKey($key);

        try {
            if ($this->cacheBackend === 'db') {
                return $this->removeFromDb($cacheKey);
            }

            // Default to file or redis (handled by Magento's cache)
            return $this->cache->remove($cacheKey);
        } catch (\Exception $e) {
            $this->logger->error('Error removing from cache: ' . $e->getMessage(), [
                'key' => $key,
                'backend' => $this->cacheBackend
            ]);
            return false;
        }
    }

    /**
     * Clean cache by tags
     *
     * @param array $tags
     * @return bool
     */
    public function clean(array $tags = []): bool
    {
        if (!$this->config->isCacheEnabled()) {
            return false;
        }

        $cacheTags = $this->prepareCacheTags($tags);

        try {
            if ($this->cacheBackend === 'db') {
                return $this->cleanInDb($cacheTags);
            }

            // Default to file or redis (handled by Magento's cache)
            return $this->cache->clean($cacheTags);
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning cache: ' . $e->getMessage(), [
                'tags' => $tags,
                'backend' => $this->cacheBackend
            ]);
            return false;
        }
    }

    /**
     * Load cache data from database
     *
     * @param string $key
     * @return mixed|null
     * @throws LocalizedException
     */
    private function loadFromDb(string $key): mixed
    {
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName(self::CACHE_TABLE_NAME);

        $select = $connection->select()
            ->from($table)
            ->where('cache_key = ?', $key)
            ->limit(1);

        $data = $connection->fetchRow($select);

        if (!$data) {
            return null;
        }

        // Check if cache is expired
        $expirationTime = (int)$data['expiration_time'];
        if ($expirationTime > 0 && $expirationTime < time()) {
            $this->removeFromDb($key);
            return null;
        }

        return $data['cache_data'];
    }

    /**
     * Save cache data to database
     *
     * @param string $key
     * @param mixed $data
     * @param array $tags
     * @param int $lifetime
     * @return bool
     * @throws LocalizedException
     */
    private function saveToDb(string $key, mixed $data, array $tags, int $lifetime): bool
    {
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName(self::CACHE_TABLE_NAME);

        // Remove existing cache entry
        $this->removeFromDb($key);

        $now = time();
        $expirationTime = $lifetime > 0 ? $now + $lifetime : 0;

        // Insert new cache entry
        return (bool)$connection->insert(
            $table,
            [
                'cache_key' => $key,
                'cache_data' => is_string($data) ? $data : $this->serializer->serialize($data),
                'cache_tags' => implode(',', $tags),
                'lifetime' => $lifetime,
                'creation_time' => $now,
                'expiration_time' => $expirationTime
            ]
        );
    }

    /**
     * Remove cache data from database
     *
     * @param string $key
     * @return bool
     * @throws LocalizedException
     */
    private function removeFromDb(string $key): bool
    {
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName(self::CACHE_TABLE_NAME);

        return (bool)$connection->delete(
            $table,
            ['cache_key = ?' => $key]
        );
    }

    /**
     * Clean cache data in database by tags
     *
     * @param array $tags
     * @return bool
     * @throws LocalizedException
     */
    private function cleanInDb(array $tags): bool
    {
        if (empty($tags)) {
            return true;
        }

        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName(self::CACHE_TABLE_NAME);

        $conditions = [];
        foreach ($tags as $tag) {
            $conditions[] = $connection->quoteInto('cache_tags LIKE ?', '%' . $tag . '%');
        }

        $where = implode(' OR ', $conditions);

        return (bool)$connection->delete($table, $where);
    }

    /**
     * Prepare cache key
     *
     * @param string $key
     * @return string
     */
    private function prepareCacheKey(string $key): string
    {
        return self::CACHE_ID_PREFIX . $key;
    }

    /**
     * Prepare cache tags
     *
     * @param array $tags
     * @return array
     */
    private function prepareCacheTags(array $tags): array
    {
        $result = [self::CACHE_TAG_PREFIX . 'ALL'];

        foreach ($tags as $tag) {
            $result[] = self::CACHE_TAG_PREFIX . $tag;
        }

        return $result;
    }

    /**
     * Get database connection
     *
     * @return AdapterInterface
     * @throws LocalizedException
     */
    private function getConnection(): AdapterInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->resourceConnection->getConnection();
        }

        return $this->connection;
    }
}

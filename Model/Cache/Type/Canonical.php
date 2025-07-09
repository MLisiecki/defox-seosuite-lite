<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Cache\Type;

use Defox\SEOSuite\Helper\Config;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Canonical cache type implementation
 */
class Canonical extends TagScope
{
    /**
     * Cache type code
     */
    public const TYPE_IDENTIFIER = 'defox_seosuite_canonical';

    /**
     * Cache tag
     */
    public const CACHE_TAG = 'DEFOX_SEOSUITE_CANONICAL';

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param FrontendPool $frontendPool
     * @param SerializerInterface $serializer
     * @param Config $config
     */
    public function __construct(
        FrontendPool $frontendPool,
        SerializerInterface $serializer,
        Config $config
    ) {
        parent::__construct($frontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
        $this->serializer = $serializer;
        $this->config = $config;
    }

    /**
     * Save data to cache
     *
     * @param string $data
     * @param string $identifier
     * @param array $tags
     * @param int|null $lifeTime
     * @return bool
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null): bool
    {
        if (!$this->config->isCanonicalCacheEnabled()) {
            return false;
        }

        if ($lifeTime === null) {
            $lifeTime = $this->config->getCanonicalCacheLifetime();
        }

        $tags[] = self::CACHE_TAG;
        
        $serializedData = $data;
        if (!is_string($data)) {
            $serializedData = $this->serializer->serialize($data);
        }

        return parent::save($serializedData, $identifier, $tags, $lifeTime);
    }

    /**
     * Load data from cache
     *
     * @param string $identifier
     * @return string|bool
     */
    public function load($identifier)
    {
        if (!$this->config->isCanonicalCacheEnabled()) {
            return false;
        }

        $data = parent::load($identifier);
        
        if ($data) {
            try {
                return $this->serializer->unserialize($data);
            } catch (\Exception $e) {
                // If unserialize fails, return the raw data (it may be a simple string)
                return $data;
            }
        }
        
        return false;
    }

    /**
     * Clean cache by tags
     *
     * @param string $mode
     * @param array $tags
     * @return bool
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $tags[] = self::CACHE_TAG;
        return parent::clean($mode, $tags);
    }
}

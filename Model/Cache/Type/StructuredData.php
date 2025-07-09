<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * Structured Data cache type
 * 
 * This class implements a dedicated cache type for structured data
 * to improve performance when generating JSON-LD schemas.
 */
class StructuredData extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    const TYPE_IDENTIFIER = 'defox_seosuite_structured_data';

    /**
     * Cache tag used to distinguish the cache type from all other caches
     */
    const CACHE_TAG = 'DEFOX_SEOSUITE_STRUCTURED_DATA';

    /**
     * Constructor
     *
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(
        FrontendPool $cacheFrontendPool
    ) {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );
    }
}

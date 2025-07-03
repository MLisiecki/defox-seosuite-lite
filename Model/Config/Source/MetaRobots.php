<?php
/**
 * Meta robots source model
 *
 * @package     Defox_SEOSuite
 * @copyright   Copyright (c) 2024 Defox (https://defox.com)
 * @license     Defox Proprietary License
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Meta robots source model
 * 
 * This class provides options for meta robots directive
 */
class MetaRobots implements OptionSourceInterface
{
    /**
     * Meta robots options
     */
    public const INDEX_FOLLOW = 'INDEX,FOLLOW';
    public const INDEX_NOFOLLOW = 'INDEX,NOFOLLOW';
    public const NOINDEX_FOLLOW = 'NOINDEX,FOLLOW';
    public const NOINDEX_NOFOLLOW = 'NOINDEX,NOFOLLOW';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::INDEX_FOLLOW, 'label' => __('INDEX, FOLLOW')],
            ['value' => self::INDEX_NOFOLLOW, 'label' => __('INDEX, NOFOLLOW')],
            ['value' => self::NOINDEX_FOLLOW, 'label' => __('NOINDEX, FOLLOW')],
            ['value' => self::NOINDEX_NOFOLLOW, 'label' => __('NOINDEX, NOFOLLOW')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::INDEX_FOLLOW => __('INDEX, FOLLOW'),
            self::INDEX_NOFOLLOW => __('INDEX, NOFOLLOW'),
            self::NOINDEX_FOLLOW => __('NOINDEX, FOLLOW'),
            self::NOINDEX_NOFOLLOW => __('NOINDEX, NOFOLLOW')
        ];
    }
}

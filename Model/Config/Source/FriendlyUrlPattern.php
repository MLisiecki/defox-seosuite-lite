<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Friendly URL pattern source model
 */
class FriendlyUrlPattern implements OptionSourceInterface
{
    /**
     * Pattern constants
     */
    public const PATTERN_PATH = 'path';
    public const PATTERN_QUERY = 'query';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::PATTERN_PATH,
                'label' => __('Path Based (category/filter-price-10-20/color-red/)')
            ],
            [
                'value' => self::PATTERN_QUERY,
                'label' => __('Query Based (category?price=10-20&color=red)')
            ]
        ];
    }
}

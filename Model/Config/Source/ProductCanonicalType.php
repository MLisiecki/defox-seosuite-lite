<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product canonical URL type source model
 */
class ProductCanonicalType implements OptionSourceInterface
{
    /**
     * Canonical type constants
     */
    public const TYPE_PRODUCT_URL = 'product_url';
    public const TYPE_WITH_CATEGORY = 'with_category';
    public const TYPE_SHORTEST_CATEGORY = 'shortest_category';
    public const TYPE_LONGEST_CATEGORY = 'longest_category';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_PRODUCT_URL, 'label' => __('Product URL (no category)')],
            ['value' => self::TYPE_WITH_CATEGORY, 'label' => __('With Primary Category')],
            ['value' => self::TYPE_SHORTEST_CATEGORY, 'label' => __('With Shortest Category Path')],
            ['value' => self::TYPE_LONGEST_CATEGORY, 'label' => __('With Longest Category Path')]
        ];
    }
}

<?php
/**
 * Open Graph product type source model
 *
 * @package     Defox_SEOSuite
 * @copyright   Copyright (c) 2024 Defox (https://defox.com)
 * @license     Defox Proprietary License
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Open Graph product type source model
 * 
 * This class provides options for Open Graph product type
 */
class OpenGraphProductType implements OptionSourceInterface
{
    /**
     * Open Graph product types
     */
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PRODUCT_ITEM = 'product.item';
    public const TYPE_PRODUCT_GROUP = 'product.group';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_PRODUCT, 'label' => __('Product')],
            ['value' => self::TYPE_PRODUCT_ITEM, 'label' => __('Product Item')],
            ['value' => self::TYPE_PRODUCT_GROUP, 'label' => __('Product Group')]
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
            self::TYPE_PRODUCT => __('Product'),
            self::TYPE_PRODUCT_ITEM => __('Product Item'),
            self::TYPE_PRODUCT_GROUP => __('Product Group')
        ];
    }
}

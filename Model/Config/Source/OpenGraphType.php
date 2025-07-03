<?php
/**
 * Open Graph type source model
 *
 * @package     Defox_SEOSuite
 * @copyright   Copyright (c) 2024 Defox (https://defox.com)
 * @license     Defox Proprietary License
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Open Graph type source model
 * 
 * This class provides options for Open Graph type
 */
class OpenGraphType implements OptionSourceInterface
{
    /**
     * Open Graph types
     */
    public const TYPE_WEBSITE = 'website';
    public const TYPE_ARTICLE = 'article';
    public const TYPE_BLOG = 'blog';
    public const TYPE_BUSINESS = 'business.business';
    public const TYPE_PRODUCT = 'product';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_WEBSITE, 'label' => __('Website')],
            ['value' => self::TYPE_ARTICLE, 'label' => __('Article')],
            ['value' => self::TYPE_BLOG, 'label' => __('Blog')],
            ['value' => self::TYPE_BUSINESS, 'label' => __('Business')],
            ['value' => self::TYPE_PRODUCT, 'label' => __('Product')]
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
            self::TYPE_WEBSITE => __('Website'),
            self::TYPE_ARTICLE => __('Article'),
            self::TYPE_BLOG => __('Blog'),
            self::TYPE_BUSINESS => __('Business'),
            self::TYPE_PRODUCT => __('Product')
        ];
    }
}

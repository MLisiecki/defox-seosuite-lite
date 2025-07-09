<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Change frequency source model
 */
class Changefreq implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'always', 'label' => __('Always')],
            ['value' => 'hourly', 'label' => __('Hourly')],
            ['value' => 'daily', 'label' => __('Daily')],
            ['value' => 'weekly', 'label' => __('Weekly')],
            ['value' => 'monthly', 'label' => __('Monthly')],
            ['value' => 'yearly', 'label' => __('Yearly')],
            ['value' => 'never', 'label' => __('Never')]
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
            'always' => __('Always'),
            'hourly' => __('Hourly'),
            'daily' => __('Daily'),
            'weekly' => __('Weekly'),
            'monthly' => __('Monthly'),
            'yearly' => __('Yearly'),
            'never' => __('Never')
        ];
    }
}

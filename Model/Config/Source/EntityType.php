<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Defox\SEOSuite\Model\Template;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Entity type source model
 * 
 * This class provides options for entity types
 */
class EntityType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Template::ENTITY_TYPE_PRODUCT, 'label' => __('Product')],
            ['value' => Template::ENTITY_TYPE_CATEGORY, 'label' => __('Category')],
            ['value' => Template::ENTITY_TYPE_CMS_PAGE, 'label' => __('CMS Page')]
        ];
    }
}

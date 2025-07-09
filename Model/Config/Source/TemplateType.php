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
 * Template type source model
 * 
 * This class provides options for template types with comprehensive option
 */
class TemplateType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Template::TYPE_COMPREHENSIVE, 'label' => __('Comprehensive (All Meta Tags)')],
            ['value' => Template::TYPE_META_TITLE, 'label' => __('Meta Title Only')],
            ['value' => Template::TYPE_META_DESCRIPTION, 'label' => __('Meta Description Only')],
            ['value' => Template::TYPE_META_KEYWORDS, 'label' => __('Meta Keywords Only')],
            ['value' => Template::TYPE_META_ROBOTS, 'label' => __('Meta Robots Only')],
            ['value' => Template::TYPE_OPEN_GRAPH, 'label' => __('Open Graph Only')]
        ];
    }
}

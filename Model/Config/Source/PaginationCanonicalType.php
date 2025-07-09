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
 * Pagination canonical URL type source model
 */
class PaginationCanonicalType implements OptionSourceInterface
{
    /**
     * Canonical type constants
     */
    public const TYPE_FIRST_PAGE = 'first_page';
    public const TYPE_SELF = 'self';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_FIRST_PAGE, 'label' => __('First Page (Recommended)')],
            ['value' => self::TYPE_SELF, 'label' => __('Self (Current Page)')]
        ];
    }
}

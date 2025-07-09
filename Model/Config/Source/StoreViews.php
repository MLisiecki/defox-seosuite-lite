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
use Magento\Store\Model\System\Store;

/**
 * Store views source model
 * 
 * This class provides options for store views selection
 */
class StoreViews implements OptionSourceInterface
{
    /**
     * @var Store
     */
    protected Store $systemStore;

    /**
     * @param Store $systemStore
     */
    public function __construct(
        Store $systemStore
    ) {
        $this->systemStore = $systemStore;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, true);
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product types source model
 */
class ProductTypes implements OptionSourceInterface
{
    /**
     * @var ProductType
     */
    private ProductType $productType;

    /**
     * @param ProductType $productType
     */
    public function __construct(
        ProductType $productType
    ) {
        $this->productType = $productType;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        $productTypes = $this->productType->getTypes();
        
        foreach ($productTypes as $typeId => $type) {
            $options[] = [
                'value' => $typeId,
                'label' => $type['label']
            ];
        }
        
        return $options;
    }
}

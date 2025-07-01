<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes source model
 * 
 * Provides list of product attributes for configuration dropdowns.
 */
class ProductAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $attributeCollectionFactory;
    
    /**
     * @var array|null
     */
    private ?array $options = null;
    
    /**
     * Constructor
     *
     * @param CollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        CollectionFactory $attributeCollectionFactory
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }
    
    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [
                ['value' => '', 'label' => __('-- Please Select --')]
            ];
            
            $collection = $this->attributeCollectionFactory->create();
            $collection->addFieldToFilter('is_visible', 1);
            $collection->setOrder('frontend_label', 'ASC');
            
            foreach ($collection as $attribute) {
                $label = $attribute->getFrontendLabel();
                if (!$label) {
                    $label = $attribute->getAttributeCode();
                }
                
                $this->options[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode())
                ];
            }
        }
        
        return $this->options;
    }
}

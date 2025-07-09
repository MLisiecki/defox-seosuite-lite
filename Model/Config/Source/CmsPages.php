<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * CMS pages source model
 */
class CmsPages implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect(['page_id', 'title', 'identifier']);
        $collection->setOrder('title', 'ASC');
        
        foreach ($collection as $page) {
            $options[] = [
                'value' => $page->getIdentifier(),
                'label' => $page->getTitle() . ' (' . $page->getIdentifier() . ')'
            ];
        }
        
        return $options;
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\ResourceModel\Template;

use Defox\SEOSuite\Model\Template;
use Defox\SEOSuite\Model\ResourceModel\Template as ResourceTemplate;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Template collection
 * 
 * This class represents a collection of template models and provides methods for filtering and sorting
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'template_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'defox_seosuite_template_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'template_collection';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Template::class, ResourceTemplate::class);
    }

    /**
     * Add type filter
     *
     * @param string $type
     * @return $this
     */
    public function addTypeFilter(string $type): Collection
    {
        $this->addFieldToFilter('type', $type);
        return $this;
    }

    /**
     * Add entity type filter
     *
     * @param string $entityType
     * @return $this
     */
    public function addEntityTypeFilter(string $entityType): Collection
    {
        $this->addFieldToFilter('entity_type', $entityType);
        return $this;
    }

    /**
     * Add store filter
     *
     * @param int|array $storeId
     * @return $this
     */
    public function addStoreFilter($storeId): Collection
    {
        if (is_array($storeId)) {
            $storeId[] = 0;
            $this->addFieldToFilter('store_id', ['in' => $storeId]);
        } else {
            $this->addFieldToFilter('store_id', ['in' => [0, $storeId]]);
        }
        return $this;
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter(): Collection
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Add priority order
     *
     * @param string $dir
     * @return $this
     */
    public function addPriorityOrder(string $dir = 'DESC'): Collection
    {
        $this->setOrder('priority', $dir);
        return $this;
    }

    /**
     * Get templates by type, entity type and store id
     *
     * @param string $type
     * @param string $entityType
     * @param int $storeId
     * @return $this
     */
    public function getByTypeAndEntityType(string $type, string $entityType, int $storeId): Collection
    {
        $this->addTypeFilter($type);
        $this->addEntityTypeFilter($entityType);
        $this->addStoreFilter($storeId);
        $this->addActiveFilter();
        $this->addPriorityOrder();
        return $this;
    }
}

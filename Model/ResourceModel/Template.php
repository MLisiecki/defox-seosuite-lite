<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\ResourceModel;

use Defox\SEOSuite\Api\Data\TemplateInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Template resource model
 * 
 * This class is responsible for database operations on the templates table
 */
class Template extends AbstractDb
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        ?string $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('defox_seosuite_template', TemplateInterface::TEMPLATE_ID);
    }

    /**
     * @inheritdoc
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object): Template
    {
        // Set updated at timestamp
        $object->setUpdatedAt($this->dateTime->gmtDate());

        // Set created at timestamp if new object
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->dateTime->gmtDate());
        }

        return parent::_beforeSave($object);
    }

    /**
     * Get templates by type, entity type and store id
     *
     * @param string $type
     * @param string $entityType
     * @param int $storeId
     * @return array
     */
    public function getByTypeAndEntityType(string $type, string $entityType, int $storeId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('type = ?', $type)
            ->where('entity_type = ?', $entityType)
            ->where('store_id IN (0, ?)', $storeId)
            ->where('is_active = 1')
            ->order('priority DESC');

        return $connection->fetchAll($select);
    }
}

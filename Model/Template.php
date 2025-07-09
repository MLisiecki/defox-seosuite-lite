<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model;

use Defox\SEOSuite\Api\Data\TemplateInterface;
use Defox\SEOSuite\Model\ResourceModel\Template as ResourceTemplate;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Template model class
 * 
 * This class represents a meta tags template and provides methods to manipulate its data
 */
class Template extends AbstractModel implements TemplateInterface
{
    /**
     * Template types
     */
    public const TYPE_COMPREHENSIVE = 'comprehensive';
    public const TYPE_META_TITLE = 'meta_title';
    public const TYPE_META_DESCRIPTION = 'meta_description';
    public const TYPE_META_KEYWORDS = 'meta_keywords';
    public const TYPE_META_ROBOTS = 'meta_robots';
    public const TYPE_OPEN_GRAPH = 'open_graph';

    /**
     * Entity types
     */
    public const ENTITY_TYPE_PRODUCT = 'product';
    public const ENTITY_TYPE_CATEGORY = 'category';
    public const ENTITY_TYPE_CMS_PAGE = 'cms_page';
    
    /**
     * Status values
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * @var string
     */
    protected $_eventPrefix = 'defox_seosuite_template';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceTemplate::class);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getData(self::TEMPLATE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        return $this->setData(self::TEMPLATE_ID, $id);
    }

    /**
     * @inheritdoc
     */
    public function getTemplateId(): ?int
    {
        return $this->getData(self::TEMPLATE_ID) ? (int)$this->getData(self::TEMPLATE_ID) : null;
    }

    /**
     * @inheritdoc
     */
    public function setTemplateId(int $templateId): TemplateInterface
    {
        return $this->setData(self::TEMPLATE_ID, $templateId);
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): TemplateInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getType(): ?string
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setType(string $type): TemplateInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritdoc
     */
    public function getEntityType(): ?string
    {
        return $this->getData(self::ENTITY_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setEntityType(string $entityType): TemplateInterface
    {
        return $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): ?int
    {
        return $this->getData(self::STORE_ID) ? (int)$this->getData(self::STORE_ID) : null;
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): TemplateInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): TemplateInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    /**
     * @inheritdoc
     */
    public function setPriority(int $priority): TemplateInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritdoc
     */
    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    /**
     * @inheritdoc
     */
    public function setConditionsSerialized(string $conditionsSerialized): TemplateInterface
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditionsSerialized);
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitleTemplate(): ?string
    {
        return $this->getData(self::META_TITLE_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitleTemplate(?string $metaTitleTemplate): TemplateInterface
    {
        return $this->setData(self::META_TITLE_TEMPLATE, $metaTitleTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescriptionTemplate(): ?string
    {
        return $this->getData(self::META_DESCRIPTION_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescriptionTemplate(?string $metaDescriptionTemplate): TemplateInterface
    {
        return $this->setData(self::META_DESCRIPTION_TEMPLATE, $metaDescriptionTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeywordsTemplate(): ?string
    {
        return $this->getData(self::META_KEYWORDS_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeywordsTemplate(?string $metaKeywordsTemplate): TemplateInterface
    {
        return $this->setData(self::META_KEYWORDS_TEMPLATE, $metaKeywordsTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getMetaRobotsTemplate(): ?string
    {
        return $this->getData(self::META_ROBOTS_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setMetaRobotsTemplate(?string $metaRobotsTemplate): TemplateInterface
    {
        return $this->setData(self::META_ROBOTS_TEMPLATE, $metaRobotsTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getOgTitleTemplate(): ?string
    {
        return $this->getData(self::OG_TITLE_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setOgTitleTemplate(?string $ogTitleTemplate): TemplateInterface
    {
        return $this->setData(self::OG_TITLE_TEMPLATE, $ogTitleTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getOgDescriptionTemplate(): ?string
    {
        return $this->getData(self::OG_DESCRIPTION_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setOgDescriptionTemplate(?string $ogDescriptionTemplate): TemplateInterface
    {
        return $this->setData(self::OG_DESCRIPTION_TEMPLATE, $ogDescriptionTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getOgTypeTemplate(): ?string
    {
        return $this->getData(self::OG_TYPE_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setOgTypeTemplate(?string $ogTypeTemplate): TemplateInterface
    {
        return $this->setData(self::OG_TYPE_TEMPLATE, $ogTypeTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getOgImageTemplate(): ?string
    {
        return $this->getData(self::OG_IMAGE_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setOgImageTemplate(?string $ogImageTemplate): TemplateInterface
    {
        return $this->setData(self::OG_IMAGE_TEMPLATE, $ogImageTemplate);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(string $createdAt): TemplateInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(string $updatedAt): TemplateInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}

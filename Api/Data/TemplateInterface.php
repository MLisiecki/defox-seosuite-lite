<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Api\Data;

/**
 * Template interface
 */
interface TemplateInterface
{
    /**
     * Constants for keys of data array
     */
    public const TEMPLATE_ID = 'template_id';
    public const NAME = 'name';
    public const TYPE = 'type';
    public const ENTITY_TYPE = 'entity_type';
    public const STORE_ID = 'store_id';
    public const IS_ACTIVE = 'is_active';
    public const PRIORITY = 'priority';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const META_TITLE_TEMPLATE = 'meta_title_template';
    public const META_DESCRIPTION_TEMPLATE = 'meta_description_template';
    public const META_KEYWORDS_TEMPLATE = 'meta_keywords_template';
    public const META_ROBOTS_TEMPLATE = 'meta_robots_template';
    public const OG_TITLE_TEMPLATE = 'og_title_template';
    public const OG_DESCRIPTION_TEMPLATE = 'og_description_template';
    public const OG_TYPE_TEMPLATE = 'og_type_template';
    public const OG_IMAGE_TEMPLATE = 'og_image_template';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID
     *
     * @param int $id
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setId($id);

    /**
     * Get template id
     *
     * @return int|null
     */
    public function getTemplateId(): ?int;

    /**
     * Set template id
     *
     * @param int $templateId
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setTemplateId(int $templateId): TemplateInterface;

    /**
     * Get name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set name
     *
     * @param string $name
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setName(string $name): TemplateInterface;

    /**
     * Get type
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Set type
     *
     * @param string $type
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setType(string $type): TemplateInterface;

    /**
     * Get entity type
     *
     * @return string|null
     */
    public function getEntityType(): ?string;

    /**
     * Set entity type
     *
     * @param string $entityType
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setEntityType(string $entityType): TemplateInterface;

    /**
     * Get store id
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Set store id
     *
     * @param int $storeId
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setStoreId(int $storeId): TemplateInterface;

    /**
     * Get is active
     *
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Set is active
     *
     * @param bool $isActive
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setIsActive(bool $isActive): TemplateInterface;

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set priority
     *
     * @param int $priority
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setPriority(int $priority): TemplateInterface;

    /**
     * Get conditions serialized
     *
     * @return string|null
     */
    public function getConditionsSerialized(): ?string;

    /**
     * Set conditions serialized
     *
     * @param string $conditionsSerialized
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setConditionsSerialized(string $conditionsSerialized): TemplateInterface;

    /**
     * Get meta title template
     *
     * @return string|null
     */
    public function getMetaTitleTemplate(): ?string;

    /**
     * Set meta title template
     *
     * @param string|null $metaTitleTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setMetaTitleTemplate(?string $metaTitleTemplate): TemplateInterface;

    /**
     * Get meta description template
     *
     * @return string|null
     */
    public function getMetaDescriptionTemplate(): ?string;

    /**
     * Set meta description template
     *
     * @param string|null $metaDescriptionTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setMetaDescriptionTemplate(?string $metaDescriptionTemplate): TemplateInterface;

    /**
     * Get meta keywords template
     *
     * @return string|null
     */
    public function getMetaKeywordsTemplate(): ?string;

    /**
     * Set meta keywords template
     *
     * @param string|null $metaKeywordsTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setMetaKeywordsTemplate(?string $metaKeywordsTemplate): TemplateInterface;

    /**
     * Get meta robots template
     *
     * @return string|null
     */
    public function getMetaRobotsTemplate(): ?string;

    /**
     * Set meta robots template
     *
     * @param string|null $metaRobotsTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setMetaRobotsTemplate(?string $metaRobotsTemplate): TemplateInterface;

    /**
     * Get Open Graph title template
     *
     * @return string|null
     */
    public function getOgTitleTemplate(): ?string;

    /**
     * Set Open Graph title template
     *
     * @param string|null $ogTitleTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setOgTitleTemplate(?string $ogTitleTemplate): TemplateInterface;

    /**
     * Get Open Graph description template
     *
     * @return string|null
     */
    public function getOgDescriptionTemplate(): ?string;

    /**
     * Set Open Graph description template
     *
     * @param string|null $ogDescriptionTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setOgDescriptionTemplate(?string $ogDescriptionTemplate): TemplateInterface;

    /**
     * Get Open Graph type template
     *
     * @return string|null
     */
    public function getOgTypeTemplate(): ?string;

    /**
     * Set Open Graph type template
     *
     * @param string|null $ogTypeTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setOgTypeTemplate(?string $ogTypeTemplate): TemplateInterface;

    /**
     * Get Open Graph image template
     *
     * @return string|null
     */
    public function getOgImageTemplate(): ?string;

    /**
     * Set Open Graph image template
     *
     * @param string|null $ogImageTemplate
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setOgImageTemplate(?string $ogImageTemplate): TemplateInterface;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setCreatedAt(string $createdAt): TemplateInterface;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     */
    public function setUpdatedAt(string $updatedAt): TemplateInterface;
}

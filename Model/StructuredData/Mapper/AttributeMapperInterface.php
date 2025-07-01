<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Mapper;

/**
 * Interface for attribute mapping
 * 
 * This interface defines the contract for mapping Magento attributes to schema.org fields.
 */
interface AttributeMapperInterface
{
    /**
     * Get attribute mappings for entity type
     *
     * @param string $entityType Entity type (product, category, etc.)
     * @return array Associative array of schema field => magento attribute
     */
    public function getMappings(string $entityType): array;
    
    /**
     * Get mapped value for attribute
     *
     * @param string $entityType Entity type
     * @param string $schemaField Schema.org field name
     * @param mixed $entity Entity object
     * @return mixed|null
     */
    public function getMappedValue(string $entityType, string $schemaField, $entity);
    
    /**
     * Add custom mapping
     *
     * @param string $entityType Entity type
     * @param string $schemaField Schema.org field name
     * @param string $magentoAttribute Magento attribute code
     * @return void
     */
    public function addMapping(string $entityType, string $schemaField, string $magentoAttribute): void;
    
    /**
     * Check if mapping exists
     *
     * @param string $entityType Entity type
     * @param string $schemaField Schema.org field name
     * @return bool
     */
    public function hasMapping(string $entityType, string $schemaField): bool;
}

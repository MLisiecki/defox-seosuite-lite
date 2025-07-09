<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData;

/**
 * Interface for structured data generators
 * 
 * This interface defines the contract for all structured data generators in the system.
 * Each generator is responsible for creating schema.org compliant structured data
 * for a specific entity type (product, category, organization, etc.).
 */
interface GeneratorInterface
{
    /**
     * Generate structured data for given entity
     *
     * @param mixed $entity Entity to generate structured data for
     * @param array $context Additional context data
     * @return array Structured data array ready for JSON-LD encoding
     */
    public function generate($entity, array $context = []): array;
    
    /**
     * Check if generator can handle given entity
     *
     * @param mixed $entity Entity to check
     * @return bool
     */
    public function canHandle($entity): bool;
    
    /**
     * Get schema type handled by this generator
     *
     * @return string Schema.org type (e.g., 'Product', 'Organization', 'WebSite')
     */
    public function getSchemaType(): string;
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId Store ID
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;
}

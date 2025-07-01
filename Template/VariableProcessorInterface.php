<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Template;

/**
 * Variable processor interface
 * 
 * Interface for processing variables in templates based on entity type
 */
interface VariableProcessorInterface
{
    /**
     * Check if processor can process given entity
     *
     * @param mixed $entity
     * @return bool
     */
    public function canProcess($entity): bool;
    
    /**
     * Process template with variables based on entity
     *
     * @param string $template
     * @param mixed $entity
     * @param array $additionalVars
     * @return string
     */
    public function process(string $template, $entity, array $additionalVars = []): string;
    
    /**
     * Get available variables for entity type
     *
     * @return array
     */
    public function getAvailableVariables(): array;
}

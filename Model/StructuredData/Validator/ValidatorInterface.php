<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Validator;

/**
 * Interface for structured data validators
 * 
 * This interface defines the contract for validating structured data.
 */
interface ValidatorInterface
{
    /**
     * Validate structured data
     *
     * @param array $data Structured data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $data): bool;
    
    /**
     * Get validation errors
     *
     * @return array Array of error messages
     */
    public function getErrors(): array;
    
    /**
     * Get validation warnings
     *
     * @return array Array of warning messages
     */
    public function getWarnings(): array;
    
    /**
     * Clear errors and warnings
     *
     * @return void
     */
    public function clear(): void;
}

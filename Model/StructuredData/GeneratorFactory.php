<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData;

use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating structured data generators
 * 
 * This factory creates instances of structured data generators based on the schema type.
 */
class GeneratorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * @var array
     */
    private array $generators;
    
    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param LoggerInterface $logger
     * @param array $generators
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger,
        array $generators = []
    ) {
        $this->objectManager = $objectManager;
        $this->logger = $logger;
        $this->generators = $generators;
    }
    
    /**
     * Create generator instance by schema type
     *
     * @param string $schemaType
     * @return GeneratorInterface|null
     */
    public function create(string $schemaType): ?GeneratorInterface
    {
        if (!isset($this->generators[$schemaType])) {
            $this->logger->warning(
                sprintf('No generator found for schema type: %s', $schemaType)
            );
            return null;
        }
        
        $generatorClass = $this->generators[$schemaType];
        
        try {
            $generator = $this->objectManager->create($generatorClass);
            
            if (!$generator instanceof GeneratorInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Generator class %s must implement %s',
                        $generatorClass,
                        GeneratorInterface::class
                    )
                );
            }
            
            return $generator;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error creating generator for schema type %s: %s', $schemaType, $e->getMessage()),
                ['exception' => $e]
            );
            return null;
        }
    }
    
    /**
     * Get all available schema types
     *
     * @return array
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->generators);
    }
    
    /**
     * Check if generator exists for schema type
     *
     * @param string $schemaType
     * @return bool
     */
    public function hasGenerator(string $schemaType): bool
    {
        return isset($this->generators[$schemaType]);
    }
}

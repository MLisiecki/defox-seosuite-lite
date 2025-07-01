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
 * Pool of structured data generators
 * 
 * This class manages all available structured data generators and provides
 * methods to generate structured data for different entity types.
 */
class GeneratorPool
{
    /**
     * @var GeneratorInterface[]
     */
    private array $generators;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;
    
    /**
     * @var array
     */
    private array $generatorInstances = [];
    
    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     * @param array $generators
     */
    public function __construct(
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        array $generators = []
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->generators = $generators;
    }
    
    /**
     * Generate structured data for entity
     *
     * @param mixed $entity
     * @param array $context
     * @return array Array of structured data from all applicable generators
     */
    public function generate($entity, array $context = []): array
    {
        $structuredData = [];
        
        foreach ($this->generators as $generatorClass) {
            try {
                $generator = $this->getGeneratorInstance($generatorClass);
                if ($generator && $generator->canHandle($entity) && $generator->isEnabled()) {
                    $data = $generator->generate($entity, $context);
                    if (!empty($data)) {
                        $structuredData[] = $data;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Error generating structured data with %s: %s',
                        is_string($generatorClass) ? $generatorClass : get_class($generatorClass),
                        $e->getMessage()
                    ),
                    ['exception' => $e]
                );
            }
        }
        
        return $structuredData;
    }
    
    /**
     * Get generator by schema type
     *
     * @param string $schemaType
     * @return GeneratorInterface|null
     */
    public function getGenerator(string $schemaType): ?GeneratorInterface
    {
        foreach ($this->generators as $generatorClass) {
            $generator = $this->getGeneratorInstance($generatorClass);
            if ($generator && $generator->getSchemaType() === $schemaType) {
                return $generator;
            }
        }
        
        return null;
    }
    
    /**
     * Get generator instance (lazy loading)
     *
     * @param string|GeneratorInterface $generatorClass
     * @return GeneratorInterface|null
     */
    private function getGeneratorInstance($generatorClass): ?GeneratorInterface
    {
        if ($generatorClass instanceof GeneratorInterface) {
            return $generatorClass;
        }
        
        if (!is_string($generatorClass)) {
            return null;
        }
        
        if (!isset($this->generatorInstances[$generatorClass])) {
            try {
                $instance = $this->objectManager->create($generatorClass);
                if ($instance instanceof GeneratorInterface) {
                    $this->generatorInstances[$generatorClass] = $instance;
                } else {
                    $this->logger->error('Generator class does not implement GeneratorInterface: ' . $generatorClass);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logger->error('Error creating generator instance: ' . $generatorClass . ' - ' . $e->getMessage());
                return null;
            }
        }
        
        return $this->generatorInstances[$generatorClass];
    }
    
    /**
     * Get all available generators
     *
     * @return GeneratorInterface[]
     */
    public function getGenerators(): array
    {
        $instances = [];
        foreach ($this->generators as $generatorClass) {
            $generator = $this->getGeneratorInstance($generatorClass);
            if ($generator) {
                $instances[] = $generator;
            }
        }
        return $instances;
    }
    
    /**
     * Get enabled generators
     *
     * @param int|null $storeId
     * @return GeneratorInterface[]
     */
    public function getEnabledGenerators(?int $storeId = null): array
    {
        return array_filter(
            $this->getGenerators(),
            function (GeneratorInterface $generator) use ($storeId) {
                return $generator->isEnabled($storeId);
            }
        );
    }
    
    /**
     * Add generator to pool
     *
     * @param GeneratorInterface $generator
     * @return void
     */
    public function addGenerator(GeneratorInterface $generator): void
    {
        $this->generators[] = $generator;
    }
}

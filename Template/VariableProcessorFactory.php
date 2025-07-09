<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Template;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Variable processor factory
 * 
 * Factory for creating variable processors based on entity type
 */
class VariableProcessorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    private array $processors = [];

    /**
     * @var array
     */
    private array $processors_instances = [];

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array $processors
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $processors = []
    ) {
        $this->objectManager = $objectManager;
        $this->processors = $processors;
    }

    /**
     * Create variable processor for entity or entity type
     *
     * @param mixed $entity
     * @return VariableProcessorInterface
     * @throws LocalizedException
     */
    public function create($entity): VariableProcessorInterface
    {
        // If entity is a string, treat it as entity type
        if (is_string($entity)) {
            return $this->createByEntityType($entity);
        }
        
        // Test each processor to see if it can handle this entity
        foreach ($this->processors as $entityType => $processorClass) {
            try {
                if (!isset($this->processors_instances[$processorClass])) {
                    $processorInstance = $this->objectManager->create($processorClass);
                    
                    if (!$processorInstance instanceof VariableProcessorInterface) {
                        throw new LocalizedException(
                            __('Processor class %1 must implement %2', $processorClass, VariableProcessorInterface::class)
                        );
                    }
                    
                    $this->processors_instances[$processorClass] = $processorInstance;
                }
                
                $processor = $this->processors_instances[$processorClass];
                
                if ($processor->canProcess($entity)) {
                    return $processor;
                }
            } catch (\Exception $e) {
                // Log error and continue to next processor
                continue;
            }
        }
        
        throw new LocalizedException(__('No variable processor found for entity: %1', get_class($entity)));
    }

    /**
     * Create variable processor by entity type
     *
     * @param string $entityType
     * @return VariableProcessorInterface
     * @throws LocalizedException
     */
    public function createByEntityType(string $entityType): VariableProcessorInterface
    {
        if (!isset($this->processors[$entityType])) {
            throw new LocalizedException(__('No variable processor found for entity type: %1', $entityType));
        }
        
        $processorClass = $this->processors[$entityType];
        
        if (!isset($this->processors_instances[$processorClass])) {
            $processorInstance = $this->objectManager->create($processorClass);
            
            if (!$processorInstance instanceof VariableProcessorInterface) {
                throw new LocalizedException(
                    __('Processor class %1 must implement %2', $processorClass, VariableProcessorInterface::class)
                );
            }
            
            $this->processors_instances[$processorClass] = $processorInstance;
        }
        
        return $this->processors_instances[$processorClass];
    }

    /**
     * Get all available variable processors
     *
     * @return VariableProcessorInterface[]
     */
    public function getAllProcessors(): array
    {
        $instances = [];
        
        foreach ($this->processors as $entityType => $processorClass) {
            if (!isset($this->processors_instances[$processorClass])) {
                $processorInstance = $this->objectManager->create($processorClass);
                
                if (!$processorInstance instanceof VariableProcessorInterface) {
                    continue;
                }
                
                $this->processors_instances[$processorClass] = $processorInstance;
            }
            
            $instances[] = $this->processors_instances[$processorClass];
        }
        
        return $instances;
    }

    /**
     * Get all available variables
     *
     * @return array
     */
    public function getAllAvailableVariables(): array
    {
        $variables = [];
        
        foreach ($this->getAllProcessors() as $processor) {
            $variables = array_merge($variables, $processor->getAvailableVariables());
        }
        
        return $variables;
    }
}

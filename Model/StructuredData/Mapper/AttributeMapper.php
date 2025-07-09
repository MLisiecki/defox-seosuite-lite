<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Mapper;

use Defox\SEOSuite\Helper\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Swatches\Model\Swatch;
use Psr\Log\LoggerInterface;

/**
 * Attribute mapper implementation
 * 
 * Maps Magento attributes to schema.org fields based on configuration.
 * Handles different attribute types including swatches, select, multiselect, text, etc.
 */
class AttributeMapper implements AttributeMapperInterface
{
    /**
     * @var Config
     */
    private Config $configHelper;
    
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * @var EavConfig
     */
    private EavConfig $eavConfig;
    
    /**
     * @var SwatchHelper
     */
    private SwatchHelper $swatchHelper;
    
    /**
     * @var array
     */
    private array $customMappings = [];
    
    /**
     * @var array Cache for attribute objects
     */
    private array $attributeCache = [];
    
    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param EavConfig $eavConfig
     * @param SwatchHelper $swatchHelper
     */
    public function __construct(
        Config $configHelper,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        EavConfig $eavConfig,
        SwatchHelper $swatchHelper
    ) {
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->eavConfig = $eavConfig;
        $this->swatchHelper = $swatchHelper;
    }
    
    /**
     * Get attribute mappings for entity type
     *
     * @param string $entityType
     * @return array
     */
    public function getMappings(string $entityType): array
    {
        // Get custom mappings first
        if (isset($this->customMappings[$entityType])) {
            return $this->customMappings[$entityType];
        }
        
        // Get mappings from configuration
        $configMappings = $this->configHelper->getStructuredDataAttributeMappings($entityType);
        
        if (is_string($configMappings) && !empty($configMappings)) {
            try {
                $mappings = $this->serializer->unserialize($configMappings);
                if (is_array($mappings)) {
                    return $mappings;
                }
            } catch (\Exception $e) {
                $this->logger->error('Error unserializing attribute mappings: ' . $e->getMessage());
            }
        }
        
        // Return default mappings based on entity type
        return $this->getDefaultMappings($entityType);
    }
    
    /**
     * Get mapped value for attribute
     *
     * @param string $entityType
     * @param string $schemaField
     * @param mixed $entity
     * @return mixed|null
     */
    public function getMappedValue(string $entityType, string $schemaField, $entity)
    {
        $mappings = $this->getMappings($entityType);
        
        if (!isset($mappings[$schemaField])) {
            return null;
        }
        
        $attributeCode = $mappings[$schemaField];
        
        if ($schemaField === 'color') {
            $this->logger->info("Processing color attribute: {$attributeCode}");
        }
        
        try {
            // Check if entity has the method to get data
            if (!method_exists($entity, 'getData')) {
                return null;
            }
            
            // Get raw attribute value
            $value = $entity->getData($attributeCode);
            
            if ($schemaField === 'color') {
                $this->logger->info("Raw color value: " . json_encode($value));
            }
            
            // If no value, return null early
            if ($value === null || $value === '' || $value === false) {
                return null;
            }
            
            // Get attribute object for proper processing
            $attribute = $this->getAttributeObject($entityType, $attributeCode);
            
            if ($schemaField === 'color') {
                $this->logger->info("Color attribute object: " . ($attribute ? $attribute->getFrontendInput() : 'null'));
            }
            
            if ($attribute) {
                // Process value based on attribute type
                $processedValue = $this->processAttributeValue($entity, $attribute, $value);
                if ($processedValue !== null) {
                    if ($schemaField === 'color') {
                        $this->logger->info("Processed color value: " . json_encode($processedValue));
                    }
                    return $processedValue;
                }
            }
            
            // Fallback: try to get attribute text for select/multiselect attributes
            if (method_exists($entity, 'getAttributeText')) {
                $textValue = $entity->getAttributeText($attributeCode);
                if ($schemaField === 'color') {
                    $this->logger->info("Color text value: " . json_encode($textValue));
                }
                if ($textValue !== false && $textValue !== null && $textValue !== '') {
                    return $textValue;
                }
            }
            
            // Return raw value as last resort
            return $value;
            
        } catch (\Exception $e) {
            $this->logger->debug(
                sprintf(
                    'Error getting mapped value for %s.%s (attribute: %s): %s',
                    $entityType,
                    $schemaField,
                    $attributeCode ?? 'unknown',
                    $e->getMessage()
                )
            );
            
            // Return a meaningful error indicator for debugging
            return "Error_" . $schemaField;
        }
    }
    
    /**
     * Add custom mapping
     *
     * @param string $entityType
     * @param string $schemaField
     * @param string $magentoAttribute
     * @return void
     */
    public function addMapping(string $entityType, string $schemaField, string $magentoAttribute): void
    {
        if (!isset($this->customMappings[$entityType])) {
            $this->customMappings[$entityType] = [];
        }
        
        $this->customMappings[$entityType][$schemaField] = $magentoAttribute;
    }
    
    /**
     * Check if mapping exists
     *
     * @param string $entityType
     * @param string $schemaField
     * @return bool
     */
    public function hasMapping(string $entityType, string $schemaField): bool
    {
        $mappings = $this->getMappings($entityType);
        return isset($mappings[$schemaField]);
    }
    
    /**
     * Get attribute object
     *
     * @param string $entityType
     * @param string $attributeCode
     * @return Attribute|null
     */
    private function getAttributeObject(string $entityType, string $attributeCode): ?Attribute
    {
        $cacheKey = $entityType . '_' . $attributeCode;
        
        if (isset($this->attributeCache[$cacheKey])) {
            return $this->attributeCache[$cacheKey];
        }
        
        try {
            $entityTypeCode = $this->getEntityTypeCode($entityType);
            if (!$entityTypeCode) {
                return null;
            }
            
            /** @var Attribute $attribute */
            $attribute = $this->eavConfig->getAttribute($entityTypeCode, $attributeCode);
            
            if (!$attribute || !$attribute->getAttributeId()) {
                $this->attributeCache[$cacheKey] = null;
                return null;
            }
            
            $this->attributeCache[$cacheKey] = $attribute;
            return $attribute;
            
        } catch (\Exception $e) {
            $this->logger->debug("Error loading attribute {$entityType}.{$attributeCode}: " . $e->getMessage());
            $this->attributeCache[$cacheKey] = null;
            return null;
        }
    }
    
    /**
     * Process attribute value based on its type
     *
     * @param mixed $entity
     * @param Attribute $attribute
     * @param mixed $value
     * @return mixed|null
     */
    private function processAttributeValue($entity, Attribute $attribute, $value)
    {
        $frontendInput = $attribute->getFrontendInput();
        $attributeCode = $attribute->getAttributeCode();
        
        try {
            switch ($frontendInput) {
                case 'select':
                    return $this->processSelectAttribute($entity, $attribute, $value);
                    
                case 'multiselect':
                    return $this->processMultiselectAttribute($entity, $attribute, $value);
                    
                case 'swatch_visual':
                case 'swatch_text':
                    return $this->processSwatchAttribute($entity, $attribute, $value);
                    
                case 'boolean':
                    return $this->processBooleanAttribute($value);
                    
                case 'date':
                case 'datetime':
                    return $this->processDateAttribute($value);
                    
                case 'text':
                case 'textarea':
                default:
                    return $this->processTextAttribute($value);
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                "Error processing {$frontendInput} attribute {$attributeCode}: " . $e->getMessage()
            );
            return null;
        }
    }
    
    /**
     * Process select attribute
     *
     * @param mixed $entity
     * @param Attribute $attribute
     * @param mixed $value
     * @return string|null
     */
    private function processSelectAttribute($entity, Attribute $attribute, $value): ?string
    {
        if (method_exists($entity, 'getAttributeText')) {
            $textValue = $entity->getAttributeText($attribute->getAttributeCode());
            if ($textValue !== false && $textValue !== null && $textValue !== '') {
                return is_string($textValue) ? $textValue : (string)$textValue;
            }
        }
        
        // Fallback to option text from attribute
        try {
            $source = $attribute->getSource();
            if ($source) {
                $options = $source->getAllOptions();
                foreach ($options as $option) {
                    if ($option['value'] == $value && !empty($option['label'])) {
                        return (string)$option['label'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error getting attribute source options: ' . $e->getMessage());
        }
        
        return is_string($value) ? $value : (string)$value;
    }
    
    /**
     * Process multiselect attribute
     *
     * @param mixed $entity
     * @param Attribute $attribute
     * @param mixed $value
     * @return array|null
     */
    private function processMultiselectAttribute($entity, Attribute $attribute, $value): ?array
    {
        if (method_exists($entity, 'getAttributeText')) {
            $textValue = $entity->getAttributeText($attribute->getAttributeCode());
            if ($textValue !== false && $textValue !== null) {
                if (is_array($textValue)) {
                    return $textValue;
                }
                if (is_string($textValue)) {
                    return explode(',', $textValue);
                }
            }
        }
        
        // Process comma-separated values
        if (is_string($value)) {
            $valueIds = explode(',', $value);
            $labels = [];
            
            try {
                $source = $attribute->getSource();
                if ($source) {
                    $options = $source->getAllOptions();
                    
                    foreach ($valueIds as $valueId) {
                        foreach ($options as $option) {
                            if ($option['value'] == trim($valueId) && !empty($option['label'])) {
                                $labels[] = (string)$option['label'];
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug('Error getting multiselect attribute options: ' . $e->getMessage());
            }
            
            return !empty($labels) ? $labels : null;
        }
        
        return is_array($value) ? $value : null;
    }
    
    /**
     * Process swatch attribute (visual or text swatch)
     *
     * @param mixed $entity
     * @param Attribute $attribute
     * @param mixed $value
     * @return string|null
     */
    private function processSwatchAttribute($entity, Attribute $attribute, $value): ?string
    {
        try {
            // For products, try to get swatch data
            if ($entity instanceof Product) {
                $swatchData = $this->swatchHelper->getSwatchAttributeImage(
                    $attribute->getAttributeCode(),
                    $value
                );
                
                if ($swatchData && isset($swatchData['value'])) {
                    return (string)$swatchData['value'];
                }
            }
            
            // Try to get swatch collection for the attribute
            $swatches = $this->swatchHelper->getSwatchesByOptionsId([$value]);
            if (isset($swatches[$value])) {
                $swatch = $swatches[$value];
                if ($swatch['type'] == Swatch::SWATCH_TYPE_TEXTUAL && !empty($swatch['value'])) {
                    return (string)$swatch['value'];
                }
            }
            
            // Fallback to regular select processing
            return $this->processSelectAttribute($entity, $attribute, $value);
            
        } catch (\Exception $e) {
            $this->logger->debug(
                "Error processing swatch attribute {$attribute->getAttributeCode()}: " . $e->getMessage()
            );
            
            // Fallback to regular select processing
            return $this->processSelectAttribute($entity, $attribute, $value);
        }
    }
    
    /**
     * Process boolean attribute
     *
     * @param mixed $value
     * @return bool|null
     */
    private function processBooleanAttribute($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool)(int)$value;
        }
        
        if (is_string($value)) {
            $lowerValue = strtolower($value);
            if (in_array($lowerValue, ['yes', 'true', '1', 'on', 'enabled'])) {
                return true;
            }
            if (in_array($lowerValue, ['no', 'false', '0', 'off', 'disabled'])) {
                return false;
            }
        }
        
        return null;
    }
    
    /**
     * Process date attribute
     *
     * @param mixed $value
     * @return string|null
     */
    private function processDateAttribute($value): ?string
    {
        if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return is_string($value) ? $value : null;
        }
    }
    
    /**
     * Process text attribute
     *
     * @param mixed $value
     * @return string|null
     */
    private function processTextAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Handle arrays (like category_ids) - don't process them as text
        if (is_array($value)) {
            return null;
        }
        
        $stringValue = is_string($value) ? $value : (string)$value;
        $cleanValue = trim(strip_tags($stringValue));
        
        return $cleanValue !== '' ? $cleanValue : null;
    }
    
    /**
     * Get entity type code for attribute repository
     *
     * @param string $entityType
     * @return string|null
     */
    private function getEntityTypeCode(string $entityType): ?string
    {
        $mapping = [
            'product' => \Magento\Catalog\Model\Product::ENTITY,
            'category' => \Magento\Catalog\Model\Category::ENTITY,
        ];
        
        return $mapping[$entityType] ?? null;
    }
    
    /**
     * Get default mappings for entity type
     *
     * @param string $entityType
     * @return array
     */
    private function getDefaultMappings(string $entityType): array
    {
        switch ($entityType) {
            case 'product':
                return [
                    'color' => 'color',
                    'material' => 'material',
                    'size' => 'size',
                    'weight' => 'weight',
                    'width' => 'width',
                    'height' => 'height',
                    'depth' => 'depth',
                    'manufacturer' => 'manufacturer',
                    'countryOfOrigin' => 'country_of_manufacture',
                    'releaseDate' => 'news_from_date',
                    // Removed 'productionDate' => 'created_at' as it's not standard Schema.org field
                    'award' => 'award',
                    'category' => 'category_ids',
                    'keywords' => 'meta_keyword'
                ];
                
            case 'category':
                return [
                    'keywords' => 'meta_keywords',
                    'alternateName' => 'url_key'
                ];
                
            default:
                return [];
        }
    }
}

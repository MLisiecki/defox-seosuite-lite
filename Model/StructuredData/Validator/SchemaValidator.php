<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Validator;

use Psr\Log\LoggerInterface;

/**
 * Schema.org validator for structured data
 * 
 * Validates structured data against schema.org specifications.
 */
class SchemaValidator implements ValidatorInterface
{
    /**
     * @var array
     */
    private array $errors = [];
    
    /**
     * @var array
     */
    private array $warnings = [];
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * Required fields for different schema types
     * @var array
     */
    private array $requiredFields = [
        'Product' => ['name', 'offers'],
        'Organization' => ['name'],
        'WebSite' => ['name', 'url'],
        'CollectionPage' => ['name'],
        'WebPage' => ['name'],
        'Article' => ['headline', 'author'],
        'Review' => ['author', 'reviewRating'],
        'Offer' => ['price', 'priceCurrency'],
        'AggregateRating' => ['ratingValue', 'reviewCount'],
        'Rating' => ['ratingValue']
    ];
    
    /**
     * Recommended fields for different schema types
     * @var array
     */
    private array $recommendedFields = [
        'Product' => ['description', 'image', 'sku', 'brand'],
        'Organization' => ['url', 'logo', 'contactPoint'],
        'WebSite' => ['potentialAction'],
        'Offer' => ['availability', 'url'],
        'Review' => ['reviewBody', 'datePublished']
    ];
    
    /**
     * Valid schema.org types
     * @var array
     */
    private array $validTypes = [
        'Product', 'Organization', 'WebSite', 'WebPage', 'CollectionPage',
        'Article', 'BlogPosting', 'Review', 'Offer', 'AggregateRating',
        'Rating', 'Brand', 'PostalAddress', 'ContactPoint', 'SearchAction',
        'EntryPoint', 'Person', 'ListItem', 'BreadcrumbList', 'ItemList',
        'PropertyValue', 'Corporation', 'LocalBusiness', 'Store', 'OnlineStore',
        'Thing' // Base type for all schema.org entities
    ];
    
    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }
    
    /**
     * Validate structured data
     *
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool
    {
        $this->clear();
        
        if (empty($data)) {
            $this->errors[] = 'Structured data is empty';
            return false;
        }
        
        // Check if data is a single item or multiple items
        if (isset($data[0]) || isset($data['@graph'])) {
            // Multiple items
            $items = isset($data['@graph']) ? $data['@graph'] : $data;
            foreach ($items as $index => $item) {
                $this->validateItem($item, "Item $index");
            }
        } else {
            // Single item
            $this->validateItem($data);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate single item
     *
     * @param array $item
     * @param string $context
     * @return void
     */
    private function validateItem(array $item, string $context = ''): void
    {
        $prefix = $context ? "$context: " : '';
        
        // Check for @type
        if (!isset($item['@type'])) {
            $this->errors[] = $prefix . 'Missing required field @type';
            return;
        }
        
        $type = $item['@type'];
        
        // Validate type
        if (!in_array($type, $this->validTypes)) {
            $this->warnings[] = $prefix . "Unknown schema type: $type";
        }
        
        // Check required fields
        if (isset($this->requiredFields[$type])) {
            foreach ($this->requiredFields[$type] as $field) {
                if (!isset($item[$field]) || $this->isEmpty($item[$field])) {
                    $this->errors[] = $prefix . "Missing required field '$field' for type $type";
                }
            }
        }
        
        // Check recommended fields
        if (isset($this->recommendedFields[$type])) {
            foreach ($this->recommendedFields[$type] as $field) {
                if (!isset($item[$field]) || $this->isEmpty($item[$field])) {
                    $this->warnings[] = $prefix . "Missing recommended field '$field' for type $type";
                }
            }
        }
        
        // Validate specific types
        switch ($type) {
            case 'Product':
                $this->validateProduct($item, $prefix);
                break;
            case 'Offer':
                $this->validateOffer($item, $prefix);
                break;
            case 'Organization':
                $this->validateOrganization($item, $prefix);
                break;
            case 'Review':
                $this->validateReview($item, $prefix);
                break;
            case 'AggregateRating':
                $this->validateAggregateRating($item, $prefix);
                break;
        }
        
        // Validate nested objects
        foreach ($item as $key => $value) {
            // Skip if key is not a string (to avoid string offset errors)
            if (!is_string($key)) {
                continue;
            }
            
            if (is_array($value) && isset($value['@type'])) {
                $this->validateItem($value, $prefix . $key);
            } elseif (is_array($value) && isset($value[0]) && is_array($value[0])) {
                foreach ($value as $index => $subItem) {
                    if (is_array($subItem) && isset($subItem['@type'])) {
                        $this->validateItem($subItem, $prefix . "{$key}[{$index}]");
                    }
                }
            }
        }
    }
    
    /**
     * Validate Product schema
     *
     * @param array $item
     * @param string $prefix
     * @return void
     */
    private function validateProduct(array $item, string $prefix): void
    {
        // Validate offers
        if (isset($item['offers'])) {
            if (!is_array($item['offers'])) {
                $this->errors[] = $prefix . 'Offers must be an object or array';
            }
        }
        
        // Validate images
        if (isset($item['image'])) {
            if (is_array($item['image'])) {
                foreach ($item['image'] as $index => $image) {
                    if (!empty($image) && !$this->isValidUrl((string)$image)) {
                        $this->warnings[] = $prefix . "Invalid image URL at index $index: $image";
                    }
                }
            } elseif (!empty($item['image']) && !$this->isValidUrl((string)$item['image'])) {
                $this->warnings[] = $prefix . "Invalid image URL: {$item['image']}";
            }
        }
        
        // Validate SKU
        if (isset($item['sku']) && empty(trim((string)$item['sku']))) {
            $this->warnings[] = $prefix . 'SKU should not be empty';
        }
    }
    
    /**
     * Validate Offer schema
     *
     * @param array $item
     * @param string $prefix
     * @return void
     */
    private function validateOffer(array $item, string $prefix): void
    {
        // Validate price
        if (isset($item['price'])) {
            if (!is_numeric($item['price']) && !is_string($item['price'])) {
                $this->errors[] = $prefix . 'Price must be a number or numeric string';
            } elseif ((float)$item['price'] < 0) {
                $this->warnings[] = $prefix . 'Price should not be negative';
            }
        }
        
        // Validate price currency
        if (isset($item['priceCurrency'])) {
            $currency = (string)$item['priceCurrency'];
            if (empty($currency) || !preg_match('/^[A-Z]{3}$/', $currency)) {
                $this->warnings[] = $prefix . 'Price currency should be a 3-letter ISO 4217 code';
            }
        }
        
        // Validate availability
        if (isset($item['availability'])) {
            $validAvailability = [
                'https://schema.org/InStock',
                'https://schema.org/OutOfStock',
                'https://schema.org/PreOrder',
                'https://schema.org/BackOrder',
                'https://schema.org/SoldOut',
                'https://schema.org/OnlineOnly',
                'https://schema.org/LimitedAvailability',
                'https://schema.org/Discontinued'
            ];
            
            if (!in_array($item['availability'], $validAvailability)) {
                $this->warnings[] = $prefix . 'Invalid availability value';
            }
        }
        
        // Validate URL
        if (isset($item['url']) && !empty($item['url']) && !$this->isValidUrl((string)$item['url'])) {
            $this->warnings[] = $prefix . 'Invalid offer URL';
        }
    }
    
    /**
     * Validate Organization schema
     *
     * @param array $item
     * @param string $prefix
     * @return void
     */
    private function validateOrganization(array $item, string $prefix): void
    {
        // Validate logo
        if (isset($item['logo']) && !empty($item['logo']) && !$this->isValidUrl((string)$item['logo'])) {
            $this->warnings[] = $prefix . 'Invalid logo URL';
        }
        
        // Validate URL
        if (isset($item['url']) && !empty($item['url']) && !$this->isValidUrl((string)$item['url'])) {
            $this->warnings[] = $prefix . 'Invalid organization URL';
        }
        
        // Validate social profiles
        if (isset($item['sameAs'])) {
            if (is_array($item['sameAs'])) {
                foreach ($item['sameAs'] as $index => $url) {
                    if (!empty($url) && !$this->isValidUrl((string)$url)) {
                        $this->warnings[] = $prefix . "Invalid social profile URL at index $index";
                    }
                }
            } elseif (!empty($item['sameAs']) && !$this->isValidUrl((string)$item['sameAs'])) {
                $this->warnings[] = $prefix . 'Invalid sameAs URL';
            }
        }
    }
    
    /**
     * Validate Review schema
     *
     * @param array $item
     * @param string $prefix
     * @return void
     */
    private function validateReview(array $item, string $prefix): void
    {
        // Validate author
        if (isset($item['author'])) {
            if (is_array($item['author']) && !isset($item['author']['name'])) {
                $this->warnings[] = $prefix . 'Author object should have a name';
            }
        }
        
        // Validate date
        if (isset($item['datePublished']) && !$this->isValidDate($item['datePublished'])) {
            $this->warnings[] = $prefix . 'Invalid datePublished format';
        }
    }
    
    /**
     * Validate AggregateRating schema
     *
     * @param array $item
     * @param string $prefix
     * @return void
     */
    private function validateAggregateRating(array $item, string $prefix): void
    {
        // Validate rating value
        if (isset($item['ratingValue'])) {
            $value = (float)$item['ratingValue'];
            $bestRating = isset($item['bestRating']) ? (float)$item['bestRating'] : 5.0;
            $worstRating = isset($item['worstRating']) ? (float)$item['worstRating'] : 1.0;
            
            if ($value < $worstRating || $value > $bestRating) {
                $this->warnings[] = $prefix . 'Rating value is outside the specified range';
            }
        }
        
        // Validate review count
        if (isset($item['reviewCount']) && (int)$item['reviewCount'] < 0) {
            $this->errors[] = $prefix . 'Review count cannot be negative';
        }
    }
    
    /**
     * Check if value is empty
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmpty($value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null || $value === '';
    }
    
    /**
     * Check if URL is valid
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        try {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        } catch (\Exception $e) {
            $this->logger->debug('Error validating URL: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if date is valid ISO 8601 format
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        try {
            new \DateTime($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Clear errors and warnings
     *
     * @return void
     */
    public function clear(): void
    {
        $this->errors = [];
        $this->warnings = [];
    }
}

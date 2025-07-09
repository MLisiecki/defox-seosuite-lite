<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Template;

use Defox\SEOSuite\Helper\Data as SeoHelper;
use Magento\Framework\Escaper;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enhanced abstract variable processor
 * 
 * Base abstract class for processing variables in templates with improved performance and DRY principles
 */
abstract class AbstractVariableProcessor implements VariableProcessorInterface
{
    /**
     * Cache key prefix
     */
    protected const CACHE_PREFIX = 'defox_seosuite_variable_';
    
    /**
     * Cache lifetime (1 hour)
     */
    protected const CACHE_LIFETIME = 3600;

    /**
     * @var array
     */
    protected array $processedCache = [];

    /**
     * @var SeoHelper
     */
    protected SeoHelper $seoHelper;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Escaper
     */
    protected Escaper $escaper;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * @param SeoHelper $seoHelper
     * @param StoreManagerInterface $storeManager
     * @param Escaper $escaper
     * @param CacheInterface $cache
     */
    public function __construct(
        SeoHelper $seoHelper,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        CacheInterface $cache
    ) {
        $this->seoHelper = $seoHelper;
        $this->storeManager = $storeManager;
        $this->escaper = $escaper;
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     */
    public function process(string $template, $entity, array $additionalVars = []): string
    {
        if (empty($template) || !$this->canProcess($entity)) {
            return $template;
        }

        $cacheKey = $this->getCacheKey($template, $entity, $additionalVars);
        
        // Check in-memory cache first
        if (isset($this->processedCache[$cacheKey])) {
            return $this->processedCache[$cacheKey];
        }
        
        // Check persistent cache
        $cachedResult = $this->cache->load($cacheKey);
        if ($cachedResult !== false) {
            $this->processedCache[$cacheKey] = $cachedResult;
            return $cachedResult;
        }

        // Ensure entity is properly loaded without force reloading
        $entity = $this->ensureEntityLoaded($entity);

        // Process variables, such as {{product.name}}, {{category.name}}, etc.
        $result = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) use ($entity, $additionalVars) {
                return $this->processVariable($matches[1], $entity, $additionalVars);
            },
            $template
        );

        // Process directives, such as {{lower(product.name)}}, {{truncate(product.description, 160)}}, etc.
        $result = preg_replace_callback(
            '/\{\{([a-zA-Z0-9_]+)\(([^)]+)\)\}\}/',
            function ($matches) use ($entity, $additionalVars) {
                return $this->processDirective($matches[1], $matches[2], $entity, $additionalVars);
            },
            $result
        );

        // Cache the result
        $this->processedCache[$cacheKey] = $result;
        $this->cache->save($result, $cacheKey, ['DEFOX_SEO_VARIABLES'], self::CACHE_LIFETIME);
        
        return $result;
    }

    /**
     * Ensure entity is properly loaded
     *
     * @param mixed $entity
     * @return mixed
     */
    protected function ensureEntityLoaded($entity)
    {
        // Only load if entity has an ID but no data loaded
        if (method_exists($entity, 'getId') && 
            method_exists($entity, 'isObjectNew') && 
            method_exists($entity, 'load') &&
            $entity->getId() && 
            !$entity->isObjectNew() && 
            !$entity->hasData()
        ) {
            $entity->load($entity->getId());
        }
        
        return $entity;
    }

    /**
     * Generate cache key for processed template
     *
     * @param string $template
     * @param mixed $entity
     * @param array $additionalVars
     * @return string
     */
    protected function getCacheKey(string $template, $entity, array $additionalVars): string
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : spl_object_hash($entity);
        $storeId = $this->storeManager->getStore()->getId();
        
        // Create simple hash from additionalVars without serialization
        $varsString = '';
        if (!empty($additionalVars)) {
            ksort($additionalVars);
            foreach ($additionalVars as $key => $value) {
                if (is_scalar($value)) {
                    $varsString .= $key . '=' . $value . ';';
                } elseif (is_object($value)) {
                    $varsString .= $key . '=' . get_class($value);
                    if (method_exists($value, 'getId')) {
                        $varsString .= ':' . $value->getId();
                    }
                    $varsString .= ';';
                }
            }
        }
        
        return self::CACHE_PREFIX . md5($template . '_' . $entityId . '_' . $storeId . '_' . $varsString);
    }

    /**
     * Process variable placeholder
     *
     * @param string $variablePath
     * @param mixed $entity
     * @param array $additionalVars
     * @return string
     */
    protected function processVariable(string $variablePath, $entity, array $additionalVars): string
    {
        $variablePath = trim($variablePath);
        $parts = explode('.', $variablePath);
        
        // Handle additional variables first
        if (isset($additionalVars[$parts[0]])) {
            return $this->getValueFromPath($additionalVars[$parts[0]], array_slice($parts, 1));
        }
        
        // Handle entity variables
        $prefix = $parts[0];
        
        // Check for store/website variables first (common to all processors)
        $storeWebsiteValue = $this->getStoreWebsiteValue($prefix, $parts[1] ?? null);
        if ($storeWebsiteValue !== null) {
            return $storeWebsiteValue;
        }
        
        // Handle entity-specific variables
        if ($this->isEntityPrefix($prefix)) {
            return $this->getEntityValue($entity, $parts);
        }
        
        // If no match found, return empty string
        return '';
    }

    /**
     * Get store or website variable value (common to all processors)
     *
     * @param string $prefix
     * @param string|null $property
     * @return string|null
     */
    protected function getStoreWebsiteValue(string $prefix, ?string $property): ?string
    {
        if ($prefix === 'store') {
            $store = $this->storeManager->getStore();
            return match($property) {
                'name' => $store->getName(),
                'url' => $store->getBaseUrl(),
                'base_url' => $store->getBaseUrl(),
                'secure_base_url' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK, true),
                'code' => $store->getCode(),
                default => null
            };
        }
        
        if ($prefix === 'website') {
            $website = $this->storeManager->getWebsite();
            return match($property) {
                'name' => $website->getName(),
                'code' => $website->getCode(),
                'default_group_id' => (string)$website->getDefaultGroupId(),
                default => null
            };
        }
        
        return null;
    }

    /**
     * Get value from object path (handles nested properties)
     *
     * @param mixed $object
     * @param array $path
     * @return string
     */
    protected function getValueFromPath($object, array $path): string
    {
        $value = $object;
        
        foreach ($path as $part) {
            if (is_object($value) && method_exists($value, 'getData')) {
                $value = $value->getData($part);
            } elseif (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return '';
            }
        }
        
        return (string)$value;
    }

    /**
     * Process directive (function call)
     *
     * @param string $directive
     * @param string $params
     * @param mixed $entity
     * @param array $additionalVars
     * @return string
     */
    protected function processDirective(string $directive, string $params, $entity, array $additionalVars): string
    {
        $params = array_map('trim', explode(',', $params));
        
        switch (strtolower($directive)) {
            case 'lower':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return strtolower($value);
                
            case 'upper':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return strtoupper($value);
                
            case 'ucfirst':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return ucfirst($value);
                
            case 'ucwords':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return ucwords($value);
                
            case 'truncate':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                $length = isset($params[1]) ? (int)$params[1] : 255;
                $suffix = isset($params[2]) ? trim($params[2], '"\'') : '...';
                return mb_strlen($value) > $length ? mb_substr($value, 0, $length) . $suffix : $value;
                
            case 'strip_tags':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return strip_tags($value);
                
            case 'escape':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return $this->escaper->escapeHtml($value);
                
            case 'url_encode':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                return urlencode($value);
                
            case 'replace':
                $value = $this->processVariable($params[0], $entity, $additionalVars);
                $search = isset($params[1]) ? trim($params[1], '"\'') : '';
                $replace = isset($params[2]) ? trim($params[2], '"\'') : '';
                return str_replace($search, $replace, $value);
                
            default:
                return '';
        }
    }

    /**
     * Check if the given prefix is valid for entity
     *
     * @param string $prefix
     * @return bool
     */
    abstract protected function isEntityPrefix(string $prefix): bool;

    /**
     * Get entity value based on property path
     *
     * @param mixed $entity
     * @param array $propertyPath
     * @return string
     */
    abstract protected function getEntityValue($entity, array $propertyPath): string;

    /**
     * Get enhanced available variables including common store/website variables
     *
     * @return array
     */
    public function getAvailableVariables(): array
    {
        $variables = $this->getEntitySpecificVariables();
        
        // Add common store/website variables
        $commonVariables = [
            'store.name' => 'Store Name',
            'store.url' => 'Store Base URL',
            'store.base_url' => 'Store Base URL',
            'store.secure_base_url' => 'Store Secure Base URL',
            'store.code' => 'Store Code',
            'website.name' => 'Website Name',
            'website.code' => 'Website Code',
        ];
        
        return array_merge($variables, $commonVariables);
    }

    /**
     * Get entity-specific variables (to be implemented by child classes)
     *
     * @return array
     */
    abstract protected function getEntitySpecificVariables(): array;
}

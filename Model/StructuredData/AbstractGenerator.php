<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for structured data generators
 * 
 * This class provides common functionality for all structured data generators,
 * including configuration management, caching, and error handling.
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    /**
     * Cache tag for structured data
     */
    const CACHE_TAG = 'defox_seosuite_structured_data';
    
    /**
     * @var Config
     */
    protected Config $configHelper;
    
    /**
     * @var CacheManager
     */
    protected CacheManager $cacheManager;
    
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;
    
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;
    
    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $configHelper,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configHelper = $configHelper;
        $this->cacheManager = $cacheManager;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * Generate structured data with caching support
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    public function generate($entity, array $context = []): array
    {
        if (!$this->canHandle($entity)) {
            return [];
        }
        
        $cacheKey = $this->getCacheKey($entity, $context);
        
        // Try to get from cache
        if ($this->isCacheEnabled()) {
            $cachedData = $this->cacheManager->load($cacheKey);
            if ($cachedData !== false) {
                return json_decode($cachedData, true) ?: [];
            }
        }
        
        try {
            // Generate structured data
            $data = $this->doGenerate($entity, $context);
            
            // Add common fields
            $data = $this->addCommonFields($data);
            
            // Save to cache
            if ($this->isCacheEnabled() && !empty($data)) {
                $this->cacheManager->save(
                    json_encode($data),
                    $cacheKey,
                    [self::CACHE_TAG, $this->getSchemaType()],
                    $this->getCacheLifetime()
                );
            }
            
            return $data;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Error generating structured data for %s: %s',
                    $this->getSchemaType(),
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
            return [];
        }
    }
    
    /**
     * Generate structured data implementation
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    abstract protected function doGenerate($entity, array $context): array;
    
    /**
     * Get cache key for entity
     *
     * @param mixed $entity
     * @param array $context
     * @return string
     */
    protected function getCacheKey($entity, array $context): string
    {
        $keyParts = [
            'structured_data',
            $this->getSchemaType(),
            $this->getEntityId($entity),
            $this->storeManager->getStore()->getId(),
            md5(json_encode($context))
        ];
        
        return implode('_', array_filter($keyParts));
    }
    
    /**
     * Get entity ID for cache key
     *
     * @param mixed $entity
     * @return string|null
     */
    abstract protected function getEntityId($entity): ?string;
    
    /**
     * Add common fields to structured data
     *
     * @param array $data
     * @return array
     */
    protected function addCommonFields(array $data): array
    {
        if (empty($data)) {
            return $data;
        }
        
        // Add @context if not present
        if (!isset($data['@context'])) {
            $data['@context'] = 'https://schema.org';
        }
        
        // Add @type if not present
        if (!isset($data['@type']) && $this->getSchemaType()) {
            $data['@type'] = $this->getSchemaType();
        }
        
        return $data;
    }
    
    /**
     * Check if cache is enabled for structured data
     *
     * @return bool
     */
    protected function isCacheEnabled(): bool
    {
        return $this->configHelper->isStructuredDataCacheEnabled();
    }
    
    /**
     * Get cache lifetime in seconds
     *
     * @return int
     */
    protected function getCacheLifetime(): int
    {
        return (int)$this->configHelper->getStructuredDataCacheLifetime();
    }
    
    /**
     * Get store ID
     *
     * @return int
     */
    protected function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Clean URL (remove query parameters, fragments)
     *
     * @param string $url
     * @return string
     */
    protected function cleanUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            return $url;
        }
        
        $cleanUrl = '';
        if (isset($parsedUrl['scheme'])) {
            $cleanUrl .= $parsedUrl['scheme'] . '://';
        }
        if (isset($parsedUrl['host'])) {
            $cleanUrl .= $parsedUrl['host'];
        }
        if (isset($parsedUrl['port'])) {
            $cleanUrl .= ':' . $parsedUrl['port'];
        }
        if (isset($parsedUrl['path'])) {
            $cleanUrl .= $parsedUrl['path'];
        }
        
        return $cleanUrl;
    }
    
    /**
     * Format price according to schema.org requirements
     *
     * @param float $price
     * @return string
     */
    protected function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
    
    /**
     * Convert date to ISO 8601 format
     *
     * @param string $date
     * @return string
     */
    protected function formatDate(string $date): string
    {
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format(\DateTime::ATOM);
        } catch (\Exception $e) {
            return $date;
        }
    }
    
    /**
     * Clean HTML from text and normalize whitespace
     *
     * @param string $text
     * @param int $maxLength Optional maximum length
     * @return string
     */
    protected function cleanHtml(string $text, int $maxLength = 0): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        // Limit length if specified
        if ($maxLength > 0 && strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }
        
        return $text;
    }
    
    /**
     * Clean description field specifically for schema.org
     *
     * @param string $description
     * @return string
     */
    protected function cleanDescription(string $description): string
    {
        // Schema.org recommends descriptions between 50-160 characters for snippets
        // but can be longer for complete descriptions
        return $this->cleanHtml($description, 5000); // Reasonable limit for structured data
    }
    
    /**
     * Clean short description or excerpt
     *
     * @param string $text
     * @return string
     */
    protected function cleanShortDescription(string $text): string
    {
        return $this->cleanHtml($text, 300); // Good length for short descriptions
    }
    
    /**
     * Clean article body or content
     *
     * @param string $content
     * @return string
     */
    protected function cleanContent(string $content): string
    {
        // No length limit for full content, but still clean HTML
        return $this->cleanHtml($content);
    }
    
    /**
     * Clean and validate text field for schema.org
     *
     * @param mixed $value
     * @param int $maxLength
     * @return string
     */
    protected function cleanTextField($value, int $maxLength = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        // Convert to string if not already
        $text = (string)$value;
        
        return $this->cleanHtml($text, $maxLength);
    }
    
    /**
     * Extract and clean text from possibly HTML content
     *
     * @param string $content
     * @param int $sentenceLimit Limit to first N sentences
     * @return string
     */
    protected function extractCleanText(string $content, int $sentenceLimit = 0): string
    {
        $cleanText = $this->cleanContent($content);
        
        if ($sentenceLimit > 0) {
            // Split into sentences and take first N
            $sentences = preg_split('/(?<=[.!?])\s+/', $cleanText, $sentenceLimit + 1);
            if (count($sentences) > $sentenceLimit) {
                array_pop($sentences); // Remove the incomplete last part
                $cleanText = implode(' ', $sentences);
            }
        }
        
        return $cleanText;
    }
}

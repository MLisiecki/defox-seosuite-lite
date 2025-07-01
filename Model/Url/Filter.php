<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Url;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Config\Source\FriendlyUrlPattern;
use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class for processing friendly filter URLs
 */
class Filter
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array
     */
    private array $attributeMap = [];

    /**
     * @var array
     */
    private array $reverseAttributeMap = [];

    /**
     * @param Config $config
     * @param Http $request
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        Http $request,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        
        $this->initAttributeMap();
    }

    /**
     * Initialize attribute map
     *
     * @return void
     */
    private function initAttributeMap(): void
    {
        $this->attributeMap = $this->config->getAttributeUrlMap();
        
        // Create reverse map
        foreach ($this->attributeMap as $attributeCode => $friendlyName) {
            $this->reverseAttributeMap[$friendlyName] = $attributeCode;
        }
    }

    /**
     * Process request path to extract filter parameters
     *
     * @param string $requestPath
     * @return array
     */
    public function processRequestPath(string $requestPath): array
    {
        if (!$this->config->areFriendlyUrlsEnabled()) {
            return [];
        }
        
        $urlPattern = $this->config->getFriendlyUrlPattern();
        
        if ($urlPattern === FriendlyUrlPattern::PATTERN_PATH) {
            return $this->processPathBasedUrl($requestPath);
        }
        
        return [];
    }

    /**
     * Process path-based URL
     *
     * @param string $requestPath
     * @return array
     */
    private function processPathBasedUrl(string $requestPath): array
    {
        $filterParams = [];
        $pathParts = explode('/', trim($requestPath, '/'));
        
        // Skip the first part (category path)
        if (count($pathParts) <= 1) {
            return [];
        }
        
        $filterParts = array_slice($pathParts, 1);
        $valueSeparator = $this->config->getValueSeparator();
        $multiValueSeparator = $this->config->getMultiValueSeparator();
        
        foreach ($filterParts as $filterPart) {
            // Skip empty parts
            if (empty($filterPart)) {
                continue;
            }
            
            // Split by value separator
            $parts = explode($valueSeparator, $filterPart);
            
            // Need at least attribute and one value
            if (count($parts) < 2) {
                continue;
            }
            
            $attributeName = array_shift($parts);
            $values = implode($valueSeparator, $parts);
            
            // Convert friendly name to attribute code if needed
            $attributeCode = $this->getFriendlyAttributeCode($attributeName);
            
            // Process values (may have multiple values separated by multi-value separator)
            $valueArray = explode($multiValueSeparator, $values);
            
            // Add to filter params
            $filterParams[$attributeCode] = count($valueArray) > 1 ? $valueArray : $values;
        }
        
        return $filterParams;
    }

    /**
     * Convert friendly URL to standard URL
     *
     * @param string $friendlyUrl
     * @return string
     */
    public function convertToStandardUrl(string $friendlyUrl): string
    {
        if (!$this->config->areFriendlyUrlsEnabled()) {
            return $friendlyUrl;
        }
        
        $urlPattern = $this->config->getFriendlyUrlPattern();
        
        if ($urlPattern === FriendlyUrlPattern::PATTERN_PATH) {
            // Process the friendly URL to extract parameters
            $params = $this->processRequestPath($friendlyUrl);
            
            // Extract the base URL (category path)
            $pathParts = explode('/', trim($friendlyUrl, '/'));
            $basePath = '/' . $pathParts[0];
            
            // Build query parameters
            $queryParams = [];
            foreach ($params as $code => $value) {
                $queryParams[$code] = $value;
            }
            
            // Build standard URL
            $standardUrl = $basePath;
            if (!empty($queryParams)) {
                $standardUrl .= '?' . http_build_query($queryParams);
            }
            
            return $standardUrl;
        }
        
        return $friendlyUrl;
    }

    /**
     * Convert standard URL to friendly URL
     *
     * @param string $standardUrl
     * @return string
     */
    public function convertToFriendlyUrl(string $standardUrl): string
    {
        if (!$this->config->areFriendlyUrlsEnabled()) {
            return $standardUrl;
        }
        
        $urlPattern = $this->config->getFriendlyUrlPattern();
        
        if ($urlPattern === FriendlyUrlPattern::PATTERN_PATH) {
            // Parse URL
            $urlParts = parse_url($standardUrl);
            $basePath = $urlParts['path'] ?? '/';
            
            // Parse query parameters
            $params = [];
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $params);
            }
            
            // Build friendly URL
            $friendlyUrl = $basePath;
            
            if (!empty($params)) {
                $valueSeparator = $this->config->getValueSeparator();
                $multiValueSeparator = $this->config->getMultiValueSeparator();
                $filterSeparator = $this->config->getFilterSeparator();
                
                foreach ($params as $code => $value) {
                    // Skip non-filter parameters
                    if (in_array($code, ['p', 'order', 'limit', 'mode'])) {
                        continue;
                    }
                    
                    // Get friendly attribute name
                    $friendlyName = $this->getFriendlyAttributeName($code);
                    
                    // Handle multiple values
                    if (is_array($value)) {
                        $value = implode($multiValueSeparator, $value);
                    }
                    
                    // Add to friendly URL
                    $friendlyUrl .= $filterSeparator . $friendlyName . $valueSeparator . $value;
                }
            }
            
            return $friendlyUrl;
        }
        
        return $standardUrl;
    }

    /**
     * Get friendly attribute name for the given attribute code
     *
     * @param string $attributeCode
     * @return string
     */
    private function getFriendlyAttributeName(string $attributeCode): string
    {
        return $this->attributeMap[$attributeCode] ?? $attributeCode;
    }

    /**
     * Get attribute code from friendly name
     *
     * @param string $friendlyName
     * @return string
     */
    private function getFriendlyAttributeCode(string $friendlyName): string
    {
        return $this->reverseAttributeMap[$friendlyName] ?? $friendlyName;
    }
}

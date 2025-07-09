<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Generator;

use Defox\SEOSuite\Model\StructuredData\AbstractGenerator;
use Magento\Store\Model\Store;

/**
 * WebSite structured data generator
 * 
 * Generates schema.org WebSite structured data with Sitelinks Searchbox support.
 */
class WebSite extends AbstractGenerator
{
    /**
     * Generate structured data for website
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl();
        
        $data = [
            '@type' => 'WebSite',
            'name' => $store->getName(),
            'url' => $baseUrl
        ];
        
        // Add alternative name if configured
        $alternateName = $this->configHelper->getStructuredDataWebsiteAlternateName();
        if ($alternateName) {
            $data['alternateName'] = $alternateName;
        }
        
        // Add Sitelinks Searchbox if enabled
        if ($this->configHelper->isSitelinksSearchboxEnabled()) {
            $data['potentialAction'] = $this->getSitelinksSearchbox();
        }
        
        // Add publisher information if organization is enabled
        if ($this->configHelper->isStructuredDataOrganizationEnabled()) {
            $organizationName = $this->configHelper->getStructuredDataOrganizationName();
            if ($organizationName) {
                $data['publisher'] = [
                    '@type' => 'Organization',
                    'name' => $organizationName,
                    'url' => $baseUrl
                ];
                
                // Add logo if available
                $logo = $this->configHelper->getStructuredDataOrganizationLogo();
                if ($logo) {
                    $data['publisher']['logo'] = $this->getLogoUrl($logo);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Check if generator can handle entity
     *
     * @param mixed $entity
     * @return bool
     */
    public function canHandle($entity): bool
    {
        // Website data is generated for homepage or store
        return $entity instanceof Store || $entity === null;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'WebSite';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataWebsiteEnabled($storeId);
    }
    
    /**
     * Get entity ID
     *
     * @param mixed $entity
     * @return string|null
     */
    protected function getEntityId($entity): ?string
    {
        return 'website_' . $this->getStoreId();
    }
    
    /**
     * Get Sitelinks Searchbox configuration
     *
     * @return array
     */
    private function getSitelinksSearchbox(): array
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $searchUrl = $baseUrl . 'catalogsearch/result/?q={search_term_string}';
        
        // Allow custom search URL pattern
        $customSearchUrl = $this->configHelper->getSitelinksSearchboxUrl();
        if ($customSearchUrl) {
            $searchUrl = str_replace(
                ['{base_url}', '{search_term}'],
                [$baseUrl, '{search_term_string}'],
                $customSearchUrl
            );
        }
        
        return [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $searchUrl
            ],
            'query-input' => 'required name=search_term_string'
        ];
    }
    
    /**
     * Get logo URL
     *
     * @param string $logoPath
     * @return string
     */
    private function getLogoUrl(string $logoPath): string
    {
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }
        
        // Build full URL for relative path
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $baseUrl . 'logo/' . ltrim($logoPath, '/');
    }
}

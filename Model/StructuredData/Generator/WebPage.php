<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Generator;

use Defox\SEOSuite\Model\StructuredData\AbstractGenerator;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Page;
use Magento\Cms\Helper\Page as PageHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * WebPage structured data generator
 * 
 * Generates schema.org WebPage structured data for CMS pages.
 */
class WebPage extends AbstractGenerator
{
    /**
     * Generate structured data for CMS page
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        /** @var Page $page */
        $page = $entity;
        
        $data = [
            '@type' => 'WebPage',
            'name' => $page->getTitle(),
            'url' => $this->getPageUrl($page),
            'description' => $this->getPageDescription($page)
        ];
        
        // Add dates
        if ($page->getCreationTime()) {
            $data['datePublished'] = $this->formatDate($page->getCreationTime());
        }
        
        if ($page->getUpdateTime()) {
            $data['dateModified'] = $this->formatDate($page->getUpdateTime());
        }
        
        // Add breadcrumb if available
        if (isset($context['breadcrumb']) && is_array($context['breadcrumb'])) {
            $data['breadcrumb'] = $this->generateBreadcrumb($context['breadcrumb']);
        }
        
        // Add main entity of page if it's about page
        if ($this->isAboutPage($page)) {
            $data['mainEntityOfPage'] = $this->getAboutOrganization();
        }
        
        // Add publisher (organization) with FULL data from SEO Suite config
        if ($this->configHelper->isStructuredDataOrganizationEnabled()) {
            $organization = $this->getFullOrganizationData();
            if (!empty($organization)) {
                $data['publisher'] = $organization;
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
        return $entity instanceof PageInterface || $entity instanceof Page;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'WebPage';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataEnabled($storeId);
    }
    
    /**
     * Get entity ID
     *
     * @param mixed $entity
     * @return string|null
     */
    protected function getEntityId($entity): ?string
    {
        return $entity->getId() ? 'cms_page_' . $entity->getId() : null;
    }
    
    /**
     * Get page URL
     *
     * @param Page $page
     * @return string
     */
    private function getPageUrl(Page $page): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $identifier = $page->getIdentifier();
        
        // Check if this is the configured home page
        if ($this->isHomePage($page)) {
            return $baseUrl;
        }
        
        // Handle standard home page identifiers
        if ($identifier === 'home' || $identifier === 'cms_index_index') {
            return $baseUrl;
        }
        
        return $this->cleanUrl($baseUrl . $identifier);
    }
    
    /**
     * Check if this CMS page is configured as home page
     *
     * @param Page $page
     * @return bool
     */
    private function isHomePage(Page $page): bool
    {
        // Get configured home page from store configuration
        $homePageId = $this->scopeConfig->getValue(
            PageHelper::XML_PATH_HOME_PAGE,
            ScopeInterface::SCOPE_STORE
        );
        
        // Check if current page is the configured home page
        if ($homePageId && $page->getIdentifier() === $homePageId) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get page description
     *
     * @param Page $page
     * @return string
     */
    private function getPageDescription(Page $page): string
    {
        // Try meta description first
        $description = $page->getMetaDescription();
        
        if (!$description) {
            // Extract from content using HTML cleaning method
            $description = $this->extractCleanText($page->getContent(), 2); // First 2 sentences
            if (strlen($description) > 160) {
                $description = $this->cleanHtml($description, 160);
            }
        } else {
            // Clean meta description too
            $description = $this->cleanTextField($description, 300);
        }
        
        return $description ?: '';
    }
    
    /**
     * Check if page is about page
     *
     * @param Page $page
     * @return bool
     */
    private function isAboutPage(Page $page): bool
    {
        $identifier = strtolower($page->getIdentifier());
        $title = strtolower($page->getTitle());
        
        return strpos($identifier, 'about') !== false || 
               strpos($title, 'about') !== false ||
               strpos($identifier, 'o-nas') !== false ||
               strpos($title, 'o nas') !== false;
    }
    
    /**
     * Get organization data for about page
     *
     * @return array
     */
    private function getAboutOrganization(): array
    {
        $data = [
            '@type' => $this->configHelper->getStructuredDataOrganizationType() ?: 'Organization',
            'name' => $this->configHelper->getStructuredDataOrganizationName()
        ];
        
        $description = $this->configHelper->getStructuredDataOrganizationDescription();
        if ($description) {
            $data['description'] = $description;
        }
        
        return $data;
    }
    
    /**
     * Generate breadcrumb structured data
     *
     * @param array $breadcrumb
     * @return array
     */
    private function generateBreadcrumb(array $breadcrumb): array
    {
        $breadcrumbList = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => []
        ];
        
        $position = 1;
        foreach ($breadcrumb as $item) {
            if (isset($item['label']) && isset($item['link'])) {
                $breadcrumbList['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'item' => [
                        '@type' => 'Thing',
                        '@id' => $item['link'],
                        'name' => $item['label']
                    ]
                ];
                $position++;
            }
        }
        
        return $breadcrumbList;
    }
    
    /**
     * Get full organization data from SEO Suite configuration
     *
     * @return array
     */
    private function getFullOrganizationData(): array
    {
        $organizationName = $this->configHelper->getStructuredDataOrganizationName();
        if (!$organizationName) {
            return [];
        }
        
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        
        $organization = [
            '@type' => $this->configHelper->getStructuredDataOrganizationType() ?: 'Organization',
            'name' => $organizationName,
            'url' => $baseUrl
        ];
        
        // Add fields only if they have values
        $this->addIfNotEmpty($organization, 'logo', $this->getLogoUrl($this->configHelper->getStructuredDataOrganizationLogo()));
        $this->addIfNotEmpty($organization, 'description', $this->configHelper->getStructuredDataOrganizationDescription());
        $this->addIfNotEmpty($organization, 'foundingDate', $this->configHelper->getStructuredDataOrganizationFoundingDate());
        $this->addIfNotEmpty($organization, 'taxID', $this->configHelper->getStructuredDataOrganizationTaxId());
        $this->addIfNotEmpty($organization, 'vatID', $this->configHelper->getStructuredDataOrganizationVatId());
        
        // Add address only if it has data
        $address = $this->getOrganizationAddress();
        if (!empty($address)) {
            $organization['address'] = $address;
        }
        
        // Add contact points only if they exist
        $contactPoints = $this->getOrganizationContactPoints();
        if (!empty($contactPoints)) {
            $organization['contactPoint'] = count($contactPoints) === 1 ? $contactPoints[0] : $contactPoints;
        }
        
        // Add social media profiles only if they exist
        $socialProfiles = $this->getOrganizationSocialProfiles();
        if (!empty($socialProfiles)) {
            $organization['sameAs'] = $socialProfiles;
        }
        
        // Add area served only if configured
        $areaServed = $this->configHelper->getStructuredDataOrganizationAreaServed();
        if (!empty($areaServed)) {
            $organization['areaServed'] = $areaServed;
        }
        
        return $organization;
    }
    
    /**
     * Add field to array only if value is not empty
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     */
    private function addIfNotEmpty(array &$array, string $key, $value): void
    {
        if ($value !== null && $value !== '' && $value !== []) {
            $array[$key] = $value;
        }
    }
    
    /**
     * Get organization address
     *
     * @return array
     */
    private function getOrganizationAddress(): array
    {
        $address = [];
        
        $streetAddress = $this->configHelper->getStructuredDataOrganizationStreetAddress();
        $locality = $this->configHelper->getStructuredDataOrganizationLocality();
        $region = $this->configHelper->getStructuredDataOrganizationRegion();
        $postalCode = $this->configHelper->getStructuredDataOrganizationPostalCode();
        $country = $this->configHelper->getStructuredDataOrganizationCountry();
        
        // Only create address if at least one field has value
        if ($streetAddress || $locality || $region || $postalCode || $country) {
            $address = ['@type' => 'PostalAddress'];
            
            $this->addIfNotEmpty($address, 'streetAddress', $streetAddress);
            $this->addIfNotEmpty($address, 'addressLocality', $locality);
            $this->addIfNotEmpty($address, 'addressRegion', $region);
            $this->addIfNotEmpty($address, 'postalCode', $postalCode);
            $this->addIfNotEmpty($address, 'addressCountry', $country);
        }
        
        return $address;
    }
    
    /**
     * Get organization contact points
     *
     * @return array
     */
    private function getOrganizationContactPoints(): array
    {
        $contactPoints = [];
        $availableLanguages = $this->configHelper->getStructuredDataOrganizationAvailableLanguages();
        
        // Customer Service
        $customerPhone = $this->configHelper->getStructuredDataOrganizationCustomerServicePhone();
        $customerEmail = $this->configHelper->getStructuredDataOrganizationCustomerServiceEmail();
        if ($customerPhone || $customerEmail) {
            $contact = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service'
            ];
            $this->addIfNotEmpty($contact, 'telephone', $customerPhone);
            $this->addIfNotEmpty($contact, 'email', $customerEmail);
            $this->addIfNotEmpty($contact, 'availableLanguage', $availableLanguages);
            $contactPoints[] = $contact;
        }
        
        // Technical Support
        $techPhone = $this->configHelper->getStructuredDataOrganizationTechSupportPhone();
        $techEmail = $this->configHelper->getStructuredDataOrganizationTechSupportEmail();
        if ($techPhone || $techEmail) {
            $contact = [
                '@type' => 'ContactPoint',
                'contactType' => 'technical support'
            ];
            $this->addIfNotEmpty($contact, 'telephone', $techPhone);
            $this->addIfNotEmpty($contact, 'email', $techEmail);
            $this->addIfNotEmpty($contact, 'availableLanguage', $availableLanguages);
            $contactPoints[] = $contact;
        }
        
        // Sales
        $salesPhone = $this->configHelper->getStructuredDataOrganizationSalesPhone();
        $salesEmail = $this->configHelper->getStructuredDataOrganizationSalesEmail();
        if ($salesPhone || $salesEmail) {
            $contact = [
                '@type' => 'ContactPoint',
                'contactType' => 'sales'
            ];
            $this->addIfNotEmpty($contact, 'telephone', $salesPhone);
            $this->addIfNotEmpty($contact, 'email', $salesEmail);
            $this->addIfNotEmpty($contact, 'availableLanguage', $availableLanguages);
            $contactPoints[] = $contact;
        }
        
        return $contactPoints;
    }
    
    /**
     * Get organization social media profiles
     *
     * @return array
     */
    private function getOrganizationSocialProfiles(): array
    {
        $profiles = [];
        
        $socialUrls = [
            $this->configHelper->getStructuredDataOrganizationFacebook(),
            $this->configHelper->getStructuredDataOrganizationTwitter(),
            $this->configHelper->getStructuredDataOrganizationInstagram(),
            $this->configHelper->getStructuredDataOrganizationYoutube(),
            $this->configHelper->getStructuredDataOrganizationLinkedin(),
            $this->configHelper->getStructuredDataOrganizationPinterest(),
            $this->configHelper->getStructuredDataOrganizationTiktok()
        ];
        
        foreach ($socialUrls as $url) {
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $profiles[] = $url;
            }
        }
        
        return $profiles;
    }
    
    /**
     * Get logo URL
     *
     * @param string $logoPath
     * @return string|null
     */
    private function getLogoUrl(string $logoPath = ''): ?string
    {
        if (empty($logoPath)) {
            return null;
        }
        
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }
        
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $baseUrl . 'logo/' . ltrim($logoPath, '/');
    }
}

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
 * Organization structured data generator
 * 
 * Generates schema.org Organization structured data for the website.
 */
class Organization extends AbstractGenerator
{
    /**
     * Generate structured data for organization
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        $data = [
            '@type' => $this->getOrganizationType(),
            'name' => $this->configHelper->getStructuredDataOrganizationName(),
            'url' => $this->storeManager->getStore()->getBaseUrl()
        ];
        
        // Add logo
        $logo = $this->configHelper->getStructuredDataOrganizationLogo();
        if ($logo) {
            $data['logo'] = $this->getLogoUrl($logo);
        }
        
        // Add description
        $description = $this->configHelper->getStructuredDataOrganizationDescription();
        if ($description) {
            $data['description'] = $description;
        }
        
        // Add address
        $address = $this->getOrganizationAddress();
        if (!empty($address)) {
            $data['address'] = $address;
        }
        
        // Add contact points
        $contactPoints = $this->getContactPoints();
        if (!empty($contactPoints)) {
            $data['contactPoint'] = $contactPoints;
        }
        
        // Add social profiles
        $sameAs = $this->getSocialProfiles();
        if (!empty($sameAs)) {
            $data['sameAs'] = $sameAs;
        }
        
        // Add founding date
        $foundingDate = $this->configHelper->getStructuredDataOrganizationFoundingDate();
        if ($foundingDate) {
            $data['foundingDate'] = $foundingDate;
        }
        
        // Add tax ID
        $taxId = $this->configHelper->getStructuredDataOrganizationTaxId();
        if ($taxId) {
            $data['taxID'] = $taxId;
        }
        
        // Add VAT ID
        $vatId = $this->configHelper->getStructuredDataOrganizationVatId();
        if ($vatId) {
            $data['vatID'] = $vatId;
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
        // Organization data is generated for store, not specific entities
        return $entity instanceof Store || $entity === null;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'Organization';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataOrganizationEnabled($storeId);
    }
    
    /**
     * Get entity ID
     *
     * @param mixed $entity
     * @return string|null
     */
    protected function getEntityId($entity): ?string
    {
        return 'organization_' . $this->getStoreId();
    }
    
    /**
     * Get organization type
     *
     * @return string
     */
    private function getOrganizationType(): string
    {
        $type = $this->configHelper->getStructuredDataOrganizationType();
        
        // Map to valid schema.org types
        $validTypes = [
            'Organization',
            'Corporation',
            'LocalBusiness',
            'Store',
            'OnlineStore',
            'Restaurant',
            'Hotel',
            'ProfessionalService',
            'MedicalBusiness',
            'AutomotiveBusiness',
            'FinancialService',
            'FoodEstablishment',
            'SportsActivityLocation'
        ];
        
        return in_array($type, $validTypes) ? $type : 'Organization';
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
        
        if ($streetAddress || $locality || $region || $postalCode || $country) {
            $address = [
                '@type' => 'PostalAddress'
            ];
            
            if ($streetAddress) {
                $address['streetAddress'] = $streetAddress;
            }
            if ($locality) {
                $address['addressLocality'] = $locality;
            }
            if ($region) {
                $address['addressRegion'] = $region;
            }
            if ($postalCode) {
                $address['postalCode'] = $postalCode;
            }
            if ($country) {
                $address['addressCountry'] = $country;
            }
        }
        
        return $address;
    }
    
    /**
     * Get contact points
     *
     * @return array
     */
    private function getContactPoints(): array
    {
        $contactPoints = [];
        
        // Customer service
        $customerServicePhone = $this->configHelper->getStructuredDataOrganizationCustomerServicePhone();
        $customerServiceEmail = $this->configHelper->getStructuredDataOrganizationCustomerServiceEmail();
        
        if ($customerServicePhone || $customerServiceEmail) {
            $customerService = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service'
            ];
            
            if ($customerServicePhone) {
                $customerService['telephone'] = $customerServicePhone;
            }
            if ($customerServiceEmail) {
                $customerService['email'] = $customerServiceEmail;
            }
            
            // Add available languages
            $languages = $this->configHelper->getStructuredDataOrganizationAvailableLanguages();
            if (!empty($languages)) {
                $customerService['availableLanguage'] = $languages;
            }
            
            // Add area served
            $areaServed = $this->configHelper->getStructuredDataOrganizationAreaServed();
            if (!empty($areaServed)) {
                $customerService['areaServed'] = $areaServed;
            }
            
            $contactPoints[] = $customerService;
        }
        
        // Technical support
        $techSupportPhone = $this->configHelper->getStructuredDataOrganizationTechSupportPhone();
        $techSupportEmail = $this->configHelper->getStructuredDataOrganizationTechSupportEmail();
        
        if ($techSupportPhone || $techSupportEmail) {
            $techSupport = [
                '@type' => 'ContactPoint',
                'contactType' => 'technical support'
            ];
            
            if ($techSupportPhone) {
                $techSupport['telephone'] = $techSupportPhone;
            }
            if ($techSupportEmail) {
                $techSupport['email'] = $techSupportEmail;
            }
            
            $contactPoints[] = $techSupport;
        }
        
        // Sales
        $salesPhone = $this->configHelper->getStructuredDataOrganizationSalesPhone();
        $salesEmail = $this->configHelper->getStructuredDataOrganizationSalesEmail();
        
        if ($salesPhone || $salesEmail) {
            $sales = [
                '@type' => 'ContactPoint',
                'contactType' => 'sales'
            ];
            
            if ($salesPhone) {
                $sales['telephone'] = $salesPhone;
            }
            if ($salesEmail) {
                $sales['email'] = $salesEmail;
            }
            
            $contactPoints[] = $sales;
        }
        
        return $contactPoints;
    }
    
    /**
     * Get social profiles
     *
     * @return array
     */
    private function getSocialProfiles(): array
    {
        $profiles = [];
        
        $socialNetworks = [
            'facebook' => $this->configHelper->getStructuredDataOrganizationFacebook(),
            'twitter' => $this->configHelper->getStructuredDataOrganizationTwitter(),
            'instagram' => $this->configHelper->getStructuredDataOrganizationInstagram(),
            'youtube' => $this->configHelper->getStructuredDataOrganizationYoutube(),
            'linkedin' => $this->configHelper->getStructuredDataOrganizationLinkedin(),
            'pinterest' => $this->configHelper->getStructuredDataOrganizationPinterest(),
            'tiktok' => $this->configHelper->getStructuredDataOrganizationTiktok()
        ];
        
        foreach ($socialNetworks as $network => $url) {
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $profiles[] = $url;
            }
        }
        
        return $profiles;
    }
}

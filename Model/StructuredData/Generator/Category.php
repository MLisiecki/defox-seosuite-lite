<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Generator;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Defox\SEOSuite\Model\StructuredData\AbstractGenerator;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Category structured data generator
 * 
 * Generates schema.org CollectionPage structured data for catalog categories.
 */
class Category extends AbstractGenerator
{
    /**
     * @var CategoryHelper
     */
    private CategoryHelper $categoryHelper;
    
    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;
    
    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;
    
    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CategoryHelper $categoryHelper
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        Config $configHelper,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        CategoryHelper $categoryHelper,
        ProductCollectionFactory $productCollectionFactory,
        CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct($configHelper, $cacheManager, $storeManager, $logger, $scopeConfig);
        $this->categoryHelper = $categoryHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryRepository = $categoryRepository;
    }
    
    /**
     * Generate structured data for category
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        /** @var CategoryModel $category */
        $category = $entity;
        
        // Determine if this is root category and should point to homepage
        $categoryUrl = $this->getCategoryUrl($category);
        
        $data = [
            '@type' => 'CollectionPage',
            'name' => $category->getName(),
            'url' => $categoryUrl,
            'description' => $this->getCategoryDescription($category)
        ];
        
        // Add breadcrumb
        $breadcrumb = $this->getBreadcrumb($category);
        if (!empty($breadcrumb)) {
            $data['breadcrumb'] = $breadcrumb;
        }
        
        // Add image if available
        $image = $this->getCategoryImage($category);
        if ($image) {
            $data['image'] = $image;
        }
        
        // Add main entity (ItemList of products)
        if ($this->configHelper->isStructuredDataCategoryProductListEnabled()) {
            $productList = $this->getProductList($category, $context);
            if (!empty($productList)) {
                $data['mainEntity'] = $productList;
            }
        }
        
        // Add isPartOf if it's a subcategory
        if ($category->getParentId() > 1) {
            $parentCategory = $category->getParentCategory();
            if ($parentCategory && $parentCategory->getId() > 1) {
                $data['isPartOf'] = [
                    '@type' => 'CollectionPage',
                    'name' => $parentCategory->getName(),
                    'url' => $this->getCategoryUrl($parentCategory)
                ];
            }
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
        return $entity instanceof CategoryInterface || $entity instanceof CategoryModel;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'CollectionPage';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataCategoryEnabled($storeId);
    }
    
    /**
     * Get entity ID
     *
     * @param mixed $entity
     * @return string|null
     */
    protected function getEntityId($entity): ?string
    {
        return $entity->getId() ? (string)$entity->getId() : null;
    }
    
    /**
     * Get proper category URL
     *
     * @param CategoryModel $category
     * @return string
     */
    private function getCategoryUrl(CategoryModel $category): string
    {
        // Check if this is root/main category - if so, point to homepage
        if ($this->isRootCategory($category)) {
            return $this->storeManager->getStore()->getBaseUrl();
        }
        
        // Get the category URL
        $categoryUrl = $category->getUrl();
        
        // Clean admin URLs if they appear
        if (strpos($categoryUrl, '/admin_') !== false) {
            // Construct proper frontend URL
            $urlKey = $category->getUrlKey();
            if ($urlKey) {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                $categoryUrl = $baseUrl . $urlKey . '.html';
            } else {
                // Fallback to homepage for root categories without URL key
                return $this->storeManager->getStore()->getBaseUrl();
            }
        }
        
        return $this->cleanUrl($categoryUrl);
    }
    
    /**
     * Check if category is root/main category
     *
     * @param CategoryModel $category
     * @return bool
     */
    private function isRootCategory(CategoryModel $category): bool
    {
        // Check if category level is 2 or less (root categories)
        if ($category->getLevel() <= 2) {
            return true;
        }
        
        // Check if this is the main category that should point to homepage
        // You can add additional logic here based on your store structure
        // For example, checking if it's marked as "main" in some custom attribute
        
        return false;
    }
    
    /**
     * Get category description
     *
     * @param CategoryModel $category
     * @return string
     */
    private function getCategoryDescription(CategoryModel $category): string
    {
        $description = $category->getDescription();
        if ($description) {
            // Use the new HTML cleaning method from AbstractGenerator
            $description = $this->cleanHtml($description, 500);
        }
        
        return $description ?: '';
    }
    
    /**
     * Get category image URL
     *
     * @param CategoryModel $category
     * @return string|null
     */
    private function getCategoryImage(CategoryModel $category): ?string
    {
        $image = $category->getImageUrl();
        if ($image) {
            return $image;
        }
        
        // Try to get image from data
        $imageData = $category->getImage();
        if ($imageData) {
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            return $mediaUrl . 'catalog/category/' . $imageData;
        }
        
        return null;
    }
    
    /**
     * Get breadcrumb structured data
     *
     * @param CategoryModel $category
     * @return array
     */
    private function getBreadcrumb(CategoryModel $category): array
    {
        $breadcrumb = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => []
        ];
        
        $path = $category->getPath();
        $pathIds = explode('/', $path);
        
        // Remove root category IDs (1 and 2)
        $pathIds = array_filter($pathIds, function($id) {
            return $id > 2;
        });
        
        $position = 1;
        foreach ($pathIds as $categoryId) {
            if ($categoryId == $category->getId()) {
                // Current category
                $breadcrumb['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'item' => [
                        '@type' => 'WebPage',
                        '@id' => $this->getCategoryUrl($category),
                        'name' => $category->getName()
                    ]
                ];
            } else {
                // Parent categories
                try {
                    $parentCategory = $this->categoryRepository->get($categoryId, $this->storeManager->getStore()->getId());
                    if ($parentCategory && $parentCategory->getIsActive()) {
                        $breadcrumb['itemListElement'][] = [
                            '@type' => 'ListItem',
                            'position' => $position,
                            'item' => [
                                '@type' => 'WebPage',
                                '@id' => $this->getCategoryUrl($parentCategory),
                                'name' => $parentCategory->getName()
                            ]
                        ];
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Error getting parent category: ' . $e->getMessage());
                }
            }
            $position++;
        }
        
        return $breadcrumb;
    }
    
    /**
     * Get product list for category
     *
     * @param CategoryModel $category
     * @param array $context
     * @return array
     */
    private function getProductList(CategoryModel $category, array $context): array
    {
        $productList = [
            '@type' => 'ItemList',
            'numberOfItems' => 0,
            'itemListElement' => []
        ];
        
        try {
            // Create product collection
            $collection = $this->productCollectionFactory->create();
            $collection->addCategoryFilter($category);
            $collection->addAttributeToSelect(['name', 'price', 'image', 'url_key']);
            $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);
            
            // Apply sorting from context if available
            if (isset($context['sort_order'])) {
                $collection->setOrder($context['sort_order'], $context['sort_direction'] ?? 'ASC');
            } else {
                $collection->setOrder('position', 'ASC');
            }
            
            // Limit to reasonable number of products
            $limit = $this->configHelper->getStructuredDataCategoryProductLimit() ?: 20;
            $collection->setPageSize($limit);
            
            // Apply current page if paginated
            if (isset($context['current_page'])) {
                $collection->setCurPage((int)$context['current_page']);
            }
            
            $position = 1;
            foreach ($collection as $product) {
                $productData = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'item' => [
                        '@type' => 'Thing',
                        '@id' => $this->cleanUrl($product->getProductUrl()),
                        'name' => $product->getName(),
                        'url' => $this->cleanUrl($product->getProductUrl())
                    ]
                ];
                
                // Don't add offers here - this is just a list item, not a full product
                
                $productList['itemListElement'][] = $productData;
                $position++;
            }
            
            $productList['numberOfItems'] = count($productList['itemListElement']);
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting category products: ' . $e->getMessage());
        }
        
        return $productList;
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
        $this->addIfNotEmpty($organization, 'logo', $this->getOrganizationLogoUrl($this->configHelper->getStructuredDataOrganizationLogo()));
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
     * Get organization logo URL
     *
     * @param string $logoPath
     * @return string|null
     */
    private function getOrganizationLogoUrl(string $logoPath = ''): ?string
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
}

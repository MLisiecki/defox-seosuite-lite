<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Generator;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Defox\SEOSuite\Model\StructuredData\AbstractGenerator;
use Defox\SEOSuite\Model\StructuredData\Mapper\AttributeMapperInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\Category;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Product structured data generator
 * 
 * Generates schema.org Product structured data for catalog products.
 * Supports simple, configurable, grouped, virtual, and downloadable product types.
 */
class Product extends AbstractGenerator
{
    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;
    
    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;
    
    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;
    
    /**
     * @var ReviewFactory
     */
    private ReviewFactory $reviewFactory;
    
    /**
     * @var AttributeMapperInterface
     */
    private AttributeMapperInterface $attributeMapper;
    
    /**
     * @var CatalogHelper
     */
    private CatalogHelper $catalogHelper;
    
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
     * @param ImageHelper $imageHelper
     * @param StockRegistryInterface $stockRegistry
     * @param PriceCurrencyInterface $priceCurrency
     * @param ReviewFactory $reviewFactory
     * @param AttributeMapperInterface $attributeMapper
     * @param CatalogHelper $catalogHelper
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        Config $configHelper,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ImageHelper $imageHelper,
        StockRegistryInterface $stockRegistry,
        PriceCurrencyInterface $priceCurrency,
        ReviewFactory $reviewFactory,
        AttributeMapperInterface $attributeMapper,
        CatalogHelper $catalogHelper,
        CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct($configHelper, $cacheManager, $storeManager, $logger, $scopeConfig);
        $this->imageHelper = $imageHelper;
        $this->stockRegistry = $stockRegistry;
        $this->priceCurrency = $priceCurrency;
        $this->reviewFactory = $reviewFactory;
        $this->attributeMapper = $attributeMapper;
        $this->catalogHelper = $catalogHelper;
        $this->categoryRepository = $categoryRepository;
    }
    
    /**
     * Generate structured data for product
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        /** @var ProductModel $product */
        $product = $entity;
        
        $this->logger->info('Starting structured data generation for product: ' . $product->getId());
        
        $data = [
            '@type' => 'Product',
            'name' => $product->getName(),
            'description' => $this->getProductDescription($product),
            'sku' => $product->getSku(),
            'url' => $this->cleanUrl($product->getProductUrl())
        ];
        
        $this->logger->info('Basic product data created: ' . json_encode($data));
        
        // Add images
        $images = $this->getProductImages($product);
        if (!empty($images)) {
            $data['image'] = count($images) === 1 ? $images[0] : $images;
        }
        
        // Add brand/manufacturer
        $brand = $this->getProductBrand($product);
        if ($brand) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => $brand
            ];
        }
        
        // Add model
        $model = $this->getProductModel($product);
        if ($model) {
            $data['model'] = $model;
        }
        
        // Add identifiers (GTIN, MPN)
        $identifiers = $this->getProductIdentifiers($product);
        $data = array_merge($data, $identifiers);
        
        // Add offers
        $offers = $this->getProductOffers($product);
        if (!empty($offers)) {
            $data['offers'] = $offers;
        }
        
        // Add reviews and ratings
        $reviewData = $this->getProductReviews($product);
        if (!empty($reviewData)) {
            $data = array_merge($data, $reviewData);
        }
        
        // Add custom attributes based on mapping
        $customAttributes = $this->getCustomAttributes($product);
        $this->logger->info('Custom attributes: ' . json_encode($customAttributes));
        if (!empty($customAttributes)) {
            $data = array_merge($data, $customAttributes);
        }
        
        // Add additional properties (weight, dimensions, etc.)
        $additionalProperties = $this->getAdditionalProperties($product);
        if (!empty($additionalProperties)) {
            $data['additionalProperty'] = $additionalProperties;
        }
        
        $this->logger->info('Final structured data: ' . json_encode($data));
        
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
        return $entity instanceof ProductInterface || $entity instanceof ProductModel;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'Product';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataProductEnabled($storeId);
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
     * Get product description
     *
     * @param ProductModel $product
     * @return string
     */
    private function getProductDescription(ProductModel $product): string
    {
        $description = $product->getDescription() ?: $product->getShortDescription();
        if ($description) {
            // Use the new HTML cleaning method from AbstractGenerator
            $description = $this->cleanDescription($description);
        }
        
        return $description ?: '';
    }
    
    /**
     * Get product images
     *
     * @param ProductModel $product
     * @return array
     */
    private function getProductImages(ProductModel $product): array
    {
        $images = [];
        $gallery = $product->getMediaGalleryImages();
        
        if ($gallery && $gallery->getSize() > 0) {
            foreach ($gallery as $image) {
                if ($image->getUrl()) {
                    $images[] = $image->getUrl();
                }
            }
        }
        
        // Fallback to main image
        if (empty($images)) {
            try {
                $imageUrl = $this->imageHelper->init($product, 'product_page_image_large')
                    ->setImageFile($product->getImage())
                    ->getUrl();
                if ($imageUrl) {
                    $images[] = $imageUrl;
                }
            } catch (\Exception $e) {
                $this->logger->debug('Error getting product image: ' . $e->getMessage());
            }
        }
        
        return $images;
    }
    
    /**
     * Get product brand
     *
     * @param ProductModel $product
     * @return string|null
     */
    private function getProductBrand(ProductModel $product): ?string
    {
        // Try mapped brand attribute first
        $brandAttribute = $this->configHelper->getStructuredDataBrandAttribute();
        if ($brandAttribute && $product->getData($brandAttribute)) {
            $brand = $product->getAttributeText($brandAttribute);
            if (!$brand) {
                $brand = $product->getData($brandAttribute);
            }
            return is_string($brand) ? $brand : null;
        }
        
        // Fallback to manufacturer
        if ($product->getManufacturer()) {
            $manufacturer = $product->getAttributeText('manufacturer');
            return is_string($manufacturer) ? $manufacturer : null;
        }
        
        return null;
    }
    
    /**
     * Get product model
     *
     * @param ProductModel $product
     * @return string|null
     */
    private function getProductModel(ProductModel $product): ?string
    {
        $modelAttribute = $this->configHelper->getStructuredDataModelAttribute();
        if ($modelAttribute && $product->getData($modelAttribute)) {
            $model = $product->getData($modelAttribute);
            return is_string($model) ? $model : null;
        }
        
        return null;
    }
    
    /**
     * Get product identifiers (GTIN, MPN)
     *
     * @param ProductModel $product
     * @return array
     */
    private function getProductIdentifiers(ProductModel $product): array
    {
        $identifiers = [];
        
        // GTIN mappings
        $gtinMappings = [
            'gtin8' => $this->configHelper->getStructuredDataGtin8Attribute(),
            'gtin13' => $this->configHelper->getStructuredDataGtin13Attribute(),
            'gtin14' => $this->configHelper->getStructuredDataGtin14Attribute(),
        ];
        
        foreach ($gtinMappings as $gtinType => $attribute) {
            if ($attribute && $product->getData($attribute)) {
                $value = $product->getData($attribute);
                if (is_string($value) && $value !== '') {
                    $identifiers[$gtinType] = $value;
                    break; // Only use one GTIN type
                }
            }
        }
        
        // MPN
        $mpnAttribute = $this->configHelper->getStructuredDataMpnAttribute();
        if ($mpnAttribute && $product->getData($mpnAttribute)) {
            $mpn = $product->getData($mpnAttribute);
            if (is_string($mpn) && $mpn !== '') {
                $identifiers['mpn'] = $mpn;
            }
        }
        
        return $identifiers;
    }
    
    /**
     * Get product offers
     *
     * @param ProductModel $product
     * @return array
     */
    private function getProductOffers(ProductModel $product): array
    {
        $offers = [
            '@type' => 'Offer',
            'url' => $this->cleanUrl($product->getProductUrl()),
            'priceCurrency' => $this->storeManager->getStore()->getCurrentCurrencyCode(),
            'availability' => $this->getProductAvailability($product),
            'priceValidUntil' => $this->getPriceValidUntil()
        ];
        
        // Add price
        $price = $this->getProductPrice($product);
        if ($price !== null) {
            $offers['price'] = $this->formatPrice($price);
        }
        
        // Add condition
        $condition = $this->getProductCondition($product);
        if ($condition) {
            $offers['itemCondition'] = $condition;
        }
        
        // Add seller (organization)
        $seller = $this->getSellerInfo();
        if ($seller) {
            $offers['seller'] = $seller;
        }
        
        return $offers;
    }
    
    /**
     * Get product price
     *
     * @param ProductModel $product
     * @return float|null
     */
    private function getProductPrice(ProductModel $product): ?float
    {
        try {
            // Get the final price without tax first
            $price = $product->getFinalPrice();
            
            if ($price === null) {
                return null;
            }
            
            // Use CatalogHelper to get price with tax (same as displayed to customer)
            $priceWithTax = $this->catalogHelper->getTaxPrice(
                $product,
                $price,
                true, // include tax
                null, // shipping address
                null, // billing address
                null, // customer tax class
                $this->storeManager->getStore(), // store
                null, // price includes tax (auto-detect)
                true  // round price
            );
            
            return $priceWithTax ? (float)$priceWithTax : null;
        } catch (\Exception $e) {
            $this->logger->debug('Error getting product price with tax: ' . $e->getMessage());
            
            // Fallback to simple final price
            try {
                $price = $product->getFinalPrice();
                return $price !== null ? (float)$price : null;
            } catch (\Exception $fallbackException) {
                return null;
            }
        }
    }
    
    /**
     * Get product availability
     *
     * @param ProductModel $product
     * @return string
     */
    private function getProductAvailability(ProductModel $product): string
    {
        try {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            
            if ($stockItem->getIsInStock()) {
                if ($stockItem->getQty() > 0) {
                    return 'https://schema.org/InStock';
                } else if ($stockItem->getBackorders() > 0) {
                    return 'https://schema.org/BackOrder';
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error getting stock status: ' . $e->getMessage());
        }
        
        return 'https://schema.org/OutOfStock';
    }
    
    /**
     * Get product condition
     *
     * @param ProductModel $product
     * @return string|null
     */
    private function getProductCondition(ProductModel $product): ?string
    {
        $conditionAttribute = $this->configHelper->getStructuredDataConditionAttribute();
        if ($conditionAttribute && $product->getData($conditionAttribute)) {
            $condition = $product->getAttributeText($conditionAttribute);
            if (!$condition) {
                $condition = $product->getData($conditionAttribute);
            }
            
            // Map to schema.org values
            $conditionMap = [
                'new' => 'https://schema.org/NewCondition',
                'used' => 'https://schema.org/UsedCondition',
                'refurbished' => 'https://schema.org/RefurbishedCondition',
                'damaged' => 'https://schema.org/DamagedCondition'
            ];
            
            $conditionLower = strtolower((string)$condition);
            return $conditionMap[$conditionLower] ?? null;
        }
        
        // Default to new condition
        return 'https://schema.org/NewCondition';
    }
    
    /**
     * Get price valid until date
     *
     * @return string
     */
    private function getPriceValidUntil(): string
    {
        // Set price validity to 1 year from now
        $date = new \DateTime();
        $date->add(new \DateInterval('P1Y'));
        return $date->format(\DateTime::ATOM);
    }
    
    /**
     * Get seller information
     *
     * @return array|null
     */
    private function getSellerInfo(): ?array
    {
        $organizationName = $this->configHelper->getStructuredDataOrganizationName();
        if (!$organizationName) {
            return null;
        }
        
        $data = [
            '@type' => $this->getOrganizationType(),
            'name' => $organizationName,
            'url' => $this->storeManager->getStore()->getBaseUrl()
        ];
        
        // Add logo
        $logo = $this->configHelper->getStructuredDataOrganizationLogo();
        if ($logo) {
            $data['logo'] = $this->getOrganizationLogoUrl($logo);
        }
        
        // Add address
        $address = $this->getOrganizationAddress();
        if (!empty($address)) {
            $data['address'] = $address;
        }
        
        // Add contact points
        $contactPoints = $this->getOrganizationContactPoints();
        if (!empty($contactPoints)) {
            $data['contactPoint'] = $contactPoints;
        }
        
        return $data;
    }
    
    /**
     * Get product reviews and ratings
     *
     * @param ProductModel $product
     * @return array
     */
    private function getProductReviews(ProductModel $product): array
    {
        $data = [];
        
        try {
            $reviewCollection = $this->reviewFactory->create()->getCollection()
                ->addStoreFilter($this->storeManager->getStore()->getId())
                ->addEntityFilter('product', $product->getId())
                ->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
                ->setDateOrder();
            
            if ($reviewCollection->getSize() > 0) {
                // Get rating summary
                $product->getRatingSummary();
                $ratingSummary = $product->getRatingSummary();
                
                if ($ratingSummary && $ratingSummary->getCount() > 0) {
                    $data['aggregateRating'] = [
                        '@type' => 'AggregateRating',
                        'ratingValue' => round($ratingSummary->getRatingSummary() / 20, 1), // Convert from 0-100 to 0-5
                        'bestRating' => '5',
                        'worstRating' => '1',
                        'ratingCount' => $ratingSummary->getCount()
                    ];
                }
                
                // Add individual reviews if enabled
                if ($this->configHelper->isStructuredDataReviewsEnabled()) {
                    $reviews = [];
                    $reviewLimit = 5; // Limit number of reviews to include
                    $reviewCount = 0;
                    
                    foreach ($reviewCollection as $review) {
                        if ($reviewCount >= $reviewLimit) {
                            break;
                        }
                        
                        $reviewData = [
                            '@type' => 'Review',
                            'author' => [
                                '@type' => 'Person',
                                'name' => $review->getNickname()
                            ],
                            'datePublished' => $this->formatDate($review->getCreatedAt()),
                            'reviewBody' => $review->getDetail()
                        ];
                        
                        // Add rating if available
                        $ratings = $review->getRatingVotes();
                        if ($ratings && count($ratings)) {
                            $totalRating = 0;
                            $ratingCount = 0;
                            foreach ($ratings as $rating) {
                                $totalRating += $rating->getValue();
                                $ratingCount++;
                            }
                            if ($ratingCount > 0) {
                                $reviewData['reviewRating'] = [
                                    '@type' => 'Rating',
                                    'ratingValue' => round($totalRating / $ratingCount / 20, 1), // Convert from 0-100 to 0-5
                                    'bestRating' => '5',
                                    'worstRating' => '1'
                                ];
                            }
                        }
                        
                        $reviews[] = $reviewData;
                        $reviewCount++;
                    }
                    
                    if (!empty($reviews)) {
                        $data['review'] = $reviews;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error getting product reviews: ' . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Get custom attributes based on mapping
     *
     * @param ProductModel $product
     * @return array
     */
    private function getCustomAttributes(ProductModel $product): array
    {
        $customAttributes = [];
        
        // Get attribute mappings from configuration
        $mappings = $this->attributeMapper->getMappings('product');
        
        foreach ($mappings as $schemaField => $magentoAttribute) {
            // Use AttributeMapper to get properly processed value
            $value = $this->attributeMapper->getMappedValue('product', $schemaField, $product);
            
            // Check if value is valid (not null, not empty, not an error)
            if ($value !== null && $value !== '' && !$this->isErrorValue($value)) {
                // Special handling for specific fields
                $formattedValue = $this->formatSchemaFieldValue($schemaField, $value, $product);
                
                // Only add if formatted value is not null
                if ($formattedValue !== null) {
                    $customAttributes[$schemaField] = $formattedValue;
                }
            }
        }
        
        return $customAttributes;
    }
    
    /**
     * Check if value is an error value
     *
     * @param mixed $value
     * @return bool
     */
    private function isErrorValue($value): bool
    {
        if (is_string($value) && str_starts_with($value, 'Error_')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Format schema field value based on field type
     *
     * @param string $schemaField
     * @param mixed $value
     * @param ProductModel $product
     * @return mixed
     */
    private function formatSchemaFieldValue(string $schemaField, $value, ProductModel $product)
    {
        switch ($schemaField) {
            case 'category':
                return $this->formatProductCategories($product);
                
            case 'keywords':
                return $this->formatProductKeywords($value);
                
            case 'manufacturer':
                // Avoid duplicate manufacturer if we already have brand
                $brand = $this->getProductBrand($product);
                if ($brand && strtolower($brand) === strtolower((string)$value)) {
                    return null; // Skip if it's the same as brand
                }
                return $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Format product categories for schema.org
     *
     * @param ProductModel $product
     * @return array|null
     */
    private function formatProductCategories(ProductModel $product): ?array
    {
        try {
            // Get category IDs first
            $categoryIds = $product->getCategoryIds();
            if (empty($categoryIds)) {
                return null;
            }
            
            $categories = [];
            
            // Load categories by IDs
            foreach ($categoryIds as $categoryId) {
                try {
                    /** @var \Magento\Catalog\Model\Category $category */
                    $category = $this->categoryRepository->get($categoryId, $this->storeManager->getStore()->getId());
                    
                    if ($category && $category->getName() && $category->getIsActive()) {
                        $categories[] = $category->getName();
                    }
                } catch (\Exception $e) {
                    // Skip this category if it can't be loaded
                    $this->logger->debug('Error loading category ' . $categoryId . ': ' . $e->getMessage());
                    continue;
                }
            }
            
            return !empty($categories) ? $categories : null;
        } catch (\Exception $e) {
            $this->logger->debug('Error getting product categories: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format product keywords for schema.org
     *
     * @param mixed $value
     * @return string|null
     */
    private function formatProductKeywords($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        if (is_array($value)) {
            $cleanKeywords = array_filter($value); // Remove empty values
            return !empty($cleanKeywords) ? implode(', ', $cleanKeywords) : null;
        }
        
        if (is_string($value)) {
            // Split by common separators, clean up, and rejoin with commas
            $keywords = preg_split('/[,;\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($keywords)) {
                $cleanKeywords = array_map('trim', $keywords);
                return implode(', ', $cleanKeywords);
            }
        }
        
        return null;
    }
    
    /**
     * Get additional properties (weight, dimensions, etc.)
     *
     * @param ProductModel $product
     * @return array
     */
    private function getAdditionalProperties(ProductModel $product): array
    {
        $properties = [];
        
        // Don't add weight here as it's already added as a main field in custom attributes
        // This prevents duplication in the JSON-LD output
        
        // Add other mapped properties
        $propertyMappings = $this->configHelper->getStructuredDataPropertyMappings();
        foreach ($propertyMappings as $propertyName => $attribute) {
            if ($product->getData($attribute)) {
                $value = $product->getAttributeText($attribute);
                if (!$value) {
                    $value = $product->getData($attribute);
                }
                
                if ($value !== null && $value !== '') {
                    $properties[] = [
                        '@type' => 'PropertyValue',
                        'name' => $propertyName,
                        'value' => $value
                    ];
                }
            }
        }
        
        return $properties;
    }
    
    /**
     * Get weight unit from configuration
     *
     * @return string
     */
    private function getWeightUnit(): string
    {
        $unit = $this->scopeConfig->getValue(
            'general/locale/weight_unit',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
        
        // Map Magento units to schema.org units
        $unitMap = [
            'kgs' => 'KGM',  // Kilogramy
            'lbs' => 'LBR'   // Funty
        ];
        
        // Default to KGM (kilograms) for European stores
        return $unitMap[$unit] ?? 'KGM';
    }
    
    /**
     * Get organization type for seller info
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
     * Get organization logo URL for seller info
     *
     * @param string $logoPath
     * @return string
     */
    private function getOrganizationLogoUrl(string $logoPath): string
    {
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }
        
        // Build full URL for relative path
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $baseUrl . 'logo/' . ltrim($logoPath, '/');
    }
    
    /**
     * Get organization contact points for seller info
     *
     * @return array
     */
    private function getOrganizationContactPoints(): array
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
     * Get organization address for seller info
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
}

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
use Magento\Review\Model\Review as ReviewModel;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Cache\CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Review structured data generator
 * 
 * Generates schema.org Review structured data for individual product reviews.
 */
class Review extends AbstractGenerator
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    
    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param CacheManager $cacheManager
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Config $configHelper,
        CacheManager $cacheManager,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($configHelper, $cacheManager, $storeManager, $logger, $scopeConfig);
        $this->productRepository = $productRepository;
    }
    
    /**
     * Generate structured data for review
     *
     * @param mixed $entity
     * @param array $context
     * @return array
     */
    protected function doGenerate($entity, array $context): array
    {
        /** @var ReviewModel $review */
        $review = $entity;
        
        $data = [
            '@type' => 'Review',
            'author' => [
                '@type' => 'Person',
                'name' => $review->getNickname()
            ],
            'datePublished' => $this->formatDate($review->getCreatedAt()),
            'reviewBody' => $review->getDetail()
        ];
        
        // Add rating if available
        $ratingData = $this->getReviewRating($review);
        if ($ratingData) {
            $data['reviewRating'] = $ratingData;
        }
        
        // Add item reviewed
        if ($review->getEntityPkValue()) {
            $itemReviewed = $this->getItemReviewed($review);
            if ($itemReviewed) {
                $data['itemReviewed'] = $itemReviewed;
            }
        }
        
        // Add publisher (organization)
        if ($this->configHelper->isStructuredDataOrganizationEnabled()) {
            $organizationName = $this->configHelper->getStructuredDataOrganizationName();
            if ($organizationName) {
                $data['publisher'] = [
                    '@type' => 'Organization',
                    'name' => $organizationName
                ];
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
        return $entity instanceof ReviewModel;
    }
    
    /**
     * Get schema type
     *
     * @return string
     */
    public function getSchemaType(): string
    {
        return 'Review';
    }
    
    /**
     * Check if generator is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configHelper->isStructuredDataReviewsEnabled($storeId);
    }
    
    /**
     * Get entity ID
     *
     * @param mixed $entity
     * @return string|null
     */
    protected function getEntityId($entity): ?string
    {
        return $entity->getId() ? 'review_' . $entity->getId() : null;
    }
    
    /**
     * Get review rating data
     *
     * @param ReviewModel $review
     * @return array|null
     */
    private function getReviewRating(ReviewModel $review): ?array
    {
        $ratings = $review->getRatingVotes();
        
        if (!$ratings || !count($ratings)) {
            return null;
        }
        
        $totalRating = 0;
        $ratingCount = 0;
        
        foreach ($ratings as $rating) {
            $totalRating += $rating->getValue();
            $ratingCount++;
        }
        
        if ($ratingCount === 0) {
            return null;
        }
        
        // Convert from 0-100 scale to 0-5 scale
        $averageRating = round(($totalRating / $ratingCount) / 20, 1);
        
        return [
            '@type' => 'Rating',
            'ratingValue' => $averageRating,
            'bestRating' => '5',
            'worstRating' => '1'
        ];
    }
    
    /**
     * Get item reviewed (product)
     *
     * @param ReviewModel $review
     * @return array|null
     */
    private function getItemReviewed(ReviewModel $review): ?array
    {
        if ($review->getEntityId() != 1) { // 1 = product entity
            return null;
        }
        
        try {
            $product = $this->productRepository->getById(
                $review->getEntityPkValue(),
                false,
                $this->storeManager->getStore()->getId()
            );
            
            return [
                '@type' => 'Product',
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'url' => $this->cleanUrl($product->getProductUrl())
            ];
        } catch (\Exception $e) {
            $this->logger->debug('Error loading product for review: ' . $e->getMessage());
            return null;
        }
    }
}

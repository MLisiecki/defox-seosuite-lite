<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\MetaTag;

use Defox\SEOSuite\Api\TemplateRepositoryInterface;
use Defox\SEOSuite\Template\VariableProcessorFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Enhanced Meta Tag Manager
 * 
 * Main class responsible for applying meta tag templates to entities with improved performance
 */
class Manager
{
    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'defox_seosuite_metatag_';
    
    /**
     * Cache lifetime (1 hour)
     */
    private const CACHE_LIFETIME = 3600;

    /**
     * @var TemplateRepositoryInterface
     */
    private TemplateRepositoryInterface $templateRepository;

    /**
     * @var VariableProcessorFactory
     */
    private VariableProcessorFactory $processorFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var array
     */
    private array $processedCache = [];

    /**
     * @param TemplateRepositoryInterface $templateRepository
     * @param VariableProcessorFactory $processorFactory
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     */
    public function __construct(
        TemplateRepositoryInterface $templateRepository,
        VariableProcessorFactory $processorFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->templateRepository = $templateRepository;
        $this->processorFactory = $processorFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Apply meta tag templates to entity
     *
     * @param mixed $entity
     * @param string $entityType
     * @param string $templateType
     * @return array
     * @throws \JsonException
     */
    public function applyTemplates($entity, string $entityType, string $templateType = 'comprehensive'): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : spl_object_hash($entity);
        
        // Check cache first
        $cacheKey = $this->getCacheKey($entityType, $entityId, $templateType, $storeId);
        if (isset($this->processedCache[$cacheKey])) {
            return $this->processedCache[$cacheKey];
        }
        
        $cachedResult = $this->cache->load($cacheKey);
        if ($cachedResult !== false) {
            $result = json_decode($cachedResult, true);
            if (is_array($result)) {
                $this->processedCache[$cacheKey] = $result;
                return $result;
            }
        }

        // Get applicable templates
        $templates = $this->templateRepository->getByTypeAndEntityType($templateType, $entityType, $storeId);
        
        if (empty($templates)) {
            $this->logDebug('No templates found for entity type and template type');
            return [];
        }

        $result = [];
        
        foreach ($templates as $template) {
            
            if (!$template->getIsActive()) {
                continue;
            }

            // Check conditions if any
            if ($template->getConditionsSerialized() && !$this->checkConditions($entity, $template->getConditionsSerialized())) {
                continue;
            }

            // Get processor for this entity
            try {
                $processor = $this->processorFactory->create($entity);
                
                if (!$processor->canProcess($entity)) {
                    continue;
                }
                
                // Process different meta tag types
                if ($template->getMetaTitleTemplate()) {
                    $result['meta_title'] = $processor->process($template->getMetaTitleTemplate(), $entity);
                }

                if ($template->getMetaDescriptionTemplate()) {
                    $result['meta_description'] = $processor->process($template->getMetaDescriptionTemplate(), $entity);
                }

                if ($template->getMetaKeywordsTemplate()) {
                    $result['meta_keywords'] = $processor->process($template->getMetaKeywordsTemplate(), $entity);
                }

                if ($template->getMetaRobotsTemplate()) {
                    $result['meta_robots'] = $template->getMetaRobotsTemplate();
                }

                if ($template->getOgTitleTemplate()) {
                    $result['og_title'] = $processor->process($template->getOgTitleTemplate(), $entity);
                }

                if ($template->getOgDescriptionTemplate()) {
                    $result['og_description'] = $processor->process($template->getOgDescriptionTemplate(), $entity);
                }

                if ($template->getOgTypeTemplate()) {
                    $result['og_type'] = $template->getOgTypeTemplate();
                }

                if ($template->getOgImageTemplate()) {
                    $result['og_image'] = $processor->process($template->getOgImageTemplate(), $entity);
                }

                // If we have a result, break (highest priority template wins)
                if (!empty($result)) {
                    break;
                }
                
            } catch (\Exception $e) {
                $this->logDebug('Error processing template', [
                    'template_id' => $template->getId(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // Cache the result
        $this->processedCache[$cacheKey] = $result;
        $this->cache->save(json_encode($result, JSON_THROW_ON_ERROR), $cacheKey, ['DEFOX_SEO_METATAG'], self::CACHE_LIFETIME);

        return $result;
    }

    /**
     * Generate cache key
     *
     * @param string $entityType
     * @param mixed $entityId
     * @param string $templateType
     * @param int $storeId
     * @return string
     */
    private function getCacheKey(string $entityType, $entityId, string $templateType, int $storeId): string
    {
        return self::CACHE_PREFIX . md5($entityType . '_' . $entityId . '_' . $templateType . '_' . $storeId);
    }

    /**
     * Log debug information
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug('[Defox_SEOSuite] MetaTag\\Manager: ' . $message, $context);
    }

    /**
     * Check if entity matches template conditions
     *
     * @param mixed $entity
     * @param string $conditionsSerialized
     * @return bool
     */
    private function checkConditions(mixed $entity, string $conditionsSerialized): bool
    {
        if (empty($conditionsSerialized)) {
            return true;
        }

        try {
            $conditions = json_decode($conditionsSerialized, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($conditions)) {
                return true;
            }

            foreach ($conditions as $attribute => $expectedValues) {
                if (!method_exists($entity, 'getData')) {
                    continue;
                }

                $entityValue = $entity->getData($attribute);
                
                // Handle different condition types
                if (is_array($expectedValues)) {
                    // Handle range conditions
                    if (isset($expectedValues['min']) || isset($expectedValues['max'])) {
                        $numericValue = (float)$entityValue;
                        if (isset($expectedValues['min']) && $numericValue < $expectedValues['min']) {
                            return false;
                        }
                        if (isset($expectedValues['max']) && $numericValue > $expectedValues['max']) {
                            return false;
                        }
                    } else {
                        // Handle array of values
                        if (!in_array($entityValue, $expectedValues, true)) {
                            return false;
                        }
                    }
                } else {
                    // Handle single value condition
                    if ($entityValue != $expectedValues) {
                        return false;
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logDebug('Error checking conditions', [
                'error' => $e->getMessage(),
                'conditions' => $conditionsSerialized
            ]);
            return true; // If conditions are malformed, allow template
        }
    }

    /**
     * Clear cache for specific entity
     *
     * @param mixed $entity
     * @param string $entityType
     * @return void
     */
    public function clearCacheForEntity(mixed $entity, string $entityType): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : spl_object_hash($entity);
        
        $templateTypes = ['comprehensive', 'meta_title', 'meta_description', 'meta_keywords', 'meta_robots', 'open_graph'];
        
        foreach ($templateTypes as $templateType) {
            $cacheKey = $this->getCacheKey($entityType, $entityId, $templateType, $storeId);
            unset($this->processedCache[$cacheKey]);
            $this->cache->remove($cacheKey);
        }
    }

    /**
     * Clear all meta tag cache
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        $this->processedCache = [];
        $this->cache->clean(['DEFOX_SEO_METATAG']);
    }
}

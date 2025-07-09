<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\StructuredData;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Defox\SEOSuite\Model\StructuredData\GeneratorPool;
use Defox\SEOSuite\Model\StructuredData\Renderer\JsonLd;
use Defox\SEOSuite\Model\StructuredData\Validator\SchemaValidator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Preview structured data controller
 * 
 * Provides admin interface for previewing and validating structured data.
 */
class Preview extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Defox_SEOSuite::structured_data_preview';
    
    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;
    
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;
    
    /**
     * @var GeneratorPool
     */
    private GeneratorPool $generatorPool;
    
    /**
     * @var JsonLd
     */
    private JsonLd $jsonLdRenderer;
    
    /**
     * @var SchemaValidator
     */
    private SchemaValidator $schemaValidator;
    
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    
    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;
    
    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param GeneratorPool $generatorPool
     * @param JsonLd $jsonLdRenderer
     * @param SchemaValidator $schemaValidator
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PageRepositoryInterface $pageRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        GeneratorPool $generatorPool,
        JsonLd $jsonLdRenderer,
        SchemaValidator $schemaValidator,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        PageRepositoryInterface $pageRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->generatorPool = $generatorPool;
        $this->jsonLdRenderer = $jsonLdRenderer;
        $this->schemaValidator = $schemaValidator;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->pageRepository = $pageRepository;
        $this->logger = $logger;
    }
    
    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            return $this->handleAjaxRequest();
        }
        
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Defox_SEOSuite::structured_data_preview');
        $resultPage->getConfig()->getTitle()->prepend(__('Structured Data Preview'));
        
        return $resultPage;
    }
    
    /**
     * Handle AJAX request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function handleAjaxRequest()
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $entityType = $this->getRequest()->getParam('entity_type');
            $entityId = (int)$this->getRequest()->getParam('entity_id');
            
            // Validation
            if (!$entityType || !$entityId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Missing required parameters: entity_type and entity_id')
                ]);
            }
            
            if (!in_array($entityType, ['product', 'category', 'page'])) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Invalid entity type: %1', $entityType)
                ]);
            }
            
            // Load entity with detailed error handling
            try {
                $entity = $this->loadEntity($entityType, $entityId);
            } catch (\Exception $e) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Error loading entity: %1', $e->getMessage())
                ]);
            }
            
            if (!$entity) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Entity not found: %1 with ID %2', $entityType, $entityId)
                ]);
            }
            
            // Generate structured data with error handling
            try {
                $structuredData = $this->generatorPool->generate($entity);
                
                // Debug logging
                $this->logger->info('Generated structured data count: ' . count($structuredData));
                $this->logger->info('Generated structured data: ' . json_encode($structuredData));
                
            } catch (\Exception $e) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Error generating structured data: %1', $e->getMessage())
                ]);
            }
            
            if (empty($structuredData)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('No structured data generated. Check if generators are enabled in configuration.')
                ]);
            }
            
            // Render JSON-LD
            $jsonLd = '';
            $rawJson = [];
            
            try {
                if (!empty($structuredData)) {
                    $jsonLd = $this->jsonLdRenderer->renderMultiple($structuredData);
                    $rawJson = $structuredData;
                }
            } catch (\Exception $e) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Error rendering JSON-LD: %1', $e->getMessage())
                ]);
            }
            
            // Validate
            $this->schemaValidator->clear();
            $isValid = true;
            $errors = [];
            $warnings = [];
            
            try {
                foreach ($structuredData as $data) {
                    if (!$this->schemaValidator->validate($data)) {
                        $isValid = false;
                    }
                }
                
                $errors = $this->schemaValidator->getErrors();
                $warnings = $this->schemaValidator->getWarnings();
            } catch (\Exception $e) {
                $warnings[] = 'Validation error: ' . $e->getMessage();
            }
            
            return $result->setData([
                'success' => true,
                'jsonLd' => $jsonLd,
                'rawData' => $rawJson,
                'isValid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
                'debugInfo' => [
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'generatorCount' => count($this->generatorPool->getGenerators()),
                    'dataCount' => count($structuredData)
                ]
            ]);
            
        } catch (\Exception $e) {
            // Log the full error for debugging
            $this->logger->error('Structured data preview error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_params' => $this->getRequest()->getParams()
            ]);
            
            return $result->setData([
                'success' => false,
                'message' => __('An unexpected error occurred: %1', $e->getMessage())
            ]);
        }
    }
    
    /**
     * Load entity by type and ID
     *
     * @param string $type
     * @param int $id
     * @return mixed|null
     * @throws \Exception
     */
    private function loadEntity(string $type, int $id)
    {
        try {
            switch ($type) {
                case 'product':
                    $product = $this->productRepository->getById($id);
                    // Verify product is visible
                    if (!$product->getId()) {
                        throw new \Exception("Product with ID $id not found");
                    }
                    return $product;
                    
                case 'category':
                    $category = $this->categoryRepository->get($id);
                    if (!$category->getId()) {
                        throw new \Exception("Category with ID $id not found");
                    }
                    return $category;
                    
                case 'page':
                    $page = $this->pageRepository->getById($id);
                    if (!$page->getId()) {
                        throw new \Exception("CMS Page with ID $id not found");
                    }
                    return $page;
                    
                default:
                    throw new \Exception("Unsupported entity type: $type");
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            throw new \Exception("Entity not found: {$e->getMessage()}");
        } catch (\Exception $e) {
            // Re-throw with more context
            throw new \Exception("Error loading $type with ID $id: {$e->getMessage()}");
        }
    }
}

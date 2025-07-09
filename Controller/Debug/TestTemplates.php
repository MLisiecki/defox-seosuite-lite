<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Debug;

use Defox\SEOSuite\Model\MetaTag\Manager;
use Defox\SEOSuite\Template\VariableProcessorFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Test controller for debugging template variables
 */
class TestTemplates implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var Manager
     */
    private Manager $metaTagManager;

    /**
     * @var VariableProcessorFactory
     */
    private VariableProcessorFactory $processorFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param Manager $metaTagManager
     * @param VariableProcessorFactory $processorFactory
     * @param ProductRepositoryInterface $productRepository
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        Manager $metaTagManager,
        VariableProcessorFactory $processorFactory,
        ProductRepositoryInterface $productRepository,
        PageRepositoryInterface $pageRepository
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->metaTagManager = $metaTagManager;
        $this->processorFactory = $processorFactory;
        $this->productRepository = $productRepository;
        $this->pageRepository = $pageRepository;
    }

    /**
     * Execute action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        
        try {
            $type = $this->request->getParam('type', 'product');
            $id = (int)$this->request->getParam('id', 1);
            $template = $this->request->getParam('template', 'Test: {{product.name}} - {{product.price}}');

            $data = [];
            
            if ($type === 'product') {
                $entity = $this->productRepository->getById($id);
                $processor = $this->processorFactory->create($entity);
                
                if ($processor && $processor->canProcess($entity)) {
                    $processed = $processor->process($template, $entity);
                    $data = [
                        'entity_type' => 'product',
                        'entity_id' => $entity->getId(),
                        'entity_name' => $entity->getName(),
                        'template' => $template,
                        'processed' => $processed,
                        'available_variables' => $processor->getAvailableVariables()
                    ];
                }
            } elseif ($type === 'cms_page') {
                $entity = $this->pageRepository->getById($id);
                $processor = $this->processorFactory->create($entity);
                
                if ($processor && $processor->canProcess($entity)) {
                    $processed = $processor->process($template, $entity);
                    $data = [
                        'entity_type' => 'cms_page',
                        'entity_id' => $entity->getId(),
                        'entity_title' => $entity->getTitle(),
                        'template' => $template,
                        'processed' => $processed,
                        'available_variables' => $processor->getAvailableVariables()
                    ];
                }
            }

            $result->setData([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }
}

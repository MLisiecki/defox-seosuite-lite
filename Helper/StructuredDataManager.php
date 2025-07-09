<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Helper;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\StructuredData\GeneratorPool;
use Defox\SEOSuite\Model\StructuredData\Renderer\JsonLd;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Structured Data Manager
 * 
 * Manages structured data generation and storage for current page context.
 * Works similarly to Magento's metadata management in PageConfig.
 */
class StructuredDataManager
{
    /**
     * @var Config
     */
    private Config $configHelper;
    
    /**
     * @var GeneratorPool
     */
    private GeneratorPool $generatorPool;
    
    /**
     * @var JsonLd
     */
    private JsonLd $jsonLdRenderer;
    
    /**
     * @var Registry
     */
    private Registry $registry;
    
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;
    
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;
    
    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;
    
    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * @var array|null
     */
    private ?array $structuredDataItems = null;
    
    /**
     * @var string|null
     */
    private ?string $renderedJsonLd = null;

    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param GeneratorPool $generatorPool
     * @param JsonLd $jsonLdRenderer
     * @param Registry $registry
     * @param HttpRequest $request
     * @param StoreManagerInterface $storeManager
     * @param PageRepositoryInterface $pageRepository
     * @param PageFactory $pageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $configHelper,
        GeneratorPool $generatorPool,
        JsonLd $jsonLdRenderer,
        Registry $registry,
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        PageRepositoryInterface $pageRepository,
        PageFactory $pageFactory,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->generatorPool = $generatorPool;
        $this->jsonLdRenderer = $jsonLdRenderer;
        $this->registry = $registry;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->pageRepository = $pageRepository;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
    }

    /**
     * Get rendered JSON-LD for current page
     *
     * @return string
     */
    public function getJsonLd(): string
    {
        if (!$this->configHelper->isStructuredDataEnabled()) {
            return '';
        }

        // Return cached result if already rendered
        if ($this->renderedJsonLd !== null) {
            return $this->renderedJsonLd;
        }

        try {
            $structuredDataItems = $this->getStructuredDataItems();
            
            if (!empty($structuredDataItems)) {
                $this->renderedJsonLd = $this->jsonLdRenderer->renderMultiple($structuredDataItems);
            } else {
                $this->renderedJsonLd = '';
            }
            
        } catch (\Exception $e) {
            $this->logger->error(
                'SEOSuite: Error generating JSON-LD: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->renderedJsonLd = '';
        }

        return $this->renderedJsonLd;
    }
    
    /**
     * Get structured data items for current page
     *
     * @return array
     */
    public function getStructuredDataItems(): array
    {
        // Return cached result if already generated
        if ($this->structuredDataItems !== null) {
            return $this->structuredDataItems;
        }

        $this->structuredDataItems = [];
        
        try {
            $context = $this->getContext();
            
            // Generate structured data based on page type
            $entity = $this->getCurrentEntity();
            
            if ($entity !== null) {
                // Generate data for current entity
                $entityData = $this->generatorPool->generate($entity, $context);
                $this->structuredDataItems = array_merge($this->structuredDataItems, $entityData);
            }
            
            // Always try to add organization and website data on homepage
            if ($this->isHomePage()) {
                // Add organization data
                $organizationData = $this->generatorPool->generate(
                    $this->storeManager->getStore(),
                    ['type' => 'organization']
                );
                $this->structuredDataItems = array_merge($this->structuredDataItems, $organizationData);
                
                // Add website data with sitelinks searchbox
                $websiteData = $this->generatorPool->generate(
                    $this->storeManager->getStore(),
                    ['type' => 'website']
                );
                $this->structuredDataItems = array_merge($this->structuredDataItems, $websiteData);
            }
            
        } catch (\Exception $e) {
            $this->logger->error(
                'SEOSuite: Error generating structured data items: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        
        return $this->structuredDataItems;
    }
    
    /**
     * Check if structured data is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->configHelper->isStructuredDataEnabled();
    }
    
    /**
     * Reset cached data (useful for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->structuredDataItems = null;
        $this->renderedJsonLd = null;
    }
    
    /**
     * Get current entity based on page type
     *
     * @return Product|Category|Page|null
     */
    private function getCurrentEntity()
    {
        // Check for product page
        $product = $this->registry->registry('current_product');
        if ($product instanceof Product) {
            return $product;
        }
        
        // Check for category page
        $category = $this->registry->registry('current_category');
        if ($category instanceof Category) {
            return $category;
        }
        
        // Check for CMS page - try multiple registry keys
        $cmsPage = $this->registry->registry('cms_page');
        if ($cmsPage instanceof Page) {
            return $cmsPage;
        }
        
        // Alternative registry key for CMS pages
        $cmsPage = $this->registry->registry('current_page');
        if ($cmsPage instanceof Page) {
            return $cmsPage;
        }
        
        // Try to detect CMS page from request
        if ($this->isCmsPageRequest()) {
            return $this->getCmsPageFromRequest();
        }
        
        return null;
    }
    
    /**
     * Get context for structured data generation
     *
     * @return array
     */
    private function getContext(): array
    {
        $context = [];
        
        // Add pagination info
        $page = (int)$this->request->getParam('p', 1);
        if ($page > 1) {
            $context['current_page'] = $page;
        }
        
        // Add sorting info
        $sortOrder = $this->request->getParam('product_list_order');
        if ($sortOrder !== null) {
            $context['sort_order'] = (string)$sortOrder;
            $context['sort_direction'] = (string)$this->request->getParam('product_list_dir', 'asc');
        }
        
        // Add filter info
        $filters = [];
        $params = $this->request->getParams();
        foreach ($params as $key => $value) {
            if (strpos((string)$key, 'filter_') === 0 || in_array($key, ['cat', 'price'], true)) {
                $filters[$key] = $value;
            }
        }
        if (!empty($filters)) {
            $context['filters'] = $filters;
        }
        
        return $context;
    }
    
    /**
     * Check if current page is homepage
     *
     * @return bool
     */
    private function isHomePage(): bool
    {
        $currentUrl = $this->request->getRequestUri();
        return $currentUrl === '/' || $currentUrl === '/index.php' || $currentUrl === '/index.php/';
    }
    
    /**
     * Check if current request is for a CMS page
     *
     * @return bool
     */
    private function isCmsPageRequest(): bool
    {
        $moduleName = $this->request->getModuleName();
        $controllerName = $this->request->getControllerName();
        $actionName = $this->request->getActionName();
        
        // Check if this is a CMS page request
        return ($moduleName === 'cms' && $controllerName === 'page' && $actionName === 'view') ||
               ($moduleName === 'cms' && $controllerName === 'index' && $actionName === 'index');
    }
    
    /**
     * Get CMS page from request parameters using proper DI
     *
     * @return Page|null
     */
    private function getCmsPageFromRequest(): ?Page
    {
        try {
            // Try to get page ID from request
            $pageId = $this->request->getParam('page_id');
            if ($pageId !== null) {
                try {
                    return $this->pageRepository->getById((int)$pageId);
                } catch (NoSuchEntityException $e) {
                    $this->logger->debug('SEOSuite: CMS page not found by ID: ' . $pageId);
                }
            }
            
            // Try to get page identifier from request URI
            $identifier = trim($this->request->getRequestUri(), '/');
            if ($identifier !== '' && $identifier !== 'index.php') {
                // Remove .html suffix if present
                $identifier = preg_replace('/\.html$/', '', $identifier);
                
                $page = $this->pageFactory->create();
                $page->load($identifier, 'identifier');
                
                if ($page->getId() && $page->getIsActive()) {
                    return $page;
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->debug(
                'SEOSuite: Error getting CMS page from request: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        
        return null;
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Observer;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Canonical\Exclusion;
use Defox\SEOSuite\Model\Canonical\Product as CanonicalProduct;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Psr\Log\LoggerInterface;

/**
 * Observer that adds canonical links to product pages
 */
class AddCanonicalToProductPage implements ObserverInterface
{
    /**
     * @var PageConfig
     */
    private PageConfig $pageConfig;

    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var Registry
     */
    private Registry $registry;
    
    /**
     * @var Config
     */
    private Config $config;
    
    /**
     * @var CanonicalProduct
     */
    private CanonicalProduct $canonicalProduct;
    
    /**
     * @var Exclusion
     */
    private Exclusion $exclusion;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PageConfig $pageConfig
     * @param Http $request
     * @param Registry $registry
     * @param Config $config
     * @param CanonicalProduct $canonicalProduct
     * @param Exclusion $exclusion
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageConfig $pageConfig,
        Http $request,
        Registry $registry,
        Config $config,
        CanonicalProduct $canonicalProduct,
        Exclusion $exclusion,
        LoggerInterface $logger
    ) {
        $this->pageConfig = $pageConfig;
        $this->request = $request;
        $this->registry = $registry;
        $this->config = $config;
        $this->canonicalProduct = $canonicalProduct;
        $this->exclusion = $exclusion;
        $this->logger = $logger;
    }

    /**
     * Add canonical link to product page
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            // Check if we're on a product page
            if ($this->request->getFullActionName() !== 'catalog_product_view') {
                return;
            }
            
            // Check if canonical links are enabled
            if (!$this->config->isProductCanonicalEnabled()) {
                return;
            }
            
            // Check if product should have NOINDEX
            if ($this->exclusion->hasNoindexTag()) {
                return;
            }
            
            // Get current product from registry
            $product = $this->registry->registry('current_product');
            
            if (!$product instanceof Product) {
                return;
            }
            
            // Get canonical URL
            $canonicalUrl = $this->canonicalProduct->getCanonicalUrl($product);
            
            if ($canonicalUrl) {
                // Add canonical link to head
                $this->pageConfig->addRemotePageAsset(
                    $canonicalUrl,
                    'canonical',
                    ['attributes' => ['rel' => 'canonical']]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error adding canonical link to product page: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}

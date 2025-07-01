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
use Defox\SEOSuite\Model\Canonical\Category as CanonicalCategory;
use Defox\SEOSuite\Model\Canonical\Exclusion;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Psr\Log\LoggerInterface;

/**
 * Observer that adds canonical links to category pages
 */
class AddCanonicalToCategoryPage implements ObserverInterface
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
     * @var CanonicalCategory
     */
    private CanonicalCategory $canonicalCategory;
    
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
     * @param CanonicalCategory $canonicalCategory
     * @param Exclusion $exclusion
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageConfig $pageConfig,
        Http $request,
        Registry $registry,
        Config $config,
        CanonicalCategory $canonicalCategory,
        Exclusion $exclusion,
        LoggerInterface $logger
    ) {
        $this->pageConfig = $pageConfig;
        $this->request = $request;
        $this->registry = $registry;
        $this->config = $config;
        $this->canonicalCategory = $canonicalCategory;
        $this->exclusion = $exclusion;
        $this->logger = $logger;
    }

    /**
     * Add canonical link to category page
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            // Check if we're on a category page
            if ($this->request->getFullActionName() !== 'catalog_category_view') {
                return;
            }
            
            // Check if canonical links are enabled
            if (!$this->config->isCategoryCanonicalEnabled()) {
                return;
            }
            
            // Check if category should have NOINDEX
            if ($this->exclusion->hasNoindexTag()) {
                return;
            }
            
            // Get current category from registry
            $category = $this->registry->registry('current_category');
            
            if (!$category instanceof Category) {
                return;
            }
            
            // Get canonical URL
            $canonicalUrl = $this->canonicalCategory->getCanonicalUrl($category);
            
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
                'Error adding canonical link to category page: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}

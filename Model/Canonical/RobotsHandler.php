<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Canonical;

use Defox\SEOSuite\Helper\Config;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * Class to handle robots tags
 */
class RobotsHandler
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var PageConfig
     */
    private PageConfig $pageConfig;

    /**
     * @var Exclusion
     */
    private Exclusion $exclusion;

    /**
     * @param Config $config
     * @param Http $request
     * @param Registry $registry
     * @param PageConfig $pageConfig
     * @param Exclusion $exclusion
     */
    public function __construct(
        Config $config,
        Http $request,
        Registry $registry,
        PageConfig $pageConfig,
        Exclusion $exclusion
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->registry = $registry;
        $this->pageConfig = $pageConfig;
        $this->exclusion = $exclusion;
    }

    /**
     * Check if page should have NOINDEX based on current parameters
     *
     * @return bool
     */
    public function shouldAddNoindex(): bool
    {
        // Check if we're on a category page with filters
        if ($this->isFilteredCategoryPage()) {
            return $this->shouldNoindexFilteredCategoryPage();
        }
        
        // Check if we're on a category page with pagination
        if ($this->isPaginatedCategoryPage()) {
            return $this->shouldNoindexPaginatedCategoryPage();
        }
        
        // Check if we're on an excluded page
        if ($this->exclusion->isPageExcluded()) {
            return true;
        }
        
        return false;
    }

    /**
     * Apply robots meta tag
     *
     * @return void
     */
    public function applyRobotsTag(): void
    {
        if ($this->shouldAddNoindex()) {
            // Set NOINDEX, FOLLOW tag
            $this->pageConfig->setRobots('NOINDEX,FOLLOW');
            $this->registry->register('robots_tag', 'NOINDEX,FOLLOW', true);
        }
    }

    /**
     * Check if we're on a category page with active filters
     *
     * @return bool
     */
    private function isFilteredCategoryPage(): bool
    {
        if ($this->request->getFullActionName() !== 'catalog_category_view') {
            return false;
        }
        
        $params = $this->request->getParams();
        
        // Exclude standard parameters
        $standardParams = ['id', 'p', 'q', '___store', '___from_store'];
        
        foreach ($params as $key => $value) {
            if (!in_array($key, $standardParams) && strpos($key, 'SID') === false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if filtered category page should have NOINDEX
     *
     * @return bool
     */
    private function shouldNoindexFilteredCategoryPage(): bool
    {
        // Check configuration
        return !$this->config->shouldUseCanonicalForCategoryFilters();
    }

    /**
     * Check if we're on a paginated category page
     *
     * @return bool
     */
    private function isPaginatedCategoryPage(): bool
    {
        if ($this->request->getFullActionName() !== 'catalog_category_view') {
            return false;
        }
        
        $page = (int)$this->request->getParam('p');
        
        return $page > 1;
    }

    /**
     * Check if paginated category page should have NOINDEX
     *
     * @return bool
     */
    private function shouldNoindexPaginatedCategoryPage(): bool
    {
        return !$this->config->shouldUseCanonicalForCategoryPagination();
    }
}

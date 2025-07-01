<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\Sitemap;

use Defox\SEOSuite\Helper\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap management index block
 * 
 * Provides main overview of sitemap functionality including:
 * - Quick access to generation and validation tools
 * - Store configuration overview
 * - Recent activity summary
 */
class Index extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Defox_SEOSuite::sitemap/index.phtml';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Get generate sitemap URL
     *
     * @return string
     */
    public function getGenerateSitemapUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/generate');
    }

    /**
     * Get validate sitemap URL
     *
     * @return string
     */
    public function getValidateSitemapUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/validate');
    }

    /**
     * Get dashboard URL
     *
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/dashboard');
    }

    /**
     * Get settings URL
     *
     * @return string
     */
    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/defox_seosuite');
    }

    /**
     * Check if sitemap is globally enabled
     *
     * @return bool
     */
    public function isSitemapEnabled(): bool
    {
        return $this->config->isSitemapEnabled();
    }

    /**
     * Get stores with sitemap enabled
     *
     * @return array
     */
    public function getEnabledStores(): array
    {
        $stores = [];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $enabled = (bool)$this->config->getValue('defox_seosuite/sitemap/enabled', (int)$store->getId());
                if ($enabled) {
                    $stores[] = [
                        'id' => $store->getId(),
                        'name' => $store->getName(),
                        'code' => $store->getCode(),
                        'website' => $store->getWebsite()->getName()
                    ];
                }
            }
        }
        
        return $stores;
    }

    /**
     * Get total active stores count
     *
     * @return int
     */
    public function getTotalStoresCount(): int
    {
        return count($this->storeManager->getStores());
    }

    /**
     * Get enabled stores count
     *
     * @return int
     */
    public function getEnabledStoresCount(): int
    {
        return count($this->getEnabledStores());
    }

    /**
     * Get quick stats for display
     *
     * @return array
     */
    public function getQuickStats(): array
    {
        $enabledStores = $this->getEnabledStores();
        $totalStores = $this->getTotalStoresCount();
        
        return [
            'sitemap_enabled' => $this->isSitemapEnabled(),
            'total_stores' => $totalStores,
            'enabled_stores' => count($enabledStores),
            'configuration_complete' => $this->isSitemapEnabled() && count($enabledStores) > 0
        ];
    }

    /**
     * Get status indicators
     *
     * @return array
     */
    public function getStatusIndicators(): array
    {
        $stats = $this->getQuickStats();
        $indicators = [];

        if (!$stats['sitemap_enabled']) {
            $indicators[] = [
                'type' => 'error',
                'title' => __('Sitemap Disabled'),
                'message' => __('Sitemap generation is globally disabled'),
                'action_url' => $this->getSettingsUrl(),
                'action_text' => __('Enable in Settings')
            ];
        }

        if ($stats['enabled_stores'] === 0) {
            $indicators[] = [
                'type' => 'warning',
                'title' => __('No Active Stores'),
                'message' => __('No stores have sitemap generation enabled'),
                'action_url' => $this->getSettingsUrl(),
                'action_text' => __('Configure Stores')
            ];
        }

        if ($stats['configuration_complete']) {
            $indicators[] = [
                'type' => 'success',
                'title' => __('Configuration Complete'),
                'message' => __('Sitemap is properly configured for %1 store(s)', $stats['enabled_stores']),
                'action_url' => $this->getGenerateSitemapUrl(),
                'action_text' => __('Generate Sitemap')
            ];
        }

        return $indicators;
    }

    /**
     * Get management tools
     *
     * @return array
     */
    public function getManagementTools(): array
    {
        return [
            [
                'title' => __('Generate Sitemap'),
                'description' => __('Create XML sitemaps for your stores'),
                'icon' => 'icon-sitemap',
                'url' => $this->getGenerateSitemapUrl(),
                'primary' => true
            ],
            [
                'title' => __('Validate Sitemap'),
                'description' => __('Validate existing sitemaps for SEO compliance'),
                'icon' => 'icon-validate',
                'url' => $this->getValidateSitemapUrl(),
                'primary' => false
            ],
            [
                'title' => __('Analytics & Reports'),
                'description' => __('View generation statistics and performance'),
                'icon' => 'icon-analytics',
                'url' => $this->getDashboardUrl(),
                'primary' => false
            ],
            [
                'title' => __('Settings'),
                'description' => __('Configure sitemap generation options'),
                'icon' => 'icon-settings',
                'url' => $this->getSettingsUrl(),
                'primary' => false
            ]
        ];
    }
}

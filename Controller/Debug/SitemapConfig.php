<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Debug;

use Defox\SEOSuite\Helper\Config;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Debug controller for XML Sitemap configuration
 */
class SitemapConfig extends Action implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Config $config
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
    }

    /**
     * Execute action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        
        $data = [
            'xml_sitemap_enabled' => $this->config->isSitemapEnabled(),
            'sitemap_path' => $this->config->getSitemapPath(),
            'module_enabled' => $this->config->isEnabled(),
            'config_values' => [
                'sitemap/enabled' => $this->config->getValue('sitemap/enabled'),
                'sitemap/path' => $this->config->getValue('sitemap/path'),
                'sitemap/enable_compression' => $this->config->getValue('sitemap/enable_compression'),
                'sitemap/ping_search_engines' => $this->config->getValue('sitemap/ping_search_engines'),
                'sitemap/enable_hreflang' => $this->config->getValue('sitemap/enable_hreflang'),
            ]
        ];
        
        return $result->setData($data);
    }
}

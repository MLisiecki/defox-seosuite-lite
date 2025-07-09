<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Framework\App\Router;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Url\Filter as FilterUrl;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Router\Base;
use Psr\Log\LoggerInterface;

/**
 * Plugin to process friendly filter URLs
 */
class FilterUrlRouter
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var FilterUrl
     */
    private FilterUrl $filterUrl;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param FilterUrl $filterUrl
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        FilterUrl $filterUrl,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->filterUrl = $filterUrl;
        $this->logger = $logger;
    }

    /**
     * Before match plugin for router
     *
     * @param Base $subject
     * @param RequestInterface $request
     * @return array
     */
    public function beforeMatch(Base $subject, RequestInterface $request): array
    {
        if (!$this->config->areFriendlyUrlsEnabled() || !$request instanceof Http) {
            return [$request];
        }

        try {
            // Get request path
            $requestPath = $request->getPathInfo();
            
            // Process request path to extract filter parameters
            $filterParams = $this->filterUrl->processRequestPath($requestPath);
            
            // If we have filter parameters, add them to the request
            if (!empty($filterParams)) {
                foreach ($filterParams as $paramName => $paramValue) {
                    $request->setParam($paramName, $paramValue);
                }
                
                // Convert to standard URL for proper routing
                $standardUrl = $this->filterUrl->convertToStandardUrl($requestPath);
                
                // Set modified path info
                $standardUrlParts = parse_url($standardUrl);
                $request->setPathInfo($standardUrlParts['path']);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error processing friendly URL: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        
        return [$request];
    }
}

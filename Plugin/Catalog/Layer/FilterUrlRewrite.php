<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Catalog\Layer;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Url\Filter as FilterUrl;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to modify filter URLs
 */
class FilterUrlRewrite
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
     * After plugin for getRemoveUrl method
     *
     * @param AbstractFilter $subject
     * @param string $result
     * @return string
     */
    public function afterGetRemoveUrl(AbstractFilter $subject, string $result): string
    {
        if (!$this->config->areFriendlyUrlsEnabled()) {
            return $result;
        }

        try {
            return $this->filterUrl->convertToFriendlyUrl($result);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error converting remove URL to friendly URL: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return $result;
        }
    }

    /**
     * After plugin for getReviewUrl method (used in swatches)
     *
     * @param AbstractFilter $subject
     * @param string $result
     * @return string
     */
    public function afterGetReviewUrl(AbstractFilter $subject, string $result): string
    {
        if (!$this->config->areFriendlyUrlsEnabled()) {
            return $result;
        }

        try {
            return $this->filterUrl->convertToFriendlyUrl($result);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error converting review URL to friendly URL: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return $result;
        }
    }
}

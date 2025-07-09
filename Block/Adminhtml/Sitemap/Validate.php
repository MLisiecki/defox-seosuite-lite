<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\Sitemap;

use Defox\SEOSuite\Helper\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap validation form block
 * 
 * Provides interface for sitemap validation including:
 * - File upload validation
 * - URL-based validation
 * - Current sitemap validation
 * - Validation options configuration
 * - Progress tracking interface
 */
class Validate extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Defox_SEOSuite::sitemap/validate.phtml';

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
     * Get validation URL
     *
     * @return string
     */
    public function getValidationUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/validate');
    }

    /**
     * Get AJAX URLs for validation operations
     *
     * @return array
     */
    public function getAjaxUrls(): array
    {
        return [
            'validate' => $this->getUrl('defox_seosuite/sitemap/validate'),
            'get_current_sitemaps' => $this->getUrl('defox_seosuite/sitemap/validate', ['ajax_action' => 'get_current_sitemaps']),
            'validate_url' => $this->getUrl('defox_seosuite/sitemap/validate', ['ajax_action' => 'validate_url']),
            'get_progress' => $this->getUrl('defox_seosuite/sitemap/validate', ['ajax_action' => 'get_validation_progress'])
        ];
    }

    /**
     * Get available stores for validation
     *
     * @return array
     */
    public function getAvailableStores(): array
    {
        $stores = [];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $stores[] = [
                    'value' => $store->getId(),
                    'label' => sprintf('%s (%s)', $store->getName(), $store->getCode()),
                    'base_url' => $store->getBaseUrl(),
                    'sitemap_enabled' => (bool)$this->config->getValue('defox_seosuite/sitemap/enabled', (int)$store->getId())
                ];
            }
        }
        
        return $stores;
    }

    /**
     * Get validation types configuration
     *
     * @return array
     */
    public function getValidationTypes(): array
    {
        return [
            'file_upload' => [
                'label' => __('Upload Sitemap File'),
                'description' => __('Upload an XML or GZ sitemap file for validation'),
                'icon' => 'icon-upload',
                'supported_formats' => ['XML', 'XML.GZ']
            ],
            'url_validation' => [
                'label' => __('Validate from URL'),
                'description' => __('Enter a sitemap URL to validate remotely'),
                'icon' => 'icon-link',
                'supported_formats' => ['HTTP', 'HTTPS']
            ],
            'current_sitemap' => [
                'label' => __('Validate Current Sitemap'),
                'description' => __('Validate the currently generated sitemap for a store'),
                'icon' => 'icon-sitemap',
                'supported_formats' => ['Generated Files']
            ]
        ];
    }

    /**
     * Get validation options configuration
     *
     * @return array
     */
    public function getValidationOptions(): array
    {
        return [
            'basic' => [
                'validate_xsd' => [
                    'label' => __('XML Schema Validation'),
                    'description' => __('Validate against official sitemap XSD schema'),
                    'default' => true,
                    'required' => true
                ],
                'validate_google_rules' => [
                    'label' => __('Google Guidelines'),
                    'description' => __('Check compliance with Google sitemap guidelines'),
                    'default' => true,
                    'required' => false
                ],
                'validate_seo_best_practices' => [
                    'label' => __('SEO Best Practices'),
                    'description' => __('Validate against SEO optimization recommendations'),
                    'default' => true,
                    'required' => false
                ]
            ],
            'advanced' => [
                'check_url_accessibility' => [
                    'label' => __('Check URL Accessibility'),
                    'description' => __('Verify that URLs in sitemap are accessible (slow)'),
                    'default' => false,
                    'required' => false
                ],
                'validate_images' => [
                    'label' => __('Validate Image URLs'),
                    'description' => __('Check image URLs for accessibility and format'),
                    'default' => false,
                    'required' => false
                ],
                'check_response_time' => [
                    'label' => __('Monitor Response Times'),
                    'description' => __('Measure and report URL response times'),
                    'default' => true,
                    'required' => false
                ],
                'follow_redirects' => [
                    'label' => __('Follow Redirects'),
                    'description' => __('Follow HTTP redirects when checking URLs'),
                    'default' => true,
                    'required' => false
                ],
                'strict_mode' => [
                    'label' => __('Strict Validation Mode'),
                    'description' => __('Apply stricter validation rules (recommended for production)'),
                    'default' => false,
                    'required' => false
                ]
            ]
        ];
    }

    /**
     * Get advanced settings configuration
     *
     * @return array
     */
    public function getAdvancedSettings(): array
    {
        return [
            'max_urls_to_check' => [
                'label' => __('Max URLs to Check'),
                'description' => __('Maximum number of URLs to validate for accessibility'),
                'default' => 10,
                'min' => 1,
                'max' => 100,
                'type' => 'number'
            ],
            'timeout_per_url' => [
                'label' => __('Timeout per URL (seconds)'),
                'description' => __('Maximum time to wait for each URL response'),
                'default' => 5,
                'min' => 1,
                'max' => 30,
                'type' => 'number'
            ],
            'max_file_size' => [
                'label' => __('Max File Size (MB)'),
                'description' => __('Maximum allowed file size for upload'),
                'default' => 50,
                'min' => 1,
                'max' => 100,
                'type' => 'number',
                'readonly' => true
            ],
            'validation_timeout' => [
                'label' => __('Validation Timeout (minutes)'),
                'description' => __('Maximum time allowed for validation process'),
                'default' => 10,
                'min' => 1,
                'max' => 30,
                'type' => 'number'
            ]
        ];
    }

    /**
     * Get validation rules information
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'xsd_schema' => [
                'title' => __('XML Schema Validation'),
                'description' => __('Validates sitemap structure against official XML schema'),
                'checks' => [
                    __('Valid XML structure and syntax'),
                    __('Required elements and attributes'),
                    __('Data type validation'),
                    __('Element hierarchy compliance')
                ]
            ],
            'google_guidelines' => [
                'title' => __('Google Guidelines'),
                'description' => __('Checks compliance with Google Search Console requirements'),
                'checks' => [
                    __('Maximum 50,000 URLs per sitemap'),
                    __('Maximum 50MB uncompressed file size'),
                    __('Valid URL formats and protocols'),
                    __('Proper lastmod date formats'),
                    __('Valid priority and changefreq values')
                ]
            ],
            'seo_best_practices' => [
                'title' => __('SEO Best Practices'),
                'description' => __('Optimizations for better search engine performance'),
                'checks' => [
                    __('Consistent URL structure'),
                    __('Appropriate priority distribution'),
                    __('Logical changefreq values'),
                    __('Image sitemap compliance'),
                    __('Hreflang implementation'),
                    __('Mobile-friendly URL structure')
                ]
            ]
        ];
    }

    /**
     * Get file upload configuration
     *
     * @return array
     */
    public function getUploadConfig(): array
    {
        return [
            'max_file_size' => 52428800, // 50MB in bytes
            'allowed_extensions' => ['xml', 'gz'],
            'allowed_mime_types' => ['text/xml', 'application/xml', 'application/gzip'],
            'upload_url' => $this->getValidationUrl(),
            'chunk_size' => 1048576 // 1MB chunks for large files
        ];
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
     * Get generate sitemap URL
     *
     * @return string
     */
    public function getGenerateSitemapUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/generate');
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
     * Check if validation is enabled
     *
     * @return bool
     */
    public function isValidationEnabled(): bool
    {
        return (bool)$this->config->getValue('defox_seosuite/sitemap_validation/enabled');
    }

    /**
     * Get validation examples
     *
     * @return array
     */
    public function getValidationExamples(): array
    {
        return [
            'valid_sitemap' => [
                'title' => __('Valid Sitemap Example'),
                'description' => __('Example of a properly formatted sitemap'),
                'url' => 'https://example.com/sitemap.xml'
            ],
            'invalid_sitemap' => [
                'title' => __('Common Issues'),
                'description' => __('Examples of common sitemap problems'),
                'issues' => [
                    __('Invalid XML syntax'),
                    __('Missing required elements'),
                    __('Incorrect date formats'),
                    __('Invalid URLs'),
                    __('File size too large')
                ]
            ]
        ];
    }

    /**
     * Get help and documentation links
     *
     * @return array
     */
    public function getHelpLinks(): array
    {
        return [
            'google_sitemap_guide' => [
                'title' => __('Google Sitemap Guidelines'),
                'url' => 'https://developers.google.com/search/docs/advanced/sitemaps/overview',
                'description' => __('Official Google documentation for sitemaps')
            ],
            'xml_schema' => [
                'title' => __('Sitemap XML Schema'),
                'url' => 'https://www.sitemaps.org/protocol.html',
                'description' => __('Technical specification for sitemap format')
            ],
            'seo_best_practices' => [
                'title' => __('SEO Best Practices'),
                'url' => 'https://developers.google.com/search/docs/advanced/guidelines/get-started',
                'description' => __('Google\'s SEO best practices guide')
            ]
        ];
    }

    /**
     * Get validation history URL
     *
     * @return string
     */
    public function getValidationHistoryUrl(): string
    {
        return $this->getUrl('defox_seosuite/sitemap/validation_history');
    }

    /**
     * Check if current user can validate sitemaps
     *
     * @return bool
     */
    public function canValidate(): bool
    {
        return $this->_authorization->isAllowed('Defox_SEOSuite::sitemap_validate');
    }

    /**
     * Get progress tracking configuration
     *
     * @return array
     */
    public function getProgressConfig(): array
    {
        return [
            'polling_interval' => 2000, // 2 seconds
            'max_attempts' => 300, // 10 minutes max
            'show_detailed_progress' => true,
            'auto_scroll_to_results' => true
        ];
    }

    /**
     * Get validation score thresholds
     *
     * @return array
     */
    public function getScoreThresholds(): array
    {
        return [
            'excellent' => 95,
            'good' => 80,
            'average' => 60,
            'poor' => 40,
            'colors' => [
                'excellent' => '#28a745',
                'good' => '#6cb2eb', 
                'average' => '#ffc107',
                'poor' => '#dc3545',
                'critical' => '#6f42c1'
            ]
        ];
    }

    /**
     * Get current sitemap information for stores
     *
     * @return array
     */
    public function getCurrentSitemapsInfo(): array
    {
        $sitemaps = [];
        $sitemapPath = $this->config->getValue('defox_seosuite/sitemap/path') ?: 'media/';
        
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            
            $sitemapEnabled = (bool)$this->config->getValue('defox_seosuite/sitemap/enabled', (int)$store->getId());
            
            $sitemaps[] = [
                'store_id' => $store->getId(),
                'store_name' => $store->getName(),
                'store_code' => $store->getCode(),
                'base_url' => $store->getBaseUrl(),
                'sitemap_url' => $store->getBaseUrl() . 'sitemap.xml',
                'enabled' => $sitemapEnabled,
                'path' => $sitemapPath
            ];
        }
        
        return $sitemaps;
    }

    /**
     * Get validation tips
     *
     * @return array
     */
    public function getValidationTips(): array
    {
        return [
            [
                'title' => __('File Size Matters'),
                'description' => __('Keep sitemap files under 50MB uncompressed for optimal performance.'),
                'icon' => 'icon-info'
            ],
            [
                'title' => __('URL Accessibility'),
                'description' => __('Enabling URL accessibility check will increase validation time but provides thorough results.'),
                'icon' => 'icon-clock'
            ],
            [
                'title' => __('Regular Validation'),
                'description' => __('Validate your sitemaps regularly, especially after major content changes.'),
                'icon' => 'icon-refresh'
            ],
            [
                'title' => __('Gzipped Files'),
                'description' => __('Compressed (GZ) sitemap files are automatically decompressed for validation.'),
                'icon' => 'icon-archive'
            ]
        ];
    }

    /**
     * Check if advanced features are enabled
     *
     * @return bool
     */
    public function isAdvancedModeEnabled(): bool
    {
        return (bool)$this->config->getValue('defox_seosuite/sitemap_validation/advanced_mode');
    }

    /**
     * Get error handling configuration
     *
     * @return array
     */
    public function getErrorHandling(): array
    {
        return [
            'show_detailed_errors' => true,
            'group_similar_errors' => true,
            'show_line_numbers' => true,
            'highlight_critical_errors' => true,
            'max_errors_display' => 50
        ];
    }

    /**
     * Get real-time validation settings
     *
     * @return array
     */
    public function getRealTimeSettings(): array
    {
        return [
            'enable_progress_updates' => true,
            'update_interval' => 1000, // 1 second
            'show_current_operation' => true,
            'estimate_remaining_time' => true
        ];
    }
}

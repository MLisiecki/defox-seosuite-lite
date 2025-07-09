<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Sitemap;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Sitemap\Validator\AdvancedXmlValidator;
use Defox\SEOSuite\Model\Sitemap\Validator\ValidationResult;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\View\Result\PageFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap validation controller
 * 
 * Provides comprehensive sitemap validation including:
 * - File upload validation
 * - URL-based validation  
 * - Multi-level validation (XSD, Google rules, SEO best practices)
 * - Detailed validation reports
 * - Progress tracking for large files
 */
class Validate extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Defox_SEOSuite::sitemap_validate';

    /**
     * Maximum file size for upload (50MB)
     */
    private const MAX_FILE_SIZE = 52428800;

    /**
     * Allowed file extensions
     */
    private const ALLOWED_EXTENSIONS = ['xml', 'gz'];

    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var AdvancedXmlValidator
     */
    private AdvancedXmlValidator $validator;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var UploaderFactory
     */
    private UploaderFactory $uploaderFactory;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param AdvancedXmlValidator $validator
     * @param StoreManagerInterface $storeManager
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        Config $config,
        AdvancedXmlValidator $validator,
        StoreManagerInterface $storeManager,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        Curl $curl
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->validator = $validator;
        $this->storeManager = $storeManager;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->curl = $curl;
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        // Check if this is an AJAX action request
        $ajaxAction = $this->getRequest()->getParam('ajax_action');
        if ($ajaxAction) {
            return $this->handleAjaxRequest();
        }
        
        if ($this->getRequest()->isPost()) {
            return $this->processValidation();
        }

        if ($this->getRequest()->isAjax()) {
            return $this->handleAjaxRequest();
        }

        return $this->showValidationForm();
    }

    /**
     * Show validation form
     *
     * @return ResultInterface
     */
    private function showValidationForm(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Defox_SEOSuite::sitemap_validate');
        $resultPage->getConfig()->getTitle()->prepend(__('Sitemap Management - Validate'));
        
        return $resultPage;
    }

    /**
     * Process validation request
     *
     * @return ResultInterface
     */
    private function processValidation(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $validationType = $this->getRequest()->getParam('validation_type');
            $validationOptions = $this->getValidationOptions();
            
            switch ($validationType) {
                case 'file_upload':
                    $validationResult = $this->validateUploadedFile($validationOptions);
                    break;
                    
                case 'url_validation':
                    $url = $this->getRequest()->getParam('sitemap_url');
                    $validationResult = $this->validateSitemapFromUrl($url, $validationOptions);
                    break;
                    
                case 'current_sitemap':
                    $storeId = (int)$this->getRequest()->getParam('store_id');
                    $validationResult = $this->validateCurrentSitemap($storeId, $validationOptions);
                    break;
                    
                default:
                    throw new LocalizedException(__('Invalid validation type'));
            }

            return $result->setData([
                'success' => true,
                'validation_result' => $this->formatValidationResult($validationResult),
                'recommendations' => $this->generateRecommendations($validationResult)
            ]);
            
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Validation failed: %1', $e->getMessage()),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle AJAX requests
     *
     * @return ResultInterface
     */
    private function handleAjaxRequest(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $action = $this->getRequest()->getParam('ajax_action');
        
        try {
            switch ($action) {
                case 'get_current_sitemaps':
                    return $result->setData($this->getCurrentSitemaps());
                    
                case 'validate_url':
                    $url = $this->getRequest()->getParam('url');
                    return $result->setData($this->checkUrlAccessibility($url));
                    
                case 'get_validation_progress':
                    $sessionId = $this->getRequest()->getParam('session_id');
                    return $result->setData($this->getValidationProgress($sessionId));
                    
                default:
                    throw new LocalizedException(__('Invalid AJAX action: %1', $action));
            }
            
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate uploaded file
     *
     * @param array $options
     * @return ValidationResult
     * @throws LocalizedException
     */
    private function validateUploadedFile(array $options): ValidationResult
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'sitemap_file']);
            $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            
            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $uploadPath = $mediaDir->getAbsolutePath('sitemap_validation/');
            
            if (!$mediaDir->isExist('sitemap_validation')) {
                $mediaDir->create('sitemap_validation');
            }
            
            $result = $uploader->save($uploadPath);
            $filePath = $uploadPath . $result['file'];
            
            // Validate file size
            if (filesize($filePath) > self::MAX_FILE_SIZE) {
                unlink($filePath);
                throw new LocalizedException(__('File size exceeds maximum limit of %1MB', self::MAX_FILE_SIZE / 1024 / 1024));
            }
            
            // Decompress if needed
            if ($result['type'] === 'application/gzip' || pathinfo($filePath, PATHINFO_EXTENSION) === 'gz') {
                $filePath = $this->decompressFile($filePath);
            }
            
            $validationResult = $this->validator->validateComprehensive($filePath, $options);
            
            // Cleanup
            unlink($filePath);
            
            return $validationResult;
            
        } catch (\Exception $e) {
            throw new LocalizedException(__('File upload validation failed: %1', $e->getMessage()));
        }
    }

    /**
     * Validate sitemap from URL
     *
     * @param string $url
     * @param array $options
     * @return ValidationResult
     * @throws LocalizedException
     */
    private function validateSitemapFromUrl(string $url, array $options): ValidationResult
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new LocalizedException(__('Invalid URL format'));
        }
        
        try {
            // Download sitemap content
            $this->curl->setTimeout(30);
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 5);
            $this->curl->get($url);
            
            $httpCode = $this->curl->getStatus();
            if ($httpCode !== 200) {
                throw new LocalizedException(__('Unable to access sitemap URL. HTTP status: %1', $httpCode));
            }
            
            $content = $this->curl->getBody();
            if (empty($content)) {
                throw new LocalizedException(__('Empty response from sitemap URL'));
            }
            
            // Check if content is gzipped
            if (substr($content, 0, 2) === "\x1f\x8b") {
                $content = gzdecode($content);
            }
            
            // Save to temporary file for validation
            $tmpFile = tempnam(sys_get_temp_dir(), 'sitemap_validation_');
            file_put_contents($tmpFile, $content);
            
            $validationResult = $this->validator->validateComprehensive($tmpFile, $options);
            
            // Add URL-specific information
            $validationResult->addMetadata('source_url', $url);
            $validationResult->addMetadata('content_length', strlen($content));
            $validationResult->addMetadata('http_status', $httpCode);
            
            // Cleanup
            unlink($tmpFile);
            
            return $validationResult;
            
        } catch (\Exception $e) {
            throw new LocalizedException(__('URL validation failed: %1', $e->getMessage()));
        }
    }

    /**
     * Validate current sitemap
     *
     * @param int $storeId
     * @param array $options
     * @return ValidationResult
     * @throws LocalizedException
     */
    private function validateCurrentSitemap(int $storeId, array $options): ValidationResult
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $sitemapPath = $this->config->getSitemapPath($storeId);
            
            // Get the pub directory
            $pubDir = $this->filesystem->getDirectoryRead(DirectoryList::PUB);
            $basePath = $pubDir->getAbsolutePath($sitemapPath);
            
            // Check possible sitemap files (both compressed and uncompressed)
            $possibleFiles = [
                $basePath . 'sitemap_index.xml.gz',
                $basePath . 'sitemap_index.xml',
                $basePath . 'sitemap.xml.gz', 
                $basePath . 'sitemap.xml'
            ];
            
            $filePath = null;
            foreach ($possibleFiles as $file) {
                if (file_exists($file)) {
                    $filePath = $file;
                    break;
                }
            }
            
            if (!$filePath) {
                throw new LocalizedException(__('Sitemap file not found for store %1. Generate sitemap first.', $store->getName()));
            }
            
            // If file is compressed, decompress it for validation
            $validationFile = $filePath;
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'gz') {
                $validationFile = $this->decompressFileForValidation($filePath);
            }
            
            $validationResult = $this->validator->validateComprehensive($validationFile, $options);
            
            // Add store-specific information
            $validationResult->addMetadata('store_id', $storeId);
            $validationResult->addMetadata('store_name', $store->getName());
            $validationResult->addMetadata('file_path', $filePath);
            $validationResult->addMetadata('file_size', filesize($filePath));
            $validationResult->addMetadata('last_modified', filemtime($filePath));
            $validationResult->addMetadata('is_compressed', pathinfo($filePath, PATHINFO_EXTENSION) === 'gz');
            
            // Cleanup temporary file if created
            if ($validationFile !== $filePath) {
                unlink($validationFile);
            }
            
            return $validationResult;
            
        } catch (\Exception $e) {
            throw new LocalizedException(__('Current sitemap validation failed: %1', $e->getMessage()));
        }
    }

    /**
     * Get validation options from request
     *
     * @return array
     */
    private function getValidationOptions(): array
    {
        return [
            'validate_xsd' => (bool)$this->getRequest()->getParam('validate_xsd', true),
            'validate_google_rules' => (bool)$this->getRequest()->getParam('validate_google_rules', true),
            'validate_seo_best_practices' => (bool)$this->getRequest()->getParam('validate_seo_best_practices', true),
            'check_url_accessibility' => (bool)$this->getRequest()->getParam('check_url_accessibility', false),
            'max_urls_to_check' => (int)$this->getRequest()->getParam('max_urls_to_check', 10),
            'timeout_per_url' => (int)$this->getRequest()->getParam('timeout_per_url', 5),
            'follow_redirects' => (bool)$this->getRequest()->getParam('follow_redirects', true),
            'check_response_time' => (bool)$this->getRequest()->getParam('check_response_time', true),
            'validate_images' => (bool)$this->getRequest()->getParam('validate_images', false),
            'strict_mode' => (bool)$this->getRequest()->getParam('strict_mode', false)
        ];
    }

    /**
     * Decompress gzipped file
     *
     * @param string $filePath
     * @return string
     * @throws LocalizedException
     */
    private function decompressFile(string $filePath): string
    {
        $compressedContent = file_get_contents($filePath);
        $decompressedContent = gzdecode($compressedContent);
        
        if ($decompressedContent === false) {
            throw new LocalizedException(__('Failed to decompress gzipped file'));
        }
        
        $newPath = str_replace('.gz', '', $filePath);
        file_put_contents($newPath, $decompressedContent);
        
        // Remove original compressed file
        unlink($filePath);
        
        return $newPath;
    }
    
    /**
     * Decompress gzipped file for validation (without removing original)
     *
     * @param string $filePath
     * @return string
     * @throws LocalizedException
     */
    private function decompressFileForValidation(string $filePath): string
    {
        $compressedContent = file_get_contents($filePath);
        $decompressedContent = gzdecode($compressedContent);
        
        if ($decompressedContent === false) {
            throw new LocalizedException(__('Failed to decompress gzipped file'));
        }
        
        $tempPath = tempnam(sys_get_temp_dir(), 'sitemap_validation_');
        file_put_contents($tempPath, $decompressedContent);
        
        return $tempPath;
    }

    /**
     * Get current sitemaps for all stores
     *
     * @return array
     */
    private function getCurrentSitemaps(): array
    {
        $sitemaps = [];
        
        // Add debugging
        $debugInfo = [];
        $debugInfo['stores_count'] = count($this->storeManager->getStores());
        
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            
            $storeId = (int)$store->getId();
            $sitemapPath = $this->config->getSitemapPath($storeId);
            
            $debugInfo['store_' . $storeId] = [
                'name' => $store->getName(),
                'sitemap_path' => $sitemapPath
            ];
            
            // Get the pub directory
            $pubDir = $this->filesystem->getDirectoryRead(DirectoryList::PUB);
            $basePath = $pubDir->getAbsolutePath($sitemapPath);
            
            $debugInfo['store_' . $storeId]['base_path'] = $basePath;
            
            // Check possible sitemap files (both compressed and uncompressed)
            $possibleFiles = [
                'sitemap_index.xml.gz' => $basePath . 'sitemap_index.xml.gz',
                'sitemap_index.xml' => $basePath . 'sitemap_index.xml',
                'sitemap.xml.gz' => $basePath . 'sitemap.xml.gz',
                'sitemap.xml' => $basePath . 'sitemap.xml'
            ];
            
            $debugInfo['store_' . $storeId]['possible_files'] = [];
            foreach ($possibleFiles as $name => $file) {
                $exists = file_exists($file);
                $debugInfo['store_' . $storeId]['possible_files'][$name] = [
                    'path' => $file,
                    'exists' => $exists
                ];
            }
            
            $filePath = null;
            $fileName = null;
            foreach ($possibleFiles as $name => $file) {
                if (file_exists($file)) {
                    $filePath = $file;
                    $fileName = $name;
                    break;
                }
            }
            
            $exists = $filePath !== null;
            $debugInfo['store_' . $storeId]['found_file'] = $filePath;
            
            $sitemapInfo = [
                'store_id' => $storeId,
                'store_name' => $store->getName(),
                'store_code' => $store->getCode(),
                'exists' => $exists,
                'path' => $filePath,
                'filename' => $fileName,
                'size' => $exists ? filesize($filePath) : 0,
                'last_modified' => $exists ? filemtime($filePath) : null,
                'url' => $store->getBaseUrl() . $sitemapPath . ($fileName ?: 'sitemap.xml'),
                'is_compressed' => $exists && pathinfo($filePath, PATHINFO_EXTENSION) === 'gz'
            ];
            
            if ($exists) {
                $sitemapInfo['formatted_size'] = $this->formatFileSize($sitemapInfo['size']);
                $sitemapInfo['formatted_date'] = date('Y-m-d H:i:s', $sitemapInfo['last_modified']);
            }
            
            $sitemaps[] = $sitemapInfo;
        }
        
        return [
            'success' => true,
            'sitemaps' => $sitemaps,
            'debug' => $debugInfo // Add debug info
        ];
    }

    /**
     * Check URL accessibility
     *
     * @param string $url
     * @return array
     */
    private function checkUrlAccessibility(string $url): array
    {
        try {
            $startTime = microtime(true);
            
            $this->curl->setTimeout(10);
            $this->curl->setOption(CURLOPT_NOBODY, true); // HEAD request
            $this->curl->get($url);
            
            $httpCode = $this->curl->getStatus();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'accessible' => $httpCode === 200,
                'http_status' => $httpCode,
                'response_time' => $responseTime,
                'headers' => $this->curl->getHeaders()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'accessible' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get validation progress (placeholder for long-running validations)
     *
     * @param string $sessionId
     * @return array
     */
    private function getValidationProgress(string $sessionId): array
    {
        // This would track progress of long-running validations
        // For now, return placeholder data
        return [
            'success' => true,
            'progress' => 100,
            'status' => 'completed',
            'message' => 'Validation completed'
        ];
    }

    /**
     * Format validation result for frontend display
     *
     * @param ValidationResult $result
     * @return array
     */
    private function formatValidationResult(ValidationResult $result): array
    {
        $errors = [];
        foreach ($result->getErrors() as $error) {
            $errors[] = [
                'level' => $error['level'] ?? 'error',
                'message' => $error['message'] ?? '',
                'line' => $error['line'] ?? null,
                'column' => $error['column'] ?? null,
                'code' => $error['code'] ?? null
            ];
        }
        
        $warnings = [];
        foreach ($result->getWarnings() as $warning) {
            $warnings[] = [
                'level' => 'warning',
                'message' => $warning['message'] ?? '',
                'recommendation' => $warning['recommendation'] ?? null
            ];
        }
        
        return [
            'is_valid' => $result->isValid(),
            'score' => $result->getScore(),
            'summary' => $result->getSummary(),
            'errors' => $errors,
            'warnings' => $warnings,
            'metadata' => $result->getMetadata(),
            'performance_metrics' => $result->getPerformanceMetrics()
        ];
    }

    /**
     * Generate recommendations based on validation result
     *
     * @param ValidationResult $result
     * @return array
     */
    private function generateRecommendations(ValidationResult $result): array
    {
        $recommendations = [];
        
        if (!$result->isValid()) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'error',
                'title' => __('Fix Validation Errors'),
                'description' => __('Your sitemap contains validation errors that must be fixed for proper search engine indexing.'),
                'action' => __('Review and fix all listed errors')
            ];
        }
        
        if ($result->getScore() < 80) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'optimization',
                'title' => __('Improve SEO Score'),
                'description' => __('Your sitemap score is below optimal. Consider implementing the suggested improvements.'),
                'action' => __('Address warnings and follow SEO best practices')
            ];
        }
        
        $metadata = $result->getMetadata();
        if (isset($metadata['file_size']) && $metadata['file_size'] > 10 * 1024 * 1024) { // 10MB
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'performance',
                'title' => __('Large Sitemap File'),
                'description' => __('Your sitemap file is quite large. Consider splitting it into multiple files.'),
                'action' => __('Enable sitemap splitting in configuration')
            ];
        }
        
        return $recommendations;
    }

    /**
     * Format file size for display
     *
     * @param int $bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}

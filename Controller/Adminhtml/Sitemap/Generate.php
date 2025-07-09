<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Sitemap;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Model\Sitemap\Analytics\StatisticsManager;
use Defox\SEOSuite\Model\Sitemap\SitemapGeneratorInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sitemap generation controller
 * 
 * Handles manual sitemap generation through admin panel with:
 * - Store view selection
 * - Generation options configuration
 * - Real-time progress tracking
 * - AJAX form submission
 */
class Generate extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Defox_SEOSuite::sitemap_generate';

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
     * @var SitemapGeneratorInterface
     */
    private SitemapGeneratorInterface $sitemapGenerator;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var StatisticsManager
     */
    private StatisticsManager $statisticsManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param SitemapGeneratorInterface $sitemapGenerator
     * @param StoreManagerInterface $storeManager
     * @param StatisticsManager $statisticsManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        Config $config,
        SitemapGeneratorInterface $sitemapGenerator,
        StoreManagerInterface $storeManager,
        StatisticsManager $statisticsManager
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->sitemapGenerator = $sitemapGenerator;
        $this->storeManager = $storeManager;
        $this->statisticsManager = $statisticsManager;
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if ($this->getRequest()->isPost()) {
            return $this->processGeneration();
        }

        return $this->showGenerationForm();
    }

    /**
     * Show generation form
     *
     * @return ResultInterface
     */
    private function showGenerationForm(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Defox_SEOSuite::sitemap_generate');
        $resultPage->getConfig()->getTitle()->prepend(__('Sitemap Management - Generate'));
        
        return $resultPage;
    }

    /**
     * Process sitemap generation via AJAX
     *
     * @return ResultInterface
     */
    private function processGeneration(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            $storeIds = $this->getRequest()->getParam('store_ids', []);
            $options = $this->getGenerationOptions();
            
            if (empty($storeIds)) {
                // Generate for all active stores if none selected
                $stores = $this->storeManager->getStores();
                $storeIds = [];
                foreach ($stores as $store) {
                    if ($store->getIsActive()) {
                        $storeIds[] = (int)$store->getId();
                    }
                }
            }
            
            $results = [];
            $totalGenerated = 0;
            $errors = [];
            
            foreach ($storeIds as $storeId) {
                try {
                    $store = $this->storeManager->getStore($storeId);
                    
                    if (!$store->getIsActive()) {
                        continue;
                    }
                    
                    $startTime = microtime(true);
                    $memoryBefore = memory_get_usage();
                    $files = $this->sitemapGenerator->generate((int)$storeId, $options);
                    $duration = microtime(true) - $startTime;
                    $memoryAfter = memory_get_usage();
                    
                    // Calculate URL count from files
                    $totalUrls = $this->calculateUrlCount($files);
                    
                    // Record statistics
                    $this->recordStatistics([
                        'store_id' => $storeId,
                        'duration' => $duration,
                        'total_urls' => $totalUrls,
                        'files' => $files,
                        'errors' => [],
                        'memory_usage' => $memoryAfter - $memoryBefore,
                        'memory_peak' => memory_get_peak_usage(),
                        'success' => true
                    ]);
                    
                    $results[] = [
                        'store_id' => $storeId,
                        'store_name' => $store->getName(),
                        'success' => true,
                        'files_count' => count($files),
                        'files' => array_map('basename', $files),
                        'duration' => round($duration, 2),
                        'message' => __('Successfully generated %1 sitemap files', count($files))
                    ];
                    
                    $totalGenerated += count($files);
                    
                } catch (\Exception $e) {
                    $store = $this->storeManager->getStore($storeId);
                    $errorMessage = sprintf(
                        'Store %s (%d): %s',
                        $store->getName(),
                        $storeId,
                        $e->getMessage()
                    );
                    $errors[] = $errorMessage;
                    
                    // Record failed generation statistics
                    $this->recordStatistics([
                        'store_id' => $storeId,
                        'duration' => 0,
                        'total_urls' => 0,
                        'files' => [],
                        'errors' => [$e->getMessage()],
                        'success' => false
                    ]);
                    
                    $results[] = [
                        'store_id' => $storeId,
                        'store_name' => $store->getName(),
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => __('Error generating sitemap: %1', $e->getMessage())
                    ];
                }
            }
            
            $success = empty($errors);
            $message = $success 
                ? __('Successfully generated %1 sitemap files for %2 stores', $totalGenerated, count($storeIds))
                : __('Generation completed with %1 errors', count($errors));
            
            return $result->setData([
                'success' => $success,
                'message' => $message,
                'results' => $results,
                'summary' => [
                    'stores_processed' => count($storeIds),
                    'files_generated' => $totalGenerated,
                    'errors_count' => count($errors),
                    'success_rate' => count($storeIds) > 0 ? round((count($storeIds) - count($errors)) / count($storeIds) * 100, 1) : 0
                ]
            ]);
            
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Fatal error during generation: %1', $e->getMessage()),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get generation options from request
     *
     * @return array
     */
    private function getGenerationOptions(): array
    {
        return [
            'force_regeneration' => (bool)$this->getRequest()->getParam('force_regeneration', false),
            'ping_search_engines' => (bool)$this->getRequest()->getParam('ping_search_engines', true),
            'validate_after_generation' => (bool)$this->getRequest()->getParam('validate_after_generation', false),
            'include_images' => (bool)$this->getRequest()->getParam('include_images', true),
            'include_hreflang' => (bool)$this->getRequest()->getParam('include_hreflang', true)
        ];
    }

    /**
     * Record generation statistics
     *
     * @param array $stats
     * @return void
     */
    private function recordStatistics(array $stats): void
    {
        try {
            // Ensure table exists before recording
            $this->ensureStatisticsTableExists();
            $this->statisticsManager->recordGenerationStats($stats);
        } catch (\Exception $e) {
            // Don't fail generation if statistics recording fails
            // Just log the error silently
        }
    }

    /**
     * Ensure statistics table exists
     *
     * @return void
     */
    private function ensureStatisticsTableExists(): void
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resourceConnection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resourceConnection->getConnection();
            $tableName = $resourceConnection->getTableName('defox_seosuite_sitemap_stats');
            
            if (!$connection->isTableExists($tableName)) {
                // Create table if it doesn't exist
                $setupPatch = $objectManager->get(\Defox\SEOSuite\Setup\Patch\Schema\CreateSitemapStatisticsTable::class);
                $setupPatch->apply();
            }
        } catch (\Exception $e) {
            // If we can't create table, skip statistics
        }
    }

    /**
     * Calculate total URL count from generated files
     *
     * @param array $files
     * @return int
     */
    private function calculateUrlCount(array $files): int
    {
        $totalUrls = 0;
        
        foreach ($files as $file) {
            if (file_exists($file) && is_readable($file)) {
                try {
                    $content = file_get_contents($file);
                    // Count <url> tags in XML files
                    $urlCount = substr_count($content, '<url>');
                    $totalUrls += $urlCount;
                } catch (\Exception $e) {
                    // If we can't read the file, skip counting
                    continue;
                }
            }
        }
        
        return $totalUrls;
    }
}

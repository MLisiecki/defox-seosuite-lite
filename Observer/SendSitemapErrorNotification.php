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
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer for sitemap generation error notifications
 */
class SendSitemapErrorNotification implements ObserverInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var TransportBuilder
     */
    private TransportBuilder $transportBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Config $config
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $storeId = $observer->getData('store_id');
        
        if (!$this->config->getValue('sitemap/generation/error_notification', $storeId)) {
            return;
        }

        try {
            $exception = $observer->getData('exception');
            $stats = $observer->getData('stats') ?: [];
            $store = $this->storeManager->getStore($storeId);
            
            // Get admin email
            $adminEmail = $this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            if (!$adminEmail) {
                $this->logger->warning('No admin email configured for sitemap error notifications');
                return;
            }
            
            // Prepare email data
            $emailData = [
                'store_name' => $store->getName(),
                'store_id' => $storeId,
                'error_message' => $exception->getMessage(),
                'error_trace' => $exception->getTraceAsString(),
                'generation_stats' => $this->formatStats($stats),
                'datetime' => date('Y-m-d H:i:s')
            ];
            
            // Send email
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('defox_seosuite_sitemap_error')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                    'store' => $storeId
                ])
                ->setTemplateVars($emailData)
                ->setFromByScope('general')
                ->addTo($adminEmail)
                ->getTransport();
            
            $transport->sendMessage();
            
            $this->logger->info('Sitemap error notification sent to ' . $adminEmail);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send sitemap error notification: ' . $e->getMessage());
        }
    }

    /**
     * Format generation stats for email
     *
     * @param array $stats
     * @return string
     */
    private function formatStats(array $stats): string
    {
        $output = [];
        
        if (isset($stats['start_time'])) {
            $output[] = 'Start Time: ' . $stats['start_time'];
        }
        
        if (isset($stats['total_urls'])) {
            $output[] = 'URLs Processed: ' . $stats['total_urls'];
        }
        
        if (isset($stats['files'])) {
            $output[] = 'Files Generated: ' . count($stats['files']);
            foreach ($stats['files'] as $file) {
                $output[] = '  - ' . $file['name'] . ' (' . $file['urls'] . ' URLs)';
            }
        }
        
        if (isset($stats['errors']) && !empty($stats['errors'])) {
            $output[] = 'Errors:';
            foreach ($stats['errors'] as $error) {
                $output[] = '  - ' . $error;
            }
        }
        
        return implode("\n", $output);
    }
}

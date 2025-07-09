<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Cron;

use Defox\SEOSuite\Helper\Config;
use Defox\SEOSuite\Logger\Logger;
use Defox\SEOSuite\Model\Sitemap\SitemapGeneratorInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cron job for automatic sitemap generation
 */
class GenerateSitemap
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var SitemapGeneratorInterface
     */
    private SitemapGeneratorInterface $sitemapGenerator;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var State
     */
    private State $appState;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Logger $logger
     * @param SitemapGeneratorInterface $sitemapGenerator
     * @param StoreManagerInterface $storeManager
     * @param State $appState
     */
    public function __construct(
        Config $config,
        Logger $logger,
        SitemapGeneratorInterface $sitemapGenerator,
        StoreManagerInterface $storeManager,
        State $appState
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->sitemapGenerator = $sitemapGenerator;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->getValue('defox_seosuite/sitemap/enabled')) {
            $this->logger->info('Sitemap generation is disabled globally.');
            return;
        }

        try {
            // Set area code first, before any other operations
            try {
                $this->appState->setAreaCode(Area::AREA_CRONTAB);
            } catch (LocalizedException $e) {
                // Area code already set, this is fine
            }

            $stores = $this->storeManager->getStores();
            $generatedCount = 0;

            foreach ($stores as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }
                
                $storeId = (int)$store->getId();
                
                // Check if sitemap is enabled for this store
                if (!$this->config->getValue('defox_seosuite/sitemap/enabled', $storeId)) {
                    $this->logger->info(sprintf('Sitemap generation is disabled for store %d', $storeId));
                    continue;
                }
                
                try {

                    $files = $this->sitemapGenerator->generate($storeId);
                    
                    $generatedCount++;
                } catch (\Exception $e) {
                    $error = sprintf(
                        'Error generating sitemap for store %d: %s',
                        $storeId,
                        $e->getMessage()
                    );
                    $this->logger->error($error);
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Critical error in scheduled sitemap generation: ' . $e->getMessage());
        }
    }
}

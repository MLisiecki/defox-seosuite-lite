<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Console\Command;

use Defox\SEOSuite\Model\Sitemap\SitemapGeneratorInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Console command for sitemap generation
 */
class GenerateSitemapCommand extends Command
{
    /**
     * Command name
     */
    private const NAME = 'defox:seosuite:sitemap:generate';

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
     * @param SitemapGeneratorInterface $sitemapGenerator
     * @param StoreManagerInterface $storeManager
     * @param State $appState
     * @param string|null $name
     */
    public function __construct(
        SitemapGeneratorInterface $sitemapGenerator,
        StoreManagerInterface $storeManager,
        State $appState,
        string $name = null
    ) {
        parent::__construct($name);
        $this->sitemapGenerator = $sitemapGenerator;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('Generate XML sitemap for specified store or all stores')
            ->addOption(
                'store',
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID to generate sitemap for (omit for all stores)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force regeneration even if sitemap exists'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Set area code
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
            
            $storeId = $input->getOption('store');
            $stores = [];
            
            if ($storeId !== null) {
                $store = $this->storeManager->getStore($storeId);
                if (!$store->getId()) {
                    $output->writeln('<error>Store with ID ' . $storeId . ' not found.</error>');
                    return Command::FAILURE;
                }
                $stores[] = $store;
            } else {
                $stores = $this->storeManager->getStores();
            }
            
            $output->writeln('<info>Starting sitemap generation...</info>');
            
            $progressBar = new ProgressBar($output, count($stores));
            $progressBar->start();
            
            $successCount = 0;
            $errors = [];
            
            foreach ($stores as $store) {
                if (!$store->getIsActive()) {
                    $progressBar->advance();
                    continue;
                }
                
                try {
                    $progressBar->setMessage('Generating sitemap for store: ' . $store->getName());
                    
                    $files = $this->sitemapGenerator->generate((int)$store->getId());
                    
                    $output->writeln(sprintf(
                        "\n<info>Generated %d sitemap files for store %s (%d)</info>",
                        count($files),
                        $store->getName(),
                        $store->getId()
                    ));
                    
                    foreach ($files as $file) {
                        $output->writeln('  - ' . basename($file));
                    }
                    
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = sprintf(
                        'Store %s (%d): %s',
                        $store->getName(),
                        $store->getId(),
                        $e->getMessage()
                    );
                    $output->writeln("\n<error>" . end($errors) . "</error>");
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $output->writeln('');
            
            // Summary
            $output->writeln('<info>Sitemap generation completed!</info>');
            $output->writeln(sprintf(
                'Success: %d stores, Errors: %d',
                $successCount,
                count($errors)
            ));
            
            if (!empty($errors)) {
                $output->writeln("\n<error>Errors encountered:</error>");
                foreach ($errors as $error) {
                    $output->writeln('  - ' . $error);
                }
                return Command::FAILURE;
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('<error>Fatal error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Blueskytechco\OptimizeSpeed\Observer;

use Defox\SEOSuite\Helper\StructuredDataManager;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

/**
 * Plugin for OptimizeSpeed Observer
 * 
 * This plugin ensures that our JSON+LD structured data is added AFTER
 * OptimizeSpeed has finished processing the page content, preventing conflicts.
 * 
 * NOTE: This plugin is automatically registered only when OptimizeSpeed module exists.
 */
class OptimizeSpeedPlugin
{
    /**
     * @var StructuredDataManager
     */
    private StructuredDataManager $structuredDataManager;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param StructuredDataManager $structuredDataManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        StructuredDataManager $structuredDataManager,
        LoggerInterface $logger
    ) {
        $this->structuredDataManager = $structuredDataManager;
        $this->logger = $logger;
    }

    /**
     * Add structured data JSON+LD after OptimizeSpeed has processed the page
     *
     * @param mixed $subject OptimizeSpeed Observer
     * @param void $result
     * @param Observer $observer
     * @return void
     */
    public function afterExecute($subject, $result, Observer $observer): void
    {
        if (!$this->structuredDataManager->isEnabled()) {
            return;
        }

        try {
            $response = $observer->getResponse();
            $content = $response->getBody();
            
            // Check if page has </head> tag (HTML page)
            if (strpos($content, '</head>') === false) {
                return;
            }
            
            $jsonLd = $this->structuredDataManager->getJsonLd();
            
            if ($jsonLd !== '') {
                // Add JSON+LD just before </head> tag
                $content = str_replace('</head>', $jsonLd . "\n</head>", $content);
                $response->setBody($content);
            }
            
        } catch (\Exception $e) {
            $this->logger->error(
                'SEOSuite: Error adding structured data after OptimizeSpeed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}

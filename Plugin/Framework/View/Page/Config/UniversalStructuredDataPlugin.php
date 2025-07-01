<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Framework\View\Page\Config;

use Defox\SEOSuite\Helper\StructuredDataManager;
use Magento\Framework\View\Page\Config\Renderer;
use Magento\Framework\Module\Manager as ModuleManager;
use Psr\Log\LoggerInterface;

/**
 * Universal Structured Data Plugin for Config Renderer
 * 
 * Intelligently handles JSON+LD placement based on environment configuration.
 */
class UniversalStructuredDataPlugin
{
    /**
     * @var StructuredDataManager
     */
    private StructuredDataManager $structuredDataManager;
    
    /**
     * @var ModuleManager
     */
    private ModuleManager $moduleManager;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param StructuredDataManager $structuredDataManager
     * @param ModuleManager $moduleManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        StructuredDataManager $structuredDataManager,
        ModuleManager $moduleManager,
        LoggerInterface $logger
    ) {
        $this->structuredDataManager = $structuredDataManager;
        $this->moduleManager = $moduleManager;
        $this->logger = $logger;
    }

    /**
     * Add structured data JSON-LD after head content is rendered
     *
     * Only adds JSON+LD here if OptimizeSpeed is NOT present.
     * If OptimizeSpeed is present, JSON+LD will be added via OptimizeSpeedPlugin.
     *
     * @param Renderer $subject
     * @param string $result
     * @return string
     */
    public function afterRenderHeadContent(Renderer $subject, string $result): string
    {
        if (!$this->structuredDataManager->isEnabled()) {
            return $result;
        }

        // If OptimizeSpeed is enabled, let OptimizeSpeedPlugin handle JSON+LD
        if ($this->isOptimizeSpeedEnabled()) {
            $this->logger->debug('SEOSuite: OptimizeSpeed detected - JSON+LD will be added after OptimizeSpeed processing');
            return $result;
        }

        try {
            $jsonLd = $this->structuredDataManager->getJsonLd();
            
            if ($jsonLd !== '') {
                // Add JSON-LD after the existing head content
                $result .= "\n" . $jsonLd;
                
                $this->logger->debug('SEOSuite: Added structured data to head content (direct method)');
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'SEOSuite: Error adding structured data to head: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $result;
    }
    
    /**
     * Check if OptimizeSpeed module is enabled
     *
     * @return bool
     */
    private function isOptimizeSpeedEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Blueskytechco_OptimizeSpeed');
    }
}

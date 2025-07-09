<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Head;

use Defox\SEOSuite\Model\Canonical\RobotsHandler;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Config\Renderer;

/**
 * Plugin to handle NOINDEX tags
 */
class NoindexHandler
{
    /**
     * @var RobotsHandler
     */
    private RobotsHandler $robotsHandler;

    /**
     * @param RobotsHandler $robotsHandler
     */
    public function __construct(
        RobotsHandler $robotsHandler
    ) {
        $this->robotsHandler = $robotsHandler;
    }

    /**
     * Before plugin for renderMetadata method
     *
     * @param Renderer $subject
     * @return void
     */
    public function beforeRenderMetadata(Renderer $subject): void
    {
        // Apply robots tag based on current page state
        $this->robotsHandler->applyRobotsTag();
    }
}

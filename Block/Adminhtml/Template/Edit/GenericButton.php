<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\Template\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;

/**
 * Generic button class for template edit form
 */
class GenericButton
{
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @param Context $context
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        Registry $registry
    ) {
        $this->context = $context;
        $this->registry = $registry;
    }

    /**
     * Get template ID
     *
     * @return int|null
     */
    public function getTemplateId(): ?int
    {
        $template = $this->registry->registry('defox_seosuite_template');
        return $template ? (int)$template->getId() : null;
    }

    /**
     * Generate URL by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}

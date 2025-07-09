<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\Template\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete button for template edit form
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        $data = [];
        if ($templateId = $this->getTemplateId()) {
            $data = [
                'label' => __('Delete Template'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to delete this template?'
                ) . '\', \'' . $this->getDeleteUrl() . '\', {"data": {}})',
                'sort_order' => 20,
                'aclResource' => 'Defox_SEOSuite::template',
            ];
        }
        
        return $data;
    }

    /**
     * Get URL for delete button
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['template_id' => $this->getTemplateId()]);
    }
}

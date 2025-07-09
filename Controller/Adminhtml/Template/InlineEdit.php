<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Template;

use Defox\SEOSuite\Api\TemplateRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Template inline edit controller
 * 
 * Handles inline editing of meta tag templates in the grid
 */
class InlineEdit extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'Defox_SEOSuite::template';

    /**
     * @var JsonFactory
     */
    protected JsonFactory $jsonFactory;

    /**
     * @var TemplateRepositoryInterface
     */
    protected TemplateRepositoryInterface $templateRepository;

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param TemplateRepositoryInterface $templateRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        TemplateRepositoryInterface $templateRepository
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->templateRepository = $templateRepository;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);
            
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $templateId) {
                    $template = $this->templateRepository->getById((int)$templateId);
                    try {
                        $template->setData(array_merge($template->getData(), $postItems[$templateId]));
                        $this->templateRepository->save($template);
                    } catch (LocalizedException $e) {
                        $messages[] = $this->getErrorWithTemplateId($template, $e->getMessage());
                        $error = true;
                    } catch (\Exception $e) {
                        $messages[] = $this->getErrorWithTemplateId(
                            $template,
                            __('Something went wrong while saving the template.')
                        );
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * Add template ID to error message
     *
     * @param \Defox\SEOSuite\Api\Data\TemplateInterface $template
     * @param string $errorText
     * @return string
     */
    protected function getErrorWithTemplateId(\Defox\SEOSuite\Api\Data\TemplateInterface $template, string $errorText): string
    {
        return '[Template ID: ' . $template->getId() . '] ' . $errorText;
    }
}

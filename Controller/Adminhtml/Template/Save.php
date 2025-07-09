<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Controller\Adminhtml\Template;

use Defox\SEOSuite\Api\Data\TemplateInterface;
use Defox\SEOSuite\Api\TemplateRepositoryInterface;
use Defox\SEOSuite\Model\Template;
use Defox\SEOSuite\Model\TemplateFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Template save controller
 * 
 * Saves a meta tag template
 */
class Save extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'Defox_SEOSuite::template';

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @var TemplateFactory
     */
    protected TemplateFactory $templateFactory;

    /**
     * @var TemplateRepositoryInterface
     */
    protected TemplateRepositoryInterface $templateRepository;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param TemplateFactory $templateFactory
     * @param TemplateRepositoryInterface $templateRepository
     */
    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor,
        TemplateFactory $templateFactory,
        TemplateRepositoryInterface $templateRepository
    ) {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->templateFactory = $templateFactory;
        $this->templateRepository = $templateRepository;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        
        if ($data) {
            if (isset($data['is_active'])) {
                $data['is_active'] = ($data['is_active'] === 'true' || (int)$data['is_active'] === 1) ? 
                    Template::STATUS_ENABLED : Template::STATUS_DISABLED;
            }
            
            if (empty($data['template_id'])) {
                $data['template_id'] = null;
            }

            $model = $this->templateFactory->create();

            $id = $this->getRequest()->getParam('template_id');
            if ($id) {
                try {
                    $model = $this->templateRepository->getById((int)$id);
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(__('This template no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $this->templateRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the template.'));
                $this->dataPersistor->clear('defox_seosuite_template');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['template_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the template.'));
            }

            $this->dataPersistor->set('defox_seosuite_template', $data);
            return $resultRedirect->setPath('*/*/edit', ['template_id' => $this->getRequest()->getParam('template_id')]);
        }
        
        return $resultRedirect->setPath('*/*/');
    }
}

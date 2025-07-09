<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model;

use Defox\SEOSuite\Api\Data\TemplateInterface;
use Defox\SEOSuite\Api\TemplateRepositoryInterface;
use Defox\SEOSuite\Model\TemplateFactory;
use Defox\SEOSuite\Model\ResourceModel\Template as ResourceTemplate;
use Defox\SEOSuite\Model\ResourceModel\Template\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Template repository
 * 
 * This class implements the repository interface for template models
 */
class TemplateRepository implements TemplateRepositoryInterface
{
    /**
     * @var ResourceTemplate
     */
    private ResourceTemplate $resource;

    /**
     * @var TemplateFactory
     */
    private TemplateFactory $templateFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var TemplateInterface[]
     */
    private array $instances = [];

    /**
     * @param ResourceTemplate $resource
     * @param TemplateFactory $templateFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceTemplate $resource,
        TemplateFactory $templateFactory,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->templateFactory = $templateFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(TemplateInterface $template): TemplateInterface
    {
        try {
            if ($template->getId()) {
                $this->instances[$template->getId()] = $template;
            }
            $this->resource->save($template);
            $this->instances[$template->getId()] = $template;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the template: %1', $exception->getMessage()),
                $exception
            );
        }
        return $template;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $templateId): TemplateInterface
    {
        if (isset($this->instances[$templateId])) {
            return $this->instances[$templateId];
        }

        $template = $this->templateFactory->create();
        $this->resource->load($template, $templateId);
        if (!$template->getId()) {
            throw new NoSuchEntityException(__('Template with id "%1" does not exist.', $templateId));
        }
        $this->instances[$templateId] = $template;
        return $template;
    }

    /**
     * @inheritdoc
     */
    public function getByTypeAndEntityType(string $type, string $entityType, int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->getByTypeAndEntityType($type, $entityType, $storeId);
        return $collection->getItems();
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(TemplateInterface $template): bool
    {
        try {
            if (isset($this->instances[$template->getId()])) {
                unset($this->instances[$template->getId()]);
            }
            $this->resource->delete($template);
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the template: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $templateId): bool
    {
        return $this->delete($this->getById($templateId));
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Api;

use Defox\SEOSuite\Api\Data\TemplateInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Template repository interface
 * 
 * Provides CRUD operations for SEO templates
 */
interface TemplateRepositoryInterface
{
    /**
     * Save template
     *
     * @param \Defox\SEOSuite\Api\Data\TemplateInterface $template
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TemplateInterface $template): TemplateInterface;

    /**
     * Get template by ID
     *
     * @param int $templateId
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $templateId): TemplateInterface;

    /**
     * Get template by custom criteria
     *
     * @param string $type
     * @param string $entityType
     * @param int $storeId
     * @return \Defox\SEOSuite\Api\Data\TemplateInterface[]
     */
    public function getByTypeAndEntityType(string $type, string $entityType, int $storeId): array;

    /**
     * Get list of templates
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete template
     *
     * @param \Defox\SEOSuite\Api\Data\TemplateInterface $template
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(TemplateInterface $template): bool;

    /**
     * Delete template by ID
     *
     * @param int $templateId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $templateId): bool;
}

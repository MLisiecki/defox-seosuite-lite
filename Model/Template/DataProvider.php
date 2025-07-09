<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Template;

use Defox\SEOSuite\Model\ResourceModel\Template\CollectionFactory;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Template data provider
 * 
 * This class provides data for template edit form
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var \Defox\SEOSuite\Model\ResourceModel\Template\Collection
     */
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @var array
     */
    protected array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $template) {
            $this->loadedData[$template->getId()] = $template->getData();
        }

        $data = $this->dataPersistor->get('defox_seosuite_template');
        if (!empty($data)) {
            $templateId = isset($data['template_id']) ? $data['template_id'] : null;
            $this->loadedData[$templateId] = $data;
            $this->dataPersistor->clear('defox_seosuite_template');
        }

        return $this->loadedData;
    }

    /**
     * Get meta
     *
     * @return array
     */
    public function getMeta(): array
    {
        return parent::getMeta();
    }
}

<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Ui\DataProvider\Template\Modifier;

use Defox\SEOSuite\Helper\VariableHelper;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

/**
 * Enhanced Available Variables modifier
 * 
 * This class modifies the template form to include dynamic available variables data
 */
class AvailableVariables implements ModifierInterface
{
    /**
     * @var VariableHelper
     */
    protected VariableHelper $variableHelper;

    /**
     * @param VariableHelper $variableHelper
     */
    public function __construct(
        VariableHelper $variableHelper
    ) {
        $this->variableHelper = $variableHelper;
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta): array
    {
        // Get all available entity types with their variables
        $entityTypesWithVariables = $this->variableHelper->getEntityTypesWithVariables();
        
        if (empty($entityTypesWithVariables)) {
            return $meta;
        }
        
        // Add dynamic variables component to the form
        $meta['variables_fieldset']['children']['dynamic_variables'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        'component' => 'Defox_SEOSuite/js/form/element/variables-display',
                        'template' => 'Defox_SEOSuite/form/element/variables-display',
                        'label' => __('Available Variables by Entity Type'),
                        'additionalClasses' => 'defox-variables-container',
                        'sortOrder' => 10,
                        'entityTypesVariables' => $entityTypesWithVariables,
                        'imports' => [
                            'updateEntityType' => '${ $.provider }:data.entity_type'
                        ],
                        'deps' => [
                            '${ $.provider }'
                        ]
                    ]
                ]
            ]
        ];
        
        // Modify the static HTML container to be hidden initially
        if (isset($meta['variables_fieldset']['children']['available_variables'])) {
            $meta['variables_fieldset']['children']['available_variables']['arguments']['data']['config']['visible'] = false;
            $meta['variables_fieldset']['children']['available_variables']['arguments']['data']['config']['additionalClasses'] = 'defox-static-variables-hidden';
        }
        
        return $meta;
    }
}

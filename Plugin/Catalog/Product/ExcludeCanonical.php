<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Plugin\Catalog\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\General;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\ArrayManager;

/**
 * Plugin to add canonical exclusion option to product form
 */
class ExcludeCanonical
{
    /**
     * @var ArrayManager
     */
    private ArrayManager $arrayManager;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param ArrayManager $arrayManager
     * @param Registry $registry
     * @param RequestInterface $request
     */
    public function __construct(
        ArrayManager $arrayManager,
        Registry $registry,
        RequestInterface $request
    ) {
        $this->arrayManager = $arrayManager;
        $this->registry = $registry;
        $this->request = $request;
    }

    /**
     * After plugin for modifyMeta method
     *
     * @param General $subject
     * @param array $result
     * @return array
     */
    public function afterModifyMeta(General $subject, array $result): array
    {
        $generalMetaPath = $this->arrayManager->findPath(
            AbstractModifier::DEFAULT_GENERAL_PANEL,
            $result,
            null,
            'children'
        );
        
        if ($generalMetaPath) {
            $generalMeta = $this->arrayManager->get($generalMetaPath, $result);
            
            // Add canonical exclusion field
            $generalChildren = $this->arrayManager->get('children', $generalMeta, []);
            $generalChildren['seo_canonical_disabled'] = $this->getCanonicalFieldConfig(40);
            
            // Set modified children
            $generalMeta = $this->arrayManager->set('children', $generalMeta, $generalChildren);
            
            // Set modified general meta
            $result = $this->arrayManager->set($generalMetaPath, $result, $generalMeta);
        }
        
        return $result;
    }

    /**
     * After plugin for modifyData method
     *
     * @param General $subject
     * @param array $result
     * @return array
     */
    public function afterModifyData(General $subject, array $result): array
    {
        $productId = $this->request->getParam('id');
        
        if ($productId) {
            $product = $this->registry->registry('product');
            
            if ($product instanceof Product) {
                $seoCanonicalDisabled = $product->getData('seo_canonical_disabled') ?? 0;
                
                $result[$productId]['product']['seo_canonical_disabled'] = $seoCanonicalDisabled;
            }
        }
        
        return $result;
    }

    /**
     * Get canonical field config
     *
     * @param int $sortOrder
     * @return array
     */
    private function getCanonicalFieldConfig(int $sortOrder): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'dataType' => 'boolean',
                        'formElement' => 'checkbox',
                        'componentType' => 'field',
                        'label' => __('Disable Canonical URL'),
                        'prefer' => 'toggle',
                        'valueMap' => [
                            'true' => 1,
                            'false' => 0
                        ],
                        'default' => 0,
                        'sortOrder' => $sortOrder,
                        'dataScope' => 'seo_canonical_disabled',
                    ]
                ]
            ]
        ];
    }
}

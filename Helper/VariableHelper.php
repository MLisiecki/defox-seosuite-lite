<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Helper;

use Defox\SEOSuite\Template\VariableProcessorFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;

/**
 * Enhanced variable helper class
 * 
 * This class manages template variables and provides methods to get available variables
 * for different entity types with improved caching and performance
 */
class VariableHelper extends AbstractHelper
{
    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'defox_seosuite_variables_';
    
    /**
     * Cache lifetime (4 hours)
     */
    private const CACHE_LIFETIME = 14400;

    /**
     * @var VariableProcessorFactory
     */
    protected VariableProcessorFactory $variableProcessorFactory;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * @var array
     */
    private array $variablesCache = [];

    /**
     * @param Context $context
     * @param VariableProcessorFactory $variableProcessorFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        Context $context,
        VariableProcessorFactory $variableProcessorFactory,
        CacheInterface $cache
    ) {
        parent::__construct($context);
        $this->variableProcessorFactory = $variableProcessorFactory;
        $this->cache = $cache;
    }

    /**
     * Get available variables for entity type
     *
     * @param string $entityType
     * @return array
     */
    public function getAvailableVariablesForEntityType(string $entityType): array
    {
        // Check memory cache first
        if (isset($this->variablesCache[$entityType])) {
            return $this->variablesCache[$entityType];
        }
        
        // Check persistent cache
        $cacheKey = self::CACHE_PREFIX . $entityType;
        $cachedVariables = $this->cache->load($cacheKey);
        if ($cachedVariables !== false) {
            $variables = json_decode($cachedVariables, true);
            if (is_array($variables)) {
                $this->variablesCache[$entityType] = $variables;
                return $variables;
            }
        }
        
        try {
            $processor = $this->variableProcessorFactory->create($entityType);
            if ($processor) {
                $variables = $processor->getAvailableVariables();
                
                // Cache the result
                $this->variablesCache[$entityType] = $variables;
                $this->cache->save(json_encode($variables), $cacheKey, ['DEFOX_SEO_VARIABLES'], self::CACHE_LIFETIME);
                
                return $variables;
            }
        } catch (\Exception $e) {
            $this->_logger->error('Error getting variables for entity type: ' . $entityType . ' - ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get formatted variables HTML for display
     *
     * @param string $entityType
     * @return string
     */
    public function getFormattedVariablesHtml(string $entityType): string
    {
        $variables = $this->getAvailableVariablesForEntityType($entityType);
        
        if (empty($variables)) {
            return '<p>' . __('No variables available for this entity type.') . '</p>';
        }

        $html = '<div class="defox-variables-list">';
        $html .= '<p class="note">' . __('Click on any variable to copy it to clipboard') . '</p>';
        $html .= '<div class="variables-grid">';
        
        foreach ($variables as $variable => $description) {
            $html .= sprintf(
                '<div class="variable-item" data-variable="%s" onclick="copyToClipboard(\'{{%s}}\')">
                    <strong class="variable-code">{{%s}}</strong>
                    <span class="variable-description">%s</span>
                </div>',
                $this->escapeHtml($variable),
                $this->escapeHtml($variable),
                $this->escapeHtml($variable),
                $this->escapeHtml($description)
            );
        }
        
        $html .= '</div></div>';
        
        return $html;
    }

    /**
     * Get variables as JSON for JavaScript
     *
     * @param string $entityType
     * @return string
     */
    public function getVariablesAsJson(string $entityType): string
    {
        $variables = $this->getAvailableVariablesForEntityType($entityType);
        return json_encode($variables);
    }

    /**
     * Get all entity types with their variable processors
     *
     * @return array
     */
    public function getEntityTypesWithVariables(): array
    {
        $entityTypes = [
            'product' => __('Product'),
            'category' => __('Category'), 
            'cms_page' => __('CMS Page')
        ];

        $result = [];
        foreach ($entityTypes as $type => $label) {
            $variables = $this->getAvailableVariablesForEntityType($type);
            if (!empty($variables)) {
                $result[$type] = [
                    'label' => $label,
                    'variables' => $variables
                ];
            }
        }

        return $result;
    }

    /**
     * Get variables grouped by category
     *
     * @param string $entityType
     * @return array
     */
    public function getGroupedVariables(string $entityType): array
    {
        $variables = $this->getAvailableVariablesForEntityType($entityType);
        $grouped = [];
        
        foreach ($variables as $variable => $description) {
            $parts = explode('.', $variable);
            $category = $parts[0] ?? 'other';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = [
                'code' => $variable,
                'template' => '{{' . $variable . '}}',
                'description' => $description
            ];
        }
        
        return $grouped;
    }

    /**
     * Get variable suggestions for autocomplete
     *
     * @param string $entityType
     * @param string $query
     * @return array
     */
    public function getVariableSuggestions(string $entityType, string $query = ''): array
    {
        $variables = $this->getAvailableVariablesForEntityType($entityType);
        $suggestions = [];
        
        foreach ($variables as $variable => $description) {
            if (empty($query) || stripos($variable, $query) !== false || stripos($description, $query) !== false) {
                $suggestions[] = [
                    'value' => '{{' . $variable . '}}',
                    'label' => $variable . ' - ' . $description,
                    'variable' => $variable,
                    'description' => $description
                ];
            }
        }
        
        return $suggestions;
    }

    /**
     * Clear variables cache
     *
     * @param string|null $entityType
     * @return void
     */
    public function clearCache(?string $entityType = null): void
    {
        if ($entityType) {
            $cacheKey = self::CACHE_PREFIX . $entityType;
            $this->cache->remove($cacheKey);
            unset($this->variablesCache[$entityType]);
        } else {
            $this->cache->clean(['DEFOX_SEO_VARIABLES']);
            $this->variablesCache = [];
        }
    }

    /**
     * Validate variable syntax
     *
     * @param string $variable
     * @param string $entityType
     * @return bool
     */
    public function isValidVariable(string $variable, string $entityType): bool
    {
        // Remove brackets if present
        $cleanVariable = trim($variable, '{}');
        
        $availableVariables = $this->getAvailableVariablesForEntityType($entityType);
        return isset($availableVariables[$cleanVariable]);
    }

    /**
     * Get category label for variables grouping
     *
     * @param string $category
     * @return string
     */
    public function getCategoryLabel(string $category): string
    {
        $labels = [
            'product' => __('Product Variables'),
            'category' => __('Category Variables'),
            'cms_page' => __('CMS Page Variables'),
            'page' => __('Page Variables'),
            'store' => __('Store Variables'),
            'website' => __('Website Variables'),
            'parent_category' => __('Parent Category Variables')
        ];
        
        return $labels[$category] ?? ucfirst($category) . ' ' . __('Variables');
    }

    /**
     * Escape HTML
     *
     * @param string $string
     * @return string
     */
    private function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

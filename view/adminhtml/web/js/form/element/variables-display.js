/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

define([
    'Magento_Ui/js/form/element/abstract',
    'ko',
    'jquery',
    'mage/translate'
], function (Abstract, ko, $, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            template: 'Defox_SEOSuite/form/element/variables-display',
            entityTypesVariables: {},
            currentEntityType: '',
            availableVariables: [],
            imports: {
                updateEntityType: '${ $.provider }:data.entity_type'
            }
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initObservables();
            return this;
        },

        /**
         * Initialize observables
         */
        initObservables: function () {
            this._super();
            this.observe(['currentEntityType', 'availableVariables']);
            return this;
        },

        /**
         * Entity type changed handler
         *
         * @param {String} entityType
         */
        updateEntityType: function (entityType) {
            if (entityType && entityType !== this.currentEntityType()) {
                this.currentEntityType(entityType);
                this.updateAvailableVariables(entityType);
            }
        },

        /**
         * Update available variables based on entity type
         *
         * @param {String} entityType
         */
        updateAvailableVariables: function (entityType) {
            var variables = [];
            
            // Default variables for all entity types
            var commonVariables = {
                'store.name': 'Store name',
                'store.url': 'Store URL',
                'website.name': 'Website name',
                'website.url': 'Website URL'
            };
            
            // Entity-specific variables
            var entitySpecificVariables = {};
            
            if (entityType === 'product') {
                entitySpecificVariables = {
                    'product.name': 'Product name',
                    'product.sku': 'Product SKU',
                    'product.price': 'Product price',
                    'product.description': 'Product description',
                    'product.short_description': 'Product short description',
                    'product.image': 'Product image URL',
                    'product.url': 'Product URL',
                    'category.name': 'Product category name',
                    'category.description': 'Product category description'
                };
            } else if (entityType === 'category') {
                entitySpecificVariables = {
                    'category.name': 'Category name',
                    'category.description': 'Category description',
                    'category.image': 'Category image URL',
                    'category.url': 'Category URL',
                    'category.product_count': 'Number of products in category',
                    'parent_category.name': 'Parent category name'
                };
            } else if (entityType === 'cms_page') {
                entitySpecificVariables = {
                    'cms_page.title': 'CMS page title',
                    'cms_page.content': 'CMS page content',
                    'cms_page.identifier': 'CMS page identifier',
                    'cms_page.url': 'CMS page URL'
                };
            }
            
            // Combine all variables
            var allVariables = Object.assign({}, commonVariables, entitySpecificVariables);
            
            // Convert to array format
            for (var variable in allVariables) {
                if (allVariables.hasOwnProperty(variable)) {
                    variables.push({
                        code: variable,
                        template: '{{' + variable + '}}',
                        description: allVariables[variable]
                    });
                }
            }
            
            this.availableVariables(variables);
        },

        /**
         * Copy variable to clipboard
         *
         * @param {Object} variable
         */
        copyVariable: function (variable) {
            var template = variable.template;
            
            // Create a temporary input element
            var tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(template).select();
            
            try {
                document.execCommand('copy');
                this.showNotification($t('Variable copied to clipboard: ') + template, 'success');
            } catch (err) {
                this.showNotification($t('Failed to copy variable'), 'error');
            }
            
            tempInput.remove();
        },

        /**
         * Show notification message
         *
         * @param {String} message
         * @param {String} type
         */
        showNotification: function (message, type) {
            var notification = $('<div class="message message-' + type + '">' + message + '</div>');
            $('.page-main-actions').after(notification);
            
            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Get variables grouped by category
         *
         * @returns {Object}
         */
        getGroupedVariables: function () {
            var grouped = {};
            var variables = this.availableVariables();
            
            if (!variables || !Array.isArray(variables)) {
                return grouped;
            }
            
            variables.forEach(function (variable) {
                var parts = variable.code.split('.');
                var category = parts[0];
                
                if (!grouped[category]) {
                    grouped[category] = [];
                }
                
                grouped[category].push(variable);
            });
            
            return grouped;
        },

        /**
         * Get category label
         *
         * @param {String} category
         * @returns {String}
         */
        getCategoryLabel: function (category) {
            var labels = {
                'product': $t('Product Variables'),
                'category': $t('Category Variables'),
                'cms_page': $t('CMS Page Variables'),
                'store': $t('Store Variables'),
                'website': $t('Website Variables')
            };
            
            return labels[category] || category.charAt(0).toUpperCase() + category.slice(1) + ' ' + $t('Variables');
        },

        /**
         * Check if has variables
         *
         * @returns {Boolean}
         */
        hasVariables: function () {
            var variables = this.availableVariables();
            return variables && Array.isArray(variables) && variables.length > 0;
        }
    });
});

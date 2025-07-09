/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'underscore'
], function (Select, registry, _) {
    'use strict';

    return Select.extend({
        defaults: {
            // Mapping of template types to their corresponding fieldsets
            fieldsetMapping: {
                'comprehensive': ['meta_title_fieldset', 'meta_description_fieldset', 'meta_keywords_fieldset', 'meta_robots_fieldset', 'open_graph_fieldset'],
                'meta_title': ['meta_title_fieldset'],
                'meta_description': ['meta_description_fieldset'],
                'meta_keywords': ['meta_keywords_fieldset'],
                'meta_robots': ['meta_robots_fieldset'],
                'open_graph': ['open_graph_fieldset']
            },
            // All template fieldsets that should be controlled
            allFieldsets: [
                'meta_title_fieldset',
                'meta_description_fieldset', 
                'meta_keywords_fieldset',
                'meta_robots_fieldset',
                'open_graph_fieldset'
            ]
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            
            // Subscribe to value changes
            this.value.subscribe(this.onTemplateTypeChange.bind(this));
            
            // Initial setup after delay
            _.delay(this.processInitialValue.bind(this), 1000);
            
            return this;
        },

        /**
         * Process initial value
         */
        processInitialValue: function () {
            var currentValue = this.value();
            
            if (currentValue) {
                this.onTemplateTypeChange(currentValue);
            } else {
                this.hideAllFieldsets();
            }
        },

        /**
         * Handle template type change
         *
         * @param {String} templateType
         */
        onTemplateTypeChange: function (templateType) {
            if (!templateType) {
                this.hideAllFieldsets();
                return;
            }
            
            // Process with delay to ensure DOM is ready
            _.delay(function () {
                this.toggleFieldsets(templateType);
            }.bind(this), 300);
        },

        /**
         * Toggle fieldsets visibility based on template type
         *
         * @param {String} templateType
         */
        toggleFieldsets: function (templateType) {
            var activeFieldsets = this.fieldsetMapping[templateType] || [];
            
            // Process each fieldset
            var self = this;
            _.each(this.allFieldsets, function (fieldsetName) {
                var shouldShow = _.contains(activeFieldsets, fieldsetName);
                self.setFieldsetVisibility(fieldsetName, shouldShow);
            });
        },

        /**
         * Set fieldset visibility
         *
         * @param {String} fieldsetName
         * @param {Boolean} visible
         */
        setFieldsetVisibility: function (fieldsetName, visible) {
            // Primary method: Try UI Registry
            this.setFieldsetVisibilityViaRegistry(fieldsetName, visible);
            
            // Backup method: DOM manipulation
            this.setFieldsetVisibilityViaDOM(fieldsetName, visible);
        },

        /**
         * Set fieldset visibility via UI Registry
         *
         * @param {String} fieldsetName
         * @param {Boolean} visible
         */
        setFieldsetVisibilityViaRegistry: function (fieldsetName, visible) {
            var possiblePaths = [
                'defox_seosuite_template_form.' + fieldsetName,
                this.ns + '.' + fieldsetName,
                fieldsetName
            ];
            
            var fieldset = null;
            
            _.each(possiblePaths, function(path) {
                if (!fieldset) {
                    try {
                        fieldset = registry.get(path);
                        if (fieldset && fieldset.visible) {
                            // Found working fieldset, stop looking
                            return false;
                        }
                    } catch (e) {
                        // Continue to next path
                    }
                }
            });
            
            if (fieldset && fieldset.visible && _.isFunction(fieldset.visible)) {
                fieldset.visible(visible);
            }
        },

        /**
         * Set fieldset visibility via DOM manipulation
         *
         * @param {String} fieldsetName
         * @param {Boolean} visible
         */
        setFieldsetVisibilityViaDOM: function (fieldsetName, visible) {
            _.delay(function() {
                var selector = '[data-index="' + fieldsetName + '"]';
                var elements = document.querySelectorAll(selector);
                
                if (elements.length > 0) {
                    _.each(elements, function(element) {
                        if (visible) {
                            element.style.display = 'block';
                            element.classList.remove('fieldset-hidden');
                        } else {
                            element.style.display = 'none';
                            element.classList.add('fieldset-hidden');
                        }
                    });
                }
            }, 100);
        },

        /**
         * Hide all fieldsets
         */
        hideAllFieldsets: function () {
            var self = this;
            _.each(this.allFieldsets, function (fieldsetName) {
                self.setFieldsetVisibility(fieldsetName, false);
            });
        }
    });
});

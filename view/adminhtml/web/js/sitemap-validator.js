/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'domReady!'
], function ($, alert, confirm) {
    'use strict';

    /**
     * Sitemap Validator JavaScript Module
     *
     * Handles all client-side functionality for sitemap validation including:
     * - File upload with drag & drop
     * - URL validation
     * - Current sitemap validation
     * - Progress tracking
     * - Results display
     */
    return function(config) {
        
        // Prevent multiple instances
        if (window.SitemapValidatorInstance) {
            return window.SitemapValidatorInstance;
        }
        
        var SitemapValidator = {
            // Configuration
            config: config || {},
            
            // State management
            currentValidation: null,
            progressTimer: null,
            currentTab: 'file-upload',

            /**
             * Initialize the validator
             */
            init: function() {
                this.bindEvents();
                this.setupFileUpload();
                this.loadCurrentSitemaps();
                this.initializeInterface();
            },

            /**
             * Initialize interface elements
             */
            initializeInterface: function() {
                // Set initial tab
                this.switchTab(this.currentTab);

                // Setup tooltips if available
                if (typeof $.fn.tooltip === 'function') {
                    $('[data-toggle="tooltip"]').tooltip();
                }

                // Initialize advanced options toggle
                this.setupAdvancedOptions();
            },

            /**
             * Setup advanced options toggle
             */
            setupAdvancedOptions: function() {
                var $advancedToggle = $('#toggle-advanced-options');
                var $advancedOptions = $('.advanced-options');

                $advancedToggle.on('click', function() {
                    $advancedOptions.slideToggle(300);
                    var isVisible = $advancedOptions.is(':visible');
                    $advancedToggle.find('span').text(
                        isVisible ? 'Hide Advanced Options' : 'Show Advanced Options'
                    );
                });
            },

            /**
             * Bind all event handlers
             */
            bindEvents: function() {
                var self = this;

                // Tab switching
                $(document).on('click.sitemapValidator', '.validation-tab', function() {
                    var tabName = $(this).data('tab');
                    self.switchTab(tabName);
                });

                // Form submissions
                $(document).on('submit.sitemapValidator', '#sitemap-upload-form', function(e) {
                    e.preventDefault();
                    self.validateUploadedFile();
                });

                $(document).on('submit.sitemapValidator', '#sitemap-url-form', function(e) {
                    e.preventDefault();
                    self.validateFromUrl();
                });

                $(document).on('submit.sitemapValidator', '#current-sitemap-form-element', function(e) {
                    e.preventDefault();
                    self.validateCurrentSitemap();
                });

                // URL checking
                $(document).on('click.sitemapValidator', '#check-url-btn', function() {
                    self.checkUrlAccessibility();
                });

                // Store selection
                $(document).on('change.sitemapValidator', '#store-select', function() {
                    self.onStoreChange();
                });

                // Other actions
                $(document).on('click.sitemapValidator', '#refresh-sitemaps-btn', function() {
                    self.loadCurrentSitemaps();
                });

                $(document).on('click.sitemapValidator', '#cancel-validation-btn', function() {
                    self.cancelValidation();
                });

                $(document).on('click.sitemapValidator', '#export-results-btn', function() {
                    self.exportResults();
                });

                $(document).on('click.sitemapValidator', '#print-results-btn', function() {
                    self.printResults();
                });

                // Validation option changes
                $(document).on('change.sitemapValidator', '.validation-option', function() {
                    self.onValidationOptionChange();
                });

                // Setting inputs
                $(document).on('change.sitemapValidator', '.setting-input', function() {
                    self.onSettingChange();
                });
            },

            /**
             * Setup file upload functionality
             */
            setupFileUpload: function() {
                var self = this;
                
                var $uploadZone = $('#upload-zone');
                var $fileInput = $('#sitemap-file');
                var $fileInputVisible = $('#sitemap-file-visible');
                
                
                if ($fileInput.length > 0) {
                }
                
                if ($uploadZone.length === 0 || $fileInput.length === 0) {
                    return;
                }

                // Clear any existing handlers first
                $uploadZone.off('click');
                $fileInput.off('change');
                $fileInputVisible.off('change');
                $(document).off('click', '#remove-file');

                // Click to upload (fallback for areas not covered by visible input)
                $uploadZone.on('click', function(e) {
                    // Only trigger if click wasn't on the visible file input
                    if (e.target.id !== 'sitemap-file-visible') {
                        e.preventDefault();
                        e.stopPropagation();
                        if ($fileInputVisible[0]) {
                            $fileInputVisible[0].click();
                        }
                    }
                });

                // Drag and drop events
                $uploadZone.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag-over');
                });

                $uploadZone.on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Only remove class if leaving the upload zone completely
                    if (!$(this).find(e.target).length && e.target !== this) {
                        $(this).removeClass('drag-over');
                    }
                });

                $uploadZone.on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag-over');

                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        self.handleFileSelection(files[0]);
                    }
                });

                // File input change handlers for both inputs
                $fileInput.on('change', function(e) {
                    if (this.files && this.files.length > 0) {
                        self.handleFileSelection(this.files[0]);
                        // Copy file to visible input
                        if ($fileInputVisible[0]) {
                            $fileInputVisible[0].files = this.files;
                        }
                    }
                });
                
                $fileInputVisible.on('change', function(e) {
                    if (this.files && this.files.length > 0) {
                        self.handleFileSelection(this.files[0]);
                        // Copy file to hidden input for form submission
                        if ($fileInput[0]) {
                            $fileInput[0].files = this.files;
                        }
                    }
                });

                // Remove file button handler
                $(document).on('click', '#remove-file', function(e) {
                    e.preventDefault();
                    self.removeSelectedFile();
                });
                
                // Add some visual feedback for debugging
                $uploadZone.css('cursor', 'pointer');
                
            },

            /**
             * Handle validation option changes
             */
            onValidationOptionChange: function() {
                var urlAccessibility = $('#check_url_accessibility');
                var maxUrls = $('#max_urls_to_check');
                var timeoutPerUrl = $('#timeout_per_url');

                if (urlAccessibility.is(':checked')) {
                    maxUrls.closest('.setting-item').show();
                    timeoutPerUrl.closest('.setting-item').show();
                } else {
                    maxUrls.closest('.setting-item').hide();
                    timeoutPerUrl.closest('.setting-item').hide();
                }
            },

            /**
             * Handle setting changes
             */
            onSettingChange: function() {
                $('.setting-input').each(function() {
                    var $input = $(this);
                    var min = parseInt($input.attr('min'));
                    var max = parseInt($input.attr('max'));
                    var value = parseInt($input.val());

                    if (!isNaN(min) && value < min) {
                        $input.val(min);
                    }
                    if (!isNaN(max) && value > max) {
                        $input.val(max);
                    }
                });
            },
    
            /**
             * Handle file selection
             */
            handleFileSelection: function(file) {
                var self = this;

                // Validate file
                if (!self.validateFile(file)) {
                    return;
                }

                // Display file info
                $('#file-name').text(file.name);
                $('#file-size').text(self.formatFileSize(file.size));

                // Show file info, hide upload zone
                $('#upload-zone').fadeOut(300, function() {
                    $('#upload-info').fadeIn(300);
                });

                $('#validate-upload-btn').prop('disabled', false).addClass('action-ready');
            },

            /**
             * Validate selected file
             */
            validateFile: function(file) {
                var config = this.config.uploadConfig || {};
                var maxSize = config.max_file_size || 52428800; // 50MB default
                var allowedExts = config.allowed_extensions || ['xml', 'gz'];

                // Check file size
                if (file.size > maxSize) {
                    this.showAlert(
                        'File Too Large',
                        'File size must be less than ' + this.formatFileSize(maxSize)
                    );
                    return false;
                }

                // Check file extension
                var extension = file.name.split('.').pop().toLowerCase();
                if (allowedExts.indexOf(extension) === -1) {
                    this.showAlert(
                        'Invalid File Type',
                        'Only ' + allowedExts.join(', ').toUpperCase() + ' files are allowed'
                    );
                    return false;
                }

                return true;
            },

            /**
             * Remove selected file
             */
            removeSelectedFile: function() {
                $('#sitemap-file').val('');
                $('#sitemap-file-visible').val('');
                $('#upload-info').fadeOut(300, function() {
                    $('#upload-zone').fadeIn(300);
                });
                $('#validate-upload-btn').prop('disabled', true).removeClass('action-ready');
            },

            /**
             * Switch between validation tabs
             */
            switchTab: function(tabName) {
                this.currentTab = tabName;

                // Update tab appearance
                $('.validation-tab').removeClass('active');
                $('.validation-form').removeClass('active').hide();

                $('[data-tab="' + tabName + '"]').addClass('active');
                $('#' + tabName + '-form').addClass('active').fadeIn(300);

                // Reset forms when switching
                this.resetFormState();
            },

            /**
             * Reset form state when switching tabs
             */
            resetFormState: function() {
                // Hide any previous results
                $('#validation-results').hide();
                $('#validation-progress').hide();

                // Reset URL check results
                $('#url-check-result').hide();

                // Reset store selection
                $('#current-sitemaps-list').hide();
            },

            /**
             * Utility functions
             */
            formatFileSize: function(bytes) {
                if (bytes === 0) return '0 B';
                var k = 1024;
                var sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },

            showAlert: function(title, content) {
                alert({
                    title: title,
                    content: content,
                    buttons: [{
                        text: 'OK',
                        class: 'action-primary',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                });
            },

            /**
             * Check URL accessibility
             */
            checkUrlAccessibility: function() {
                var self = this;
                var url = $('#sitemap-url').val().trim();
                
                if (!url) {
                    self.showAlert('URL Required', 'Please enter a sitemap URL to check.');
                    return;
                }
                
                if (!self.isValidUrl(url)) {
                    self.showAlert('Invalid URL', 'Please enter a valid URL starting with http:// or https://');
                    return;
                }
                
                var $checkBtn = $('#check-url-btn');
                var $result = $('#url-check-result');
                var originalText = $checkBtn.find('span').text();
                
                $checkBtn.addClass('loading').prop('disabled', true);
                $checkBtn.find('span').text('Checking...');
                $result.hide();
                
                $.ajax({
                    url: self.config.ajaxUrls.validate_url,
                    type: 'POST',
                    data: {
                        url: url,
                        form_key: $('input[name="form_key"]').val()
                    },
                    dataType: 'json',
                    timeout: 15000,
                    success: function(response) {
                        self.displayUrlCheckResult(response);
                        if (response.success && response.accessible) {
                            $('#validate-url-btn').prop('disabled', false);
                        } else {
                            $('#validate-url-btn').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        self.displayUrlCheckResult({
                            success: false,
                            accessible: false,
                            error: status === 'timeout' ? 'Request timeout' : 'Failed to check URL'
                        });
                        $('#validate-url-btn').prop('disabled', true);
                    },
                    complete: function() {
                        $checkBtn.removeClass('loading').prop('disabled', false);
                        $checkBtn.find('span').text(originalText);
                    }
                });
            },

            /**
             * Display URL check result
             */
            displayUrlCheckResult: function(result) {
                var $resultDiv = $('#url-check-result');
                var $status = $('#url-status');
                var $statusText = $('#url-status-text');
                var $details = $('#url-check-details');

                if (result.accessible) {
                    $status.removeClass('status-error').addClass('status-success');
                    $statusText.text('URL is accessible');

                    var details = '';
                    if (result.http_status) {
                        details += '<p>HTTP Status: ' + result.http_status + '</p>';
                    }
                    if (result.response_time) {
                        details += '<p>Response Time: ' + result.response_time + 'ms</p>';
                    }
                    if (result.content_type) {
                        details += '<p>Content Type: ' + result.content_type + '</p>';
                    }
                    $details.html(details);
                } else {
                    $status.removeClass('status-success').addClass('status-error');
                    $statusText.text('URL is not accessible');
                    $details.html('<p>Error: ' + (result.error || 'HTTP ' + result.http_status) + '</p>');
                }

                $resultDiv.fadeIn(300);
            },

            /**
             * Load current sitemaps for selected store
             */
            loadCurrentSitemaps: function() {
                var self = this;
                var storeId = $('#store-select').val();
                
                
                if (!storeId) {
                    $('#current-sitemaps-list').hide();
                    $('#validate-current-btn').prop('disabled', true);
                    return;
                }
                
                var $listContainer = $('#current-sitemaps-list');
                var $grid = $('#sitemaps-grid');
                
                $grid.html('<div class="loading-message">Loading sitemaps...</div>');
                $listContainer.show();
                
                var ajaxData = {
                    store_id: storeId,
                    form_key: $('input[name="form_key"]').val(),
                    ajax_action: 'get_current_sitemaps'
                };
                
                
                $.ajax({
                    url: self.config.ajaxUrls.get_current_sitemaps,
                    type: 'POST',
                    data: ajaxData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.sitemaps) {
                            self.displayCurrentSitemaps(response.sitemaps, storeId);
                            $('#validate-current-btn').prop('disabled', false);
                        } else {
                            $grid.html('<div class="no-sitemaps">No sitemaps found for this store</div>');
                            $('#validate-current-btn').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText,
                            statusCode: xhr.status
                        });
                        $grid.html('<div class="error-message">Error loading sitemaps</div>');
                        $('#validate-current-btn').prop('disabled', true);
                    }
                });
            },

            /**
             * Handle store selection change
             */
            onStoreChange: function() {
                var storeId = $('#store-select').val();
                if (storeId) {
                    this.loadCurrentSitemaps();
                } else {
                    $('#current-sitemaps-list').hide();
                    $('#validate-current-btn').prop('disabled', true);
                }
            },

            /**
             * Display current sitemaps
             */
            displayCurrentSitemaps: function(sitemaps, selectedStoreId) {
                var $grid = $('#sitemaps-grid');
                
                if (!sitemaps || sitemaps.length === 0) {
                    $grid.html('<div class="no-sitemaps"><p>No active stores found.</p></div>');
                    return;
                }
                
                // If selectedStoreId is provided, filter for that store
                var storesToDisplay = sitemaps;
                if (selectedStoreId) {
                    storesToDisplay = sitemaps.filter(function(sitemap) {
                        return sitemap.store_id == selectedStoreId;
                    });
                }
                
                if (storesToDisplay.length === 0) {
                    $grid.html('<div class="no-sitemaps">No sitemaps found for this store. Generate sitemap first.</div>');
                    return;
                }
                
                $grid.empty();
                
                storesToDisplay.forEach(function(sitemap, index) {
                    var card = $('<div class="sitemap-card">');
                    card.css('animation-delay', (index * 0.1) + 's');

                    card.append('<h4>' + sitemap.store_name + ' (' + sitemap.store_code + ')</h4>');

                    if (sitemap.exists) {
                        card.append('<p class="sitemap-status status-success">✓ Available</p>');
                        if (sitemap.formatted_size) {
                            card.append('<p class="sitemap-info"><strong>Size:</strong> ' + sitemap.formatted_size + '</p>');
                        }
                        if (sitemap.formatted_date) {
                            card.append('<p class="sitemap-info"><strong>Modified:</strong> ' + sitemap.formatted_date + '</p>');
                        }
                        if (sitemap.filename) {
                            card.append('<p class="sitemap-info"><strong>File:</strong> ' + sitemap.filename + '</p>');
                        }
                        if (sitemap.is_compressed) {
                            card.append('<p class="sitemap-note">Compressed (GZ)</p>');
                        }
                        card.append('<a href="' + sitemap.url + '" target="_blank" class="action-secondary action-small">View Sitemap</a>');
                    } else {
                        card.append('<p class="sitemap-status status-error">✗ Not Found</p>');
                        card.append('<p class="sitemap-note">Generate sitemap first</p>');
                    }

                    $grid.append(card);
                });
            },

            /**
             * Validate uploaded file
             */
            validateUploadedFile: function() {
                var self = this;
                var $form = $('#sitemap-upload-form');
                var fileInput = $('#sitemap-file')[0];
                
                if (!fileInput.files || fileInput.files.length === 0) {
                    self.showAlert('No File Selected', 'Please select a sitemap file to validate.');
                    return;
                }
                
                self.startValidation('file_upload', new FormData($form[0]));
            },

            /**
             * Validate from URL
             */
            validateFromUrl: function() {
                var self = this;
                var url = $('#sitemap-url').val().trim();
                
                if (!url) {
                    self.showAlert('URL Required', 'Please enter a sitemap URL to validate.');
                    return;
                }
                
                if (!self.isValidUrl(url)) {
                    self.showAlert('Invalid URL', 'Please enter a valid URL starting with http:// or https://');
                    return;
                }
                
                var formData = new FormData();
                formData.append('form_key', $('input[name="form_key"]').val());
                formData.append('validation_type', 'url_validation');
                formData.append('sitemap_url', url);
                
                self.appendValidationOptions(formData);
                self.startValidation('url_validation', formData);
            },

            /**
             * Validate current sitemap
             */
            validateCurrentSitemap: function() {
                var self = this;
                var storeId = $('#store-select').val();
                
                if (!storeId) {
                    self.showAlert('Store Required', 'Please select a store to validate its sitemap.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('form_key', $('input[name="form_key"]').val());
                formData.append('validation_type', 'current_sitemap');
                formData.append('store_id', storeId);
                
                self.appendValidationOptions(formData);
                self.startValidation('current_sitemap', formData);
            },

            /**
             * Start validation process
             */
            startValidation: function(type, formData) {
                var self = this;
                
                // Show progress
                self.showValidationProgress();
                
                // Submit validation request
                $.ajax({
                    url: self.config.ajaxUrls.validate,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.displayValidationResults(response.validation_result, response.recommendations);
                        } else {
                            self.showValidationError(response.message || 'Validation failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        var message = 'Validation request failed';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        self.showValidationError(message);
                    },
                    complete: function() {
                        self.hideValidationProgress();
                    }
                });
            },

            /**
             * Append validation options to form data
             */
            appendValidationOptions: function(formData) {
                $('.validation-option').each(function() {
                    var $option = $(this);
                    formData.append($option.attr('name'), $option.is(':checked') ? '1' : '0');
                });
                
                $('.setting-input').each(function() {
                    var $setting = $(this);
                    formData.append($setting.attr('name'), $setting.val());
                });
            },

            /**
             * Validate URL format
             */
            isValidUrl: function(string) {
                try {
                    new URL(string);
                    return true;
                } catch (_) {
                    return false;
                }
            },

            /**
             * Show validation progress
             */
            showValidationProgress: function() {
                $('.validation-form').hide();
                $('#validation-progress').show();
                $('#validation-progress-fill').css('width', '0%');
                $('#validation-progress-text').text('Starting validation...');
                $('#validation-progress-percentage').text('0%');
                
                // Simulate progress
                this.simulateProgress();
            },

            /**
             * Hide validation progress
             */
            hideValidationProgress: function() {
                $('#validation-progress').hide();
                if (this.progressTimer) {
                    clearInterval(this.progressTimer);
                    this.progressTimer = null;
                }
            },

            /**
             * Simulate progress for better UX
             */
            simulateProgress: function() {
                var self = this;
                var progress = 0;
                var steps = [
                    'Parsing XML structure...',
                    'Validating schema...',
                    'Checking URLs...',
                    'Analyzing SEO factors...',
                    'Generating report...'
                ];
                var currentStep = 0;
                
                this.progressTimer = setInterval(function() {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90;
                    
                    $('#validation-progress-fill').css('width', progress + '%');
                    $('#validation-progress-percentage').text(Math.round(progress) + '%');
                    
                    if (currentStep < steps.length && progress > (currentStep + 1) * 18) {
                        $('#validation-progress-text').text(steps[currentStep]);
                        currentStep++;
                    }
                }, 800);
            },

            /**
             * Display validation results
             */
            displayValidationResults: function(result, recommendations) {
                this.hideValidationProgress();
                
                // Show results container
                $('#validation-results').show();
                
                // Display summary
                this.displayResultsSummary(result);
                
                // Display detailed results
                this.displayDetailedResults(result);
                
                // Display recommendations
                this.displayRecommendations(recommendations || []);
                
                // Scroll to results
                $('html, body').animate({
                    scrollTop: $('#validation-results').offset().top - 100
                }, 500);
            },

            /**
             * Show validation error
             */
            showValidationError: function(message) {
                this.hideValidationProgress();
                this.showAlert('Validation Error', message);
            },

            /**
             * Cancel validation
             */
            cancelValidation: function() {
                if (this.currentValidation) {
                    this.currentValidation.abort();
                    this.currentValidation = null;
                }
                this.hideValidationProgress();
                $('.validation-form.active').show();
            },

            /**
             * Export results
             */
            exportResults: function() {
                // Implementation for exporting results
            },

            /**
             * Print results
             */
            printResults: function() {
                window.print();
            },

            // Placeholder methods for detailed result display
            displayResultsSummary: function(result) {
                var $summary = $('#results-summary');
                
                var scoreClass = this.getScoreClass(result.score);
                var statusClass = result.is_valid ? 'status-success' : 'status-error';
                var statusText = result.is_valid ? 'Valid' : 'Invalid';
                
                var html = '<div class="summary-cards">';
                
                // Overall Status Card
                html += '<div class="summary-card ' + statusClass + '">';
                html += '<div class="card-icon"><i class="' + (result.is_valid ? 'icon-check' : 'icon-close') + '"></i></div>';
                html += '<div class="card-content">';
                html += '<h3>Status</h3>';
                html += '<p>' + statusText + '</p>';
                html += '</div></div>';
                
                // Score Card
                html += '<div class="summary-card">';
                html += '<div class="card-icon ' + scoreClass + '"><i class="icon-chart"></i></div>';
                html += '<div class="card-content">';
                html += '<h3>SEO Score</h3>';
                html += '<p>' + result.score + '/100</p>';
                html += '</div></div>';
                
                // Errors Card
                html += '<div class="summary-card ' + (result.errors.length > 0 ? 'status-error' : '') + '">';
                html += '<div class="card-icon"><i class="icon-warning"></i></div>';
                html += '<div class="card-content">';
                html += '<h3>Errors</h3>';
                html += '<p class="errors-count">' + result.errors.length + '</p>';
                html += '</div></div>';
                
                // Warnings Card
                html += '<div class="summary-card ' + (result.warnings.length > 0 ? 'status-warning' : '') + '">';
                html += '<div class="card-icon"><i class="icon-info"></i></div>';
                html += '<div class="card-content">';
                html += '<h3>Warnings</h3>';
                html += '<p class="warnings-count">' + result.warnings.length + '</p>';
                html += '</div></div>';
                
                html += '</div>';
                
                $summary.html(html);
            },
            
            displayDetailedResults: function(result) {
                var $details = $('#results-details');
                var html = '';
                
                // Display Errors
                if (result.errors && result.errors.length > 0) {
                    html += '<div class="results-section errors-section">';
                    html += '<h3 class="section-title">Errors (' + result.errors.length + ')</h3>';
                    html += '<div class="issues-list">';
                    
                    result.errors.forEach(function(error) {
                        html += '<div class="issue-item error-item">';
                        html += '<div class="issue-icon"><i class="icon-close"></i></div>';
                        html += '<div class="issue-content">';
                        html += '<h4>' + (error.message || 'Unknown error') + '</h4>';
                        if (error.line) {
                            html += '<p class="issue-location">Line: ' + error.line + (error.column ? ', Column: ' + error.column : '') + '</p>';
                        }
                        if (error.code) {
                            html += '<p class="issue-code">Code: ' + error.code + '</p>';
                        }
                        html += '</div></div>';
                    });
                    
                    html += '</div></div>';
                }
                
                // Display Warnings
                if (result.warnings && result.warnings.length > 0) {
                    html += '<div class="results-section warnings-section">';
                    html += '<h3 class="section-title">Warnings (' + result.warnings.length + ')</h3>';
                    html += '<div class="issues-list">';
                    
                    result.warnings.forEach(function(warning) {
                        html += '<div class="issue-item warning-item">';
                        html += '<div class="issue-icon"><i class="icon-warning"></i></div>';
                        html += '<div class="issue-content">';
                        html += '<h4>' + (warning.message || 'Unknown warning') + '</h4>';
                        if (warning.recommendation) {
                            html += '<p class="issue-recommendation">Recommendation: ' + warning.recommendation + '</p>';
                        }
                        html += '</div></div>';
                    });
                    
                    html += '</div></div>';
                }
                
                // Display Metadata
                if (result.metadata && Object.keys(result.metadata).length > 0) {
                    html += '<div class="results-section">';
                    html += '<h3 class="section-title">Sitemap Information</h3>';
                    html += '<div class="metadata-grid">';
                    
                    for (var key in result.metadata) {
                        if (result.metadata.hasOwnProperty(key)) {
                            var displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                            var value = result.metadata[key];
                            
                            // Format specific values
                            if (key === 'file_size' && typeof value === 'number') {
                                value = this.formatFileSize(value);
                            } else if (key === 'last_modified' && typeof value === 'number') {
                                value = new Date(value * 1000).toLocaleString();
                            }
                            
                            html += '<div class="metadata-item">';
                            html += '<span class="metadata-key">' + displayKey + ':</span>';
                            html += '<span class="metadata-value">' + value + '</span>';
                            html += '</div>';
                        }
                    }
                    
                    html += '</div></div>';
                }
                
                $details.html(html);
            },
            
            displayRecommendations: function(recommendations) {
                var $recommendations = $('#results-recommendations');
                
                if (!recommendations || recommendations.length === 0) {
                    $recommendations.html('<div class="results-section"><div class="no-recommendations">No specific recommendations available.</div></div>');
                    return;
                }
                
                var html = '<div class="results-section">';
                html += '<h3 class="section-title">Recommendations (' + recommendations.length + ')</h3>';
                html += '<div class="recommendations-list">';
                
                recommendations.forEach(function(rec) {
                    var priorityClass = 'priority-' + (rec.priority || 'low');
                    
                    html += '<div class="recommendation-item ' + priorityClass + '">';
                    html += '<div class="rec-priority">' + (rec.priority || 'low').toUpperCase() + '</div>';
                    html += '<div class="rec-content">';
                    html += '<h4>' + (rec.title || 'Recommendation') + '</h4>';
                    html += '<p>' + (rec.description || '') + '</p>';
                    if (rec.action) {
                        html += '<p class="rec-action">Action: ' + rec.action + '</p>';
                    }
                    html += '</div></div>';
                });
                
                html += '</div></div>';
                
                $recommendations.html(html);
            },
            
            /**
             * Get score class based on score value
             */
            getScoreClass: function(score) {
                if (score >= 95) return 'score-excellent';
                if (score >= 80) return 'score-good';
                if (score >= 60) return 'score-average';
                if (score >= 40) return 'score-poor';
                return 'score-critical';
            }
        };

        // Store instance globally to prevent duplicates
        window.SitemapValidatorInstance = SitemapValidator;
        
        // Initialize when DOM is ready
        $(document).ready(function() {
            SitemapValidator.init();
        });
        
        return SitemapValidator;
    };
});

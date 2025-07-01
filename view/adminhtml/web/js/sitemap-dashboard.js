/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

define([
    'jquery',
    'chartjs',
    'Magento_Ui/js/modal/alert'
], function ($, Chart, alert) {
    'use strict';

    /**
     * Sitemap Dashboard JavaScript Module
     * 
     * Handles all dashboard interactions including:
     * - KPI cards updates
     * - Chart rendering and updates
     * - Recent history loading
     * - Auto-refresh functionality
     * - AJAX data loading
     */
    return function (config) {
        
        var Dashboard = {
            // Configuration
            config: config,
            
            // Chart instances
            charts: {
                timeline: null,
                performance: null
            },
            
            /**
             * Update KPI cards when no data is available
             */
            updateKpiCardsEmpty: function () {
                var cards = [
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(1)',
                        value: '0',
                        icon: 'icon-play'
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(2)',
                        value: '0%',
                        icon: 'icon-check'
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(3)',
                        value: '0',
                        icon: 'icon-link'
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(4)',
                        value: '0s',
                        icon: 'icon-clock'
                    }
                ];
                
                var self = this;
                cards.forEach(function (card) {
                    var $card = $(card.selector);
                    if ($card.length === 0) return;
                    
                    // Remove loading state
                    $card.removeClass('loading');
                    
                    // Update value
                    $card.find('.value').text(card.value);
                    
                    // Remove any change indicators
                    $card.find('.change-indicator').remove();
                });
                
                // Update status to show no data
                $('.kpi-section .admin__data-grid-header .note').html('No sitemap generation data available yet. <a href="' + this.config.generateUrl + '">Generate your first sitemap</a> to start tracking statistics.');
            },
            
            // State
            currentPage: 1,
            isLoading: false,
            refreshTimer: null,
            isManualRefresh: false,
            
            /**
             * Initialize dashboard
             */
            init: function () {
                this.loadKpiData();
                this.loadChartData();
                this.loadRecentHistory();
                this.bindEvents();
                this.startAutoRefresh();
                this.initializeTooltips();
            },
            
            /**
             * Bind event handlers
             */
            bindEvents: function () {
                var self = this;
                
                // Refresh KPI button
                $('#refresh-kpi-btn').on('click', function (e) {
                    e.preventDefault();
                    self.isManualRefresh = true;
                    self.loadKpiData();
                    $(this).addClass('loading').prop('disabled', true);
                    setTimeout(function () {
                        $('#refresh-kpi-btn').removeClass('loading').prop('disabled', false);
                    }, 2000);
                });
                
                // Load more history button
                $('#load-more-history').on('click', function (e) {
                    e.preventDefault();
                    self.currentPage++;
                    self.loadRecentHistory(false);
                });
                
                // Chart refresh buttons
                $('.chart-refresh').on('click', function (e) {
                    e.preventDefault();
                    self.loadChartData();
                });
                
                // Export functionality
                $('.export-data').on('click', function (e) {
                    e.preventDefault();
                    self.exportData($(this).data('type'));
                });
                
                // Window resize handler for charts
                $(window).on('resize', this.debounce(function () {
                    self.resizeCharts();
                }, 250));
            },
            
            /**
             * Load KPI data via AJAX
             */
            loadKpiData: function () {
                var self = this;
                
                if (this.isLoading) return;
                this.isLoading = true;
                
                // Check if URLs are properly configured
                if (!this.config.ajaxUrls || !this.config.ajaxUrls.kpi_data) {
                    self.updateKpiCardsEmpty();
                    self.isLoading = false;
                    return;
                }
                
                $.ajax({
                    url: this.config.ajaxUrls.kpi_data,
                    type: 'GET',
                    dataType: 'json',
                    timeout: 10000, // Reduced timeout
                    success: function (response) {
                        if (response.success && response.data) {
                            self.updateKpiCards(response.data);
                            // Don't show success message for automatic loads
                            if (self.isManualRefresh) {
                                self.showSuccess('KPI data updated successfully');
                                self.isManualRefresh = false;
                            }
                        } else {
                            // Handle case where there's no data yet
                            self.updateKpiCardsEmpty();
                        }
                    },
                    error: function (xhr, status, error) {
                        // Always show empty state instead of error for KPI data
                        // This prevents popup errors when module is first installed
                        self.updateKpiCardsEmpty();
                    },
                    complete: function () {
                        self.isLoading = false;
                    }
                });
            },
            
            /**
             * Update KPI cards with new data
             */
            updateKpiCards: function (data) {
                var self = this;
                
                var cards = [
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(1)',
                        value: this.formatNumber(data.total_generations || 0),
                        change: this.calculateChange(data.total_generations, data.previous_total_generations),
                        icon: 'icon-play'
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(2)',
                        value: (data.success_rate || 0).toFixed(1) + '%',
                        change: this.calculateChange(data.success_rate, data.previous_success_rate),
                        icon: 'icon-check',
                        isPercentage: true
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(3)',
                        value: this.formatNumber(data.total_urls || 0),
                        change: this.calculateChange(data.total_urls, data.previous_total_urls),
                        icon: 'icon-link'
                    },
                    {
                        selector: '#kpi-cards .kpi-card:nth-child(4)',
                        value: (data.avg_duration || 0).toFixed(2) + 's',
                        change: this.calculateChange(data.avg_duration, data.previous_avg_duration, true),
                        icon: 'icon-clock',
                        isInverse: true
                    }
                ];
                
                cards.forEach(function (card, index) {
                    var $card = $(card.selector);
                    if ($card.length === 0) return;
                    
                    // Remove loading state
                    $card.removeClass('loading');
                    
                    // Update value with animation
                    self.animateValue($card.find('.value'), card.value);
                    
                    // Update change indicator
                    $card.find('.change-indicator').remove();
                    if (card.change !== null && !isNaN(card.change)) {
                        var changeClass = card.change > 0 ? 'positive' : (card.change < 0 ? 'negative' : 'neutral');
                        var changeIcon = card.change > 0 ? '↑' : (card.change < 0 ? '↓' : '→');
                        
                        // For inverse metrics (like duration), flip the color logic
                        if (card.isInverse) {
                            changeClass = card.change > 0 ? 'negative' : (card.change < 0 ? 'positive' : 'neutral');
                        }
                        
                        var changeIndicator = $('<span>', {
                            class: 'change-indicator ' + changeClass,
                            text: changeIcon + ' ' + Math.abs(card.change).toFixed(1) + (card.isPercentage ? 'pp' : '%')
                        });
                        
                        $card.find('.period').after(changeIndicator);
                    }
                    
                    // Add success/error styling based on performance
                    $card.removeClass('dashboard-success dashboard-warning dashboard-error');
                    if (index === 1) { // Success rate card
                        if (data.success_rate >= 95) {
                            $card.addClass('dashboard-success');
                        } else if (data.success_rate >= 80) {
                            $card.addClass('dashboard-warning');
                        } else {
                            $card.addClass('dashboard-error');
                        }
                    }
                });
                
                // Update last generation status
                this.updateLastGenerationStatus(data);
            },
            
            /**
             * Update last generation status display
             */
            updateLastGenerationStatus: function (data) {
                if (data.last_generation_time) {
                    var statusClass = data.last_generation_status === 'success' ? 'success' : 'error';
                    var statusText = data.last_generation_status === 'success' ? 'Success' : 'Error';
                    var timeText = this.formatDateTime(data.last_generation_time);
                    
                    var statusHtml = 'Statistics for the last 30 days | Last generation: ' +
                        '<span class="status-' + statusClass + '">' + statusText + '</span> ' +
                        '<span class="time">' + timeText + '</span>';
                    
                    $('.kpi-section .admin__data-grid-header .note').html(statusHtml);
                }
            },
            
            /**
             * Load chart data via AJAX
             */
            loadChartData: function () {
                var self = this;
                
                // Show loading state
                $('.chart-loading').show();
                $('.chart-content canvas').addClass('loading');
                
                // Check if URLs are properly configured
                if (!this.config.ajaxUrls || !this.config.ajaxUrls.chart_data) {
                    self.initializeEmptyCharts();
                    $('.chart-loading').hide();
                    $('.chart-content canvas').removeClass('loading');
                    return;
                }
                
                $.ajax({
                    url: this.config.ajaxUrls.chart_data,
                    type: 'GET',
                    dataType: 'json',
                    timeout: 15000, // Reduced timeout
                    success: function (response) {
                        if (response.success && response.data) {
                            self.initializeCharts(response.data);
                        } else {
                            // Initialize empty charts
                            self.initializeEmptyCharts();
                        }
                    },
                    error: function (xhr, status, error) {
                        // Always show empty charts instead of errors
                        self.initializeEmptyCharts();
                    },
                    complete: function () {
                        $('.chart-loading').hide();
                        $('.chart-content canvas').removeClass('loading');
                    }
                });
            },
            
            /**
             * Initialize all charts
             */
            initializeCharts: function (data) {
                this.initTimelineChart(data.timeline || []);
                this.initPerformanceChart(data.performance || []);
                // Provider chart is not implemented yet
                // if (data.providers) {
                //     this.initProviderChart(data.providers);
                // }
            },
            
            /**
             * Initialize empty charts when no data is available
             */
            initializeEmptyCharts: function () {
                this.initTimelineChart([]);
                this.initPerformanceChart([]);
            },
            
            /**
             * Initialize timeline chart
             */
            initTimelineChart: function (data) {
                var ctx = document.getElementById('timeline-chart');
                if (!ctx) return;
                
                // Handle empty data
                if (data.length === 0) {
                    data = [{
                        date: new Date().toISOString().split('T')[0],
                        generations: 0,
                        success_rate: 0,
                        total_urls: 0,
                        avg_duration: 0
                    }];
                }
                
                var chartData = {
                    labels: data.map(function (item) {
                        return new Date(item.date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        });
                    }),
                    datasets: [
                        {
                            label: 'Generations',
                            data: data.map(function (item) { return item.generations || 0; }),
                            borderColor: this.config.chartColors.primary,
                            backgroundColor: this.config.chartColors.primary + '20',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: this.config.chartColors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        },
                        {
                            label: 'Success Rate (%)',
                            data: data.map(function (item) { return item.success_rate || 0; }),
                            borderColor: this.config.chartColors.success,
                            backgroundColor: this.config.chartColors.success + '20',
                            tension: 0.4,
                            yAxisID: 'y1',
                            fill: false,
                            pointBackgroundColor: this.config.chartColors.success,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }
                    ]
                };
                
                var config = {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: this.config.chartColors.primary,
                                borderWidth: 1,
                                cornerRadius: 6,
                                displayColors: true,
                                callbacks: {
                                    title: function (context) {
                                        return 'Date: ' + context[0].label;
                                    },
                                    label: function (context) {
                                        var label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y;
                                        if (context.dataset.label.includes('%')) {
                                            label += '%';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Date',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: '#f0f0f0'
                                }
                            },
                            y: {
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Generations Count',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: '#f0f0f0'
                                },
                                beginAtZero: true
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Success Rate (%)',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                                min: 0,
                                max: 100
                            }
                        },
                        elements: {
                            point: {
                                hoverRadius: 6
                            }
                        }
                    }
                };
                
                // Destroy existing chart
                if (this.charts.timeline) {
                    this.charts.timeline.destroy();
                }
                
                // Create new chart
                this.charts.timeline = new Chart(ctx, config);
            },
            
            /**
             * Initialize performance chart
             */
            initPerformanceChart: function (data) {
                var ctx = document.getElementById('performance-chart');
                if (!ctx) return;
                
                // Handle empty data
                if (data.length === 0) {
                    data = [{
                        date: new Date().toISOString().split('T')[0],
                        avg_duration: 0,
                        total_files: 0,
                        errors_count: 0
                    }];
                }
                
                var chartData = {
                    labels: data.map(function (item) {
                        return new Date(item.date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        });
                    }),
                    datasets: [
                        {
                            label: 'Avg Duration (s)',
                            data: data.map(function (item) { return item.avg_duration || 0; }),
                            borderColor: this.config.chartColors.warning,
                            backgroundColor: this.config.chartColors.warning + '20',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: this.config.chartColors.warning,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        },
                        {
                            label: 'Files Generated',
                            data: data.map(function (item) { return item.total_files || 0; }),
                            borderColor: this.config.chartColors.info,
                            backgroundColor: this.config.chartColors.info + '20',
                            tension: 0.4,
                            yAxisID: 'y1',
                            fill: false,
                            pointBackgroundColor: this.config.chartColors.info,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }
                    ]
                };
                
                var config = {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: this.config.chartColors.warning,
                                borderWidth: 1,
                                cornerRadius: 6,
                                displayColors: true
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Date',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: '#f0f0f0'
                                }
                            },
                            y: {
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Duration (seconds)',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: '#f0f0f0'
                                },
                                beginAtZero: true
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Files Count',
                                    color: '#666',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                                beginAtZero: true
                            }
                        }
                    }
                };
                
                // Destroy existing chart
                if (this.charts.performance) {
                    this.charts.performance.destroy();
                }
                
                // Create new chart
                this.charts.performance = new Chart(ctx, config);
            },
            
            /**
             * Load recent history via AJAX
             */
            loadRecentHistory: function (clearExisting) {
                var self = this;
                
                if (clearExisting !== false) {
                    clearExisting = true;
                    this.currentPage = 1;
                }
                
                // Check if URLs are properly configured
                if (!this.config.ajaxUrls || !this.config.ajaxUrls.recent_history) {
                    if (clearExisting) {
                        $('#recent-history').html('<div class="no-data"><p>No generation history available yet.</p><p><a href="' + (this.config.generateUrl || '#') + '">Generate your first sitemap</a> to start tracking activity.</p></div>');
                    }
                    return;
                }
                
                // Show loading state
                if (clearExisting) {
                    $('#recent-history').html('<div class="history-loading"><div class="loading-spinner"></div><span>Loading recent activity...</span></div>');
                }
                
                $('#load-more-history').prop('disabled', true).addClass('loading');
                
                $.ajax({
                    url: this.config.ajaxUrls.recent_history,
                    type: 'GET',
                    data: {
                        page: this.currentPage,
                        limit: 20
                    },
                    dataType: 'json',
                    timeout: 10000, // Reduced timeout
                    success: function (response) {
                        if (response.success && response.data) {
                            self.updateRecentHistory(response.data, response.pagination, clearExisting);
                        } else {
                            if (clearExisting) {
                                $('#recent-history').html('<div class="no-data"><p>No generation history available yet.</p><p>Generate your first sitemap to see activity here.</p></div>');
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        if (clearExisting) {
                            // Always show no data message instead of error
                            $('#recent-history').html('<div class="no-data"><p>No generation history available yet.</p><p><a href="' + (self.config.generateUrl || '#') + '">Generate your first sitemap</a> to start tracking activity.</p></div>');
                        }
                    },
                    complete: function () {
                        $('#load-more-history').prop('disabled', false).removeClass('loading');
                    }
                });
            },
            
            /**
             * Update recent history display
             */
            updateRecentHistory: function (data, pagination, clearExisting) {
                var $container = $('#recent-history');
                
                if (clearExisting) {
                    $container.empty();
                }
                
                if (data.length === 0 && clearExisting) {
                    $container.html('<div class="no-data"><p>No generation history available yet.</p><p>Generate your first sitemap to see activity here.</p></div>');
                    $('#load-more-history').hide();
                    return;
                }
                
                // Create table if it doesn't exist
                if (clearExisting) {
                    var tableHtml = '<div class="admin__data-grid-outer-wrap">';
                    tableHtml += '<table class="admin__data-table">';
                    tableHtml += '<thead>';
                    tableHtml += '<tr>';
                    tableHtml += '<th>Store</th>';
                    tableHtml += '<th>Generation Time</th>';
                    tableHtml += '<th>Status</th>';
                    tableHtml += '<th>Duration</th>';
                    tableHtml += '<th>URLs</th>';
                    tableHtml += '<th>Files</th>';
                    tableHtml += '<th>Size</th>';
                    tableHtml += '<th>Actions</th>';
                    tableHtml += '</tr>';
                    tableHtml += '</thead>';
                    tableHtml += '<tbody id="history-tbody">';
                    tableHtml += '</tbody>';
                    tableHtml += '</table>';
                    tableHtml += '</div>';
                    $container.html(tableHtml);
                }
                
                // Add rows to table
                var $tbody = $('#history-tbody');
                var self = this;
                
                data.forEach(function (item) {
                    var rowHtml = '<tr data-stat-id="' + item.id + '">';
                    rowHtml += '<td><strong>' + self.escapeHtml(item.store_name) + '</strong><br><small>ID: ' + item.store_id + '</small></td>';
                    rowHtml += '<td>' + self.formatDateTime(item.generation_time) + '</td>';
                    rowHtml += '<td><span class="' + item.status_class + '">' + item.status_text + '</span>';
                    if (item.errors_count > 0) {
                        rowHtml += '<br><small>' + item.errors_count + ' errors</small>';
                    }
                    rowHtml += '</td>';
                    rowHtml += '<td>' + item.duration + 's</td>';
                    rowHtml += '<td>' + self.formatNumber(item.total_urls) + '</td>';
                    rowHtml += '<td>' + item.files_generated + '</td>';
                    rowHtml += '<td>' + item.file_size_mb + ' MB</td>';
                    rowHtml += '<td>';
                    rowHtml += '<button type="button" class="action-secondary action-small view-details" data-stat-id="' + item.id + '">Details</button>';
                    if (item.success) {
                        rowHtml += ' <button type="button" class="action-primary action-small regenerate" data-store-id="' + item.store_id + '">Regenerate</button>';
                    }
                    rowHtml += '</td>';
                    rowHtml += '</tr>';
                    $tbody.append(rowHtml);
                });
                
                // Bind row actions
                this.bindHistoryActions();
                
                // Update load more button
                $('#load-more-history').toggle(pagination.has_more);
            },
            
            /**
             * Bind actions for history rows
             */
            bindHistoryActions: function () {
                var self = this;
                
                // View details action
                $('.view-details').off('click').on('click', function () {
                    var statId = $(this).data('stat-id');
                    self.showGenerationDetails(statId);
                });
                
                // Regenerate action
                $('.regenerate').off('click').on('click', function () {
                    var storeId = $(this).data('store-id');
                    self.regenerateForStore(storeId);
                });
            },
            
            /**
             * Show generation details modal
             */
            showGenerationDetails: function (statId) {
                var self = this;
                
                // Fetch detailed statistics for specific generation
                $.ajax({
                    url: this.config.ajaxUrls.recent_history,
                    type: 'GET',
                    data: {
                        stat_id: statId,
                        details: true
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success && response.data) {
                            var stat = response.data;
                            var content = '<div class="generation-details">';
                            content += '<div class="detail-row"><strong>Store:</strong> ' + stat.store_name + ' (ID: ' + stat.store_id + ')</div>';
                            content += '<div class="detail-row"><strong>Generation Time:</strong> ' + self.formatDateTime(stat.generation_time) + '</div>';
                            content += '<div class="detail-row"><strong>Duration:</strong> ' + stat.duration + ' seconds</div>';
                            content += '<div class="detail-row"><strong>Total URLs:</strong> ' + self.formatNumber(stat.total_urls) + '</div>';
                            content += '<div class="detail-row"><strong>Files Generated:</strong> ' + stat.files_generated + '</div>';
                            content += '<div class="detail-row"><strong>File Size:</strong> ' + stat.file_size_mb + ' MB</div>';
                            content += '<div class="detail-row"><strong>Status:</strong> <span class="' + stat.status_class + '">' + stat.status_text + '</span></div>';
                            if (stat.errors_count > 0) {
                                content += '<div class="detail-row"><strong>Errors:</strong> ' + stat.errors_count + '</div>';
                            }
                            content += '</div>';
                            content += '<style>.generation-details { font-size: 14px; line-height: 1.6; } .detail-row { margin: 8px 0; padding: 4px 0; border-bottom: 1px solid #eee; } .detail-row:last-child { border-bottom: none; }</style>';
                            
                            alert({
                                title: 'Generation Details - ID ' + statId,
                                content: content,
                                buttons: [{
                                    text: 'Close',
                                    class: 'action-primary',
                                    click: function () {
                                        this.closeModal();
                                    }
                                }]
                            });
                        } else {
                            alert({
                                title: 'Generation Details',
                                content: 'Could not load detailed statistics for generation ID ' + statId + '.',
                                buttons: [{
                                    text: 'Close',
                                    class: 'action-primary',
                                    click: function () {
                                        this.closeModal();
                                    }
                                }]
                            });
                        }
                    },
                    error: function () {
                        alert({
                            title: 'Error',
                            content: 'Failed to load generation details. Please try again.',
                            buttons: [{
                                text: 'Close',
                                class: 'action-primary',
                                click: function () {
                                    this.closeModal();
                                }
                            }]
                        });
                    }
                });
            },
            
            /**
             * Regenerate sitemap for specific store
             */
            regenerateForStore: function (storeId) {
                var self = this;
                
                alert({
                    title: 'Regenerate Sitemap',
                    content: 'Do you want to regenerate the sitemap for this store? This will redirect you to the generation page.',
                    buttons: [
                        {
                            text: 'Cancel',
                            class: 'action-secondary',
                            click: function () {
                                this.closeModal();
                            }
                        },
                        {
                            text: 'Regenerate',
                            class: 'action-primary',
                            click: function () {
                                this.closeModal();
                                // Stop auto-refresh to prevent errors during redirect
                                self.stopAutoRefresh();
                                // Small delay to ensure modal closes cleanly
                                setTimeout(function() {
                                    window.location.href = self.config.generateUrl + '?store=' + storeId;
                                }, 200);
                            }
                        }
                    ]
                });
            },
            
            /**
             * Start auto-refresh functionality
             */
            startAutoRefresh: function () {
                var self = this;
                
                if (this.config.refreshInterval > 0) {
                    this.refreshTimer = setInterval(function () {
                        if (!self.isLoading) {
                            self.loadKpiData();
                        }
                    }, this.config.refreshInterval);
                }
            },
            
            /**
             * Stop auto-refresh
             */
            stopAutoRefresh: function () {
                if (this.refreshTimer) {
                    clearInterval(this.refreshTimer);
                    this.refreshTimer = null;
                }
            },
            
            /**
             * Initialize tooltips
             */
            initializeTooltips: function () {
                // Add tooltips to KPI cards
                $('.kpi-card').each(function () {
                    var $card = $(this);
                    var title = $card.find('h3').text();
                    var tooltip = 'Click to view detailed information about ' + title.toLowerCase();
                    $card.attr('title', tooltip);
                });
            },
            
            /**
             * Resize charts on window resize
             */
            resizeCharts: function () {
                if (this.charts.timeline) {
                    this.charts.timeline.resize();
                }
                if (this.charts.performance) {
                    this.charts.performance.resize();
                }
            },
            
            /**
             * Export data functionality
             */
            exportData: function (type) {
                var url;
                switch (type) {
                    case 'csv':
                        url = this.config.ajaxUrls.recent_history + '&export=csv';
                        break;
                    case 'statistics':
                        url = this.config.ajaxUrls.performance_stats + '&export=csv';
                        break;
                    default:
                        this.showError('Invalid export type');
                        return;
                }
                
                // Create hidden iframe for download
                var $iframe = $('<iframe>', {
                    src: url,
                    style: 'display: none;'
                });
                $('body').append($iframe);
                
                // Remove iframe after download
                setTimeout(function () {
                    $iframe.remove();
                }, 5000);
            },
            
            /**
             * Utility functions
             */
            
            /**
             * Calculate percentage change
             */
            calculateChange: function (current, previous, inverse) {
                if (typeof previous === 'undefined' || previous === null || previous === 0) {
                    return null;
                }
                
                var change = ((current - previous) / previous) * 100;
                return Math.round(change * 10) / 10;
            },
            
            /**
             * Format large numbers with K/M suffixes
             */
            formatNumber: function (num) {
                if (num >= 1000000) {
                    return (num / 1000000).toFixed(1) + 'M';
                } else if (num >= 1000) {
                    return (num / 1000).toFixed(1) + 'K';
                }
                return num.toString();
            },
            
            /**
             * Format datetime for display
             */
            formatDateTime: function (datetime) {
                var date = new Date(datetime);
                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },
            
            /**
             * Animate value changes in KPI cards
             */
            animateValue: function ($element, targetValue) {
                var currentValue = $element.text();
                var numericCurrent = parseFloat(currentValue.replace(/[^\d.-]/g, ''));
                var numericTarget = parseFloat(targetValue.toString().replace(/[^\d.-]/g, ''));
                
                if (isNaN(numericCurrent) || isNaN(numericTarget)) {
                    $element.text(targetValue);
                    return;
                }
                
                var duration = 1000; // 1 second
                var startTime = Date.now();
                var suffix = targetValue.toString().replace(/[\d.-]/g, '');
                
                var animate = function () {
                    var elapsed = Date.now() - startTime;
                    var progress = Math.min(elapsed / duration, 1);
                    
                    // Easing function
                    var easeProgress = 1 - Math.pow(1 - progress, 3);
                    
                    var currentAnimValue = numericCurrent + (numericTarget - numericCurrent) * easeProgress;
                    
                    if (suffix.includes('.')) {
                        $element.text(currentAnimValue.toFixed(1) + suffix.replace(/[\d.-]/g, ''));
                    } else if (currentAnimValue >= 1000) {
                        $element.text(Math.round(currentAnimValue) + suffix);
                    } else {
                        $element.text(Math.round(currentAnimValue) + suffix);
                    }
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    }
                };
                
                requestAnimationFrame(animate);
            },
            
            /**
             * Escape HTML to prevent XSS
             */
            escapeHtml: function (text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function (m) { return map[m]; });
            },
            
            /**
             * Debounce function for performance
             */
            debounce: function (func, wait, immediate) {
                var timeout;
                return function () {
                    var context = this, args = arguments;
                    var later = function () {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    var callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            },
            
            /**
             * Show success message
             */
            showSuccess: function (message) {
                // Create temporary success indicator
                var $indicator = $('<div>', {
                    class: 'success-indicator',
                    text: message,
                    css: {
                        position: 'fixed',
                        top: '20px',
                        right: '20px',
                        background: '#28a745',
                        color: '#fff',
                        padding: '10px 20px',
                        borderRadius: '4px',
                        zIndex: 9999,
                        fontSize: '14px'
                    }
                });
                
                $('body').append($indicator);
                
                setTimeout(function () {
                    $indicator.fadeOut(function () {
                        $indicator.remove();
                    });
                }, 3000);
            },
            
            /**
             * Show error message
             */
            showError: function (message) {
                alert({
                    title: 'Dashboard Error',
                    content: message,
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
             * Destroy dashboard (cleanup)
             */
            destroy: function () {
                this.stopAutoRefresh();
                
                // Destroy charts
                if (this.charts.timeline) {
                    this.charts.timeline.destroy();
                }
                if (this.charts.performance) {
                    this.charts.performance.destroy();
                }
                
                // Unbind events
                $(window).off('resize.dashboard');
                $('.view-details, .regenerate, #refresh-kpi-btn, #load-more-history').off('click');
            }
        };
        
        // Auto-initialize dashboard
        Dashboard.init();
        
        return Dashboard;
    };
});

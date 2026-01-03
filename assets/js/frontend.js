/**
 * Impreza WooCommerce Filter Plugin - Frontend JS
 * Handles filter panel functionality including accordion, checkboxes, price slider, AJAX filtering, and URL management
 */

(function($) {
    'use strict';

    var ImprezaFilter = {
        
        /**
         * Initialize the filter module
         */
        init: function() {
            this.bindEvents();
            this.initPriceSlider();
            this.loadFiltersFromURL();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Accordion toggle functionality
            $(document).on('click', '.impreza-filter-accordion-header', function(e) {
                e.preventDefault();
                self.toggleAccordion($(this));
            });

            // Checkbox change event
            $(document).on('change', '.impreza-filter-checkbox', function() {
                self.handleCheckboxChange($(this));
            });

            // Price slider change event
            $(document).on('change', '.impreza-price-slider', function() {
                self.handlePriceChange();
            });

            // Clear filters button
            $(document).on('click', '.impreza-clear-filters', function(e) {
                e.preventDefault();
                self.clearAllFilters();
            });

            // Apply filters button (if manual apply is needed)
            $(document).on('click', '.impreza-apply-filters', function(e) {
                e.preventDefault();
                self.applyFilters();
            });
        },

        /**
         * Toggle accordion section
         */
        toggleAccordion: function($header) {
            var $section = $header.closest('.impreza-filter-section');
            var $content = $section.find('.impreza-filter-content');
            var $icon = $header.find('.impreza-accordion-icon');

            // Close other sections if not multi-expand
            if (!$header.data('multi-expand')) {
                $('.impreza-filter-section').not($section).find('.impreza-filter-content').slideUp(300, function() {
                    $(this).closest('.impreza-filter-section').removeClass('active');
                });
                $('.impreza-accordion-icon').not($icon).removeClass('open');
            }

            // Toggle current section
            $content.slideToggle(300, function() {
                $section.toggleClass('active');
            });
            $icon.toggleClass('open');
        },

        /**
         * Handle checkbox change events
         */
        handleCheckboxChange: function($checkbox) {
            var filterType = $checkbox.data('filter-type');
            var filterValue = $checkbox.val();
            var isChecked = $checkbox.is(':checked');

            // Update URL parameters
            this.updateURLParameter(filterType, filterValue, isChecked);

            // Apply filters with AJAX
            this.applyFilters();
        },

        /**
         * Initialize price slider
         */
        initPriceSlider: function() {
            var $slider = $('.impreza-price-slider');
            
            if ($slider.length) {
                var minPrice = parseInt($slider.data('min') || 0);
                var maxPrice = parseInt($slider.data('max') || 1000);
                var currentMin = parseInt($slider.data('current-min') || minPrice);
                var currentMax = parseInt($slider.data('current-max') || maxPrice);
                var step = parseInt($slider.data('step') || 1);
                var currency = $slider.data('currency') || '$';

                // Initialize slider if jQuery UI is available
                if ($.ui && $.ui.slider) {
                    $slider.slider({
                        range: true,
                        min: minPrice,
                        max: maxPrice,
                        step: step,
                        values: [currentMin, currentMax],
                        slide: function(event, ui) {
                            var $minInput = $('.impreza-price-min');
                            var $maxInput = $('.impreza-price-max');
                            var $priceDisplay = $('.impreza-price-display');

                            $minInput.val(ui.values[0]);
                            $maxInput.val(ui.values[1]);
                            $priceDisplay.text(currency + ui.values[0] + ' - ' + currency + ui.values[1]);
                        },
                        change: function(event, ui) {
                            // Trigger filter on slider change
                            var $minInput = $('.impreza-price-min');
                            var $maxInput = $('.impreza-price-max');

                            $minInput.val(ui.values[0]);
                            $maxInput.val(ui.values[1]);

                            ImprezaFilter.handlePriceChange();
                        }
                    });

                    // Set initial display
                    $('.impreza-price-display').text(currency + currentMin + ' - ' + currency + currentMax);
                }
            }
        },

        /**
         * Handle price slider changes
         */
        handlePriceChange: function() {
            var $minInput = $('.impreza-price-min');
            var $maxInput = $('.impreza-price-max');

            if ($minInput.length && $maxInput.length) {
                var minPrice = parseInt($minInput.val() || 0);
                var maxPrice = parseInt($maxInput.val() || 1000);

                // Update URL parameters
                this.updateURLParameter('min_price', minPrice, true);
                this.updateURLParameter('max_price', maxPrice, true);

                // Apply filters with AJAX
                this.applyFilters();
            }
        },

        /**
         * Apply filters via AJAX
         */
        applyFilters: function() {
            var self = this;
            var filterData = this.getFilterData();

            // Show loading state
            var $container = $('.impreza-products-container');
            if ($container.length) {
                $container.css('opacity', '0.5').css('pointer-events', 'none');
            }

            // Add loading indicator
            $('<div class="impreza-loading"><span class="spinner"></span></div>').appendTo('body');

            $.ajax({
                type: 'POST',
                url: imprezaFilterVars.ajaxUrl,
                data: {
                    action: 'impreza_filter_products',
                    nonce: imprezaFilterVars.nonce,
                    filters: filterData
                },
                success: function(response) {
                    if (response.success) {
                        // Update products container
                        if ($container.length) {
                            $container.html(response.data.html);
                        }

                        // Update product count
                        var $count = $('.impreza-product-count');
                        if ($count.length && response.data.count !== undefined) {
                            $count.text(response.data.count);
                        }

                        // Trigger custom event for other scripts
                        $(document).trigger('impreza-filters-applied', [response.data]);
                    } else {
                        console.error('Filter error:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    // Remove loading indicator
                    $('.impreza-loading').remove();

                    // Reset loading state
                    if ($container.length) {
                        $container.css('opacity', '1').css('pointer-events', 'auto');
                    }
                }
            });
        },

        /**
         * Get current filter data from form
         */
        getFilterData: function() {
            var filters = {};

            // Get checked attribute filters
            $('.impreza-filter-checkbox:checked').each(function() {
                var filterType = $(this).data('filter-type');
                var filterValue = $(this).val();

                if (!filters[filterType]) {
                    filters[filterType] = [];
                }
                filters[filterType].push(filterValue);
            });

            // Get price range
            var $minInput = $('.impreza-price-min');
            var $maxInput = $('.impreza-price-max');

            if ($minInput.length && $maxInput.length) {
                var minPrice = parseInt($minInput.val() || 0);
                var maxPrice = parseInt($maxInput.val() || 1000);

                if (minPrice > 0 || maxPrice < 10000) {
                    filters.price_range = {
                        min: minPrice,
                        max: maxPrice
                    };
                }
            }

            // Get sorting if available
            var $sortSelect = $('.impreza-sort-select');
            if ($sortSelect.length && $sortSelect.val()) {
                filters.sort = $sortSelect.val();
            }

            return filters;
        },

        /**
         * Update URL parameters
         */
        updateURLParameter: function(paramName, paramValue, isActive) {
            var url = window.location.href;
            var regex = new RegExp('[?&]' + paramName + '=([^&#]*)', 'i');
            var separator = url.indexOf('?') > -1 ? '&' : '?';

            // Remove existing parameter
            url = url.replace(regex, '');

            // Add new parameter if active
            if (isActive && paramValue !== null && paramValue !== undefined && paramValue !== '') {
                // Handle array parameters
                if (Array.isArray(paramValue)) {
                    paramValue.forEach(function(val) {
                        url = url + separator + paramName + '[]=' + encodeURIComponent(val);
                        separator = '&';
                    });
                } else {
                    url = url + separator + paramName + '=' + encodeURIComponent(paramValue);
                }
            }

            // Update browser history without reload
            window.history.replaceState({path: url}, '', url);
        },

        /**
         * Load filters from URL parameters on page load
         */
        loadFiltersFromURL: function() {
            var self = this;
            var urlParams = new URLSearchParams(window.location.search);
            var hasFilters = false;

            // Load attribute filters
            urlParams.forEach(function(value, key) {
                if (key.endsWith('[]')) {
                    // Array parameter
                    var filterType = key.replace('[]', '');
                    var values = urlParams.getAll(key);

                    values.forEach(function(val) {
                        var $checkbox = $('.impreza-filter-checkbox[data-filter-type="' + filterType + '"][value="' + val + '"]');
                        if ($checkbox.length) {
                            $checkbox.prop('checked', true);
                            hasFilters = true;
                        }
                    });
                } else if (key === 'min_price' || key === 'max_price') {
                    // Price filter
                    if (key === 'min_price') {
                        $('.impreza-price-min').val(value);
                    } else {
                        $('.impreza-price-max').val(value);
                    }
                    hasFilters = true;
                } else if (key === 'sort') {
                    // Sort filter
                    var $sortSelect = $('.impreza-sort-select');
                    if ($sortSelect.length) {
                        $sortSelect.val(value);
                    }
                }
            });

            // Apply filters if any were found in URL
            if (hasFilters) {
                this.applyFilters();
            }
        },

        /**
         * Clear all filters
         */
        clearAllFilters: function() {
            var self = this;

            // Uncheck all checkboxes
            $('.impreza-filter-checkbox').prop('checked', false);

            // Reset price slider to default
            var $slider = $('.impreza-price-slider');
            if ($slider.length && $.ui && $.ui.slider) {
                var minPrice = parseInt($slider.data('min') || 0);
                var maxPrice = parseInt($slider.data('max') || 1000);

                $slider.slider('values', [minPrice, maxPrice]);
                $('.impreza-price-min').val(minPrice);
                $('.impreza-price-max').val(maxPrice);
            }

            // Reset sort
            var $sortSelect = $('.impreza-sort-select');
            if ($sortSelect.length) {
                $sortSelect.val('');
            }

            // Clear URL parameters
            window.history.replaceState({}, document.title, window.location.pathname);

            // Apply filters
            this.applyFilters();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ImprezaFilter.init();
    });

    // Expose to global scope if needed
    window.ImprezaFilter = ImprezaFilter;

})(jQuery);

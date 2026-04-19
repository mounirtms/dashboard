/**
 * Checkout Performance Enhancements
 * Optimizes loading, validation, and user experience
 * Date: April 19, 2026
 */

define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data'
], function ($, ko, quote, stepNavigator, selectShippingMethodAction, checkoutData) {
    'use strict';

    return function (config, element) {
        console.log('Checkout Performance Enhancement initialized');

        /**
         * Debounce function to limit rate of function calls
         */
        function debounce(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Performance: Lazy load images
         */
        function lazyLoadImages() {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });

                $('.product-image-container img[data-src]').each(function() {
                    imageObserver.observe(this);
                });
            } else {
                // Fallback for browsers without IntersectionObserver
                $('.product-image-container img[data-src]').each(function() {
                    var $img = $(this);
                    $img.attr('src', $img.data('src')).removeAttr('data-src');
                });
            }
        }

        /**
         * Performance: Optimize form validation
         * Only validate on blur, not on every keystroke
         */
        function optimizeFormValidation() {
            var $inputs = $('.checkout-index-index input[type="text"], .checkout-index-index input[type="email"], .checkout-index-index input[type="tel"]');
            
            $inputs.off('keyup.validation').on('blur.validation', debounce(function() {
                var $input = $(this);
                if ($input.data('validate')) {
                    $input.valid();
                }
            }, 300));
        }

        /**
         * Performance: Prefetch next step resources
         */
        function prefetchNextStep() {
            var activeIndex = stepNavigator.getActiveItemIndex();
            
            if (activeIndex === 0) {
                // On shipping step, prefetch payment methods
                console.log('Prefetching payment step resources...');
                // Prefetch logic here
            }
        }

        /**
         * UX: Auto-save form data to localStorage
         */
        function autoSaveFormData() {
            var $form = $('#co-shipping-form, #co-payment-form');
            
            $form.find('input, select, textarea').on('change', debounce(function() {
                var formData = {};
                $form.find('input, select, textarea').each(function() {
                    var $field = $(this);
                    if ($field.attr('name')) {
                        formData[$field.attr('name')] = $field.val();
                    }
                });
                
                try {
                    localStorage.setItem('checkout_form_backup', JSON.stringify(formData));
                    console.log('Form data auto-saved');
                } catch (e) {
                    console.warn('Could not save form data to localStorage:', e);
                }
            }, 1000));
        }

        /**
         * UX: Restore form data from localStorage
         */
        function restoreFormData() {
            try {
                var savedData = localStorage.getItem('checkout_form_backup');
                if (savedData) {
                    var formData = JSON.parse(savedData);
                    console.log('Restoring saved form data...');
                    
                    Object.keys(formData).forEach(function(name) {
                        var $field = $('[name="' + name + '"]');
                        if ($field.length && !$field.val()) {
                            $field.val(formData[name]).trigger('change');
                        }
                    });
                }
            } catch (e) {
                console.warn('Could not restore form data:', e);
            }
        }

        /**
         * UX: Add loading state to buttons
         */
        function enhanceButtonStates() {
            $(document).on('click', 'button.action.primary', function() {
                var $btn = $(this);
                if (!$btn.hasClass('disabled')) {
                    $btn.addClass('loading');
                    $btn.prop('disabled', true);
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(function() {
                        $btn.removeClass('loading').prop('disabled', false);
                    }, 5000);
                }
            });
        }

        /**
         * Performance: Optimize shipping method selection
         */
        function optimizeShippingSelection() {
            var shippingRates = quote.shippingMethod.subscribe(function(method) {
                if (method) {
                    console.log('Shipping method selected:', method.carrier_code + '_' + method.method_code);
                    
                    // Auto-advance to payment after selection (with delay for UX)
                    setTimeout(function() {
                        var nextStepIndex = stepNavigator.getActiveItemIndex() + 1;
                        if (nextStepIndex < stepNavigator.steps().length) {
                            var nextStep = stepNavigator.steps()[nextStepIndex];
                            if (nextStep && nextStep.code === 'payment') {
                                console.log('Auto-advancing to payment step...');
                                stepNavigator.navigateTo(nextStep.code);
                            }
                        }
                    }, 800);
                }
            });
        }

        /**
         * Analytics: Track checkout steps
         */
        function trackCheckoutProgress() {
            stepNavigator.steps().forEach(function(step) {
                step.isVisible.subscribe(function(visible) {
                    if (visible) {
                        console.log('Checkout step visible:', step.code);
                        
                        // Send analytics event
                        if (window.dataLayer) {
                            window.dataLayer.push({
                                'event': 'checkout_step',
                                'step_code': step.code,
                                'step_title': step.title
                            });
                        }
                    }
                });
            });
        }

        /**
         * UX: Smooth scroll to validation errors
         */
        function smoothScrollToErrors() {
            var checkErrorInterval = setInterval(function() {
                var $firstError = $('.field-error:visible:first, .mage-error:visible:first');
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                    clearInterval(checkErrorInterval);
                }
            }, 100);
            
            // Clear after 3 seconds
            setTimeout(function() {
                clearInterval(checkErrorInterval);
            }, 3000);
        }

        /**
         * Performance: Debounced window resize handler
         */
        function handleResponsiveAdjustments() {
            var resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    var width = $(window).width();
                    console.log('Window resized to:', width);
                    
                    // Adjust mobile-specific UI
                    if (width < 768) {
                        $('.opc-sidebar').insertBefore('.opc-wrapper .step-content');
                    } else {
                        $('.opc-sidebar').appendTo('.opc-wrapper');
                    }
                }, 250);
            });
        }

        /**
         * UX: Add keyboard shortcuts
         */
        function addKeyboardShortcuts() {
            $(document).on('keydown', function(e) {
                // Alt + N: Next step
                if (e.altKey && e.key === 'n') {
                    e.preventDefault();
                    $('button.action.primary.continue:visible').first().trigger('click');
                }
                
                // Alt + B: Back step
                if (e.altKey && e.key === 'b') {
                    e.preventDefault();
                    $('button.action.back:visible').first().trigger('click');
                }
                
                // Escape: Close modals
                if (e.key === 'Escape') {
                    $('.modal-popup._show .action-close').trigger('click');
                }
            });
        }

        /**
         * Initialize all enhancements
         */
        function init() {
            console.log('Initializing checkout performance enhancements...');
            
            // Wait for DOM ready
            $(document).ready(function() {
                // Performance enhancements
                lazyLoadImages();
                optimizeFormValidation();
                prefetchNextStep();
                
                // UX enhancements
                autoSaveFormData();
                restoreFormData();
                enhanceButtonStates();
                optimizeShippingSelection();
                handleResponsiveAdjustments();
                addKeyboardShortcuts();
                
                // Analytics
                trackCheckoutProgress();
                
                // Error handling
                $(document).on('ajaxError', function() {
                    smoothScrollToErrors();
                });
                
                console.log('✅ Checkout performance enhancements initialized');
            });
        }

        // Initialize
        init();

        return {
            lazyLoadImages: lazyLoadImages,
            optimizeFormValidation: optimizeFormValidation,
            prefetchNextStep: prefetchNextStep,
            trackCheckoutProgress: trackCheckoutProgress
        };
    };
});

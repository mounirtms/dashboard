/**
 * Enhanced Field Validation Mixin
 * Provides real-time validation, auto-correction, and user guidance
 */
define([
    'jquery',
    'mage/translate',
    'mage/validation'
], function ($, $t) {
    'use strict';

    return function (target) {
        return target.extend({
            
            /**
             * Initialize enhanced validation
             */
            initialize: function () {
                this._super();
                this.setupEnhancedValidation();
                return this;
            },

            /**
             * Setup enhanced validation features
             */
            setupEnhancedValidation: function () {
                var self = this;
                
                // Wait for DOM to be ready
                setTimeout(function() {
                    self.addPhoneValidation();
                    self.addAddressValidation();
                    self.addRealTimeValidation();
                    self.addAutoCorrection();
                }, 1000);
            },

            /**
             * Enhanced phone validation for Algeria
             */
            addPhoneValidation: function () {
                $.validator.addMethod(
                    'validate-algeria-phone',
                    function (value) {
                        if (!value) {
                            return true;
                        }
                        
                        // Remove spaces and special characters
                        var cleaned = value.replace(/[\s\-\(\)]/g, '');
                        
                        // Algeria phone: starts with 0, followed by 5/6/7, then 8 digits
                        // Examples: 0555123456, 0661234567, 0770123456
                        var pattern = /^0[567]\d{8}$/;
                        
                        return pattern.test(cleaned);
                    },
                    $t('Numéro de téléphone invalide. Format: 05XX XX XX XX ou 06XX XX XX XX')
                );
                
                // Apply to telephone fields
                $('input[name="telephone"]').addClass('validate-algeria-phone');
                
                // Auto-format phone number as user types
                $('input[name="telephone"]').on('input', function() {
                    var value = $(this).val().replace(/\D/g, '');
                    if (value.length > 0) {
                        // Format: 0XXX XX XX XX
                        var formatted = value.substring(0, 4);
                        if (value.length >= 5) {
                            formatted += ' ' + value.substring(4, 6);
                        }
                        if (value.length >= 7) {
                            formatted += ' ' + value.substring(6, 8);
                        }
                        if (value.length >= 9) {
                            formatted += ' ' + value.substring(8, 10);
                        }
                        $(this).val(formatted);
                    }
                });
            },

            /**
             * Enhanced address validation
             */
            addAddressValidation: function () {
                $.validator.addMethod(
                    'validate-algeria-address',
                    function (value) {
                        if (!value) {
                            return false;
                        }
                        
                        // Minimum 10 characters for valid address
                        // Must contain at least one number (for street number)
                        var hasNumber = /\d/.test(value);
                        var minLength = value.length >= 10;
                        
                        return hasNumber && minLength;
                    },
                    $t('Veuillez entrer une adresse complète (min. 10 caractères avec un numéro)')
                );
                
                // Apply to street address fields
                $('input[name="street[0]"], textarea[name="street[0]"]').addClass('validate-algeria-address');
            },

            /**
             * Real-time validation feedback
             */
            addRealTimeValidation: function () {
                var self = this;
                
                // Add validation feedback on blur
                $('.checkout-index-index input, .checkout-index-index select, .checkout-index-index textarea').on('blur', function() {
                    var $field = $(this);
                    var $parent = $field.closest('.field');
                    
                    // Skip if already has error
                    if ($parent.hasClass('_error')) {
                        return;
                    }
                    
                    // Validate field
                    if ($field.valid && $field.valid()) {
                        // Show success state temporarily
                        $parent.addClass('_success');
                        setTimeout(function() {
                            $parent.removeClass('_success');
                        }, 2000);
                    }
                });
                
                // Add helpful placeholder examples
                self.addPlaceholderExamples();
            },

            /**
             * Add placeholder examples for better UX
             */
            addPlaceholderExamples: function () {
                // Phone field
                $('input[name="telephone"]').attr('placeholder', 'Ex: 0555 12 34 56');
                
                // Address field
                $('input[name="street[0]"], textarea[name="street[0]"]').attr('placeholder', 'Ex: Rue Ben Boulaid, Cité 20 Août');
                
                // First name
                $('input[name="firstname"]').attr('placeholder', 'Ex: Mohamed');
                
                // Last name
                $('input[name="lastname"]').attr('placeholder', 'Ex: Benali');
            },

            /**
             * Auto-correction features
             */
            addAutoCorrection: function () {
                // Auto-capitalize names
                $('input[name="firstname"], input[name="lastname"]').on('blur', function() {
                    var value = $(this).val();
                    if (value) {
                        // Capitalize first letter of each word
                        var corrected = value.toLowerCase().replace(/\b\w/g, function(l) {
                            return l.toUpperCase();
                        });
                        $(this).val(corrected);
                    }
                });
                
                // Clean and trim all text inputs on blur
                $('.checkout-index-index input[type="text"], .checkout-index-index textarea').on('blur', function() {
                    var value = $(this).val();
                    if (value) {
                        // Remove extra spaces
                        var cleaned = value.trim().replace(/\s+/g, ' ');
                        $(this).val(cleaned);
                    }
                });
                
                // Format postal code (if applicable)
                $('input[name="postcode"]').on('blur', function() {
                    var value = $(this).val();
                    if (value) {
                        // Remove non-digits
                        var cleaned = value.replace(/\D/g, '');
                        // Algeria postal codes are 5 digits
                        if (cleaned.length === 5) {
                            $(this).val(cleaned);
                        }
                    }
                });
            },

            /**
             * Show inline help messages
             */
            showFieldHelp: function (fieldName, message) {
                var $field = $('[name="' + fieldName + '"]').closest('.field');
                var $help = $field.find('.field-help');
                
                if ($help.length === 0) {
                    $help = $('<div class="field-help"></div>');
                    $field.find('.control').append($help);
                }
                
                $help.html(message).show();
            },

            /**
             * Validate entire form before submission
             */
            validateForm: function () {
                var isValid = this._super();
                
                if (!isValid) {
                    // Scroll to first error
                    var $firstError = $('.field._error').first();
                    if ($firstError.length) {
                        $('html, body').animate({
                            scrollTop: $firstError.offset().top - 100
                        }, 500);
                        
                        // Focus on the field
                        $firstError.find('input, select, textarea').first().focus();
                    }
                }
                
                return isValid;
            }
        });
    };
});

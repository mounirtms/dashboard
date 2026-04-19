/**
 * Fixed French Gift Card Component
 * Fixes: API response parsing, balance display, card application
 */
define([
    'Amasty_GiftCardAccount/js/view/payment/gift-card',
    'Amasty_GiftCardAccount/js/action/gift-code',
    'Magento_Ui/js/model/messageList',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/totals',
    'mage/translate',
    'ko',
    'jquery'
], function (Component, giftCodeActions, messageContainer, customer, totals, $t, ko, $) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/payment/gift-card-fr',
            emptyFieldText: $t('Entrez votre code bon cadeau'),
            wrongCodeText: $t('Code invalide ou solde épuisé'),
            noCodesText: $t('Vous n\'avez aucun bon cadeau actif'),
            successText: $t('✅ Bon cadeau appliqué!'),
            removeText: $t('Retirer'),
            applyText: $t('Appliquer'),
            checkText: $t('Vérifier le solde'),
            placeholderText: $t('Code cadeau'),
            titleText: $t('🎁 Carte Cadeau'),
            requireLogin: false  // Allow guests to try
        },

        /**
         * Initialize observables
         */
        initObservable: function () {
            this._super()
                .observe([
                    'balanceVisible',
                    'balanceAmount',
                    'balanceStatus',
                    'isChecking',
                    'isApplying'
                ]);
            
            this.balanceVisible(false);
            this.balanceAmount('');
            this.balanceStatus('');
            this.isChecking(false);
            this.isApplying(false);
            
            return this;
        },

        /**
         * Fixed check method - handles API response correctly
         */
        check: function () {
            var self = this;
            
            if (!this.validate()) {
                messageContainer.addErrorMessage({
                    'message': $t('Veuillez entrer un code valide')
                });
                return;
            }

            this.isChecking(true);
            this.loader.start();
            
            giftCodeActions.check(this.cardCode())
                .done(function(response) {
                    console.log('[GiftCard] Check response:', response);
                    
                    try {
                        // Parse response if string
                        var data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        // Check for error
                        if (data.error || !data.id) {
                            messageContainer.addErrorMessage({
                                'message': data.message || self.wrongCodeText
                            });
                            self.balanceVisible(false);
                            return;
                        }
                        
                        // Success - show balance
                        self.checkedCards([data]);
                        
                        // Extract balance (handle HTML in balance field)
                        var balance = data.balance || '';
                        if (balance.indexOf('<span') !== -1) {
                            // Extract from HTML
                            var tempDiv = document.createElement('div');
                            tempDiv.innerHTML = balance;
                            balance = tempDiv.textContent || tempDiv.innerText;
                        }
                        
                        self.balanceAmount(balance);
                        
                        // Status translation
                        var statusText = data.status || 'Active';
                        if (statusText === 'Actif' || statusText === 'Active' || statusText == 1) {
                            self.balanceStatus($t('Actif'));
                        } else {
                            self.balanceStatus($t('Inactif'));
                        }
                        
                        self.balanceVisible(true);
                        
                        messageContainer.addSuccessMessage({
                            'message': $t('✅ Code valide! Solde: ') + balance
                        });
                        
                    } catch (e) {
                        console.error('[GiftCard] Parse error:', e);
                        messageContainer.addErrorMessage({
                            'message': $t('Erreur lors de la vérification')
                        });
                        self.balanceVisible(false);
                    }
                })
                .fail(function(error) {
                    console.error('[GiftCard] Check failed:', error);
                    messageContainer.addErrorMessage({
                        'message': $t('Code invalide ou solde épuisé')
                    });
                    self.balanceVisible(false);
                })
                .always(function() {
                    self.isChecking(false);
                    self.loader.stop();
                });
        },

        /**
         * Fixed apply method - ensures cart updates
         */
        apply: function () {
            var self = this;
            
            if (!this.validate()) {
                messageContainer.addErrorMessage({
                    'message': $t('Veuillez entrer un code valide')
                });
                return;
            }

            this.isApplying(true);
            this.loader.start();
            
            giftCodeActions.apply(this.cardCode())
                .done(function(response) {
                    console.log('[GiftCard] Apply response:', response);
                    
                    if (response.error) {
                        messageContainer.addErrorMessage({
                            'message': response.message || self.wrongCodeText
                        });
                    } else {
                        // Success
                        self.cardCode('');
                        self.balanceVisible(false);
                        
                        messageContainer.addSuccessMessage({
                            'message': self.successText
                        });
                        
                        // Force totals reload
                        totals.isLoading(true);
                        setTimeout(function() {
                            totals.isLoading(false);
                        }, 100);
                    }
                })
                .fail(function(error) {
                    console.error('[GiftCard] Apply failed:', error);
                    messageContainer.addErrorMessage({
                        'message': $t('Impossible d\'appliquer le code')
                    });
                })
                .always(function() {
                    self.isApplying(false);
                    self.loader.stop();
                });
        },

        /**
         * Validate card code
         */
        validate: function () {
            var code = this.cardCode();
            return code && code.trim().length > 0;
        },

        /**
         * Check if customer is logged in
         */
        isCustomerLoggedIn: function () {
            return customer.isLoggedIn();
        },

        /**
         * Check if visible
         */
        isVisible: function () {
            // Always show if gift cards are enabled
            return this.isGiftCardEnable();
        }
    });
});

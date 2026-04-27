/**
 * Mab_CheckoutCustomization - Gift Card Payment Component (French)
 * - Hidden for guest users
 * - French locale labels
 * - Fixed check status with expiry date display
 */
define([
    'jquery',
    'underscore',
    'uiComponent',
    'Magento_Customer/js/model/customer',
    'Amasty_GiftCardAccount/js/model/payment/gift-card-messages',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'Amasty_GiftCardAccount/js/action/loader',
    'Magento_Checkout/js/model/error-processor',
    'Amasty_GiftCardAccount/js/action/gift-code-actions',
    'Magento_Customer/js/model/customer'
], function (
    $,
    _,
    Component,
    customerModel,
    messageContainer,
    total,
    getPaymentInformationAction,
    fullScreenLoader,
    $t,
    loader,
    errorProcessor,
    giftCodeActions,
    Customer
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/payment/gift-card-fr',
            cardCode: '',
            applyCodes: '',
            loader: {},
            isCart: false,
            titleText: 'Carte Cadeau',
            placeholderText: 'Entrez votre code',
            applyText: 'Appliquer',
            checkText: 'Vérifier le solde',
            removeText: 'Supprimer',
            emptyFieldText: 'Veuillez saisir un code',
            wrongCodeText: 'Code invalide.',
            noCodesText: 'Aucune carte cadeau active.',
            guestCodesText: 'Connectez-vous pour utiliser une carte cadeau.',
            balanceVisible: false,
            balanceAmount: '',
            balanceStatus: '',
            balanceExpiry: '',
            expiryVisible: false,
            errorMessage: '',
            successMessage: '',
            links: {
                checkedCards: '${ "amcard-cart-render" }:cards'
            },
            datalistMessage: '',
            isShowDatalist: false,
            enterKeyCode: 13,
            options: []
        },

        initialize: function () {
            this._super();
            var codes, availableCodes = [];

            if (this.isCart) {
                this.template = 'Mab_CheckoutCustomization/payment/gift-card-fr';
            }

            if (total.getSegment('amgiftcard')) {
                codes = total.getSegment('amgiftcard').title.split(' ').join('');
                this.applyCodes(codes);
            }

            if (!this.applyCodes()) {
                this.applyCodes('');
            }

            if (!_.isUndefined(window.checkoutConfig.amGiftCardAvailableCodes)) {
                _.each(window.checkoutConfig.amGiftCardAvailableCodes, function (code) {
                    availableCodes.push({ value: code });
                });
            }

            this.outsideDatalistClick = this.onOutsideDatalistClick.bind(this);
            this.options(availableCodes);
            this.loader = loader(this.isCart);

            return this;
        },

        initObservable: function () {
            this._super()
                .observe([
                    'cardCode',
                    'checkedCards',
                    'applyCodes',
                    'options',
                    'isShowDatalist',
                    'datalistMessage',
                    'balanceVisible',
                    'balanceAmount',
                    'balanceStatus',
                    'balanceExpiry',
                    'expiryVisible',
                    'errorMessage',
                    'successMessage'
                ]);

            return this;
        },

        isComponentVisible: function () {
            return Customer.isLoggedIn();
        },

        isCustomerLoggedIn: function () {
            return Customer.isLoggedIn();
        },

        setContainer: function (element) {
            this.container = element;
        },

        onDatalistClick: function () {
            var noticeMessage = Customer.isLoggedIn()
                ? this.noCodesText
                : this.guestCodesText;

            if (!this.options().length) {
                this.datalistMessage(noticeMessage);
            }

            this.toggleDatalist();
        },

        toggleDatalist: function () {
            if (!this.isShowDatalist()) {
                this.isShowDatalist(true);
                window.addEventListener('click', this.outsideDatalistClick);
            } else {
                this.hideDatalist();
            }
        },

        onOutsideDatalistClick: function (event) {
            if (!this.container.contains(event.target)) {
                this.hideDatalist();
            }
        },

        onOptionClick: function (value) {
            this.cardCode(value);
            this.hideDatalist();
        },

        hideDatalist: function () {
            this.datalistMessage('');
            this.isShowDatalist(false);
            window.removeEventListener('click', this.outsideDatalistClick);
        },

        removeSelected: function (cartCode) {
            this.loader.start();

            giftCodeActions.remove(cartCode)
                .done(function (code) {
                    this.removeDone(code);
                }.bind(this))
                .fail(function (response) {
                    total.isLoading(false);
                    this.loader.stop();
                    errorProcessor.process(response, messageContainer);
                }.bind(this));
        },

        removeDone: function (code) {
            var deferred = $.Deferred(),
                appliedCodes = this.applyCodes().split(','),
                message = $t('Carte %1 supprimée.').replace('%1', code);

            if (appliedCodes.indexOf(code) !== -1) {
                appliedCodes.splice(appliedCodes.indexOf(code), 1);
            }

            total.isLoading(true);
            getPaymentInformationAction(deferred);
            $.when(deferred).done(function () {
                this.applyCodes(appliedCodes.join(','));
                total.isLoading(false);
                this.loader.stop();
            }.bind(this));

            messageContainer.addSuccessMessage({
                'message': message
            });
        },

        apply: function (component, event) {
            if ((!!event.keyCode && event.keyCode !== this.enterKeyCode) || !this.validate()) {
                return;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            this.loader.start();
            this.errorMessage('');
            this.successMessage('');

            var self = this;
            giftCodeActions.set(this.cardCode()).done(function (response) {
                if (response) {
                    self.applyDone(response);
                }
            }.bind(this))
            .fail(function (response) {
                self.loader.stop();
                total.isLoading(false);
                errorProcessor.process(response, messageContainer);
            }.bind(this));
        },

        applyDone: function (response) {
            var deferred,
                appliedCodes = this.applyCodes().split(','),
                newCode = response.account.code_model.code;

            deferred = $.Deferred();
            total.isLoading(true);
            getPaymentInformationAction(deferred);

            $.when(deferred).done(function () {
                appliedCodes.push(newCode);
                this.applyCodes(appliedCodes.join(','));
                this.loader.stop();
                total.isLoading(false);
                this.cardCode('');
                this.balanceVisible(false);
            }.bind(this));

            messageContainer.addMessages(response.messages);
        },

        /**
         * FIXED: Check gift card - handles JSON object response + shows expiry
         */
        check: function () {
            if (!this.validate()) {
                return;
            }

            this.loader.start();
            this.errorMessage('');
            this.successMessage('');
            this.balanceVisible(false);
            this.expiryVisible(false);

            var self = this;
            giftCodeActions.check(this.cardCode())
                .done(function (response) {
                    var data;
                    try {
                        if (typeof response === 'string') {
                            data = JSON.parse(response);
                        } else {
                            data = response;
                        }
                    } catch (e) {
                        data = response;
                    }

                    if (!data || !data.id || data.error) {
                        self.errorMessage(data && data.message ? data.message : self.wrongCodeText);
                        return;
                    }

                    // Extract balance text (could be HTML)
                    var balanceText = data.balance || '';
                    if (balanceText.indexOf('<') !== -1) {
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = balanceText;
                        balanceText = tempDiv.textContent || tempDiv.innerText || '';
                    }

                    self.balanceAmount(balanceText.trim());

                    // Status
                    if (data.status === 'Actif' || data.status === 'active' || data.status == 1) {
                        self.balanceStatus('Actif');
                    } else if (data.status === 'Expiré' || data.status === 'expired' || data.status == 0) {
                        self.balanceStatus('Expiré');
                    } else {
                        self.balanceStatus(data.status || '');
                    }

                    // Expiry date
                    var expiredDate = data.expiredDate || '';
                    if (expiredDate && expiredDate !== 'unlimited' && expiredDate !== 'null') {
                        var dateObj = new Date(expiredDate);
                        var formattedDate = dateObj.toLocaleDateString('fr-FR', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        });
                        self.balanceExpiry('Expiration: ' + formattedDate);
                        self.expiryVisible(true);
                    } else if (expiredDate === 'unlimited' || expiredDate === 'null') {
                        self.balanceExpiry('Illimité');
                        self.expiryVisible(true);
                    }

                    self.balanceVisible(true);
                    self.successMessage('Carte valide - Solde: ' + balanceText.trim());

                    self.checkedCards([data]);
                })
                .always(function () {
                    self.loader.stop();
                });
        },

        validate: function () {
            if (this.cardCode()) {
                return true;
            }

            this.errorMessage(this.emptyFieldText);
            return false;
        },

        isGiftCardEnable: function () {
            return window.checkoutConfig.isGiftCardEnabled;
        }
    });
});

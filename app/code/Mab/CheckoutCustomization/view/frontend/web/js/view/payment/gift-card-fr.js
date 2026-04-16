/**
 * French version of Amasty Gift Card for logged-in customers only
 */
define([
    'Amasty_GiftCardAccount/js/view/payment/gift-card',
    'Magento_Customer/js/model/customer',
    'mage/translate',
    'ko'
], function (Component, customer, $t, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Mab_CheckoutCustomization/payment/gift-card-fr',
            emptyFieldText: $t('Entrez votre code bon cadeau'),
            wrongCodeText: $t('Code bon cadeau incorrect. Veuillez vérifier et réessayer.'),
            noCodesText: $t('Vous n\'avez aucun bon cadeau actif dans votre compte.'),
            guestCodesText: $t('Veuillez vous connecter pour utiliser vos bons cadeaux.'),
            successText: $t('✅ Bon cadeau appliqué avec succès !'),
            removeText: $t('Retirer'),
            applyText: $t('Appliquer le code'),
            checkText: $t('Vérifier le solde'),
            placeholderText: $t('Ex: TECHNO-XXXX-XXXX'),
            titleText: $t('🎁 Techno Bon Cadeau'),
            addCodeText: $t('Ajouter un code'),
            requireLogin: true
        },

        /**
         * Check if customer is logged in
         * @returns {Boolean}
         */
        isCustomerLoggedIn: function () {
            return customer.isLoggedIn();
        },

        /**
         * Check if gift card should be visible
         * @returns {Boolean}
         */
        isVisible: function () {
            if (this.requireLogin && !this.isCustomerLoggedIn()) {
                return false;
            }
            return this.isGiftCardEnable();
        },

        /**
         * Override initialize to add French translations
         */
        initialize: function () {
            this._super();
            
            // Update messages to French
            this.emptyFieldText = $t('Entrez votre code bon cadeau');
            this.wrongCodeText = $t('Code bon cadeau incorrect. Veuillez vérifier et réessayer.');
            this.noCodesText = $t('Vous n\'avez aucun bon cadeau actif dans votre compte.');
            this.guestCodesText = $t('Veuillez vous connecter pour utiliser vos bons cadeaux.');
            
            return this;
        },

/**
         * Override removeDone to show French message
         */
        removeDone: function (code) {
            var message = $t('Le bon cadeau %1 a été retiré avec succès.').replace('%1', code);
            this._super(code);
        }
    });
});

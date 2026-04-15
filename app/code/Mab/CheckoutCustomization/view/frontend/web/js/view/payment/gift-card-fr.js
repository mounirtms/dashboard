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
            emptyFieldText: $t('Entrez le code de la carte cadeau'),
            wrongCodeText: $t('Code de carte cadeau incorrect.'),
            noCodesText: $t('Vous n\'avez aucun code de carte cadeau actif ajouté à votre compte client.'),
            guestCodesText: $t('Veuillez vous connecter ou vous inscrire en tant que client pour ajouter des codes de carte cadeau à votre compte client et les afficher ici.'),
            successText: $t('Carte cadeau appliquée avec succès'),
            removeText: $t('Retirer'),
            applyText: $t('Appliquer'),
            checkText: $t('Vérifier le statut'),
            placeholderText: $t('Ex: XXXX-XXXX-XXXX'),
            titleText: $t('🎁 Carte Cadeau'),
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
            this.emptyFieldText = $t('Entrez le code de la carte cadeau');
            this.wrongCodeText = $t('Code de carte cadeau incorrect.');
            this.noCodesText = $t('Vous n\'avez aucun code de carte cadeau actif ajouté à votre compte client.');
            this.guestCodesText = $t('Veuillez vous connecter ou vous inscrire en tant que client pour ajouter des codes de carte cadeau à votre compte client et les afficher ici.');
            
            return this;
        },

        /**
         * Override removeDone to show French message
         */
        removeDone: function (code) {
            var message = $t('La carte cadeau %1 a été retirée.').replace('%1', code);
            this._super(code);
        }
    });
});

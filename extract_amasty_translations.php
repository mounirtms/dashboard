<?php
$amastyModules = [
    'vendor/amasty/module-one-step-checkout-core/i18n/en_US.csv',
    'vendor/amasty/module-checkout-delivery-date/i18n/en_US.csv',
    'vendor/amasty/module-checkout-gift-wrap/i18n/en_US.csv',
    'vendor/amasty/module-checkout-layout-builder/i18n/en_US.csv',
    'vendor/amasty/module-checkout-style-switcher/i18n/en_US.csv',
    'vendor/amasty/module-checkout-thank-you-page/i18n/en_US.csv',
    'vendor/amasty/module-gift-card/i18n/en_US.csv',
    'vendor/amasty/module-gift-card-account/i18n/en_US.csv',
    'vendor/amasty/module-gift-card-pro-functionality/i18n/en_US.csv',
];

$translations = [];

// French translations map for Amasty-specific terms
$frenchMap = [
    // Checkout
    'Checkout' => 'Commande',
    'Shipping Address' => 'Adresse de livraison',
    'Shipping Method' => 'Mode de livraison',
    'Payment Method' => 'Mode de paiement',
    'Order Summary' => 'Récapitulatif de commande',
    'Place Order' => 'Passer commande',
    'Discount Code' => 'Code promo',
    'Apply Discount' => 'Appliquer le code',
    'Gift Wrap' => 'Emballage cadeau',
    'Add Gift Wrap' => 'Ajouter emballage cadeau',
    'Delivery Date' => 'Date de livraison',
    'Delivery Comment' => 'Commentaire livraison',
    'Order Comment' => 'Commentaire de commande',
    'Create an Account' => 'Créer un compte',
    'Newsletter Subscription' => 'Inscription newsletter',
    'Subscribe to Newsletter' => 'S\'abonner à la newsletter',
    
    // Gift Card
    'Gift Card' => 'Carte Cadeau',
    'Gift Card Account' => 'Compte Carte Cadeau',
    'Gift Card Code' => 'Code Carte Cadeau',
    'Apply Gift Card' => 'Appliquer Carte Cadeau',
    'Check Balance' => 'Vérifier le solde',
    'Gift Card Balance' => 'Solde Carte Cadeau',
    'Gift Card Amount' => 'Montant Carte Cadeau',
    'Recipient Name' => 'Nom du destinataire',
    'Recipient Email' => 'Email du destinataire',
    'Sender Name' => 'Nom de l\'expéditeur',
    'Gift Message' => 'Message cadeau',
    
    // Common
    'Continue' => 'Continuer',
    'Next' => 'Suivant',
    'Back' => 'Retour',
    'Cancel' => 'Annuler',
    'Save' => 'Enregistrer',
    'Edit' => 'Modifier',
    'Remove' => 'Supprimer',
    'Update' => 'Mettre à jour',
    'Required' => 'Requis',
    'Optional' => 'Optionnel',
    'Please select' => 'Veuillez sélectionner',
    'Select' => 'Sélectionner',
    'Yes' => 'Oui',
    'No' => 'Non',
];

// Extract all English strings from Amasty modules
foreach ($amastyModules as $csvFile) {
    if (!file_exists($csvFile)) continue;
    
    $handle = fopen($csvFile, 'r');
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 1) {
            $english = trim($data[0]);
            if ($english && $english !== 'en_US') {
                // Apply French translation if available
                $french = $frenchMap[$english] ?? $english;
                $translations[$english] = $french;
            }
        }
    }
    fclose($handle);
}

echo "Extracted " . count($translations) . " unique Amasty strings\n";

// Load existing French translations
$frenchCsv = 'app/i18n/Mab/fr_FR/fr_FR.csv';
$existing = [];
if (file_exists($frenchCsv)) {
    $handle = fopen($frenchCsv, 'r');
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 2) {
            $existing[trim($data[0])] = trim($data[1]);
        }
    }
    fclose($handle);
}

// Merge translations
$merged = array_merge($translations, $existing);
ksort($merged);

// Write back to CSV
$handle = fopen($frenchCsv, 'w');
foreach ($merged as $en => $fr) {
    fputcsv($handle, [$en, $fr]);
}
fclose($handle);

echo "✓ Written " . count($merged) . " translations to $frenchCsv\n";

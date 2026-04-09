#!/usr/bin/env php
<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * 🧪 CHECKOUT COMPLETE TEST & VALIDATION
 * ═══════════════════════════════════════════════════════════════════════════
 * Tests checkout page, configuration, and all components
 * 
 * Usage: php test-checkout-complete.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "🧪 TEST COMPLET DU PAIEMENT (CHECKOUT)\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";
echo "📅 Test : " . date('Y-m-d H:i:s') . "\n\n";

$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

function test($name, $result, $message = '') {
    global $testResults;
    $testResults['total']++;
    
    if ($result) {
        $testResults['passed']++;
        echo "✅ $name\n";
    } else {
        $testResults['failed']++;
        echo "❌ $name";
        if ($message) {
            echo " - $message";
        }
        echo "\n";
    }
}

function warning($name, $message) {
    global $testResults;
    $testResults['total']++;
    $testResults['warnings']++;
    echo "⚠️  $name - $message\n";
}

try {
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Layout Files
    // ═══════════════════════════════════════════════════════════════════════════
    echo "┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 1: Fichiers de Layout\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $layoutFile = 'app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml';
    test("Fichier de layout existe", file_exists($layoutFile));
    
    $layoutContent = file_get_contents($layoutFile);
    test("Layout ne référence pas custom.phtml", 
        strpos($layoutContent, 'custom.phtml') === false,
        "custom.phtml trouvé dans le layout");
    
    test("Layout contient shipping-method-first", 
        strpos($layoutContent, 'shipping-method-first') !== false);
    
    test("Layout contient social-buttons-checkout", 
        strpos($layoutContent, 'social-buttons-checkout') !== false);
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: JavaScript Files
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 2: Fichiers JavaScript\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $jsFile = 'app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-first.js';
    test("JavaScript shipping-method-first existe", file_exists($jsFile));
    
    if (file_exists($jsFile)) {
        $jsContent = file_get_contents($jsFile);
        test("JavaScript contient définition de composant", 
            strpos($jsContent, 'Component.extend') !== false);
        test("JavaScript contient selectedMethod observable", 
            strpos($jsContent, 'selectedMethod') !== false);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: HTML Templates
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 3: Templates HTML\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $htmlFile = 'app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-first.html';
    test("Template HTML existe", file_exists($htmlFile));
    
    if (file_exists($htmlFile)) {
        $htmlContent = file_get_contents($htmlFile);
        test("Template contient shippingMethods foreach", 
            strpos($htmlContent, 'foreach: shippingMethods') !== false);
        test("Template contient showAddressForm", 
            strpos($htmlContent, 'showAddressForm') !== false);
        test("Template contient showStorePickup", 
            strpos($htmlContent, 'showStorePickup') !== false);
        test("Template contient styles CSS", 
            strpos($htmlContent, '<style>') !== false);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Fichiers de Traduction
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 4: Traductions Françaises\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $translations = [
        'CheckoutCustomization' => 'app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv',
        'YellowSaturdayPopup' => 'app/code/Mab/YellowSaturdayPopup/i18n/fr_FR.csv',
        'SocialLogin' => 'app/code/Mab/SocialLogin/i18n/fr_FR.csv',
        'YalidineCarrier' => 'app/code/Mab/YalidineCarrier/i18n/fr_FR.csv'
    ];
    
    foreach ($translations as $module => $file) {
        $exists = file_exists($file);
        test("Traduction $module existe", $exists);
        
        if ($exists) {
            $lines = count(file($file));
            echo "   📊 $lines traductions\n";
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Configuration Magento
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 5: Configuration Magento\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
    
    // Langue
    $locale = $scopeConfig->getValue('general/locale/code');
    test("Langue configurée en français", $locale === 'fr_FR', "Actuel: $locale");
    
    // Yalidine
    $yalidineActive = $scopeConfig->getValue('carriers/yalidine/active');
    test("Yalidine activé", $yalidineActive == 1);
    
    $originWilaya = $scopeConfig->getValue('carriers/yalidine/origin_wilaya');
    test("Wilaya d'origine configurée", !empty($originWilaya), "Valeur: $originWilaya");
    
    // Store Pickup
    $pickupActive = $scopeConfig->getValue('carriers/amstorepickup/active');
    test("Retrait en magasin activé", $pickupActive == 1);
    
    // Flat Rate désactivé
    $flatrateActive = $scopeConfig->getValue('carriers/flatrate/active');
    test("Flat Rate désactivé", $flatrateActive == 0);
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Modules
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 6: Modules Magento\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $moduleManager = $objectManager->get('Magento\Framework\Module\Manager');
    
    $modules = [
        'Mab_CheckoutCustomization',
        'Mab_YalidineCarrier',
        'Mab_SocialLogin',
        'Mab_YellowSaturdayPopup',
        'Amasty_StorePickupWithLocator'
    ];
    
    foreach ($modules as $module) {
        $enabled = $moduleManager->isEnabled($module);
        test("Module $module activé", $enabled);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Fichiers Statiques Déployés
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 7: Contenu Statique Déployé\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    // Check if frontend static files exist
    $staticDirs = glob('pub/static/frontend/*/fr_FR', GLOB_ONLYDIR);
    test("Contenu statique fr_FR déployé", count($staticDirs) > 0, 
        "Trouvé " . count($staticDirs) . " thèmes");
    
    // Check for our custom JS
    $customJs = glob('pub/static/frontend/*/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-first.js');
    test("JavaScript custom déployé", count($customJs) > 0);
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Permissions
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 8: Permissions des Répertoires\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $dirs = [
        'var/cache' => 'Cache',
        'var/page_cache' => 'Page Cache',
        'var/log' => 'Logs',
        'pub/static' => 'Static',
        'generated' => 'Generated'
    ];
    
    foreach ($dirs as $dir => $name) {
        $writable = is_writable($dir);
        test("$name inscriptible", $writable);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Cache Status
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 9: État du Cache\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    $cacheTypes = [
        'config' => 'Configuration',
        'layout' => 'Layout',
        'block_html' => 'Block HTML',
        'full_page' => 'Full Page'
    ];
    
    $cacheTypeList = $objectManager->get('Magento\Framework\App\Cache\TypeListInterface');
    
    foreach ($cacheTypes as $code => $name) {
        $status = $cacheTypeList->getTypeLabels()[$code] ?? null;
        if ($status) {
            echo "   ✓ $name\n";
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: Recommandations
    // ═══════════════════════════════════════════════════════════════════════════
    echo "\n┌─────────────────────────────────────────────────────────────────────────\n";
    echo "│ TEST 10: Recommandations\n";
    echo "└─────────────────────────────────────────────────────────────────────────\n\n";
    
    // Check if maintenance mode is off
    $maintenanceFile = 'var/.maintenance.flag';
    if (file_exists($maintenanceFile)) {
        warning("Mode maintenance", "Mode maintenance activé - désactiver avec: php bin/magento maintenance:disable");
    } else {
        echo "   ✓ Mode maintenance désactivé\n";
    }
    
    // Check generated files
    if (is_dir('generated/code')) {
        echo "   ✓ Code généré présent\n";
    } else {
        warning("Code généré", "Exécuter: php bin/magento setup:di:compile");
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    $testResults['failed']++;
}

// ═══════════════════════════════════════════════════════════════════════════
// RÉSUMÉ FINAL
// ═══════════════════════════════════════════════════════════════════════════
echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "📊 RÉSUMÉ DES TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

echo "Total:        {$testResults['total']}\n";
echo "✅ Réussis:   {$testResults['passed']}\n";
echo "❌ Échecs:    {$testResults['failed']}\n";
echo "⚠️  Avertis:  {$testResults['warnings']}\n\n";

$successRate = $testResults['total'] > 0 
    ? round(($testResults['passed'] / $testResults['total']) * 100, 1) 
    : 0;

if ($testResults['failed'] === 0) {
    echo "🎉 TOUS LES TESTS PASSENT ! Taux de réussite: {$successRate}%\n";
    if ($testResults['warnings'] > 0) {
        echo "⚠️  Note: {$testResults['warnings']} avertissement(s) trouvé(s)\n";
    }
    $exitCode = 0;
} else {
    echo "❌ CERTAINS TESTS ÉCHOUENT ! Taux de réussite: {$successRate}%\n";
    echo "📋 Veuillez examiner les échecs ci-dessus.\n";
    $exitCode = 1;
}

echo "\n📋 TESTS MANUELS À EFFECTUER:\n";
echo "  1. Ouvrir https://beta.technostationery.com/checkout\n";
echo "  2. Vérifier que la page se charge sans erreur\n";
echo "  3. Vérifier les boutons de connexion sociale\n";
echo "  4. Vérifier le sélecteur de méthode de livraison\n";
echo "  5. Sélectionner Yalidine → Formulaire d'adresse apparaît\n";
echo "  6. Sélectionner Retrait → Sélecteur de magasin apparaît\n";
echo "  7. Vérifier tous les textes en français\n";
echo "  8. Tester sur mobile\n";

echo "\n═══════════════════════════════════════════════════════════════════════════\n";

exit($exitCode);

<?php
namespace Mab\AdminLocale\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\App\State;

class AuditFrontendTranslations extends Command
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var State
     */
    private $appState;

    /**
     * @param Filesystem $filesystem
     * @param State $appState
     */
    public function __construct(
        Filesystem $filesystem,
        State $appState
    ) {
        parent::__construct();
        $this->filesystem = $filesystem;
        $this->appState = $appState;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('mab:frontend:audit-translations')
            ->setDescription('Audit frontend for missing French translations')
            ->addOption(
                'fix',
                'f',
                InputOption::VALUE_NONE,
                'Automatically create missing translation files'
            );
    }

    /**
     * Execute command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Exception $e) {
            // Area already set
        }

        $output->writeln('<info>Starting frontend translation audit...</info>');

        $missingTranslations = $this->findMissingTranslations();
        $this->reportMissingTranslations($output, $missingTranslations);

        if ($input->getOption('fix')) {
            $this->createMissingTranslations($output, $missingTranslations);
        }

        $output->writeln('<info>Frontend translation audit completed.</info>');
        return 0;
    }

    /**
     * Find missing French translations
     */
    private function findMissingTranslations()
    {
        $missing = [];
        $rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);

        // Check common areas for missing translations
        $areasToCheck = [
            'app/code' => 'Custom modules',
            'vendor/amasty' => 'Amasty extensions',
            'app/design/frontend' => 'Frontend themes'
        ];

        foreach ($areasToCheck as $path => $description) {
            if ($rootDir->isExist($path)) {
                $missing[$description] = $this->scanDirectoryForTranslations($path);
            }
        }

        return $missing;
    }

    /**
     * Scan directory for missing translations
     */
    private function scanDirectoryForTranslations($path)
    {
        $missing = [];
        $rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootDir->getAbsolutePath($path))
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    
                    // Look for __() translation calls
                    preg_match_all('/__\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
                    
                    if (!empty($matches[1])) {
                        $relativePath = str_replace($rootDir->getAbsolutePath(), '', $file->getPathname());
                        $missing[$relativePath] = $matches[1];
                    }
                }
            }
        } catch (\Exception $e) {
            // Skip directories that can't be read
        }

        return $missing;
    }

    /**
     * Report missing translations
     */
    private function reportMissingTranslations(OutputInterface $output, array $missing)
    {
        $totalMissing = 0;

        foreach ($missing as $area => $files) {
            if (!empty($files)) {
                $output->writeln("<comment>$area:</comment>");
                
                foreach ($files as $file => $translations) {
                    $count = count($translations);
                    $totalMissing += $count;
                    $output->writeln("  - $file: $count untranslated strings");
                }
                
                $output->writeln('');
            }
        }

        $output->writeln("<info>Total potentially missing translations: $totalMissing</info>");
    }

    /**
     * Create missing translation files
     */
    private function createMissingTranslations(OutputInterface $output, array $missing)
    {
        $output->writeln('<info>Creating missing translation files...</info>');

        // Create common frontend translations
        $this->createCommonFrontendTranslations();

        // Create theme-specific translations
        $this->createThemeTranslations();

        $output->writeln('<info>Translation files created. Please review and customize as needed.</info>');
    }

    /**
     * Create common frontend translations
     */
    private function createCommonFrontendTranslations()
    {
        $commonTranslations = [
            // Common frontend strings
            '"Add to Cart","Ajouter au Panier"',
            '"Add to Wishlist","Ajouter à la Liste de Souhaits"',
            '"Add to Compare","Ajouter à la Comparaison"',
            '"Quick View","Aperçu Rapide"',
            '"View Details","Voir les Détails"',
            '"Out of Stock","Rupture de Stock"',
            '"In Stock","En Stock"',
            '"Price","Prix"',
            '"Special Price","Prix Spécial"',
            '"Regular Price","Prix Régulier"',
            '"Save %1","Économisez %1"',
            '"Free Shipping","Livraison Gratuite"',
            '"Search","Rechercher"',
            '"Search entire store here...","Rechercher dans tout le magasin..."',
            '"My Account","Mon Compte"',
            '"Sign In","Se Connecter"',
            '"Create an Account","Créer un Compte"',
            '"Checkout","Commander"',
            '"Shopping Cart","Panier d\'Achat"',
            '"Wishlist","Liste de Souhaits"',
            '"Compare Products","Comparer les Produits"',
            '"Newsletter","Newsletter"',
            '"Subscribe","S\'abonner"',
            '"Contact Us","Nous Contacter"',
            '"About Us","À Propos de Nous"',
            '"Privacy Policy","Politique de Confidentialité"',
            '"Terms and Conditions","Conditions Générales"',
            '"Customer Service","Service Client"',
            '"Return Policy","Politique de Retour"',
            '"Shipping Information","Informations de Livraison"',
            '"Payment Methods","Méthodes de Paiement"',
            '"Size Guide","Guide des Tailles"',
            '"Product Reviews","Avis sur le Produit"',
            '"Write a Review","Écrire un Avis"',
            '"Rating","Évaluation"',
            '"Quality","Qualité"',
            '"Value","Valeur"',
            '"Recommended","Recommandé"',
            '"New","Nouveau"',
            '"Sale","Solde"',
            '"Best Seller","Meilleure Vente"',
            '"Featured","En Vedette"',
            '"Categories","Catégories"',
            '"Brands","Marques"',
            '"Filter","Filtrer"',
            '"Sort By","Trier Par"',
            '"Show","Afficher"',
            '"per page","par page"',
            '"View as","Voir comme"',
            '"List","Liste"',
            '"Grid","Grille"',
            '"Previous","Précédent"',
            '"Next","Suivant"',
            '"First","Premier"',
            '"Last","Dernier"',
            '"Page","Page"',
            '"of","de"',
            '"items","articles"',
            '"Item","Article"',
            '"Items","Articles"',
            '"Product","Produit"',
            '"Products","Produits"',
            '"Category","Catégorie"',
            '"Home","Accueil"',
            '"Back","Retour"',
            '"Continue","Continuer"',
            '"Submit","Soumettre"',
            '"Cancel","Annuler"',
            '"Close","Fermer"',
            '"Save","Enregistrer"',
            '"Edit","Modifier"',
            '"Delete","Supprimer"',
            '"Remove","Retirer"',
            '"Update","Mettre à Jour"',
            '"Refresh","Actualiser"',
            '"Loading...","Chargement..."',
            '"Please wait...","Veuillez patienter..."',
            '"Error","Erreur"',
            '"Success","Succès"',
            '"Warning","Avertissement"',
            '"Information","Information"',
            '"Required field","Champ obligatoire"',
            '"Optional","Optionnel"',
            '"Yes","Oui"',
            '"No","Non"',
            '"Select","Sélectionner"',
            '"Choose","Choisir"',
            '"All","Tout"',
            '"None","Aucun"',
            '"More","Plus"',
            '"Less","Moins"',
            '"Show More","Afficher Plus"',
            '"Show Less","Afficher Moins"',
            '"Read More","Lire Plus"',
            '"Read Less","Lire Moins"'
        ];

        $translationContent = implode("\n", $commonTranslations);
        
        // Save to app/i18n/fr_FR.csv for global frontend translations
        $rootDir = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $rootDir->writeFile('app/i18n/Mab_Frontend_fr_FR.csv', $translationContent);
    }

    /**
     * Create theme-specific translations
     */
    private function createThemeTranslations()
    {
        $themeTranslations = [
            // Sm/market theme specific
            '"Shop Now","Acheter Maintenant"',
            '"Discover More","Découvrir Plus"',
            '"Latest Products","Derniers Produits"',
            '"Trending Now","Tendance Maintenant"',
            '"Best Deals","Meilleures Offres"',
            '"Limited Time","Temps Limité"',
            '"Exclusive","Exclusif"',
            '"Premium Quality","Qualité Premium"',
            '"Fast Delivery","Livraison Rapide"',
            '"Secure Payment","Paiement Sécurisé"',
            '"Money Back Guarantee","Garantie de Remboursement"',
            '"24/7 Support","Support 24/7"',
            '"Free Returns","Retours Gratuits"',
            '"Worldwide Shipping","Livraison Mondiale"'
        ];

        $themeContent = implode("\n", $themeTranslations);
        
        // Save to theme directory
        $rootDir = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $themePath = 'app/design/frontend/Sm/market/i18n';
        
        if (!$rootDir->isExist($themePath)) {
            $rootDir->create($themePath);
        }
        
        $rootDir->writeFile($themePath . '/fr_FR.csv', $themeContent);
    }
}

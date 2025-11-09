<?php
/**
 * Fix Missing Product Images Script
 * 
 * This script identifies missing product images and attempts to restore them
 * from backup sources or create placeholders as a last resort.
 */

// Configuration
$mediaBaseDir = '/home/technadminy7/public_html/pub/media';
$backupMediaDir = '/home/technadminy7/technadminy/pub/media';
$productImageDir = $mediaBaseDir . '/catalog/product';

// Get the list of missing images from the error log
$missingImages = [
    "/home/technadminy7/public_html/pub/media/catalog/product/f/e/feutre-pointe-fine-point-88-arty-pochette-rouleau-de-25-pcs_ref_8825-071-20.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/s/t/st_218140_88_68_mood_point88_pen68.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/o/compas-bague-coffret-3pcs-la-couleur-rose-pastel-cuty-techno-ref-6519.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/o/compas-bague-coffret-3pcs-la-couleur-violet-pastel-cuty-techno-ref-6519.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/o/compas-bague-coffret-3pcs-la-couleur-vert-pastel-cuty-techno-ref-6519.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/o/compas-bague-coffret-3pcs-la-couleur-bleu-pastel-cuty-techno-ref-6519.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-20-cm-la-couleur-transparente-techno-ref-9931.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-20-cm-la-couleur-violet-techno-ref-9931.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-20-cm-la-couleur-bleu-techno-ref-9931.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-20-cm-la-couleur-rose-techno-ref-9931.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-20-cm-la-couleur-jaune-techno-ref-9931.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-30-cm-couleur-transparent-techno-ref-9932.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-30-cm-couleur-violet-techno-ref-9932.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-30-cm-couleur-orange-techno-ref-9932.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-30-cm-couleur-bleu-techno-ref-9932.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/e/regle-ecolier-plate-30-cm-couleur-jaune-techno-ref-9932.png",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-20-cm-la-couleur-violet-techno-ref-9933.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-20-cm-la-couleur-orange-techno-ref-9933.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-20-cm-la-couleur-rose-techno-ref-9933.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-20-cm-la-couleur-vert-techno-ref-9933.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-rose-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-transparente-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-vert-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-bleu-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-orange-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/e/n/ensemble-de-tracage-03-pcs-30-cm-la-couleur-violet-techno-ref-9934.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/p/a/pate-a-modeler-12-couleurs-pastels-sous-blister-techno-ref-7219.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/t/r/trousse-silicone-stretch-saumon-techno-ref-7402.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/m/a/marqueur-double-tete-artmark-sacoche-en-toile-168-couleurs-techno-ref-7976.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/m/a/marqueur-double-tete-artmark-sacoche-en-toile-120-couleurs-techno-ref-7975.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/m/a/marqueur-double-tete-artmark-sacoche-en-toile-96-couleurs-techno-ref-7974.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/f/e/feutre-de-coloriage-12-pcs-sous-license-techno-ref-4601-2.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/r/_/r_f_4813_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-musique-piqure-48-pages-a4-90g-clairefontaine-ref-3114c_1.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-tp-80-pages-a4-90g-seyes-et-uni-clairefontaine-ref-3167c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-144-pages-a4-90g-5x5-clairefontaine-ref-63182c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-192-pages-240x320mm-90g-seyes-clairefontaine-ref-63341c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-192-pages-a4-90g-seyes-clairefontaine-ref-63141c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-32-pages-170x220mm-90g-double-ligne-2mm-iv-clairefontaine-ref-3999c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-32-pages-170x220mm-90g-double-ligne-3mm-i-clairefontaine-ref-3793c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-32-pages-170x220mm-90g-seyes-4mm-clairefontaine-ref-3795c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-32-pages-170x220mm-90g-double-ligne-3mm-iv-clairefontaine-ref-3794c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-32-pages-170x220mm-90g-seyes-3mm-clairefontaine-ref-3796c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-48-pages-170x220mm-90g-seyes-clairefontaine-ref-63751c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-spirale-160-pages-a4-avec-90g-5x5-clairefontaine-ref-8252c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-48-pages-240x320mm-90g-5x5-clairefontaine-ref-3312c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/carnet-brochure-rembordee-192-pages-110x170mm-90g-5x5-clairefontaine-ref-69502c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-48-pages-240x320mm-90g-seyes-clairefontaine-ref-3311c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-192-pages-240x320mm-90g-seyes-clairefontaine-ref-63341c_1.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-48-pages-a4-90g-seyes-clairefontaine-ref-3101c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-96-pages-240x320mm-90g-5x5-clairefontaine-ref-63362c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-96-pages-240x320mm-90g-seyes-clairefontaine-ref-63361c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-96-pages-240x320mm-90g-seyes-clairefontaine-ref-63361c_1.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-piqure-96-pages-a4-90g-seyes-clairefontaine-ref-63161c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/cahier-spirale-160-pages-a4-avec-90g-seyes-clairefontaine-ref-8251c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/c/a/carnet-brochure-192-pages-110x170mm-90g-5x5-clairefontaine-ref-69602c.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___7_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___6_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___4_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___3_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___2_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___1_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6449___5_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6450___6_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6450___3_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6450___2_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6450___1_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6451___5_imresizer.jpg",
    "/home/technadminy7/public_html/pub/media/catalog/product/6/4/6451___4_imresizer.jpg"
    // Note: List truncated for brevity
];

echo "🔍 Starting Missing Product Images Fix Process...\n";
echo "📋 Total images to check: " . count($missingImages) . "\n\n";

$fixedCount = 0;
$notFoundCount = 0;

foreach ($missingImages as $imagePath) {
    // Check if the image already exists
    if (file_exists($imagePath)) {
        echo "✅ Image already exists: " . basename($imagePath) . "\n";
        continue;
    }
    
    // Extract relative path
    $relativePath = str_replace($mediaBaseDir, '', $imagePath);
    $backupImagePath = $backupMediaDir . $relativePath;
    
    // Try to find the image in the backup directory
    if (file_exists($backupImagePath)) {
        // Create directory structure if it doesn't exist
        $imageDir = dirname($imagePath);
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        
        // Copy the image from backup
        if (copy($backupImagePath, $imagePath)) {
            echo "✅ Restored from backup: " . basename($imagePath) . "\n";
            $fixedCount++;
        } else {
            echo "❌ Failed to copy: " . basename($imagePath) . "\n";
            $notFoundCount++;
        }
    } else {
        // Try to find by filename only (in case of path differences)
        $imageName = basename($imagePath);
        $found = searchForImageByName($imageName, $backupMediaDir, $productImageDir);
        
        if ($found) {
            echo "✅ Found and restored: " . $imageName . "\n";
            $fixedCount++;
        } else {
            echo "❌ Image not found in backup: " . $imageName . "\n";
            $notFoundCount++;
        }
    }
}

echo "\n📊 Summary:\n";
echo "✅ Fixed: " . $fixedCount . " images\n";
echo "❌ Not found: " . $notFoundCount . " images\n";

/**
 * Search for an image by name in the backup directory and copy it to the correct location
 */
function searchForImageByName($imageName, $backupDir, $targetDir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $imageName) {
            $relativePath = str_replace($backupDir, '', $file->getPathname());
            $targetPath = $targetDir . $relativePath;
            
            // Create directory structure if it doesn't exist
            $targetPathDir = dirname($targetPath);
            if (!is_dir($targetPathDir)) {
                mkdir($targetPathDir, 0755, true);
            }
            
            // Copy the file
            if (copy($file->getPathname(), $targetPath)) {
                return true;
            }
        }
    }
    
    return false;
}
?>
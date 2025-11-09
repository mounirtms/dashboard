<?php
/**
 * Generate Missing Images Report Script
 * 
 * This script generates a detailed CSV report of missing product images
 * based on the errors encountered during image resizing.
 */

// Configuration
$reportDir = '/home/technadminy7/public_html/var/log';
$reportFile = $reportDir . '/missing-images-detailed-' . date('Y-m-d-H-i-s') . '.csv';

// Create report directory if it doesn't exist
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

// List of missing images from the error log
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
    // Note: List truncated for brevity - in a real implementation, this would include all missing images
];

echo "🔍 Generating Missing Images Report...\n";

// Open CSV file for writing
$file = fopen($reportFile, 'w');

// Write CSV header
fputcsv($file, [
    'SKU',
    'Image Path',
    'File Name',
    'Directory Path',
    'File Extension',
    'Status',
    'Notes'
]);

$reportData = [];

foreach ($missingImages as $imagePath) {
    // Extract information from the image path
    $fileName = basename($imagePath);
    $directoryPath = dirname($imagePath);
    $fileExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
    
    // Try to extract SKU from filename (common patterns)
    $sku = extractSkuFromFilename($fileName);
    
    // Check if file exists
    $status = file_exists($imagePath) ? 'Exists' : 'Missing';
    $notes = '';
    
    if ($status === 'Missing') {
        // Check if backup exists
        $backupPath = str_replace('/home/technadminy7/public_html/', '/home/technadminy7/technadminy/', $imagePath);
        if (file_exists($backupPath)) {
            $notes = 'Available in backup';
        } else {
            $notes = 'Not found in backup';
        }
    }
    
    // Add to report data
    $reportData[] = [
        'sku' => $sku,
        'image_path' => $imagePath,
        'file_name' => $fileName,
        'directory_path' => $directoryPath,
        'file_extension' => $fileExtension,
        'status' => $status,
        'notes' => $notes
    ];
    
    // Write to CSV
    fputcsv($file, [
        $sku,
        $imagePath,
        $fileName,
        $directoryPath,
        $fileExtension,
        $status,
        $notes
    ]);
}

fclose($file);

echo "✅ Report generated successfully: " . $reportFile . "\n";
echo "📊 Summary:\n";
echo "📋 Total images checked: " . count($reportData) . "\n";

$missingCount = 0;
$existsCount = 0;
$backupAvailableCount = 0;

foreach ($reportData as $data) {
    if ($data['status'] === 'Missing') {
        $missingCount++;
        if ($data['notes'] === 'Available in backup') {
            $backupAvailableCount++;
        }
    } else {
        $existsCount++;
    }
}

echo "✅ Images that exist: " . $existsCount . "\n";
echo "❌ Images missing: " . $missingCount . "\n";
echo "🔄 Images available in backup: " . $backupAvailableCount . "\n";

/**
 * Extract SKU from filename using common patterns
 */
function extractSkuFromFilename($filename) {
    // Remove extension
    $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
    
    // Common patterns for SKUs in filenames
    $patterns = [
        '/ref[_-]([a-zA-Z0-9-]+)/i', // ref_12345 or ref-12345
        '/_([a-zA-Z0-9-]+)\.jpg$/i', // _12345.jpg
        '/_([a-zA-Z0-9-]+)\.png$/i', // _12345.png
        '/([a-zA-Z0-9-]+)_imresizer/i' // 12345_imresizer
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $filename, $matches)) {
            return $matches[1];
        }
    }
    
    // If no pattern matches, return the filename without extension
    return $nameWithoutExt;
}
?>
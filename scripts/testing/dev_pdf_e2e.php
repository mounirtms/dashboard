<?php
/**
 * DEV-ONLY E2E test: render order PDFs through the exact helper the admin
 * print controller uses (Xtento PdfCustomizer Helper\GeneratePdf) and verify
 * the Réf. column resolves techno_ref (task #24 / #48).
 * Usage: php -d opcache.enable=0 /tmp/dev_pdf_e2e.php
 */
use Magento\Framework\App\Bootstrap;

require '/home/dev/public_html/current/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(\Magento\Framework\App\State::class);

$state->emulateAreaCode('adminhtml', function () use ($om) {
    $helper = $om->get(\Xtento\PdfCustomizer\Helper\GeneratePdf::class);
    foreach ([8118 => '000008251', 1 => '000000001'] as $entityId => $increment) {
        $order = $om->create(\Magento\Sales\Model\Order::class)->load($entityId);
        if (!$order->getId()) {
            echo "SKIP order {$increment} (entity_id {$entityId} not found)\n";
            continue;
        }
        if (!$order->getStoreId()) {
            // Legacy order with NULL store_id would emulate store 0 (admin) as
            // frontend and fatal in theme fallback; use default store view.
            $order->setStoreId(1);
            echo "NOTE order {$increment}: store_id was NULL, forced store 1\n";
        }
        $pdf = $helper->generatePdfForObject('order', $order, 1);
        if (!is_array($pdf) || empty($pdf['output'])) {
            echo "FAIL order {$increment}: generatePdfForObject returned " . var_export($pdf, true) . "\n";
            continue;
        }
        $out = "/tmp/dev_print_test_{$entityId}.pdf";
        file_put_contents($out, $pdf['output']);
        echo "OK order {$increment}: " . strlen($pdf['output']) . " bytes -> {$out}\n";
    }
});

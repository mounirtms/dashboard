<?php
namespace Mab\SourceSelector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Mab\Core\Helper\License as CoreLicense;

class License extends CoreLicense
{
    /**
     * Check license key against Firebase Realtime Database
     * @return bool
     */
    public function isValid($licenseKey)
    {
        // Example: Use GuzzleHttp or cURL to check Firebase DB
        // This is a placeholder for actual Firebase REST API call
        $firebaseConfig = include __DIR__ . '/../etc/firebase.php';
        $url = $firebaseConfig['databaseURL'] . '/licenses/' . $licenseKey . '.json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($result, true);
        return !empty($data) && isset($data['valid']) && $data['valid'] === true;
    }
}

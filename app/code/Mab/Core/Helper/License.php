<?php
namespace Mab\Core\Helper;

class License extends AbstractLicense
{
    public function isValid($licenseKey)
    {
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

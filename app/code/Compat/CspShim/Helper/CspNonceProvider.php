<?php
namespace Compat\CspShim\Helper;

class CspNonceProvider
{
    public function generateNonce(): string
    {
        try {
            if (function_exists('random_bytes')) {
                return bin2hex(random_bytes(16));
            }
        } catch (\Exception $e) {
        }

        return substr(md5(uniqid((string)mt_rand(), true)), 0, 32);
    }
}

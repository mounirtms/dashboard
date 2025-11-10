<?php
namespace Magento\Csp\Helper;

/**
 * Compatibility shim for missing CspNonceProvider class
 * Some 3rd-party modules expect this helper to exist and call generateNonce().
 */
class CspNonceProvider
{
    /**
     * Generate a nonce string for CSP usage.
     *
     * @return string
     */
    public function generateNonce(): string
    {
        try {
            if (function_exists('random_bytes')) {
                return bin2hex(random_bytes(16));
            }
        } catch (\Exception $e) {
            // fallthrough to fallback
        }

        return substr(md5(uniqid((string)mt_rand(), true)), 0, 32);
    }
}

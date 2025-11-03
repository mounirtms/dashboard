<?php
/**
 * MAB Core - Disable Magefan Popup Plugin
 * 
 * @category    Mab
 * @package     Mab_Core
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\Core\Plugin;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;

/**
 * Class DisableMagefanPopup
 * 
 * Disables annoying Magefan extension update popups
 */
class DisableMagefanPopup
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
    }

    /**
     * Suppress Magefan messages
     *
     * @param ManagerInterface $subject
     * @param callable $proceed
     * @param MessageInterface $message
     * @param string|null $group
     * @return ManagerInterface
     */
    public function aroundAddMessage(
        ManagerInterface $subject,
        callable $proceed,
        MessageInterface $message,
        $group = null
    ) {
        $messageText = $message->getText();
        
        // Block Magefan popup messages
        if (is_string($messageText) && (
            strpos($messageText, 'Blog extension version is extremely outdated') !== false ||
            strpos($messageText, 'magefan.com') !== false ||
            strpos($messageText, 'Magefan') !== false && strpos($messageText, 'outdated') !== false
        )) {
            // Don't add the message - effectively suppressing it
            return $subject;
        }
        
        return $proceed($message, $group);
    }

    /**
     * Clear existing Magefan messages
     *
     * @param ManagerInterface $subject
     * @param array $result
     * @return array
     */
    public function afterGetMessages(
        ManagerInterface $subject,
        $result
    ) {
        if (!is_array($result)) {
            return $result;
        }

        $filteredMessages = [];
        foreach ($result as $message) {
            if ($message instanceof MessageInterface) {
                $messageText = $message->getText();
                
                // Filter out Magefan messages
                if (is_string($messageText) && (
                    strpos($messageText, 'Blog extension version is extremely outdated') !== false ||
                    strpos($messageText, 'magefan.com') !== false ||
                    strpos($messageText, 'Magefan') !== false && strpos($messageText, 'outdated') !== false
                )) {
                    continue; // Skip this message
                }
            }
            $filteredMessages[] = $message;
        }

        return $filteredMessages;
    }
}
<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-11-02T20:23:10+00:00
 * File:          Model/Email/TransportBuilder.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Email;

class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    protected $attachments = [];

    /**
     *
     * Add attachments to the email
     *
     * @param $body
     * @param $mimeType
     * @param $disposition
     * @param $encoding
     * @param null $filename
     *
     * @return $this
     */
    public function xtAddAttachment(
        $body,
        $mimeType = \Laminas\Mime\Mime::TYPE_OCTETSTREAM,
        $disposition = \Laminas\Mime\Mime::DISPOSITION_ATTACHMENT,
        $encoding = \Laminas\Mime\Mime::ENCODING_BASE64,
        $filename = null
    ) {
        if (version_compare($this->objectManager->get('\Xtento\XtCore\Helper\Utils')->getMagentoVersion(), '2.2.8', '>=')) {
            $this->attachments[] = ['body' => $body, 'mime_type' => $mimeType, 'disposition' => $disposition, 'encoding' => $encoding, 'filename' => $filename];
        } else {
            $this->message->createAttachment(
                $body,
                $mimeType,
                $disposition,
                $encoding,
                $filename
            );
        }

        return $this;
    }

    /**
     * @return \Magento\Framework\Mail\Template\TransportBuilder
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareMessage()
    {
        $origReturn = parent::prepareMessage();

        // New code to add attachments to message
        if (!empty($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                $attachmentPart = new \Laminas\Mime\Part($attachment['body']);
                $attachmentPart->filename = $attachment['filename'];
                $attachmentPart->type = $attachment['mime_type'];
                $attachmentPart->disposition = $attachment['disposition'];
                $attachmentPart->encoding = $attachment['encoding'];

                if (version_compare($this->objectManager->get('\Xtento\XtCore\Helper\Utils')->getMagentoVersion(), '2.3.3', '>=')) {
                    $message = $this->message;
                    $messageClass = isset(class_implements($message)['Magento\Framework\Interception\InterceptorInterface']) ? get_parent_class($message) : get_class($message);
                    if (property_exists($message, 'message')) { // 2.3.3 pre-patch where they changed it back to zendMessage (how it was in <2.3.3)
                        $messageProperty = new \ReflectionProperty($messageClass, 'message');
                    } else {
                        $messageProperty = new \ReflectionProperty($messageClass, 'zendMessage');
                    }
                    $messageProperty->setAccessible(true);
                    /** @var \Zend\Mail\Message $zendMessage */
                    $zendMessage = $messageProperty->getValue($message);

                    if ($zendMessage === null) { // Try to get "message" again
                        $messageProperty = new \ReflectionProperty($messageClass, 'zendMessage');
                        $messageProperty->setAccessible(true);
                        $zendMessage = $messageProperty->getValue($message);
                    }
                    if ($zendMessage === null) { // Try to get "message" again
                        $messageProperty = new \ReflectionProperty($messageClass, 'message');
                        $messageProperty->setAccessible(true);
                        $zendMessage = $messageProperty->getValue($message);
                    }

                    $currentBody = $zendMessage->getBody();
                    if (!$currentBody) {
                        $currentBody = new \Laminas\Mime\Message();
                    }
                    $currentBody->addPart($attachmentPart);
                    $zendMessage->setBody($currentBody);
                } else {
                    $currentBody = $this->message->getBody();
                    if (!$currentBody) {
                        $currentBody = new \Laminas\Mime\Message();
                        $currentBody->addPart($attachmentPart);
                    } else {
                        $currentBody->addPart($attachmentPart);
                    }
                    $this->message->setBody($currentBody);
                }
            }
            $this->attachments = [];
        }

        return $origReturn;
    }
}

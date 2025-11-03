<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Model/PdfTemplateRepository.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model;

use \Xtento\PdfCustomizer\Api\Data\TemplatesInterface;
use \Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate as TemplateResource;
use \Xtento\PdfCustomizer\Api\TemplatesRepositoryInterface;
use Exception;
use Magento\Framework\Message\ManagerInterface;

class PdfTemplateRepository implements TemplatesRepositoryInterface
{

    /**
     * @var array
     */
    private $instances = [];

    /**
     * @var TemplateResource
     */
    private $resource;

    /**
     * @var TemplatesInterface
     */
    private $templatesInterface;

    /**
     * @var \Xtento\PdfCustomizer\Model\PdfTemplateFactory
     */
    private $pdfTemplateFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * PdfTemplateRepository constructor.
     *
     * @param TemplateResource $resource
     * @param TemplatesInterface $templatesInterface
     * @param PdfTemplateFactory $pdfTemplateFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        TemplateResource $resource,
        TemplatesInterface $templatesInterface,
        PdfTemplateFactory $pdfTemplateFactory,
        ManagerInterface $messageManager
    ) {
        $this->resource = $resource;
        $this->templatesInterface = $templatesInterface;
        $this->pdfTemplateFactory = $pdfTemplateFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * @param TemplatesInterface|PdfTemplate $template
     *
     * @return TemplatesInterface
     */
    public function save(TemplatesInterface $template)
    {
        try {
            $this->resource->save($template);
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, 'There was an error');
        }

        return $template;
    }

    /**
     * @param int $templateId
     * @return mixed
     */
    public function getById($templateId)
    {
        if (!isset($this->instances[$templateId])) {
            $template = $this->pdfTemplateFactory->create();
            $this->resource->load($template, $templateId);

            $this->instances[$templateId] = $template;
        }

        return $this->instances[$templateId];
    }

    /**
     * @param TemplatesInterface|PdfTemplate $template
     *
     * @return bool
     */
    public function delete(TemplatesInterface $template)
    {
        $id = $template->getId();
        try {
            unset($this->instances[$id]);
            $this->resource->delete($template);
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, 'There was an error');
        }

        unset($this->instances[$id]);

        return true;
    }

    /**
     * @param int $templateId
     * @return bool
     */
    public function deleteById($templateId)
    {
        $template = $this->getById($templateId);
        return $this->delete($template);
    }
}

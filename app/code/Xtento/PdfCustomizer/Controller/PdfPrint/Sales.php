<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/PdfPrint/Sales.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\PdfPrint;

use Xtento\PdfCustomizer\Api\TemplatesRepositoryInterface;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order;

/**
 * Class Sales
 * @package Xtento\PdfCustomizer\Controller\PdfPrint
 */
class Sales extends AbstractPdf
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var TemplatesRepositoryInterface
     */
    private $templatesRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * Sales constructor.
     *
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     * @param Session $customerSession
     * @param TemplatesRepositoryInterface $templatesRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param FilterBuilder $filterBuilder
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper,
        Session $customerSession,
        TemplatesRepositoryInterface $templatesRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->fileFactory = $fileFactory;
        $this->generatePdfHelper = $generatePdfHelper;
        $this->customerSession = $customerSession;
        $this->templatesRepository = $templatesRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        parent::__construct($context, $fileFactory, $generatePdfHelper);
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $templateId = $this->getRequest()->getParam('template_id');
        $templateModel = $this->templatesRepository->getById($templateId);
        if (!$templateModel->getId()) {
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $templateTypeName = TemplateType::TYPES[$templateModel->getTemplateType()];
        $repository = 'Magento\Sales\Api\\' . ucfirst($templateTypeName) . 'RepositoryInterface';

        // Check object belongs to customer
        $collection = $this->collection($templateTypeName);
        foreach ($collection as $source) {
            if ($source instanceof Order) {
                $customerId = $source->getCustomerId();
            } else {
                $customerId = $source->getOrder()->getCustomerId();
            }
            if ($this->customerSession->getCustomer()->getId() != $customerId) {
                return $this->_redirect($this->_redirect->getRefererUrl());
            }
        }

        $pdf = $this->returnFile($repository, 'entity_id');
        return $pdf;
    }

    /**
     * @param $templateTypeName
     * @return mixed
     */
    private function collection($templateTypeName)
    {
        $this->criteriaBuilder->addFilters(
            [$this->filterBuilder
                ->setField('entity_id')
                ->setValue($this->getRequest()->getParam('entity_id'))
                ->setConditionType('eq')
                ->create()]
        );
        $searchCriteria = $this->criteriaBuilder->create();
        //@codingStandardsIgnoreLine
        $collection = $this->_objectManager->create(
            'Magento\Sales\Api\\' .
            ucfirst($templateTypeName) .
            'RepositoryInterface'
        )->getList($searchCriteria);

        return $collection;
    }
}

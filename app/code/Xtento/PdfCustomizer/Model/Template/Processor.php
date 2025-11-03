<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-11-03T22:26:59+00:00
 * File:          Model/Template/Processor.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Template;

use Magento\Email\Model\Template;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Xtento\PdfCustomizer\Model\Email\Template\CustomFilterFactory;
use Xtento\XtCore\Helper\Utils;

/**
 * Class Processor
 * @package Xtento\PdfCustomizer\Model\Template
 */
class Processor extends Template
{
    /**
     * Email logo url - take it from "Invoice and Packing Slip Design" instead
     */
    const XML_PATH_DESIGN_EMAIL_LOGO = 'sales/identity/logo';

    /**
     * @var int;
     */
    private $storeId;

    /**
     * @var CustomFilterFactory
     */
    private $customFilterFactory;

    /**
     * Email template filter
     *
     * @var \Magento\Email\Model\Template\Filter
     */
    private $customTemplateFilter;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlModel;

    private $designConfig;

    /**
     * @var Utils
     */
    private $utilsHelper;

    /**
     * Processor constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\View\DesignInterface $design
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Store\Model\App\Emulation $appEmulation
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Template\Config $emailConfig
     * @param \Magento\Email\Model\TemplateFactory $templateFactory
     * @param \Magento\Framework\Filter\FilterManager $filterManager
     * @param \Magento\Framework\UrlInterface $urlModel
     * @param Template\FilterFactory $filterFactory
     * @param CustomFilterFactory $customFilterFactory
     * @param Utils $utilsHelper
     * @param array $data
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\View\DesignInterface $design,
        \Magento\Framework\Registry $registry,
        \Magento\Store\Model\App\Emulation $appEmulation,
        StoreManagerInterface $storeManager,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Template\Config $emailConfig,
        \Magento\Email\Model\TemplateFactory $templateFactory,
        \Magento\Framework\Filter\FilterManager $filterManager,
        \Magento\Framework\UrlInterface $urlModel,
        \Magento\Email\Model\Template\FilterFactory $filterFactory,
        CustomFilterFactory $customFilterFactory,
        Utils $utilsHelper,
        array $data = [],
        $serializer = null
    ) {
        if (version_compare($utilsHelper->getMagentoVersion(), '2.2', '<')) {
            // 2.0/2.1
            parent::__construct($context, $design, $registry, $appEmulation, $storeManager, $assetRepo, $filesystem, $scopeConfig, $emailConfig, $templateFactory, $filterManager, $urlModel, $filterFactory, $data);
        } else {
            parent::__construct($context, $design, $registry, $appEmulation, $storeManager, $assetRepo, $filesystem, $scopeConfig, $emailConfig, $templateFactory, $filterManager, $urlModel, $filterFactory, $data, $serializer);
        }
        $this->utilsHelper = $utilsHelper;
        $this->urlModel = $urlModel;
        $this->customFilterFactory = $customFilterFactory;
    }

    /**
     * @return mixed
     * get the pdf template body
     */
    public function getTemplateHtml()
    {
        return $this->getTemplate()->getTemplateHtml();
    }

    /**
     * @return mixed
     */
    public function getTemplateFileName()
    {
        return $this->getTemplate()->getTemplateFileName();
    }

    /**
     * @param null $storeId
     *
     * @return array|string
     */
    public function processTemplate($storeId = null)
    {
        if ($storeId === null) {
            $template = $this->getTemplate();
            $storeId = $template->getData('store_id');
            $storeId = isset($storeId[0]) ? $storeId[0] : $storeId;
        }
        if (empty($storeId)) {
            $storeId = \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        }
        $this->storeId = $storeId;

        // Support theme fallback for email templates
        $isDesignApplied = $this->applyDesignConfig();

        $processor = $this->getCustomTemplateFilter();
        if (version_compare($this->utilsHelper->getMagentoVersion(), '2.3.5', '<')) {
            // Deprecated as of Magento 2.3.5
            $processor->setUseSessionInUrl(false);
        }
        $processor->setPlainTemplateMode($this->isPlain())
            ->setIsChildTemplate($this->isChildTemplate())
            ->setTemplateProcessor([$this, 'getTemplateContent'])
            ->setStoreId($storeId);

        $variables = $this->getVariables();
        // Populate the variables array with store, store info, logo, etc. variables
        $variables = $this->addEmailVariables($variables, $storeId);
        $processor->setVariables($variables);
        $this->setUseAbsoluteLinks(true);
        $html = $this->html($processor);

        if ($isDesignApplied) {
            $this->cancelDesignConfig();
            $this->designConfig = null;
        }

        return $html;
    }

    /**
     * @param \Magento\Email\Model\Template\Filter $processor
     * @param $content
     *
     * @return mixed
     */
    private function processArea($processor, $content)
    {
        if (method_exists($processor, 'setStrictMode')) {
            $previousStrictMode = $processor->setStrictMode(false);
        }
        try {
            $textProcessor = $processor
                ->setStoreId($this->storeId)
                ->setDesignParams([0])
                ->filter((string)__($content));
        } catch (\Exception $e) {
        } finally {
            if (method_exists($processor, 'setStrictMode')) {
                $processor->setStrictMode($previousStrictMode);
            }
        }

        return $textProcessor;
    }

    /**
     * Important for localization
     *
     * @return \Magento\Framework\DataObject|DataObject
     */
    public function getDesignConfig()
    {
        if ($this->designConfig === null) {
            //@codingStandardsIgnoreLine
            $this->designConfig = new DataObject(
                ['area' => Area::AREA_FRONTEND, 'store' => $this->storeId]
            );
        }

        return $this->designConfig;
    }

    /**
     * @param \Magento\Email\Model\Template\Filter $processor
     *
     * @return array
     */
    private function html($processor)
    {
        $header = '';
        $body = '';
        $footer = '';
        $cover = '';

        // Split template into three parts - header, body, footer
        $fullTemplate = $this->getTemplate()->getData('template_html_full');
        if ($fullTemplate !== null) {
            // Items
            $body = $this->getTemplate()->getTemplateHtml();
            preg_match_all('/##header_start##(.*?)##header_end##/mis', $fullTemplate, $headerMatch);
            if (isset($headerMatch[1]) && isset($headerMatch[1][0])) {
                $header = $headerMatch[1][0];
            }
            preg_match_all('/##footer_start##(.*?)##footer_end##/mis', $fullTemplate, $footerMatch);
            if (isset($footerMatch[1]) && isset($footerMatch[1][0])) {
                $footer = $footerMatch[1][0];
            }
        } else {
            // Other parts
            $fullTemplate = $this->getTemplate()->getTemplateHtml();
            preg_match_all('/##body_start##(.*?)##body_end##/mis', $fullTemplate, $bodyMatch);
            if (isset($bodyMatch[1]) && isset($bodyMatch[1][0])) {
                $body = $bodyMatch[1][0];
            }
            preg_match_all('/##header_start##(.*?)##header_end##/mis', $fullTemplate, $headerMatch);
            if (isset($headerMatch[1]) && isset($headerMatch[1][0])) {
                $header = $headerMatch[1][0];
            }
            preg_match_all('/##footer_start##(.*?)##footer_end##/mis', $fullTemplate, $footerMatch);
            if (isset($footerMatch[1]) && isset($footerMatch[1][0])) {
                $footer = $footerMatch[1][0];
            }
            preg_match_all('/##cover_start##(.*?)##cover_end##/mis', $fullTemplate, $coverMatch);
            if (isset($coverMatch[1]) && isset($coverMatch[1][0])) {
                $cover = $coverMatch[1][0];
            }
            if (empty($body)) {
                $body = $fullTemplate; // Fallback
            }
        }

        // Build HTML
        $html = [
            'body' => (!empty($body)) ? $this->processArea($processor, $body) : '',
            'header' => (!empty($header)) ? $this->processArea($processor, $header) : '',
            'footer' => (!empty($footer)) ? $this->processArea($processor, $footer) : '',
            'cover' => (!empty($cover)) ? $this->processArea($processor, $cover) : '',
            'filename' => $this->processArea($processor, $this->getTemplateFileName()),
        ];

        return $html;
    }

    /**
     * Return logo URL for emails. Take logo from theme if custom logo is undefined
     *
     * @param  Store|int|string $store
     * @return string
     */
    protected function getLogoUrl($store)
    {
        $store = $this->storeManager->getStore($store);
        $fileName = $this->scopeConfig->getValue(
            self::XML_PATH_DESIGN_EMAIL_LOGO,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        if ($fileName) {
            $imagePath = '/sales/store/logo/' . $fileName;
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if ($mediaDirectory->isFile($imagePath)) {
                return $mediaDirectory->getAbsolutePath($imagePath);
            }
        }
        // Get default logo
        $designParams = $this->getDesignParams();
        return $this->assetRepo->createAsset(
            self::DEFAULT_LOGO_FILE_ID,
            array_merge($designParams, ['area' => 'frontend'])
        )->getSourceFile();
    }

    /**
     * @return Template\Filter
     */
    protected function getCustomTemplateFilter()
    {
        if (empty($this->customTemplateFilter)) {
            $this->customTemplateFilter = $this->customFilterFactory->create();
            $this->customTemplateFilter->setUseAbsoluteLinks($this->getUseAbsoluteLinks())
                ->setStoreId($this->getDesignConfig()->getStore())
                ->setUrlModel($this->urlModel);
        }
        return $this->customTemplateFilter;
    }
}

<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData\Image;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use MageWorx\OrdersGrid\Helper\Image as ImageHelper;

/**
 * Class Upload
 */
class Upload extends Action implements HttpPostActionInterface
{
    /**
     * ACL Resource Key
     */
    const ADMIN_RESOURCE = 'MageWorx_OrdersGrid::grid_additional_data';

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var ImageHelper
     */
    protected $helper;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var AdapterFactory
     */
    protected $imageAdapterFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param ImageHelper $helper
     * @param UploaderFactory $uploaderFactory
     * @param AdapterFactory $imageAdapterFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context         $context,
        ResultFactory   $resultFactory,
        ImageHelper     $helper,
        UploaderFactory $uploaderFactory,
        AdapterFactory  $imageAdapterFactory,
        Filesystem      $filesystem
    ) {
        parent::__construct($context);
        $this->resultFactory       = $resultFactory;
        $this->helper              = $helper;
        $this->uploaderFactory     = $uploaderFactory;
        $this->imageAdapterFactory = $imageAdapterFactory;
        $this->filesystem          = $filesystem;
    }

    /**
     * Upload file controller action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            /** @var Uploader $uploader */
            $uploader = $this->uploaderFactory->create(['fileId' => 'general[icon_image_data]']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'svg']);
            /** @var \Magento\Framework\Image\Adapter\AdapterInterface $imageAdapter */
            $imageAdapter = $this->imageAdapterFactory->create();
            $uploader->addValidateCallback('catalog_product_image', $imageAdapter, 'validateUploadFile');
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            /** @var \Magento\Framework\Filesystem\Directory\Read $mediaDirectory */
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            $result = $uploader->save($mediaDirectory->getAbsolutePath($this->helper->getBaseMediaPath()));
            unset($result['tmp_name']);
            unset($result['path']);

            $result['url'] = $this->helper->getMediaUrl($result['file']);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var \Magento\Framework\Controller\Result\Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($result);

        return $response;
    }
}

<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class Image extends AbstractHelper
{
    const MEDIA_TYPE_CONFIG_NODE      = 'images';
    const IMAGE_TYPE_THUMBNAIL        = 'thumbnail_image';
    const IMAGE_TYPE_FORM_PREVIEW     = 'preview_in_form';
    const IMAGE_TYPE_FRONTEND_PREVIEW = 'preview_frontend';
    const BASE_MEDIA_PATH_ICON_IMAGE  = 'mageworx/orders_grid/additional_data/icon_image';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ImageFactory
     */
    private $imageFactory;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Image\Factory $imageFactory
     */
    public function __construct(
        Context               $context,
        StoreManagerInterface $storeManager,
        Filesystem            $filesystem,
        ImageFactory          $imageFactory
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->filesystem   = $filesystem;
        $this->imageFactory = $imageFactory;
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMediaUrl(string $file): string
    {
        return $this->getBaseMediaUrl() . '/' . $this->prepareFile($file);
    }

    /**
     * Filesystem directory path of option value images
     * relatively to media folder
     *
     * @return string
     */
    public function getBaseMediaPath(): string
    {
        return static::BASE_MEDIA_PATH_ICON_IMAGE;
    }

    /**
     * Get image url for specified type, width or height
     *
     * @param string $path
     * @param string|null $type
     * @param int|null $height
     * @param int|null $width
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getImageUrl(string $path, ?string $type = null, ?int $height = 300, ?int $width = 300): string
    {
        if (empty($path)) {
            return '';
        }

        if ($type !== null) {
            $attributes = $this->getAttributesByType($type);
            $height     = !empty($attributes['height']) ? $attributes['height'] : $height;
            $width      = !empty($attributes['width']) ? $attributes['width'] : $width;
        }

        $filePath      = $this->getMediaPath($path);
        $pathArray     = explode('/', $filePath);
        $fileName      = array_pop($pathArray);
        $directoryPath = implode('/', $pathArray);
        $imagePath     = $directoryPath . '/' . $width . 'x' . $height . '/';

        $mediaDirectory   = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $imgAbsolutePath  = $mediaDirectory->getAbsolutePath($imagePath);
        $fileAbsolutePath = $mediaDirectory->getAbsolutePath($filePath);

        $imgFilePath = $imgAbsolutePath . $fileName;
        if (!file_exists($imgFilePath)) {
            $this->createImageFile($fileAbsolutePath, $imgAbsolutePath, $fileName, $width, $height);
        }

        return $this->getUrl($imagePath . $fileName);
    }

    /**
     * Get file size in bytes. Used in uploader element (form)
     *
     * @param string $image
     *
     * @return int
     */
    public function getImageOrigSize(string $image): int
    {
        $fullPathToImage  = $this->getMediaPath($image);
        $mediaDirectory   = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $fileAbsolutePath = $mediaDirectory->getAbsolutePath($fullPathToImage);
        if (file_exists($fileAbsolutePath)) {
            $fileSize = filesize($fileAbsolutePath);
        } else {
            return 0;
        }

        return $fileSize;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    private function getBaseMediaUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) .
            $this->getBaseMediaPath();
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function prepareFile(string $file): string
    {
        return ltrim(str_replace('\\', '/', $file), '/');
    }

    /**
     * Get image attributes by type.
     * Must be public for the case when image width or height should be changed according theme
     *
     * @param string $type
     *
     * @return array
     */
    public function getAttributesByType(string $type): array
    {
        $data = [];
        switch ($type) {
            case static::IMAGE_TYPE_THUMBNAIL:
                $data['width']  = 75;
                $data['height'] = 75;
                break;
            case static::IMAGE_TYPE_FORM_PREVIEW:
                $data['width']  = 116;
                $data['height'] = 148;
                break;
            case static::IMAGE_TYPE_FRONTEND_PREVIEW:
                $data['width']  = 150;
                $data['height'] = 150;
                break;
            default:
                $data['width']  = 300;
                $data['height'] = 300;
                break;
        }

        return $data;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function getMediaPath(string $file): string
    {
        return $this->getBaseMediaPath() . '/' . $this->prepareFile($file);
    }

    /**
     * Create image based on size
     *
     * @param string $origFilePath
     * @param string $imagePath
     * @param string $newFileName
     * @param int $width
     * @param int $height
     *
     */
    private function createImageFile(
        string $origFilePath,
        string $imagePath,
        string $newFileName,
        int    $width,
        int    $height
    ): void {
        try {
            $image = $this->imageFactory->create($origFilePath);
            $image->keepAspectRatio(true);
            $image->keepFrame(true);
            $image->keepTransparency(true);
            $image->constrainOnly(false);
            $image->backgroundColor([255, 255, 255]);
            $image->quality(100);
            $image->resize($width, $height);
            $image->constrainOnly(true);
            $image->keepAspectRatio(true);
            $image->keepFrame(false);
            $image->save($imagePath, $newFileName);
        } catch (\Exception $e) {
            $this->_logger->error($e);
        }
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getUrl(string $file): string
    {
        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->prepareFile($file), '/');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    private function getBaseUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }
}

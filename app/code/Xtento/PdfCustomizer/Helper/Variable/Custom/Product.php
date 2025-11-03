<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-05-16T21:25:38+00:00
 * File:          Helper/Variable/Custom/Product.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Custom;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product as ProductObject;
use Magento\Framework\DataObject;
use Magento\Framework\View\LayoutInterface;
use Xtento\PdfCustomizer\Block\Product\View\Attributes;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;

class Product implements CustomInterface
{
    /**
     * @var ProductObject
     */
    private $source;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var GalleryReadHandler
     */
    private $galleryReadHandler;

    /**
     * @var \Magento\Framework\Filesystem
     */
    private $filesystem;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    private $appEmulation;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * Product constructor.
     *
     * @param ImageHelper $imageHelper
     * @param LayoutInterface $layout
     * @param GalleryReadHandler $galleryReadHandler
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Store\Model\App\Emulation $appEmulation
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     */
    public function __construct(
        ImageHelper $imageHelper,
        LayoutInterface $layout,
        GalleryReadHandler $galleryReadHandler,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
    ) {
        $this->imageHelper = $imageHelper;
        $this->layout = $layout;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->filesystem = $filesystem;
        $this->appEmulation = $appEmulation;
        $this->stockRegistry = $stockRegistry;
    }

    /**
     * @param $source
     * @return $this
     */
    public function entity($source)
    {
        $this->source = $source;

        if (!$source || !$source->getId()) {
            return $this;
        }

        $this->appEmulation->startEnvironmentEmulation($source->getStoreId(), \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $this->imageProcessor();
        $this->stockProcessor();
        $this->priceProcessor();
        $this->appEmulation->stopEnvironmentEmulation();

        return $this;
    }

    /**
     *
     */
    protected function stockProcessor()
    {
        try {
            $stockItem = $this->stockRegistry->getStockItem($this->source->getId());
            if ($stockItem) {
                $this->source->setData('stock_qty', $stockItem->getQty());
            }
        } catch (\Exception $e) {}
    }

    /**
     *
     */
    protected function priceProcessor()
    {
        /*$product = $this->source;
        if ($product->getPrice() == 0) {
            $regularPrice = $product->getPriceInfo()->getPrice('regular_price');
            $this->source->setData('price', $regularPrice->getMinRegularAmount());
        }*/
    }

    /**
     * @return DataObject|object
     */
    public function processAndReadVariables()
    {
        if (!$this->source || !$this->source->getId()) {
            return new DataObject();
        }

        $productData = new DataObject($this->source->getData());
        $product = $this->source;
        foreach ($product->getData() as $key => $value) {
            $attribute = $product->getResource()->getAttribute($key);
            if ($attribute instanceof \Magento\Catalog\Model\ResourceModel\Eav\Attribute) {
                $attribute->setStoreId($product->getStoreId());
            }
            $attrText = '';
            if ($attribute) {
                if ($attribute->getFrontendInput() === 'weee' || $attribute->getFrontendInput() === 'media_gallery') {
                    continue;
                }
                try {
                    $attrText = $product->getAttributeText($key);
                } catch (\Exception $e) {
                    //echo "Problem with attribute $key: ".$e->getMessage();
                    continue;
                }
            }
            if (!empty($attrText)) {
                if (is_array($attrText)) {
                    $productData->setData($key, implode(",", $attrText));
                } else {
                    $productData->setData($key, $attrText);
                }
            } else {
                $productData->setData($key, $value);
            }
        }
        return $productData;
    }

    /**
     * Add the images, parse the image gallery to get all
     */
    protected function imageProcessor()
    {
        $this->mediaFiles();
        $this->image();
        $this->smallImage();
        $this->thumbnail();
        $this->attributeTableSource();
    }

    /**
     * @return $this
     */
    protected function mediaFiles()
    {
        $this->galleryReadHandler->execute($this->source);
        $mediaGallery = $this->source->getMediaGalleryImages();

        if (empty($mediaGallery)) {
            return $this;
        }

        $i = 0;
        foreach ($mediaGallery as $mediaImage) {
            $t = $i++;
            $key = "custom_image_{$t}"; // Counting from 0
            $this->source->setData($key, $mediaImage['path']);
        }

        return $this;
    }

    /**
     * @return ProductObject
     */
    protected function image()
    {
        $html = $this->imageHtml($this->imagePath('image'), 'product_base_image', 275);
        $this->source->setImageHtml($html);
        return $this->source;
    }

    /**
     * @return ProductObject
     */
    protected function smallImage()
    {
        $html = $this->imageHtml($this->imagePath('small_image'), 'product_small_image', 100);
        $this->source->setSmallImageHtml($html);
        return $this->source;
    }

    /**
     * @return ProductObject
     */
    protected function thumbnail()
    {
        $html = $this->imageHtml($this->imagePath('thumbnail'), 'product_thumbnail_image', 50);
        $this->source->setThumbnailImageHtml($html);
        return $this->source;
    }

    /**
     * @param $type
     * @param $size
     * @return string
     */
    protected function imagePath($type)
    {
        return $this->getAbsoluteMediaPath($this->source->getData($type));
    }

    /**
     * @param $relativeMediaPath
     *
     * @return string
     */
    protected function getAbsoluteMediaPath($relativeMediaPath)
    {
        $reader = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        return $reader->getAbsolutePath('catalog/product/' . ltrim((string)$relativeMediaPath, '/'));
    }

    /**
     * @param $imagePath
     * @param $imageType
     * @param int $size
     *
     * @return bool|string
     * @throws \ReflectionException
     */
    public function imageHtml($imagePath, $imageType, $size = 100)
    {
        try {
            if (!is_file($imagePath)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        $imgSrc = $this->resizeImageAndGetPath($imagePath, $imageType, $size);
        $html = '<img src="' . $imgSrc . '" style="max-width: ' . $size . 'px"/>';
        $this->source->setData($imageType . '_url', $imgSrc);
        return $html;
    }

    protected function attributeTableSource()
    {
        $html = $this->layout
            ->createBlock(Attributes::class)
            ->setProduct($this->source)
            ->toHtml();

        $this->source->setAttributesTableHtml($html);
        return $this->source;
    }

    /**
     * @param $imagePath
     * @param $imageType
     * @param $size
     *
     * @return mixed
     * @throws \ReflectionException
     */
    protected function resizeImageAndGetPath($imagePath, $imageType, $size)
    {
        // Resize image
        $resizedImage = $this->imageHelper->init($this->source, $imageType)/*->setImageFile($imagePath)*/->resize($size);
        $resizedImage->getUrl(); // To apply scheduled actions
        // Now some serious magic is required to get the path of the resized image (instead of the URL)
        // Make function _getModel() accessible
        $reflectionMethod = new \ReflectionMethod($resizedImage, '_getModel');
        $reflectionMethod->setAccessible(true);
        $imageModel = $reflectionMethod->invoke($resizedImage);
        // Make property imageAsset of model class accessible
        $reflectionClass = new \ReflectionClass($imageModel);
        if (!$reflectionClass->hasProperty('imageAsset')) {
            // <M2.2
            return $imagePath;
        }
        $property = $reflectionClass->getProperty('imageAsset');
        $property->setAccessible(true);
        // Get path to image
        $imageAsset = $property->getValue($imageModel);
        if ($imageAsset !== null) {
            $imagePath = $imageAsset->getPath();
        } else {
            return $imagePath;
        }
        // Reset classes to original states
        $property->setAccessible(false);
        $reflectionMethod->setAccessible(false);
        return $imagePath;
    }
}

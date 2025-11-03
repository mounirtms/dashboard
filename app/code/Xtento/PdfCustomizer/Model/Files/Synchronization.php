<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-07-17T13:46:21+00:00
 * File:          Model/Files/Synchronization.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Files;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Mpdf\Mpdf;
use ReflectionClass;

/**
 * Class Synchronization
 * @package Xtento\PdfCustomizer\Model
 */
class Synchronization
{

    /**
     * Folders to check and synchronize
     */
    const FILES = [
        'tmp',
    ];

    /**
     * @var File
     */
    private $file;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Synchronization constructor.
     * @param File $file
     * @param DirectoryList $directoryList
     */
    public function __construct(
        File $file,
        DirectoryList $directoryList
    ) {
        $this->file          = $file;
        $this->directoryList = $directoryList;
    }

    /**
     * Check if the directorys exit. Else disable the
     * pdf printing system so we do not get errors.
     * @return bool
     * @throws LocalizedException
     *
     */
    public function isInSync()
    {
        foreach (self::FILES as $directory) {
            $directoryPath = $this->directoryList->getPath(DirectoryList::VAR_DIR) .
                DIRECTORY_SEPARATOR .
                $directory;
            if ($this->file->isExists($directoryPath) && $this->file->isWritable($directoryPath)) {
                continue;
            }
            return false;
        }

        return true;
    }

    /**
     * This will copy all the files from source to destination,
     * the folders will be created if they are not available.
     * This will eliminate the exceptions for not writable.
     * @return $this
     */
    public function synchronizeData()
    {
        foreach (self::FILES as $directory) {
            $directoryPath = $this->directoryList->getPath(DirectoryList::VAR_DIR) .
                DIRECTORY_SEPARATOR .
                $directory;
            if (!$this->file->isExists($directoryPath)) {
                $this->file->createDirectory($directoryPath, 0777);
            }
            $source = $this->sourcePath() . $directory;
            $this->copyFiles($source, $directoryPath);
        }

        return $this;
    }

    /**
     * Copy all files from source to destination
     * @param $source
     * @param $destination
     * @return $this
     */
    private function copyFiles($source, $destination)
    {
        $files = $this->file->readDirectory($source);

        foreach ($files as $file) {
            //@codingStandardsIgnoreLine
            $fileName = basename($file);
            $this->file->copy($file, $destination . DIRECTORY_SEPARATOR. $fileName);
        }

        return $this;
    }

    /**
     * This will get the library directory path.
     * @return mixed
     */
    private function sourcePath()
    {
        //@codingStandardsIgnoreLine
        $mPDFclass = new ReflectionClass(Mpdf::class);
        $nameSpace = $mPDFclass->getFileName();
        $path = str_replace('src/Mpdf.php', '', $nameSpace);

        return $path;
    }
}

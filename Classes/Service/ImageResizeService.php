<?php
/*
 *  Copyright notice
 *
 *  (c) 2016 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 22.06.16 15:45
 */


namespace Iresults\RestImageService\Service;


use RuntimeException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\AbstractFileFolder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Core\Resource\FileReference;


class ImageResizeService
{
    /**
     * Resizes the image (if required) and returns its path. If the image was not resized, the path will be equal to $src
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/ImgResource/
     * @param string                           $src
     * @param FileInterface|AbstractFileFolder $image
     * @param string                           $width              width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.
     * @param string                           $height             height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.
     * @param int                              $minWidth           minimum width of the image
     * @param int                              $minHeight          minimum height of the image
     * @param int                              $maxWidth           maximum width of the image
     * @param int                              $maxHeight          maximum height of the image
     * @param bool                             $treatIdAsReference given src argument is a sys_file_reference record
     * @param string|bool                      $crop               overrule cropping of image (setting to FALSE disables the cropping set in FileReference)
     * @throws RuntimeException
     * @return string path to the image
     */
    public function resizeImage(
        $src = null,
        $image = null,
        $width = null,
        $height = null,
        $minWidth = null,
        $minHeight = null,
        $maxWidth = null,
        $maxHeight = null,
        $treatIdAsReference = false,
        $crop = null
    ) {
        if (is_null($src) && is_null($image) || !is_null($src) && !is_null($image)) {
            throw new RuntimeException('You must either specify a string src or a File object.', 1382284105);
        }

        $imageService = $this->getImageService();
        $image = $imageService->getImage($src, $image, $treatIdAsReference);

        if ($crop === null) {
            $crop = $image instanceof FileReference ? $image->getProperty('crop') : null;
        }

        $processingInstructions = array(
            'width'     => $width,
            'height'    => $height,
            'minWidth'  => $minWidth,
            'minHeight' => $minHeight,
            'maxWidth'  => $maxWidth,
            'maxHeight' => $maxHeight,
            'crop'      => $crop,
        );
        $processedImage = $imageService->applyProcessingInstructions($image, $processingInstructions);

        return $imageService->getImageUri($processedImage);
    }

    /**
     * Return an instance of ImageService using object manager
     *
     * @return ImageService
     */
    protected function getImageService()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        return $objectManager->get(ImageService::class);
    }
}
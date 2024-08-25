<?php

function calculateEffort($quality) {
    return min(max(intval(($quality - 60) / 10) + 6, 0), 9);
}

function handleImageObject($image) {
    $image->clear();
    $image->destroy();
}

function GifToWebp($source, $destination, $quality) {
    try {
        $image = new Imagick($source);
        $image = $image->coalesceImages();
        foreach ($image as $frame) {
            $frame->setImageFormat('webp');
            $frame->setImageCompressionQuality($quality);
        }
        $image = $image->optimizeImageLayers();
        $image->writeImages($destination, true);
        handleImageObject($image);
        return true;
    } catch (Exception $e) {
        logMessage('GIF转换WebP失败: ' . $e->getMessage() . ' (Source: ' . $source . ', Destination: ' . $destination . ')', 'error');
        return false;
    }
}

function ToAvif($source, $destination, $quality) {
    try {
        $effort = calculateEffort($quality);
        $image = new Imagick($source);
        $image->setImageFormat('avif');
        $image->setOption('avif:quality', (string)$quality);
        $image->setOption('avif:effort', (string)$effort);
        $image->setOption('avif:chroma-subsampling', '4:4:4');
        if ($image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        } else {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }
        $maxWidth = 2500;
        $maxHeight = 1600;
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_BOX, 1);
        }
        $image->writeImage($destination);
        handleImageObject($image);
        return true;
    } catch (Exception $e) {
        logMessage('转换AVIF失败: ' . $e->getMessage() . ' (Source: ' . $source . ', Destination: ' . $destination . ')', 'error');
        return false;
    }
}
?>

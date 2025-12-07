<?php

function ToWebp($source, $destination, $quality) {
    try {
        if (!file_exists($source) || !is_readable($source)) {
            return false;
        }
        $image = new Imagick();
        $image->setOption('jpeg:size', '2500x1600');
        $image->readImage($source);
        $image->stripImage();
        if ($image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }
        $format = strtolower($image->getImageFormat());
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '6');
        $image->setOption('webp:thread-level', '1');
        $width  = $image->getImageWidth();
        $height = $image->getImageHeight();
        if ($width > 2500 || $height > 1600) {
            $ratio = min(2500 / $width, 1600 / $height);
            $image->resizeImage(
                intval($width * $ratio),
                intval($height * $ratio),
                Imagick::FILTER_LANCZOS,
                1
            );
        }
        $result = $image->writeImage($destination);
        $image->clear();
        $image->destroy();
        gc_collect_cycles();

        return $result;
    } catch (Exception $e) {
        logMessage('Imagick转换失败: ' . $e->getMessage());
        return false;
    }
}

function GifToWebp($source, $destination, $quality) {
    if (!file_exists($source) || !is_readable($source)) return false;
    $gif = new Imagick($source);
    if ($gif->getNumberImages() > 1) {
        $gif = $gif->coalesceImages();
    }
    $gif->setOption('webp:lossless', 'false');
    $gif->setOption('webp:method', '3');
    $gif->setOption('webp:thread-level', '1');
    $gif->setOption('webp:alpha-quality', '90');
    foreach ($gif as $frame) {
        $frame->setImageFormat('webp');
        $frame->setImageCompressionQuality($quality);
        $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
    }
    $result = $gif->writeImages($destination, true);
    $gif->clear();
    $gif->destroy();
    return $result;
}
?>

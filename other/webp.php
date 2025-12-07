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
    try {
        if (!file_exists($source) || !is_readable($source)) {
            return false;
        }
        $gif = new Imagick();
        $gif->readImage($source);
        $gif = $gif->coalesceImages();
        foreach ($gif as $frame) {
            $frame->stripImage();
            if ($frame->getImageAlphaChannel() === Imagick::ALPHACHANNEL_UNDEFINED) {
                $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            }
            $frame->setImageFormat('webp');
            $frame->setOption('webp:lossless', 'false');
            $frame->setOption('webp:method', '6');
            $frame->setOption('webp:thread-level', '1');
            $frame->setOption('webp:alpha-quality', '90');
            $frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
            $delay = $frame->getImageDelay();
            $frame->setImageDelay($delay);
            $frame->setImageCompressionQuality($quality);
        }
        $gif = $gif->optimizeImageLayers();
        $result = $gif->writeImages($destination, true);
        $gif->clear();
        $gif->destroy();
        return $result;
    } catch (Exception $e) {
        logMessage('GIF转换WebP失败: ' . $e->getMessage());
        return false;
    }
}
?>

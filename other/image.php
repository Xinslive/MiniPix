<?php

function minipix_release_image($image) {
    if ($image instanceof Imagick) {
        $image->clear();
        $image->destroy();
    }
}

function minipix_resize_to_limit(Imagick $image, $maxWidth = 2500, $maxHeight = 1600) {
    $width = $image->getImageWidth();
    $height = $image->getImageHeight();

    if ($width <= 0 || $height <= 0) {
        return;
    }

    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $image->resizeImage(
            max(1, (int)round($width * $ratio)),
            max(1, (int)round($height * $ratio)),
            Imagick::FILTER_LANCZOS,
            1
        );
    }
}

function minipix_image_has_transparent_pixels(Imagick $image) {
    if (!$image->getImageAlphaChannel()) {
        return false;
    }

    if (method_exists($image, 'getImageChannelRange')) {
        $range = $image->getImageChannelRange(Imagick::CHANNEL_ALPHA);
        if (isset($range['minima'], $range['maxima']) && $range['minima'] <= $range['maxima']) {
            return $range['minima'] < $range['maxima'];
        }
    }

    $image->setIteratorIndex(0);
    $iterator = $image->getPixelIterator();
    foreach ($iterator as $pixels) {
        foreach ($pixels as $pixel) {
            if ($pixel->getColorValue(Imagick::COLOR_ALPHA) < 0.999) {
                return true;
            }
        }
    }

    return false;
}

function minipix_file_has_transparent_pixels($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }

    $image = null;
    try {
        $image = new Imagick($filePath);
        return minipix_image_has_transparent_pixels($image);
    } catch (Exception $e) {
        return false;
    } finally {
        minipix_release_image($image);
    }
}

function minipix_set_truecolor_alpha(Imagick $image) {
    if (defined('Imagick::IMGTYPE_TRUECOLORALPHA')) {
        $image->setImageType(Imagick::IMGTYPE_TRUECOLORALPHA);
    } elseif (defined('Imagick::IMGTYPE_TRUECOLORMATTE')) {
        $image->setImageType(Imagick::IMGTYPE_TRUECOLORMATTE);
    }
}

function minipix_convert_to_webp($source, $destination, $quality) {
    if (!file_exists($source) || !is_readable($source)) {
        return false;
    }

    $image = null;
    try {
        $image = new Imagick($source);
        minipix_resize_to_limit($image);
        $image->stripImage();

        if ($image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }

        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '6');

        return (bool)$image->writeImage($destination);
    } catch (Exception $e) {
        return false;
    } finally {
        minipix_release_image($image);
        gc_collect_cycles();
    }
}

function minipix_convert_gif_to_webp($source, $destination, $quality) {
    if (!file_exists($source) || !is_readable($source)) {
        return false;
    }

    $gif = null;
    try {
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

        return (bool)$gif->writeImages($destination, true);
    } catch (Exception $e) {
        return false;
    } finally {
        minipix_release_image($gif);
        gc_collect_cycles();
    }
}

function minipix_avif_effort($quality) {
    return min(max((int)(($quality - 60) / 10) + 6, 0), 9);
}

function minipix_convert_to_avif($source, $destination, $quality) {
    if (!file_exists($source) || !is_readable($source)) {
        return false;
    }

    $image = null;
    try {
        $image = new Imagick($source);
        minipix_resize_to_limit($image);
        $hasTransparentPixels = minipix_image_has_transparent_pixels($image);
        $image->stripImage();

        if ($hasTransparentPixels) {
            $image->setImageBackgroundColor(new ImagickPixel('transparent'));
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            minipix_set_truecolor_alpha($image);
        } else {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }

        $image->setImageFormat('avif');
        $image->setImageCompressionQuality($quality);
        $image->setOption('avif:quality', (string)$quality);
        $image->setOption('avif:effort', (string)minipix_avif_effort($quality));
        $image->setOption('avif:chroma-subsampling', '4:2:0');

        $written = (bool)$image->writeImage($destination);
        if ($written && $hasTransparentPixels && !minipix_file_has_transparent_pixels($destination)) {
            @unlink($destination);
            return false;
        }

        return $written;
    } catch (Exception $e) {
        return false;
    } finally {
        minipix_release_image($image);
        gc_collect_cycles();
    }
}

function minipix_read_image_dimensions($filePath, $mimeType) {
    if ($mimeType === 'image/svg+xml') {
        return [100, 100];
    }

    $info = @getimagesize($filePath);
    if ($info !== false) {
        return [(int)$info[0], (int)$info[1]];
    }

    $image = null;
    try {
        $image = new Imagick($filePath);
        return [(int)$image->getImageWidth(), (int)$image->getImageHeight()];
    } catch (Exception $e) {
        return [0, 0];
    } finally {
        minipix_release_image($image);
    }
}

?>

<?php
function ToWebp($source, $destination, $quality) {
    if (!file_exists($source) || !is_readable($source)) {
        logAsync("Imagick源文件不存在: $source", 'error');
        return false;
    }
    try {
        $image = new Imagick($source);
        $width  = $image->getImageWidth();
        $height = $image->getImageHeight();

        // 性能优化：转换前先缩小到最大尺寸，减少内存峰值
        $maxW = 2500;
        $maxH = 1600;
        if ($width > $maxW || $height > $maxH) {
            $ratio = min($maxW / $width, $maxH / $height);
            $image->resizeImage(
                (int)($width * $ratio),
                (int)($height * $ratio),
                Imagick::FILTER_LANCZOS, 1
            );
        }

        $image->stripImage(); // 去除 EXIF 元数据，减小文件体积
        if ($image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '6');

        $result = $image->writeImage($destination);
        $image->clear();
        $image->destroy();
        gc_collect_cycles();

        return (bool)$result;
    } catch (Exception $e) {
        logAsync('Imagick转换失败: ' . $e->getMessage(), 'error');
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

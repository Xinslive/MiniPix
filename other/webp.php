<?php

require_once __DIR__ . '/image.php';

function ToWebp($source, $destination, $quality) {
    return minipix_convert_to_webp($source, $destination, $quality);
}

function GifToWebp($source, $destination, $quality) {
    return minipix_convert_gif_to_webp($source, $destination, $quality);
}

?>

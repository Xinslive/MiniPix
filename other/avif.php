<?php

require_once __DIR__ . '/image.php';

function calculateEffort($quality) {
    return minipix_avif_effort($quality);
}

function handleImageObject($image) {
    minipix_release_image($image);
}

function GifToWebp($source, $destination, $quality) {
    return minipix_convert_gif_to_webp($source, $destination, $quality);
}

function ToAvif($source, $destination, $quality) {
    return minipix_convert_to_avif($source, $destination, $quality);
}

?>

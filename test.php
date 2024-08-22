<?php
$imagick = new Imagick();
echo "ImageMagick Version: " . $imagick->getVersion()['versionString'] . "\n";
echo "Supported Formats: \n";
print_r($imagick->queryFormats('*'));
?>

<?php
$imagick = new Imagick();
$version = $imagick->getVersion()['versionString'];
$supportedFormats = $imagick->queryFormats('*');
$supportAvif = in_array('AVIF', $supportedFormats);
$supportWebp = in_array('WEBP', $supportedFormats);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImageMagick 格式支持检测</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            text-align: center;
            padding: 50px;
        }
        h1 {
            color: #4CAF50;
        }
        .result {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .supported {
            color: #4CAF50;
            font-weight: bold;
        }
        .unsupported {
            color: #f44336;
            font-weight: bold;
        }
        footer {
            margin-top: 50px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
</head>
<body>

    <h1>ImageMagick 格式支持检测</h1>
    <p>ImageMagick 版本: <?php echo $version; ?></p>
    
    <div class="result">
        <h2>AVIF 格式</h2>
        <p class="<?php echo $supportAvif ? 'supported' : 'unsupported'; ?>">
            <?php echo $supportAvif ? '你的服务器支持图片转AVIF格式' : '可惜，你的服务器不支持图片转AVIF格式'; ?>
        </p>
        <p class="supported">
            <?php echo $supportAvif ? 'MiniPIX提供转AVIF功能，可以考虑使用' : '你可以使用兼容性更好的WEBP格式，前提是支持WEBP'; ?>
        </p>
    </div>

    <div class="result">
        <h2>WEBP 格式</h2>
        <p class="<?php echo $supportWebp ? 'supported' : 'unsupported'; ?>">
            <?php echo $supportWebp ? '你的服务器支持图片转WEBP格式' : '可惜，你的服务器不支持图片转WEBP格式'; ?>
        </p>
        <p class="<?php echo $supportWebp ? 'supported' : 'unsupported'; ?>">
            <?php echo $supportWebp ? '这说明你能正常使用 MiniPIX 图床' : '想办法装一个支持WEBP的 imagick 拓展吧'; ?>
        </p>
    </div>


    <footer>
        &copy; <?php echo date("Y"); ?> MiniPIX
    </footer>

</body>
</html>

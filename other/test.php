<?php
if (!extension_loaded('imagick')) {
    die('未检测到 ImageMagick 扩展，请安装拓展后再试。');
}
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
        .result1 {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .result2 {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            display: none;
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
    
    <div class="result1">
        <h2>AVIF 格式</h2>
        <p class="<?php echo $supportAvif ? 'supported' : 'unsupported'; ?>">
            <?php echo $supportAvif ? '你的拓展支持 AVIF 格式' : '不支持 AVIF ，但是你还有 WEBP 可以选'; ?>
        </p>
    </div>

    <div class="result1">
        <h2>WEBP 格式</h2>
        <p class="<?php echo $supportWebp ? 'supported' : 'unsupported'; ?>">
            <?php echo $supportWebp ? '你的服务器支持 WEBP 格式' : '想办法换一个支持WEBP的拓展版本吧'; ?>
        </p>
    </div>

    <div class="<?php echo $supportWebp ? 'result2' : 'result1'; ?>">
        <h2>收费服务：安装合格拓展</h2>
        <p>如果你搞不定的话，我可以提供技术支持</p>
        <p>支付10元，我可以帮你装上支持WEBP的拓展</p>
        <p>仅装拓展，不提供其他服务，微信：axeocc</p>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> MiniPIX
    </footer>

</body>
</html>

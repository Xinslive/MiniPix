<?php
require_once 'vendor/autoload.php';
use OSS\OssClient;
use OSS\Core\OssException;

$config = parse_ini_file('./admin/config.ini');
$accessKeyId = $config['accessKeyId'];
$accessKeySecret = $config['accessKeySecret'];
$endpoint = $config['endpoint'];
$bucket = $config['bucket'];
$cdndomain = $config['cdndomain'];
$validToken = $config['validToken'];
$dbHost = $config['dbHost'];
$dbUser = $config['dbUser'];
$dbPass = $config['dbPass'];
$dbName = $config['dbName'];
$storage = $config['storage'];

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error);
}

function logMessage($message) {
    $logFile = 'process_log.txt';
    $currentTime = date('Y-m-d H:i:s');
    $logMessage = "[$currentTime] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function respondAndExit($response) {
    ob_end_clean();
    echo json_encode($response);
    ob_flush();
    flush();
    exit;
}

function isValidToken($token) {
    global $validToken;
    return $token === $validToken;
}

function convertToWebp($source, $destination, $quality = 60) {
    $info = getimagesize($source);

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/gif') {
        return false;
    } else {
        return false;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 2500;
    $maxHeight = 1600;
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }
    $result = imagewebp($image, $destination, $quality);
    imagedestroy($image);
    gc_collect_cycles();
    return $result;
}

function convertPngWithImagick($source, $destination, $quality = 60) {
    try {
        $image = new Imagick($source);
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $maxWidth = 2500;
        $maxHeight = 1600;
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
            $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        }
        $result = $image->writeImage($destination);
        $image->clear();
        $image->destroy();
        return $result;
    } catch (Exception $e) {
        logMessage('Imagick转换PNG失败: ' . $e->getMessage());
        return false;
    }
}

function convertGifToWebp($source, $destination, $quality = 60) {
    try {
        $image = new Imagick();
        $image->readImage($source);
        $image = $image->coalesceImages();
        foreach ($image as $frame) {
            $frame->setImageFormat('webp');
            $frame->setImageCompressionQuality($quality);
        }
        $image = $image->optimizeImageLayers();
        $result = $image->writeImages($destination, true);
        $image->clear();
        $image->destroy();
        return $result;
    } catch (Exception $e) {
        logMessage('GIF转换WebP失败: ' . $e->getMessage());
        return false;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        if (!isValidToken($token)) {
            respondAndExit(['result' => 'error', 'code' => 403, 'message' => 'Token错误']);
        }
        $uploadDir = 'upload/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/octet-stream'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileMimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        $datePath = date('Y/m/d');
        $uploadDirWithDatePath = $uploadDir . $datePath . '/';
        if (!is_dir($uploadDirWithDatePath)) {
             if (!mkdir($uploadDirWithDatePath, 0777, true)) {
                    logMessage('无法创建上传目录: ' . $uploadDirWithDatePath);
                    respondAndExit(['result' => 'error', 'code' => 500, 'message' => '无法创建上传目录']);
            }
        }

        if (!in_array($fileMimeType, $allowedTypes)) {
            logMessage('不支持的文件类型: ' . $fileMimeType);
            respondAndExit(['result' => 'error', 'code' => 406, 'message' => '不支持的文件类型']);
        }

        $imageInfo = getimagesize($_FILES['image']['tmp_name']);
        if ($imageInfo === false) {
            logMessage('文件不是有效的图片');
            respondAndExit(['result' => 'error', 'code' => 406, 'message' => '文件不是有效的图片']);
        }

        if ($fileMimeType === 'application/octet-stream') {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $image = imagecreatefromstring($imageData);
            if ($image === false) {
                logMessage('文件不是有效的图片');
                respondAndExit(['result' => 'error', 'code' => 406, 'message' => '文件不是有效的图片']);
            }
            imagedestroy($image);
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                logMessage('无法创建上传目录');
                respondAndExit(['result' => 'error', 'code' => 500, 'message' => '无法创建上传目录']);
            }
        }

        $randomFileName = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $newFilePathWithoutExt = $uploadDirWithDatePath . $randomFileName;
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if(empty($extension)){
            $extension = 'webp';
        }
        $newFilePath = $newFilePathWithoutExt . '.' . $extension;
        $finalFilePath = $newFilePath;

if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
    logMessage("文件上传成功: $newFilePath");
    ini_set('memory_limit', '1024M');
    set_time_limit(300);
    $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 60;

    $convertSuccess = true;
    $timeout = 10;

    pcntl_async_signals(true);

    $signalReceived = false;
    pcntl_signal(SIGALRM, function() use (&$signalReceived) {
        $signalReceived = true;
    });

    pcntl_alarm($timeout);

if ($fileMimeType === 'image/png') {
    $convertSuccess = convertPngWithImagick($newFilePath, $newFilePathWithoutExt . '.webp', $quality);
    if ($convertSuccess) {
        $finalFilePath = $newFilePathWithoutExt . '.webp';
        unlink($newFilePath);
    }
} elseif ($fileMimeType === 'image/gif') {
    $convertSuccess = convertGifToWebp($newFilePath, $newFilePathWithoutExt . '.webp', $quality);
    if ($convertSuccess) {
        $finalFilePath = $newFilePathWithoutExt . '.webp';
        unlink($newFilePath);
    }
} elseif ($fileMimeType !== 'image/webp') {
    $convertSuccess = convertToWebp($newFilePath, $newFilePathWithoutExt . '.webp', $quality);
    if ($convertSuccess) {
        $finalFilePath = $newFilePathWithoutExt . '.webp';
        unlink($newFilePath);
    }
}

    pcntl_alarm(0);

    if ($signalReceived) {
        logMessage('转换超时，上传原始图片');
        $convertSuccess = false;
        $finalFilePath = $newFilePath;
    }

    $compressedInfo = getimagesize($finalFilePath);
    if (!$compressedInfo) {
        logMessage('无法获取压缩后图片信息');
        respondAndExit(['result' => 'error', 'code' => 500, 'message' => '无法获取压缩后图片信息']);
    }
    $compressedWidth = $compressedInfo[0];
    $compressedHeight = $compressedInfo[1];
    $compressedSize = filesize($finalFilePath);


if ($storage === 'oss') {
    try {
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $ossFilePath = $datePath . '/' . basename($finalFilePath);
        $ossClient->uploadFile($bucket, $ossFilePath, $finalFilePath);

        if (file_exists($finalFilePath)) {
            unlink($finalFilePath);
            if ($finalFilePath !== $newFilePath) {
                unlink($newFilePath);
            }
            logMessage("本地文件已删除: {$finalFilePath}");
        } else {
            logMessage("尝试删除不存在的文件: {$finalFilePath}");
        }

        logMessage("文件上传到OSS成功: $ossFilePath");
        $fileUrl = 'https://' . $cdndomain . '/' . $ossFilePath;
        $stmt = $mysqli->prepare("INSERT INTO images (url, path, storage) VALUES (?, ?, ?)");
        $storageType = 'oss';
        $stmt->bind_param("sss", $fileUrl, $ossFilePath, $storageType);
        $stmt->execute();
        $stmt->close();

        respondAndExit([
            'result' => 'success',
            'code' => 200,
            'url' => $fileUrl,
            'srcName' => $randomFileName,
            'width' => $compressedWidth,
            'height' => $compressedHeight,
            'size' => $compressedSize,
            'thumb' => $fileUrl,
            'path' => $ossFilePath
        ]);
    } catch (OssException $e) {
        logMessage('文件上传到OSS失败: ' . $e->getMessage());
        respondAndExit(['result' => 'error', 'code' => 500, 'message' => '文件上传到OSS失败: ' . $e->getMessage()]);
    }
} else if ($storage === 'local') {
    logMessage("文件存储在本地: $finalFilePath");
    $fileUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $uploadDirWithDatePath . basename($finalFilePath);
    $stmt = $mysqli->prepare("INSERT INTO images (url, path, storage) VALUES (?, ?, ?)");
    $storageType = 'local';
    $stmt->bind_param("sss", $fileUrl, $finalFilePath, $storageType);
    $stmt->execute();
    $stmt->close();

    respondAndExit([
        'result' => 'success',
        'code' => 200,
        'url' => $fileUrl,
        'srcName' => $randomFileName,
        'width' => $compressedWidth,
        'height' => $compressedHeight,
        'size' => $compressedSize,
        'thumb' => $fileUrl,
        'path' => $finalFilePath
    ]);
}

} else {
    logMessage('文件上传失败: ' . $file['error']);
            respondAndExit(['result' => 'error', 'code' => 500, 'message' => '文件上传失败']);
}
} else {
    respondAndExit(['result' => 'error', 'code' => 204, 'message' => '无文件上传']);
}
} catch (Exception $e) {
    logMessage('未知错误: ' . $e->getMessage());
    respondAndExit(['result' => 'error', 'code' => 500, 'message' => '发生未知错误: ' . $e->getMessage()]);
}
?>

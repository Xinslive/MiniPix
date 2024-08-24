<?php
//警告，AVIF对服务器性能要求较高，不适合洋垃圾CPU，并且可能存在兼容问题，请谨慎选择。
//include 'vendor/validate.php';
require_once 'vendor/autoload.php';
use OSS\OssClient;
use OSS\Core\OssException;

$config = parse_ini_file('./static/config.ini');
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
    $logFile = '运行日志.txt';
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

function calculateEffort($quality) {
    return min(max(intval(($quality - 60) / 10) + 6, 0), 9);
}

function handleImageObject($image) {
    $image->clear();
    $image->destroy();
}

function GifToWebp($source, $destination, $quality) {
    try {
        $image = new Imagick($source);
        $image = $image->coalesceImages();
        foreach ($image as $frame) {
            $frame->setImageFormat('webp');
            $frame->setImageCompressionQuality($quality);
        }
        $image = $image->optimizeImageLayers();
        $image->writeImages($destination, true);
        handleImageObject($image);
        return true;
    } catch (Exception $e) {
        logMessage('GIF转换WebP失败: ' . $e->getMessage() . ' (Source: ' . $source . ', Destination: ' . $destination . ')', 'error');
        return false;
    }
}

function ToAvif($source, $destination, $quality) {
    try {
        $effort = calculateEffort($quality);
        $image = new Imagick($source);
        $image->setImageFormat('avif');
        $image->setOption('avif:quality', (string)$quality);
        $image->setOption('avif:effort', (string)$effort);
        $image->setOption('avif:chroma-subsampling', '4:4:4');
        if ($image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        } else {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }
        $maxWidth = 2500;
        $maxHeight = 1600;
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_MITCHELL, 1);
        }
        $image->writeImage($destination);
        handleImageObject($image);
        return true;
    } catch (Exception $e) {
        logMessage('转换AVIF失败: ' . $e->getMessage() . ' (Source: ' . $source . ', Destination: ' . $destination . ')', 'error');
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
        $uploadDir = 'uploads/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/octet-stream', 'image/heic', 'image/avif'];
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
        if ($imageInfo === false && $fileMimeType !== 'image/svg+xml' && $fileMimeType !== 'image/avif') {
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

        $randomFileName = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $newFilePathWithoutExt = $uploadDirWithDatePath . $randomFileName;
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilePath = $newFilePathWithoutExt . '.' . $extension;

        if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
            logMessage("接收文件成功: $newFilePath");
            ini_set('memory_limit', '1024M');
            set_time_limit(60);
            $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 70;
            if ($quality === 100) {
            $finalFilePath = $newFilePath;
            } else {
            $convertSuccess = true;
            if ($fileMimeType === 'image/gif') {
                $convertSuccess = GifToWebp($newFilePath, $newFilePathWithoutExt . '.webp', $quality);
                if ($convertSuccess) {
                    $finalFilePath = $newFilePathWithoutExt . '.webp';
                    unlink($newFilePath);
                }
            } elseif ($fileMimeType !== 'image/avif' && $fileMimeType !== 'image/svg+xml') {
                $convertSuccess = ToAvif($newFilePath, $newFilePathWithoutExt . '.avif', $quality);
                if ($convertSuccess) {
                    $finalFilePath = $newFilePathWithoutExt . '.avif';
                    unlink($newFilePath);
                }
            } else {
                $finalFilePath = $newFilePath;
            }
        }
if ($fileMimeType !== 'image/svg+xml') {
    try {
        $image = new Imagick($finalFilePath);
        $compressedWidth = $image->getImageWidth();
        $compressedHeight = $image->getImageHeight();
        $compressedSize = filesize($finalFilePath);

        if ($compressedWidth === false || $compressedHeight === false) {
            logMessage('无法获取压缩后图片信息');
            respondAndExit(['result' => 'error', 'code' => 500, 'message' => '无法获取压缩后图片信息']);
        }
    } catch (Exception $e) {
        logMessage('获取图片信息失败: ' . $e->getMessage());
        respondAndExit(['result' => 'error', 'code' => 500, 'message' => '获取图片信息失败']);
    }
} else {
    $compressedWidth = 100;
    $compressedHeight = 100;
    $compressedSize = filesize($finalFilePath);
}

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
            'path' => $ossFilePath
        ]);
    } catch (OssException $e) {
        logMessage('文件上传到OSS失败: ' . $e->getMessage());
        respondAndExit(['result' => 'error', 'code' => 500, 'message' => '文件上传到OSS失败: ' . $e->getMessage()]);
    }
} else if ($storage === 'local') {
    logMessage("文件存储在本地");
    $fileUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uploadDirWithDatePath . basename($finalFilePath);
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

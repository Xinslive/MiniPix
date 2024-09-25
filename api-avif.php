<?php
//用于将图片转换为avif格式的api文件，对服务器性能要求较高，转换速度不如WEBP，仅作为可选功能提供，默认不使用。
//include 'other/validate.php';
require_once 'other/avif.php';

$config = parse_ini_file('./other/config.ini');

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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        if (!isValidToken($token)) {
            respondAndExit(['result' => 'error', 'code' => 403, 'message' => 'Token错误']);
        }
        $uploadDir = 'uploads/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/octet-stream', 'image/avif'];
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

        $randomFileName = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $newFilePathWithoutExt = $uploadDirWithDatePath . $randomFileName;
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilePath = $newFilePathWithoutExt . '.' . $extension;

        if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
            $startTime = microtime(true);
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
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000);
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


interface StorageInterface {
    public function upload($filePath, $datePath);
    public function getFileUrl($path);
}

class OssStorage implements StorageInterface {
    private $ossClient;
    private $bucket;
    private $cdndomain;

    public function __construct($ossClient, $bucket, $cdndomain) {
        $this->ossClient = $ossClient;
        $this->bucket = $bucket;
        $this->cdndomain = $cdndomain;
    }

    public function upload($filePath, $datePath) {
        $ossFilePath = $datePath . '/' . basename($filePath);
        $this->ossClient->uploadFile($this->bucket, $ossFilePath, $filePath);
        return $ossFilePath;
    }

    public function getFileUrl($path) {
        return 'https://' . $this->cdndomain . '/' . $path;
    }
}

class LocalStorage implements StorageInterface {
    public function upload($filePath, $datePath) {
        return 'uploads/' . $datePath . '/' . basename($filePath);
    }
    public function getFileUrl($path) {
        return 'https://' . $_SERVER['HTTP_HOST'] . '/' . $path;
    }
}

class S3Storage implements StorageInterface {
    private $s3Client;
    private $bucket;
    private $cdndomain;
    private $customUrlPrefix;

    public function __construct($config) {
        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $config['S3Region'],
            'endpoint' => $config['S3Endpoint'],
            'credentials' => [
                'key'    => $config['S3AccessKeyId'],
                'secret' => $config['S3AccessKeySecret'],
            ],
        ]);
        $this->bucket = $config['S3Bucket'];
        $this->cdndomain = $config['S3cdndomain'];
        $this->customUrlPrefix = $config['customUrlPrefix'] ?? '';
    }

    public function upload($filePath, $datePath) {
        $s3FilePath = $datePath . '/' . basename($filePath);
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $s3FilePath,
                'SourceFile' => $filePath,
                'ACL'    => 'public-read',
            ]);
            logMessage("文件上传到S3成功: $s3FilePath");
        } catch (Aws\Exception\AwsException $e) {
            throw new Exception("S3 上传失败: " . $e->getMessage());
        }
        return $s3FilePath;
    }

    public function getFileUrl($path) {
        if (empty($this->customUrlPrefix)) {
            return 'https://' . $this->cdndomain . '/' . $path;
        } else {
            return $this->customUrlPrefix . '/' . $path;
        }
    }
}

class FtpStorage implements StorageInterface {
    private $ftpConn;
    private $ftpConfig;

    public function __construct($config) {
        $this->ftpConfig = $config;
        $this->ftpConn = ftp_connect($config['host'], $config['port']);
        if (!$this->ftpConn) {
            throw new Exception("FTP 连接失败");
        }
        $login = ftp_login($this->ftpConn, $config['username'], $config['password']);
        if (!$login) {
            throw new Exception("FTP 登录失败");
        }
        ftp_pasv($this->ftpConn, true); // 启用被动模式
    }

    public function upload($filePath, $datePath) {
        if (!file_exists($filePath)) {
            throw new Exception("本地文件不存在: $filePath");
        }

        $ftpFilePath = $datePath . '/' . basename($filePath);
        $ftpDir = dirname($ftpFilePath);
        $this->createDirectoryIfNotExists($ftpDir);

        if (!ftp_put($this->ftpConn, $ftpFilePath, $filePath, FTP_BINARY)) {
            $error = error_get_last()['message'];
            throw new Exception("FTP 上传失败: " . $error);
        }
        return $ftpFilePath;
    }

    private function createDirectoryIfNotExists($ftpDir) {
        $dirs = explode('/', $ftpDir);
        $path = '';
        foreach ($dirs as $dir) {
            if ($dir === '') continue;
            $path .= '/' . $dir;

            if (!$this->directoryExists($path)) {
                if (!@ftp_mkdir($this->ftpConn, $path)) {
                    $error = error_get_last()['message'];
                    throw new Exception("无法创建目录: $path. 错误信息: " . $error);
                }
            }
        }
    }

    private function directoryExists($path) {
        $currentDir = ftp_pwd($this->ftpConn);
        if (@ftp_chdir($this->ftpConn, $path)) {
            ftp_chdir($this->ftpConn, $currentDir);
            return true;
        }
        ftp_chdir($this->ftpConn, $currentDir);
        return false;
    }

    public function getFileUrl($path) {
        return 'ftp://' . $this->ftpConfig['host'] . '/' . $path;
    }

    public function __destruct() {
        if ($this->ftpConn) {
            ftp_close($this->ftpConn);
        }
    }
}



function getStorage($storage) {
    global $config;

    switch ($storage) {
        case 'oss':
            if (!class_exists('OSS\OssClient')) {
                require_once 'vendor/autoload.php';
            }
            $ossClientClass = 'OSS\OssClient';
            $ossExceptionClass = 'OSS\Core\OssException';
            if (!class_exists($ossClientClass) || !class_exists($ossExceptionClass)) {
                throw new Exception("OSS 类未加载");
            }
            $ossClient = new $ossClientClass($config['ossAccessKeyId'], $config['ossAccessKeySecret'], $config['ossEndpoint']);
            return new OssStorage($ossClient, $config['ossBucket'], $config['ossdomain']);

        case 'local':
            return new LocalStorage();

        case 's3':
            if (!class_exists('Aws\S3\S3Client')) {
                require_once 'vendor/autoload.php';
            }
            return new S3Storage($config);

        case 'ftp':
            return new FtpStorage([
                'host' => $config['ftpHost'],
                'port' => $config['ftpPort'],
                'username' => $config['ftpUsername'],
                'password' => $config['ftpPassword'],
                'domain' => $config['ftpdomain']
            ]);

        default:
            throw new Exception("不支持的存储类型: " . $storage);
    }
}

try {
    $storageInstance = getStorage($storage);
    $uploadedFilePath = $storageInstance->upload($finalFilePath, $datePath);
    if ($storage !== 'local') {
        if (file_exists($finalFilePath)) {
            unlink($finalFilePath);
            if ($finalFilePath !== $newFilePath) {
                unlink($newFilePath);
            }
        } else {
            logMessage("尝试删除不存在的文件: {$finalFilePath}");
        }
    }

    $fileUrl = $storageInstance->getFileUrl($uploadedFilePath);
    $stmt = $mysqli->prepare("INSERT INTO images (url, path, srcName , storage) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $fileUrl, $uploadedFilePath, $randomFileName, $storage);
    $stmt->execute();
    $stmt->close();

    respondAndExit([
        'result' => 'success',
        'code' => 200,
        'url' => $fileUrl,
        'srcName' => $randomFileName,
        'width' => $compressedWidth,
        'height' => $compressedHeight,
        'ptime' => $processingTime,
        'size' => $compressedSize
    ]);
} catch (Exception $e) {
    logMessage('文件上传失败: ' . $e->getMessage());
    respondAndExit(['result' => 'error', 'code' => 500, 'message' => '文件上传失败: ' . $e->getMessage()]);
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

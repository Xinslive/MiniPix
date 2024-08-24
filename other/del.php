<?php
require_once '../vendor/autoload.php';

class StorageHandler {
    protected $storage;
    protected $config;

    public function __construct($storage, $config) {
        $this->storage = $storage;
        $this->config = $config;
    }

    public function delete($path) {
        throw new Exception("未实现的删除方法");
    }
}

class OssStorage extends StorageHandler {
    private $ossClient;
    private $bucket;

    public function __construct($config) {
        parent::__construct('oss', $config);
        $this->ossClient = new \OSS\OssClient($config['accessKeyId'], $config['accessKeySecret'], $config['endpoint']);
        $this->bucket = $config['bucket'];
    }

    public function delete($path) {
        $ossKey = parse_url($path, PHP_URL_PATH);
        try {
            $this->ossClient->deleteObject($this->bucket, $ossKey);
        } catch (\OSS\Core\OssException $e) {
            throw new Exception("从 OSS 删除失败: " . $e->getMessage());
        }
    }
}

class LocalStorage extends StorageHandler {
    public function __construct($config) {
        parent::__construct('local', $config);
    }

    public function delete($path) {
        $localFilePath = '../' . $path;
        if (file_exists($localFilePath)) {
            unlink($localFilePath);
        } else {
            throw new Exception("本地文件不存在");
        }
    }
}

class FtpStorage extends StorageHandler {
    private $ftpConn;

    public function __construct($config) {
        parent::__construct('ftp', $config);
        $ftpHost = $config['ftpHost'];
        $ftpPort = isset($config['ftpPort']) ? $config['ftpPort'] : 21;
        $this->ftpConn = ftp_connect($ftpHost, $ftpPort);
        if (!$this->ftpConn) {
            throw new Exception("无法连接到 FTP 服务器: $ftpHost:$ftpPort");
        }
        $login = ftp_login($this->ftpConn, $config['ftpUsername'], $config['ftpPassword']);
        if (!$login) {
            ftp_close($this->ftpConn);
            throw new Exception("FTP 登录失败");
        }
        ftp_pasv($this->ftpConn, true);
    }

    public function delete($path) {
        if (!ftp_delete($this->ftpConn, $path)) {
            throw new Exception("从 FTP 删除失败");
        }
    }

    public function __destruct() {
        if ($this->ftpConn) {
            ftp_close($this->ftpConn);
        }
    }
}


class S3Storage extends StorageHandler {
    private $s3Client;
    private $bucket;

    public function __construct($config) {
        parent::__construct('s3', $config);
        $this->s3Client = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $config['S3Region'],
            'credentials' => [
                'key' => $config['S3AccessKeyId'],
                'secret' => $config['S3AccessKeySecret'],
            ],
            'endpoint' => $config['S3Endpoint'],
            'use_path_style_endpoint' => true,
        ]);

        $this->bucket = $config['S3Bucket'];
    }

    public function delete($path) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => ltrim(parse_url($path, PHP_URL_PATH), '/'),
            ]);
        } catch (\Aws\Exception\AwsException $e) {
            throw new \Exception("从 S3 删除失败: " . $e->getMessage());
        }
    }
}


$config = parse_ini_file('config.ini');

$mysqli = new mysqli($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $srcName = $_POST['srcName'] ?? '';

    if (empty($srcName)) {
        echo json_encode(['result' => 'error', 'message' => '文件名不能为空']);
        exit;
    }

    try {
        $stmt = $mysqli->prepare("SELECT path, storage FROM images WHERE srcName = ?");
        if (!$stmt) {
            throw new Exception("数据库错误: " . $mysqli->error);
        }
        $stmt->bind_param("s", $srcName);
        $stmt->execute();
        $stmt->bind_result($path, $storage);
        $stmt->fetch();
        $stmt->close();

        if (empty($path) || empty($storage)) {
            throw new Exception("未找到相应的图片记录");
        }

        switch ($storage) {
            case 'oss':
                $storageHandler = new OssStorage($config);
                break;
            case 'local':
                $storageHandler = new LocalStorage($config);
                break;
            case 'ftp':
                $storageHandler = new FtpStorage($config);
                break;
            case 's3':
                $storageHandler = new S3Storage($config);
                break;
            default:
                throw new Exception("无效的 storage 配置");
        }

        $storageHandler->delete($path);

        $stmt = $mysqli->prepare("DELETE FROM images WHERE srcName = ?");
        if (!$stmt) {
            throw new Exception("数据库错误: " . $mysqli->error);
        }
        $stmt->bind_param("s", $srcName);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['result' => 'success', 'message' => '图片删除成功']);
        } else {
            echo json_encode(['result' => 'error', 'message' => '无法从数据库中删除']);
            error_log("无法从数据库中删除': $srcName");
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['result' => 'error', 'message' => $e->getMessage()]);
        error_log("删除错误: " . $e->getMessage());
    }
} else {
    echo json_encode(['result' => 'error', 'message' => '仅允许 POST 请求。']);
}

$mysqli->close();
?>

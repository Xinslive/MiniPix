<?php

interface MinipixStorageInterface {
    public function upload($filePath, $datePath);
    public function delete($path);
    public function getUrl($path);
}

class MinipixLocalStorage implements MinipixStorageInterface {
    public function upload($filePath, $datePath) {
        return 'uploads/' . $datePath . '/' . basename($filePath);
    }

    public function delete($path) {
        $relativePath = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $absolutePath = minipix_path($relativePath);
        $root = realpath(minipix_path('uploads')) ?: minipix_path('uploads');
        $targetDir = realpath(dirname($absolutePath));

        if ($targetDir === false || strpos($targetDir, $root) !== 0) {
            throw new RuntimeException('本地文件路径无效');
        }

        if (!is_file($absolutePath)) {
            return;
        }

        if (!unlink($absolutePath)) {
            throw new RuntimeException('本地文件删除失败');
        }
    }

    public function getUrl($path) {
        return minipix_join_url(minipix_public_base_url(), $path);
    }
}

class MinipixOssStorage implements MinipixStorageInterface {
    private $ossClient;
    private $bucket;
    private $domain;

    public function __construct(array $config) {
        if (!class_exists('OSS\\OssClient')) {
            require_once minipix_path('vendor/autoload.php');
        }
        $this->ossClient = new OSS\OssClient($config['ossAccessKeyId'], $config['ossAccessKeySecret'], $config['ossEndpoint']);
        $this->bucket = $config['ossBucket'];
        $this->domain = $config['ossdomain'];
    }

    public function upload($filePath, $datePath) {
        $key = $datePath . '/' . basename($filePath);
        $this->ossClient->uploadFile($this->bucket, $key, $filePath);
        return $key;
    }

    public function delete($path) {
        $key = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $this->ossClient->deleteObject($this->bucket, $key);
    }

    public function getUrl($path) {
        return minipix_join_url('https://' . $this->domain, $path);
    }
}

class MinipixS3Storage implements MinipixStorageInterface {
    private $s3Client;
    private $bucket;
    private $customUrlPrefix;

    public function __construct(array $config) {
        if (!class_exists('Aws\\S3\\S3Client')) {
            require_once minipix_path('vendor/autoload.php');
        }

        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $config['S3Region'],
            'endpoint' => $config['S3Endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $config['S3AccessKeyId'],
                'secret' => $config['S3AccessKeySecret'],
            ],
        ]);
        $this->bucket = $config['S3Bucket'];
        $this->customUrlPrefix = trim((string)($config['customUrlPrefix'] ?? ''));
    }

    public function upload($filePath, $datePath) {
        $key = $datePath . '/' . basename($filePath);
        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $filePath,
            'ACL' => 'public-read',
        ]);

        return $key;
    }

    public function delete($path) {
        $key = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $this->s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
    }

    public function getUrl($path) {
        if ($this->customUrlPrefix !== '') {
            return minipix_join_url(str_replace('http://', 'https://', $this->customUrlPrefix), $path);
        }

        return minipix_join_url(rtrim($this->s3Client->getEndpoint(), '/') . '/' . $this->bucket, $path);
    }
}

class MinipixFtpStorage implements MinipixStorageInterface {
    private $ftpConn;
    private $domain;

    public function __construct(array $config) {
        $this->domain = $config['ftpdomain'];
        $this->ftpConn = ftp_connect($config['ftpHost'], (int)($config['ftpPort'] ?: 21));
        if (!$this->ftpConn) {
            throw new RuntimeException('FTP 连接失败');
        }
        if (!ftp_login($this->ftpConn, $config['ftpUsername'], $config['ftpPassword'])) {
            throw new RuntimeException('FTP 登录失败');
        }
        ftp_pasv($this->ftpConn, true);
    }

    public function upload($filePath, $datePath) {
        $path = $datePath . '/' . basename($filePath);
        $this->ensureDirectory(dirname($path));

        if (!ftp_put($this->ftpConn, $path, $filePath, FTP_BINARY)) {
            throw new RuntimeException('FTP 上传失败');
        }

        return $path;
    }

    public function delete($path) {
        $ftpPath = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        if (!ftp_delete($this->ftpConn, $ftpPath)) {
            throw new RuntimeException('FTP 删除失败');
        }
    }

    public function getUrl($path) {
        return minipix_join_url('https://' . $this->domain, $path);
    }

    private function ensureDirectory($ftpDir) {
        $parts = explode('/', trim($ftpDir, '/'));
        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current .= '/' . $part;
            if (!@ftp_chdir($this->ftpConn, $current)) {
                if (!@ftp_mkdir($this->ftpConn, $current)) {
                    throw new RuntimeException('无法创建 FTP 目录: ' . $current);
                }
            }
        }
        @ftp_chdir($this->ftpConn, '/');
    }

    public function __destruct() {
        if ($this->ftpConn) {
            ftp_close($this->ftpConn);
        }
    }
}

function minipix_storage(array $config, $storage = null) {
    $storage = $storage ?: ($config['storage'] ?? 'local');

    switch ($storage) {
        case 'local':
            return new MinipixLocalStorage();
        case 'oss':
            return new MinipixOssStorage($config);
        case 's3':
            return new MinipixS3Storage($config);
        case 'ftp':
            return new MinipixFtpStorage($config);
        default:
            throw new RuntimeException('不支持的存储类型: ' . $storage);
    }
}

?>

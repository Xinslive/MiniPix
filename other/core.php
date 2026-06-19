<?php

define('MINIPIX_ROOT', dirname(__DIR__));
define('MINIPIX_OTHER', __DIR__);

require_once MINIPIX_OTHER . '/image.php';
require_once MINIPIX_OTHER . '/storage.php';

function minipix_path($path) {
    return MINIPIX_ROOT . '/' . ltrim($path, '/');
}

function minipix_other_path($path) {
    return MINIPIX_OTHER . '/' . ltrim($path, '/');
}

function minipix_config_path() {
    return minipix_other_path('config.ini');
}

function minipix_ini_value($value) {
    $value = str_replace(["\r", "\n"], '', (string)$value);
    return '"' . addcslashes($value, "\\\"") . '"';
}

function minipix_write_frontend_config($token) {
    $scriptPath = minipix_path('static/js/script.js');
    if (!is_file($scriptPath) || !is_writable($scriptPath)) {
        return false;
    }

    $script = file_get_contents($scriptPath);
    if ($script === false) {
        return false;
    }

    $token = addcslashes((string)$token, "\\'");
    $updated = preg_replace(
        "/let uploadToken = '[^']*';/",
        "let uploadToken = '$token';",
        $script,
        1
    );

    if ($updated === null || $updated === $script) {
        return false;
    }

    return file_put_contents($scriptPath, $updated) !== false;
}

function minipix_load_config() {
    $path = minipix_config_path();
    if (!is_file($path)) {
        throw new RuntimeException('配置文件不存在');
    }

    $config = parse_ini_file($path);
    if ($config === false) {
        throw new RuntimeException('配置文件读取失败');
    }

    if (empty($config['S3AccessKeySecret']) && !empty($config['s3AccessKeySecret'])) {
        $config['S3AccessKeySecret'] = $config['s3AccessKeySecret'];
    }

    return $config;
}

function minipix_config_value($config, $key, $default = '') {
    return isset($config[$key]) ? $config[$key] : $default;
}

function minipix_json_response($response, $httpStatus = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function minipix_db(array $config) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = new mysqli(
        $config['dbHost'] ?? '',
        $config['dbUser'] ?? '',
        $config['dbPass'] ?? '',
        $config['dbName'] ?? ''
    );

    if ($mysqli->connect_error) {
        throw new RuntimeException('数据库连接失败: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function minipix_is_valid_token($token, array $config) {
    $validToken = (string)($config['validToken'] ?? '');
    return $validToken !== '' && hash_equals($validToken, (string)$token);
}

function minipix_normalize_quality($quality) {
    $value = filter_var($quality, FILTER_VALIDATE_INT);
    if ($value === false) {
        return 70;
    }

    return max(60, min(100, $value));
}

function minipix_configured_image_format(array $config) {
    $format = strtolower(trim((string)($config['imageFormat'] ?? 'webp')));
    return in_array($format, ['webp', 'avif'], true) ? $format : 'webp';
}

function minipix_scheme() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
    }

    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}

function minipix_public_base_url() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return minipix_scheme() . '://' . $host;
}

function minipix_join_url($prefix, $path) {
    return rtrim($prefix, '/') . '/' . ltrim($path, '/');
}

function minipix_random_name(mysqli $mysqli) {
    for ($i = 0; $i < 8; $i++) {
        $name = bin2hex(random_bytes(8));
        $stmt = $mysqli->prepare('SELECT id FROM images WHERE srcName = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('数据库错误: ' . $mysqli->error);
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            return $name;
        }
    }

    throw new RuntimeException('无法生成唯一文件名');
}

function minipix_delete_token($srcName, array $config) {
    $secret = (string)($config['validToken'] ?? '');
    return hash_hmac('sha256', $srcName, $secret);
}

function minipix_supported_uploads() {
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'image/avif' => ['avif'],
    ];
}

function minipix_detect_upload(array $file) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('上传参数无效');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '文件超过服务器上传限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单上传限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '无文件上传',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入上传文件',
            UPLOAD_ERR_EXTENSION => '上传被服务器扩展阻止',
        ];
        throw new RuntimeException($messages[$file['error']] ?? '文件上传失败');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('上传文件无效');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        throw new RuntimeException('Fileinfo 扩展不可用');
    }
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $supported = minipix_supported_uploads();

    if ($mimeType === 'application/octet-stream') {
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($image !== false) {
            imagedestroy($image);
            $mimeType = 'image/png';
        }
    }

    if (!isset($supported[$mimeType])) {
        throw new RuntimeException('不支持的文件类型');
    }

    if ($extension === 'jpg') {
        $extension = 'jpeg';
    }

    if ($extension === '' || !in_array($extension, $supported[$mimeType], true)) {
        $extension = $supported[$mimeType][0];
    }

    if ($mimeType !== 'image/svg+xml' && $mimeType !== 'image/avif' && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('文件不是有效的图片');
    }

    return [$mimeType, $extension];
}

function minipix_prepare_upload_dir($datePath) {
    $relative = 'uploads/' . $datePath;
    $absolute = minipix_path($relative);
    if (!is_dir($absolute) && !mkdir($absolute, 0755, true)) {
        throw new RuntimeException('无法创建上传目录');
    }

    return [$relative, $absolute];
}

function minipix_process_upload($targetFormat = null) {
    ob_start();

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
            minipix_json_response(['result' => 'error', 'code' => 204, 'message' => '无文件上传']);
        }

        $config = minipix_load_config();
        $targetFormat = $targetFormat ?: minipix_configured_image_format($config);
        if (!minipix_is_valid_token($_POST['token'] ?? '', $config)) {
            minipix_json_response(['result' => 'error', 'code' => 403, 'message' => 'Token错误'], 403);
        }

        $mysqli = minipix_db($config);
        [$mimeType, $extension] = minipix_detect_upload($_FILES['image']);
        $quality = minipix_normalize_quality($_POST['quality'] ?? 70);
        $datePath = date('Y/m/d');
        [, $uploadDir] = minipix_prepare_upload_dir($datePath);

        $srcName = minipix_random_name($mysqli);
        $originalPath = $uploadDir . '/' . $srcName . '.' . $extension;
        $finalPath = $originalPath;
        $createdFiles = [];

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $originalPath)) {
            throw new RuntimeException('文件保存失败');
        }
        $createdFiles[] = $originalPath;

        $startTime = microtime(true);
        $finalMimeType = $mimeType;

        if ($quality < 100 && $mimeType !== 'image/svg+xml') {
            $convertedPath = $uploadDir . '/' . $srcName . '.' . $targetFormat;
            $convertSuccess = false;

            if ($targetFormat === 'webp') {
                if ($mimeType === 'image/gif') {
                    $convertSuccess = minipix_convert_gif_to_webp($originalPath, $convertedPath, $quality);
                } elseif ($mimeType !== 'image/webp' && $mimeType !== 'image/avif') {
                    $convertSuccess = minipix_convert_to_webp($originalPath, $convertedPath, $quality);
                }
            } elseif ($targetFormat === 'avif') {
                if ($mimeType === 'image/gif') {
                    $convertedPath = $uploadDir . '/' . $srcName . '.webp';
                    $convertSuccess = minipix_convert_gif_to_webp($originalPath, $convertedPath, $quality);
                    $targetFormat = 'webp';
                } elseif ($mimeType !== 'image/avif') {
                    if (minipix_file_has_transparent_pixels($originalPath)) {
                        $convertedPath = $uploadDir . '/' . $srcName . '.webp';
                        $convertSuccess = minipix_convert_to_webp($originalPath, $convertedPath, $quality);
                        if ($convertSuccess) {
                            $targetFormat = 'webp';
                        }
                    } else {
                        $convertSuccess = minipix_convert_to_avif($originalPath, $convertedPath, $quality);
                        if (!$convertSuccess) {
                            $convertedPath = $uploadDir . '/' . $srcName . '.webp';
                            $convertSuccess = minipix_convert_to_webp($originalPath, $convertedPath, $quality);
                            if ($convertSuccess) {
                                $targetFormat = 'webp';
                            }
                        }
                    }
                }
            }

            if ($convertSuccess && is_file($convertedPath)) {
                $finalPath = $convertedPath;
                $createdFiles[] = $convertedPath;
                $finalMimeType = $targetFormat === 'avif' ? 'image/avif' : 'image/webp';
                if ($originalPath !== $finalPath && is_file($originalPath)) {
                    unlink($originalPath);
                }
            }
        }

        $processingTime = (int)round((microtime(true) - $startTime) * 1000);
        [$width, $height] = minipix_read_image_dimensions($finalPath, $finalMimeType);
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('无法获取压缩后图片信息');
        }
        $size = filesize($finalPath);

        $storageName = $config['storage'] ?? 'local';
        $storage = minipix_storage($config, $storageName);
        $uploadedPath = $storage->upload($finalPath, $datePath);
        $url = $storage->getUrl($uploadedPath);

        $stmt = $mysqli->prepare('INSERT INTO images (url, path, srcName, storage) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('数据库错误: ' . $mysqli->error);
        }
        $stmt->bind_param('ssss', $url, $uploadedPath, $srcName, $storageName);
        if (!$stmt->execute()) {
            if ($storageName === 'local' && is_file($finalPath)) {
                @unlink($finalPath);
            } else {
                try {
                    $storage->delete($uploadedPath);
                } catch (Exception $deleteError) {
                }
            }
            throw new RuntimeException('数据库写入失败: ' . $stmt->error);
        }
        $stmt->close();
        $mysqli->close();

        if ($storageName !== 'local') {
            foreach ($createdFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        minipix_json_response([
            'result' => 'success',
            'code' => 200,
            'url' => $url,
            'srcName' => $srcName,
            'deleteToken' => minipix_delete_token($srcName, $config),
            'width' => $width,
            'height' => $height,
            'ptime' => $processingTime,
            'size' => $size,
        ]);
    } catch (Exception $e) {
        if (!empty($createdFiles)) {
            foreach ($createdFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        minipix_json_response(['result' => 'error', 'code' => 500, 'message' => '文件上传失败: ' . $e->getMessage()], 500);
    }
}

?>

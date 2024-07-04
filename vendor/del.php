<?php
require_once 'autoload.php';
use OSS\OssClient;
use OSS\Core\OssException;

$config = parse_ini_file('../static/config.ini');
$accessKeyId = $config['accessKeyId'];
$accessKeySecret = $config['accessKeySecret'];
$endpoint = $config['endpoint'];
$bucket = $config['bucket'];
$dbHost = $config['dbHost'];
$dbUser = $config['dbUser'];
$dbPass = $config['dbPass'];
$dbName = $config['dbName'];

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = $_POST['path'] ?? '';

    if (empty($path)) {
        echo json_encode(['result' => 'error', 'message' => '路径不能为空']);
        exit;
    }

    try {
        $stmt = $mysqli->prepare("SELECT storage FROM images WHERE path = ?");
        if (!$stmt) {
            throw new Exception("数据库错误: " . $mysqli->error);
        }
        $stmt->bind_param("s", $path);
        $stmt->execute();
        $stmt->bind_result($storage);
        $stmt->fetch();
        $stmt->close();

        if (empty($storage)) {
            throw new Exception("未找到相应的图片记录");
        }

        if ($storage === 'oss') {
            $ossKey = parse_url($path, PHP_URL_PATH);
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->deleteObject($bucket, $ossKey);
        } elseif ($storage === 'local') {
            $localFilePath = '../' . $path;
            if (file_exists($localFilePath)) {
                unlink($localFilePath);
            } else {
                throw new Exception("文件不存在");
            }
        } else {
            throw new Exception("无效的 storage 配置");
        }

        $stmt = $mysqli->prepare("DELETE FROM images WHERE path = ?");
        if (!$stmt) {
            throw new Exception("数据库错误: " . $mysqli->error);
        }
        $stmt->bind_param("s", $path);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['result' => 'success', 'message' => '图片删除成功']);
        } else {
            echo json_encode(['result' => 'error', 'message' => '无法从数据库中删除']);
            error_log("无法从数据库中删除': $path");
        }
        $stmt->close();
    } catch (OssException $e) {
        echo json_encode(['result' => 'error', 'message' => '从 oss 删除失败: ' . $e->getMessage()]);
        error_log("从 oss 删除失败: " . $e->getMessage());
    } catch (Exception $e) {
        echo json_encode(['result' => 'error', 'message' => '未知错误: ' . $e->getMessage()]);
        error_log("未知错误: " . $e->getMessage());
    }
} else {
    echo json_encode(['result' => 'error', 'message' => '仅允许 POST 请求。']);
}

$mysqli->close();
?>


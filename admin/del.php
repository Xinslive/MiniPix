<?php
require_once '../vendor/autoload.php';
use OSS\OssClient;
use OSS\Core\OssException;

$config = parse_ini_file('config.ini');
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
    die("Database connection failed: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = $_POST['path'] ?? '';

    if (empty($path)) {
        echo json_encode(['result' => 'error', 'message' => '路径不能为空']);
        exit;
    }

    try {
        $ossKey = parse_url($path, PHP_URL_PATH);

        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $ossClient->deleteObject($bucket, $ossKey);

        $stmt = $mysqli->prepare("DELETE FROM images WHERE path = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement error: " . $mysqli->error);
        }
        $stmt->bind_param("s", $path);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['result' => 'success', 'message' => '删除成功']);
        } else {
            echo json_encode(['result' => 'error', 'message' => '无法从数据库中删除']);
            error_log("Failed to delete image from database. Path: $path");
        }
        $stmt->close();
    } catch (OssException $e) {
        echo json_encode(['result' => 'error', 'message' => '从oss删除失败: ' . $e->getMessage()]);
        error_log("Failed to delete image from OSS: " . $e->getMessage());
    } catch (Exception $e) {
        echo json_encode(['result' => 'error', 'message' => '未知错误: ' . $e->getMessage()]);
        error_log("未知错误: " . $e->getMessage());
    }
} else {
    echo json_encode(['result' => 'error', 'message' => '仅允许 POST 请求。']);
}

$mysqli->close();
?>
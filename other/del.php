<?php

session_start();
require_once __DIR__ . '/core.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        minipix_json_response(['result' => 'error', 'message' => '仅允许 POST 请求。'], 405);
    }

    $config = minipix_load_config();
    $srcName = trim((string)($_POST['srcName'] ?? ''));
    $deleteToken = (string)($_POST['deleteToken'] ?? '');

    if ($srcName === '') {
        minipix_json_response(['result' => 'error', 'message' => '文件名不能为空']);
    }

    $isAdmin = !empty($_SESSION['loggedin']);
    $hasDeleteToken = $deleteToken !== '' && hash_equals(minipix_delete_token($srcName, $config), $deleteToken);
    if (!$isAdmin && !$hasDeleteToken) {
        minipix_json_response(['result' => 'error', 'message' => '无权删除图片'], 403);
    }

    $mysqli = minipix_db($config);
    $stmt = $mysqli->prepare('SELECT path, storage FROM images WHERE srcName = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('数据库错误: ' . $mysqli->error);
    }
    $stmt->bind_param('s', $srcName);
    $stmt->execute();
    $stmt->bind_result($path, $storage);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        minipix_json_response(['result' => 'error', 'message' => '未找到相应的图片记录']);
    }

    $storageHandler = minipix_storage($config, $storage);
    $storageHandler->delete($path);

    $stmt = $mysqli->prepare('DELETE FROM images WHERE srcName = ?');
    if (!$stmt) {
        throw new RuntimeException('数据库错误: ' . $mysqli->error);
    }
    $stmt->bind_param('s', $srcName);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    $mysqli->close();

    if ($deleted) {
        minipix_json_response(['result' => 'success', 'message' => '图片删除成功']);
    }

    minipix_json_response(['result' => 'error', 'message' => '无法从数据库中删除']);
} catch (Exception $e) {
    minipix_json_response(['result' => 'error', 'message' => $e->getMessage()], 500);
}

?>

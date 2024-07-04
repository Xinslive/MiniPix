<?php
session_start();

function isUploadAllowed() {
    // 上传大小限制
    if ($_FILES['image']['size'] > 5000000) {
        return '文件大小超过5MB';
    }

    // 上传频率限制
    $timeLimit = 3; // 3秒
    if (isset($_SESSION['last_upload_time'])) {
        $lastUploadTime = $_SESSION['last_upload_time'];
        if (time() - $lastUploadTime < $timeLimit) {
            return '上传过于频繁，请稍后再试';
        }
    }

    // 更新最后上传时间
    $_SESSION['last_upload_time'] = time();

    return true;
}

$uploadCheck = isUploadAllowed();
if ($uploadCheck !== true) {
    echo json_encode(['error' => $uploadCheck]);
    exit();
}
?>

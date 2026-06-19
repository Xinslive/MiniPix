<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/core.php';

if (file_exists(__DIR__ . '/install.lock')) {
    $host = $_SERVER['HTTP_HOST'];
    header("Location: https://$host/");
    exit();
}

$step = isset($_GET['step']) ? max(1, intval($_GET['step'])) : 1;
$error = '';

function install_write_section($title, array $values) {
    $content = "[$title]\n";
    foreach ($values as $key => $value) {
        $content .= $key . ' = ' . minipix_ini_value($value) . "\n";
    }
    return $content;
}

function install_write_other_section(array $values) {
    $content = "[Other]\n";
    $content .= "; imageFormat 可选 webp 或 avif，默认 webp。AVIF 压缩更慢、服务器压力更高；透明图若无法安全保存为 AVIF，会自动回退为 WebP。\n";
    foreach ($values as $key => $value) {
        $content .= $key . ' = ' . minipix_ini_value($value) . "\n";
    }
    return $content;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $mysql = [
            'dbHost' => trim($_POST['db_host'] ?? ''),
            'dbName' => trim($_POST['db_name'] ?? ''),
            'dbUser' => trim($_POST['db_user'] ?? ''),
            'dbPass' => (string)($_POST['db_pass'] ?? ''),
            'adminUser' => trim($_POST['admin_user'] ?? ''),
            'adminPass' => password_hash((string)($_POST['admin_pass'] ?? ''), PASSWORD_DEFAULT),
        ];

        file_put_contents(__DIR__ . '/config.ini', install_write_section('MySQL', $mysql));

        $mysqli = new mysqli($mysql['dbHost'], $mysql['dbUser'], $mysql['dbPass'], $mysql['dbName']);
        if ($mysqli->connect_error) {
            $error = '数据库连接失败: ' . $mysqli->connect_error;
        } else {
            $mysqli->set_charset('utf8mb4');
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    url VARCHAR(1024) NOT NULL,
                    path VARCHAR(1024) NOT NULL,
                    srcName VARCHAR(255) NOT NULL UNIQUE,
                    storage VARCHAR(32) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_srcName (srcName),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            if ($mysqli->query($createTableSQL) === false) {
                $error = '创建数据表失败: ' . $mysqli->error;
            } else {
                header('Location: install.php?step=2');
                exit;
            }
            $mysqli->close();
        }
    } elseif ($step === 2) {
        $storageMethod = $_POST['storage_method'] ?? 'local';
        if (!in_array($storageMethod, ['local', 'oss', 'ftp', 's3'], true)) {
            $storageMethod = 'local';
        }

        $other = [
            'validToken' => bin2hex(random_bytes(16)),
            'storage' => $storageMethod,
            'imageFormat' => 'webp',
        ];
        minipix_write_frontend_config($other['validToken']);
        $oss = [
            'ossAccessKeyId' => $_POST['oss_accessKeyId'] ?? '',
            'ossAccessKeySecret' => $_POST['oss_accessKeySecret'] ?? '',
            'ossEndpoint' => $_POST['oss_endpoint'] ?? '',
            'ossBucket' => $_POST['oss_bucket'] ?? '',
            'ossdomain' => $_POST['oss_domain'] ?? '',
        ];
        $s3 = [
            'S3Region' => $_POST['S3Region'] ?? '',
            'S3Bucket' => $_POST['S3Bucket'] ?? '',
            'S3Endpoint' => $_POST['S3Endpoint'] ?? '',
            'S3AccessKeyId' => $_POST['S3AccessKeyId'] ?? '',
            'S3AccessKeySecret' => $_POST['S3AccessKeySecret'] ?? '',
            'customUrlPrefix' => $_POST['customUrlPrefix'] ?? '',
        ];
        $ftp = [
            'ftpHost' => $_POST['ftpHost'] ?? '',
            'ftpPort' => $_POST['ftpPort'] ?? '21',
            'ftpUsername' => $_POST['ftpUsername'] ?? '',
            'ftpPassword' => $_POST['ftpPassword'] ?? '',
            'ftpdomain' => $_POST['ftpdomain'] ?? '',
        ];

        $configPath = __DIR__ . '/config.ini';
        $configContent = file_get_contents($configPath);
        $configContent .= "\n" . install_write_other_section($other);
        $configContent .= "\n" . install_write_section('OSS', $oss);
        $configContent .= "\n" . install_write_section('S3', $s3);
        $configContent .= "\n" . install_write_section('FTP', $ftp);

        file_put_contents($configPath, $configContent);
        chmod($configPath, 0600);
        file_put_contents(__DIR__ . '/install.lock', '安装锁');

        header('Location: /');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站安装</title>
    <link rel="shortcut icon" href="../static/favicon.ico">
    <link rel="stylesheet" type="text/css" href="../static/css/install.css">
    <script>
        function updateStorageFields() {
            var storageMethod = document.getElementById('storage_method').value;
            var storageSections = document.querySelectorAll('.storage-section');
            storageSections.forEach(section => section.style.display = 'none');

            if (storageMethod === 'oss') {
                document.getElementById('oss_section').style.display = 'block';
            } else if (storageMethod === 'ftp') {
                document.getElementById('ftp_section').style.display = 'block';
            } else if (storageMethod === 's3') {
                document.getElementById('s3_section').style.display = 'block';
            }
        }
    </script>
</head>
<body>
<div class="container">
<h2>网站安装向导</h2>
<?php if ($step === 1): ?>
    <form action="install.php?step=1" method="post">
        <div class="form-group">
            <label for="db_host">MySQL 主机</label>
            <input type="text" id="db_host" name="db_host" value="127.0.0.1" required>
        </div>
        <div class="form-group">
            <label for="db_name">数据库名称</label>
            <input type="text" id="db_name" name="db_name" required>
        </div>
        <div class="form-group">
            <label for="db_user">数据库用户名</label>
            <input type="text" id="db_user" name="db_user" required>
        </div>
        <div class="form-group">
            <label for="db_pass">数据库密码</label>
            <input type="password" id="db_pass" name="db_pass" required>
        </div>
        <div class="form-group">
            <label for="admin_user">管理员用户名</label>
            <input type="text" id="admin_user" name="admin_user" required>
        </div>
        <div class="form-group">
            <label for="admin_pass">管理员密码</label>
            <input type="password" id="admin_pass" name="admin_pass" required>
        </div>
        <div class="form-group">
            <button type="submit">下一步</button>
        </div>
    </form>
<?php elseif ($step === 2): ?>
    <form action="install.php?step=2" method="post">
        <div class="form-group">
            <label for="storage_method">选择存储方式</label>
            <select id="storage_method" name="storage_method" class="styled-select" onchange="updateStorageFields()">
                <option value="local">本地存储</option>
                <option value="oss">OSS</option>
                <option value="ftp">FTP</option>
                <option value="s3">S3</option>
            </select>
        </div>
        <div id="oss_section" class="storage-section" style="display:none;">
            <h3>OSS 配置</h3>
            <div class="form-group">
                <label for="oss_accessKeyId">AccessKeyId</label>
                <input type="text" id="oss_accessKeyId" name="oss_accessKeyId">
            </div>
            <div class="form-group">
                <label for="oss_accessKeySecret">AccessKeySecret</label>
                <input type="text" id="oss_accessKeySecret" name="oss_accessKeySecret">
            </div>
            <div class="form-group">
                <label for="oss_endpoint">Endpoint</label>
                <input type="text" id="oss_endpoint" name="oss_endpoint">
            </div>
            <div class="form-group">
                <label for="oss_bucket">Bucket</label>
                <input type="text" id="oss_bucket" name="oss_bucket">
            </div>
            <div class="form-group">
                <label for="oss_domain">OSS 域名</label>
                <input type="text" id="oss_domain" name="oss_domain">
            </div>
        </div>
        <div id="ftp_section" class="storage-section" style="display:none;">
            <h3>FTP 配置</h3>
            <div class="form-group">
                <label for="ftpHost">FTP 主机</label>
                <input type="text" id="ftpHost" name="ftpHost">
            </div>
            <div class="form-group">
                <label for="ftpPort">FTP 端口</label>
                <input type="text" id="ftpPort" name="ftpPort">
            </div>
            <div class="form-group">
                <label for="ftpUsername">FTP 用户名</label>
                <input type="text" id="ftpUsername" name="ftpUsername">
            </div>
            <div class="form-group">
                <label for="ftpPassword">FTP 密码</label>
                <input type="password" id="ftpPassword" name="ftpPassword">
            </div>
            <div class="form-group">
                <label for="ftpdomain">FTP 域名</label>
                <input type="text" id="ftpdomain" name="ftpdomain">
            </div>
        </div>
        <div id="s3_section" class="storage-section" style="display:none;">
            <h3>S3 配置</h3>
            <div class="form-group">
                <label for="S3Region">S3 Region</label>
                <input type="text" id="S3Region" name="S3Region">
            </div>
            <div class="form-group">
                <label for="S3Bucket">S3 Bucket</label>
                <input type="text" id="S3Bucket" name="S3Bucket">
            </div>
            <div class="form-group">
                <label for="S3Endpoint">S3 Endpoint</label>
                <input type="text" id="S3Endpoint" name="S3Endpoint">
            </div>
            <div class="form-group">
                <label for="S3AccessKeyId">S3 AccessKeyId</label>
                <input type="text" id="S3AccessKeyId" name="S3AccessKeyId">
            </div>
            <div class="form-group">
                <label for="S3AccessKeySecret">S3 AccessKeySecret</label>
                <input type="text" id="S3AccessKeySecret" name="S3AccessKeySecret">
            </div>
            <div class="form-group">
                <label for="customUrlPrefix">自定义 URL 前缀</label>
                <input type="text" id="customUrlPrefix" name="customUrlPrefix">
            </div>
        </div>
        <div class="form-group">
            <button type="submit">完成安装</button>
        </div>
    </form>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error-message">
        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
<?php endif; ?>
</div>
</body>
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 600);

if (file_exists('install.lock')) {
    $host = $_SERVER['HTTP_HOST'];
    $url = "https://$host/";
    header("Location: $url");
    exit();
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $mysql = [
            'dbHost' => $_POST['db_host'],
            'dbName' => $_POST['db_name'],
            'dbUser' => $_POST['db_user'],
            'dbPass' => $_POST['db_pass'],
            'adminUser' => $_POST['admin_user'],
            'adminPass' => $_POST['admin_pass'],
        ];
        $configContent = "[MySQL]\n";
        foreach ($mysql as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        file_put_contents('config.ini', $configContent);

        $mysqli = new mysqli($mysql['dbHost'], $mysql['dbUser'], $mysql['dbPass'], $mysql['dbName']);
        $checkTableSQL = "SHOW TABLES LIKE 'images'";
        $result = $mysqli->query($checkTableSQL);
        if ($result && $result->num_rows === 0) {
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                srcName VARCHAR(255) NOT NULL UNIQUE,
                storage VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_srcName (srcName)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            if ($mysqli->query($createTableSQL) === FALSE) {
                $error = '创建数据表失败: ' . $mysqli->error;
            } else {
                header('Location: install.php?step=2');
                exit;
            }
        } else {
            header('Location: install.php?step=3');
            exit;
        }
    } elseif ($step === 2) {
        $other = [
            'validToken' => '1c17b11693cb5ec63859b091c5b9c1b2',
            'storage' => $_POST['storage_method'],
        ];
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
            's3AccessKeySecret' => $_POST['s3AccessKeySecret'] ?? '',
            'customUrlPrefix' => $_POST['customUrlPrefix'] ?? '',
        ];
        $ftp = [
            'ftpHost' => $_POST['ftpHost'] ?? '',
            'ftpPort' => $_POST['ftpPort'] ?? '',
            'ftpUsername' => $_POST['ftpUsername'] ?? '',
            'ftpPassword' => $_POST['ftpPassword'] ?? '',
            'ftpdomain' => $_POST['ftpdomain'] ?? '',
        ];

        $configContent = file_get_contents('config.ini');
        $configContent .= "\n[Other]\n";
        foreach ($other as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        $configContent .= "\n[OSS]\n";
        foreach ($oss as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        $configContent .= "\n[S3]\n";
        foreach ($s3 as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        $configContent .= "\n[FTP]\n";
        foreach ($ftp as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        file_put_contents('config.ini', $configContent);
        chmod('config.ini', 0600);

        $parentDir = dirname(__DIR__);
        exec("composer require aws/aws-sdk-php -d $parentDir", $outputAws, $returnAws);
        exec("composer require aliyuncs/oss-sdk-php -d $parentDir", $outputOss, $returnOss);
        exec("composer require qcloud/cos-sdk-v5 -d $parentDir", $outputCos, $returnCos);

        file_put_contents('install.lock', '安装锁');
        header('Location: install.php?step=1');
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
    <link rel="shortcut icon" href="static/favicon.ico">
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
                <label for="s3AccessKeySecret">S3 AccessKeySecret</label>
                <input type="text" id="s3AccessKeySecret" name="s3AccessKeySecret">
            </div>
            <div class="form-group">
                <label for="customUrlPrefix">自定义 URL 前缀</label>
                <input type="text" id="customUrlPrefix" name="customUrlPrefix">
            </div>
        </div>
        <div class="form-group">
            <button type="submit">继续安装（后台安装SDK，耐心等待）</button>
        </div>
    </form>
<?php elseif ($step === 3): ?>
    <p>创建数据库表失败，请清空数据库再安装</p>
    <div class="message-box"><a href="/" class="go-home-button">重新开始</a></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error-message">
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
<?php endif; ?>
</div>
</body>
</html>

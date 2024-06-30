<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists('./admin/install.lock')) {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系统已安装</title>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f0f0f0;
                font-family: Arial, sans-serif;
            }
            .message-box {
                max-width: 600px;
                padding: 20px;
                background-color: #fff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                border-radius: 5px;
                text-align: center;
            }
            .message-box h1 {
                color: #a94442;
                font-size: 24px;
                margin-bottom: 10px;
            }
            .message-box p {
                color: #666;
                font-size: 18px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1>系统已经安装成功</h1>
            <p>如需重新安装，请删除./admin/install.lock文件。</p>
        </div>
    </body>
    </html>
    ';
    die();
}


$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $mysql = [
            'dbHost' => $_POST['mysql_dbHost'],
            'dbName' => $_POST['mysql_dbName'],
            'dbUser' => $_POST['mysql_dbUser'],
            'dbPass' => $_POST['mysql_dbPass'],
            'adminUser' => $_POST['mysql_adminUser'],
            'adminPass' => $_POST['mysql_adminPass'],
        ];
        $configContent = "[MySQL]\n";
        foreach ($mysql as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        file_put_contents('./admin/config.ini', $configContent);
        chmod('./admin/config.ini', 0777);

        $mysqli = new mysqli($mysql['dbHost'], $mysql['dbUser'], $mysql['dbPass'], $mysql['dbName']);
        if ($mysqli->connect_error) {
            $error = '数据库连接失败: ' . $mysqli->connect_error;
        } else {
            $checkTableSQL = "SHOW TABLES LIKE 'images'";
            $result = $mysqli->query($checkTableSQL);
            if ($result && $result->num_rows === 0) {
                $createTableSQL = "
                CREATE TABLE images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    url VARCHAR(255) NOT NULL,
                    path VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
                ";
                if ($mysqli->query($createTableSQL) === FALSE) {
                    $error = '创建数据表失败: ' . $mysqli->error;
                } else {
                    header('Location: install.php?step=2');
                    exit;
                }
            } else {
                header('Location: install.php?step=2');
                exit;
            }
        }
    } elseif ($step === 3) {
        $oss = [
            'accessKeyId' => $_POST['oss_accessKeyId'],
            'accessKeySecret' => $_POST['oss_accessKeySecret'],
            'endpoint' => $_POST['oss_endpoint'],
            'bucket' => $_POST['oss_bucket'],
            'cdndomain' => $_POST['oss_cdndomain'],
        ];
        $token = [
            'validToken' => $_POST['token_validToken'],
        ];

        $configContent = file_get_contents('./admin/config.ini');
        $configContent .= "\n[OSS]\n";
        foreach ($oss as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        $configContent .= "\n[Token]\n";
        foreach ($token as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        file_put_contents('./admin/config.ini', $configContent);
        chmod('./admin/config.ini', 0600);

        file_put_contents('./admin/install.lock', '安装锁');
        header('Location: install.php?step=4');
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
    <style>
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

.container {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 600px;
}

.container h2 {
    margin-top: 0;
    text-align: center;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #666;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

.form-group input[type="submit"] {
    background-color: #5cb85c;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 16px;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.form-group input[type="submit"]:hover {
    background-color: #4cae4c;
}

.error {
    color: red;
    text-align: center;
    margin-bottom: 15px;
}

div p {
    text-align: center;
    color: #333;
    font-size: 16px;
}


div form .form-group input[type="submit"] {
    margin-top: 10px;
}

    </style>
</head>
<body>
    <div class="container">
        <h2>网站安装向导</h2>
        <?php if ($step === 1): ?>
            <form method="POST">
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="mysql_dbHost">MySQL Host</label>
                    <input type="text" id="mysql_dbHost" name="mysql_dbHost" value="127.0.0.1" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbName">MySQL Database Name</label>
                    <input type="text" id="mysql_dbName" name="mysql_dbName" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbUser">MySQL User</label>
                    <input type="text" id="mysql_dbUser" name="mysql_dbUser" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbPass">MySQL Password</label>
                    <input type="password" id="mysql_dbPass" name="mysql_dbPass" required>
                </div>
                <div class="form-group">
                    <label for="mysql_adminUser">网站管理员账号</label>
                    <input type="text" id="mysql_adminUser" name="mysql_adminUser" required>
                </div>
                <div class="form-group">
                    <label for="mysql_adminPass">网站管理员密码</label>
                    <input type="password" id="mysql_adminPass" name="mysql_adminPass" required>
                </div>
                <div class="form-group">
                    <input type="submit" value="下一步">
                </div>
            </form>
        <?php elseif ($step === 2): ?>
            <div>
                <p>数据库表创建成功！</p>
                <form method="GET">
                    <input type="hidden" name="step" value="3">
                    <div class="form-group">
                        <input type="submit" value="下一步">
                    </div>
                </form>
            </div>
        <?php elseif ($step === 3): ?>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label for="oss_accessKeyId">OSS Access Key ID</label>
                    <input type="text" id="oss_accessKeyId" name="oss_accessKeyId" required>
                </div>
                <div class="form-group">
                    <label for="oss_accessKeySecret">OSS Access Key Secret</label>
                    <input type="text" id="oss_accessKeySecret" name="oss_accessKeySecret" required>
                </div>
                <div class="form-group">
                    <label for="oss_endpoint">OSS Endpoint</label>
                    <input type="text" id="oss_endpoint" name="oss_endpoint" required>
                </div>
                <div class="form-group">
                    <label for="oss_bucket">OSS Bucket</label>
                    <input type="text" id="oss_bucket" name="oss_bucket" required>
                </div>
                <div class="form-group">
                    <label for="oss_cdndomain">OSS CDN 域名</label>
                    <input type="text" id="oss_cdndomain" name="oss_cdndomain" value="oss-cdn.your-domain.com" required>
                </div>
                <div class="form-group">
                    <label for="token_validToken">Valid Token (保持默认就好，修改需要同步修改static/script.js)</label>
                    <input type="text" id="token_validToken" name="token_validToken" value="1c17b11693cb5ec63859b091c5b9c1b2" required>
                </div>
                <div class="form-group">
                    <input type="submit" value="完成安装">
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

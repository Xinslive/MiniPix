<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists('../other/install.lock')) {
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系统已安装</title>
        <link rel="shortcut icon" href="static/favicon.ico">
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: url(/static/background.webp) no-repeat 100% 100%;
                background-size: cover;
                background-attachment: fixed;
                -webkit-tap-highlight-color: transparent;
            }
            .message-box {
                max-width: 600px;
                padding: 20px;
                background-color: #fff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                border-radius: 5px;
                text-align: center;
                background-color: rgba(255, 255, 255, 0.4);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
            .message-box h1 {
                color: #a94442;
                font-size: 24px;
                margin-bottom: 10px;
            }
            .go-home-button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-size: 18px;
                transition: background-color 0.3s;
            }
            .go-home-button:hover {
                background-color: #45a049;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1>系统已经安装成功</h1>
            <a href="/" class="go-home-button">前往首页</a>
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
        file_put_contents('../other/config.ini', $configContent);

        $mysqli = new mysqli($mysql['dbHost'], $mysql['dbUser'], $mysql['dbPass'], $mysql['dbName']);
        if ($mysqli->connect_error) {
            $error = '数据库连接失败: ' . $mysqli->connect_error;
        } else {
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
            'validToken' => '1c17b11693cb5ec63859b091c5b9c1b2',
            'storage' => 'oss'
        ];

        $configContent = file_get_contents('../other/config.ini');
        $configContent .= "\n[OSS]\n";
        foreach ($oss as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        $configContent .= "\n[Other]\n";
        foreach ($token as $key => $value) {
            $configContent .= "$key = $value\n";
        }
        file_put_contents('../other/config.ini', $configContent);
        chmod('../other/config.ini', 0600);

        file_put_contents('../other/install.lock', '安装锁');
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
	<link rel="shortcut icon" href="static/favicon.ico">
    <style>
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
    background: url(/static/background.webp) no-repeat 100% 100%;
    background-size: cover;
    background-attachment: fixed;
    -webkit-tap-highlight-color: transparent;
}

.container {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 450px;
    background-color: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.container h2 {
    margin-top: 0;
    text-align: center;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
}

.form-group input {
    width: calc(100% - 20px);
    padding: 10px;
    outline: 0;
    color: #e9ffe1;
    border: 1px solid #aaa;
    border-radius: 8px;
    background-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.form-group input[type="submit"] {
    width: 100%;
    padding: 10px;
    background-color: #5cb85c;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 16px;
    border-radius: 8px; 
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
                    <label for="mysql_dbHost">MySQL Host 数据库地址</label>
                    <input type="text" id="mysql_dbHost" name="mysql_dbHost" value="127.0.0.1" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbName">MySQL Database Name 数据库名</label>
                    <input type="text" id="mysql_dbName" name="mysql_dbName" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbUser">MySQL User 数据库用户名</label>
                    <input type="text" id="mysql_dbUser" name="mysql_dbUser" required>
                </div>
                <div class="form-group">
                    <label for="mysql_dbPass">MySQL Password 数据库密码</label>
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
                    <label for="oss_accessKeyId">阿里云OSS Access Key ID</label>
                    <input type="text" id="oss_accessKeyId" name="oss_accessKeyId" required>
                </div>
                <div class="form-group">
                    <label for="oss_accessKeySecret">阿里云OSS Access Key Secret</label>
                    <input type="text" id="oss_accessKeySecret" name="oss_accessKeySecret" required>
                </div>
                <div class="form-group">
                    <label for="oss_endpoint">OSS Endpoint 地域代码</label>
                    <input type="text" id="oss_endpoint" name="oss_endpoint" required>
                </div>
                <div class="form-group">
                    <label for="oss_bucket">OSS Bucket 储存桶名</label>
                    <input type="text" id="oss_bucket" name="oss_bucket" required>
                </div>
                <div class="form-group">
                    <label for="oss_cdndomain">OSS CDN 域名</label>
                    <input type="text" id="oss_cdndomain" name="oss_cdndomain" value="oss-cdn.your-domain.com" required>
                </div>
                <a>提示：如欲使用本地储存图片，上方信息随意填写就行！</a>
                <div class="form-group">
                    <input type="submit" value="完成安装">
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

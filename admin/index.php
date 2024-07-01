<?php
session_start();

$config = parse_ini_file('config.ini');
$dbHost = $config['dbHost'];
$dbUser = $config['dbUser'];
$dbPass = $config['dbPass'];
$dbName = $config['dbName'];
$adminUser = $config['adminUser'];
$adminPass = $config['adminPass'];

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    die("连接数据库失败：" . $mysqli->connect_error);
}

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "用户名或密码无效。";
    }
}

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo '
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>登录</title>
        <link rel="stylesheet" type="text/css" href="static/login.css">
    </head>
    <body>
        <div class="login-container">
            <h2>登录</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label for="username">账号：</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码：</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="login">登录</button>
                </div>
                ' . (isset($error) ? '<div class="error-message">' . $error . '</div>' : '') . '
            </form>
        </div>
    </body>
    </html>
    ';
    exit;
}

$query = "SELECT id, url, path FROM images ORDER BY id DESC";
$result = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台</title>
    <link rel="stylesheet" type="text/css" href="static/admin.css">
    <script type="text/javascript" src="admin.js"></script>
</head>
<body>
    <div class="gallery">
        <?php
        $items_per_page = 35; // 每页显示的图片数量
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $offset = ($current_page - 1) * $items_per_page;

        $query = "SELECT * FROM images ORDER BY id DESC LIMIT $items_per_page OFFSET $offset";
        $result = $mysqli->query($query);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<div class="gallery-item" id="image-' . $row['id'] . '">';
                echo '<img src="' . $row['url'] . '" alt="Image">';
                echo '<button class="delete-btn" onclick="deleteImage(' . $row['id'] . ', \'' . $row['path'] . '\')"><svg t="1719644054502" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2559" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20"><path d="M813.2 301.2c25-25 25-65.6 0-90.6s-65.6-25-90.6 0L512 421.4 301.2 210.8c-25-25-65.6-25-90.6 0s-25 65.6 0 90.6L421.4 512 210.8 722.8c-25 25-25 65.6 0 90.6s65.6 25 90.6 0L512 602.6l210.8 210.6c25 25 65.6 25 90.6 0s25-65.6 0-90.6L602.6 512l210.6-210.8z" p-id="2560" fill="#eee"></path></svg></button>';
                echo '<button class="copy-btn" onclick="copyUrl(\'' . $row['url'] . '\')"><svg t="1719644033606" class="icon" viewBox="0 0 1280 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2353" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20"><path d="M1159.6 535.4c113-113 113-296 0-409-100-100-257.6-113-372.6-30.8l-3.2 2.2c-28.8 20.6-35.4 60.6-14.8 89.2s60.6 35.4 89.2 14.8l3.2-2.2c64.2-45.8 152-38.6 207.6 17.2 63 63 63 165 0 228L844.6 669.6c-63 63-165 63-228 0-55.8-55.8-63-143.6-17.2-207.6l2.2-3.2c20.6-28.8 13.8-68.8-14.8-89.2s-68.8-13.8-89.2 14.8l-2.2 3.2C413 502.4 426 660 526 760c113 113 296 113 409 0l224.6-224.6zM120.4 488.6c-113 113-113 296 0 409 100 100 257.6 113 372.6 30.8l3.2-2.2c28.8-20.6 35.4-60.6 14.8-89.2s-60.6-35.4-89.2-14.8l-3.2 2.2c-64.2 45.8-152 38.6-207.6-17.2C148 744 148 642 211 579l224.4-224.6c63-63 165-63 228 0 55.8 55.8 63 143.6 17.2 207.8l-2.2 3.2c-20.6 28.8-13.8 68.8 14.8 89.2s68.8 13.8 89.2-14.8l2.2-3.2C867 521.6 854 364 754 264c-113-113-296-113-409 0L120.4 488.6z" p-id="2354" fill="#eee"></path></svg></button>';
                echo '</div>';
            }
        } else {
            echo '<p>啥也没有</p>';
        }
        ?>
    </div>

<div class="pagination">
<?php
$total_pages_query = "SELECT COUNT(id) as total FROM images";
$total_pages_result = $mysqli->query($total_pages_query);

if ($total_pages_result) {
    $total_rows = $total_pages_result->fetch_assoc()['total'];

    if ($total_rows > 0) {
        $total_pages = ceil($total_rows / $items_per_page);
    } else {
        $total_pages = 0;
    }
} else {
    die("查询错误：" . $mysqli->error);
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$max_links = 7;
$half_max_links = floor($max_links / 2);

if ($total_pages > 1) {
    if ($current_page > 1) {
        echo '<a class="page-link" href="?page=' . ($current_page - 1) . '">&laquo;</a> ';
    }

    if ($total_pages <= $max_links) {
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '">' . $i . '</a> ';
        }
    } else {
        if ($current_page <= $half_max_links) {
            for ($i = 1; $i <= $max_links - 1; $i++) {
                echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '">' . $i . '</a> ';
            }
            echo '... <a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a> ';
        } elseif ($current_page > $total_pages - $half_max_links) {
            echo '<a class="page-link" href="?page=1">1</a> ... ';
            for ($i = $total_pages - $max_links + 2; $i <= $total_pages; $i++) {
                echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '">' . $i . '</a> ';
            }
        } else {
            echo '<a class="page-link" href="?page=1">1</a> ... ';
            for ($i = $current_page - $half_max_links + 1; $i <= $current_page + $half_max_links - 1; $i++) {
                echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '">' . $i . '</a> ';
            }
            echo '... <a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a> ';
        }
    }

    if ($current_page < $total_pages) {
        echo '<a class="page-link" href="?page=' . ($current_page + 1) . '">&raquo;</a> ';
    }
}

$mysqli->close();
?>
</div>
<a href="/" class="floating-link"><svg t="1719609773699" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4283" xmlns:xlink="http://www.w3.org/1999/xlink" width="30" height="30"><path d="M566.869333 94.72l6.101334 4.778667 353.493333 298.666666c7.637333 6.101333 12.202667 15.232 12.202667 22.869334a30.549333 30.549333 0 0 1-30.464 30.464 31.445333 31.445333 0 0 1-17.664-5.077334l-3.669334-2.56-9.173333-7.594666v339.797333c0 66.048-49.450667 120.746667-110.250667 124.757333l-7.082666 0.213334H263.68c-63.146667 0-113.493333-52.266667-117.12-117.376l-0.213333-7.594667v-339.797333l-10.666667 9.130666a28.970667 28.970667 0 0 1-19.84 7.637334A30.549333 30.549333 0 0 1 85.333333 422.570667c0-8.917333 3.157333-15.658667 8.661334-21.205334l3.541333-3.2 358.101333-298.666666a90.154667 90.154667 0 0 1 111.232-4.821334zM497.493333 144l-3.797333 2.688-286.464 239.232v390.101333c0 32.981333 22.954667 60.586667 52.352 63.701334l5.546667 0.298666h124.970666v-213.333333a91.733333 91.733333 0 0 1 84.906667-91.178667l6.528-0.256h60.928a91.733333 91.733333 0 0 1 91.221333 84.906667l0.213334 6.528v213.333333h124.970666c30.122667 0 54.826667-25.6 57.6-57.856l0.298667-6.144V384.469333L533.333333 146.730667a32.64 32.64 0 0 0-35.84-2.688z m44.970667 452.224h-60.928a30.549333 30.549333 0 0 0-30.506667 30.464v213.333333h121.941334v-213.333333a30.549333 30.549333 0 0 0-30.506667-30.464z" fill="#bbb" p-id="4284"></path></svg></a>
<a href="#" class="top-link"><svg t="1719611181828" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2153" xmlns:xlink="http://www.w3.org/1999/xlink" width="30" height="30"><path d="M512 896a64 64 0 0 0 64-64V325.12l168.106667 168.106667a64 64 0 0 0 90.453333-90.453334L557.226667 125.44a64 64 0 0 0-90.453334 0L189.44 402.773333a64 64 0 1 0 90.453333 90.453334l168.106667-168.106667v506.88c0 35.328 28.672 64 64 64z" p-id="2154" fill="#bbb"></path></svg></a>
<div id="notification" class="notification"></div>
</body>
</html>

<?php
session_start();

$config = parse_ini_file('../other/config.ini');
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
        <link rel="shortcut icon" href="/static/favicon.ico">
        <link rel="stylesheet" type="text/css" href="/static/css/login.css">
    </head>
    <body>
        <div class="login-container">
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
        <script type="text/javascript" src="/static/js/cursor.js"></script>
    </body>
    </html>
    ';
    exit;
}

function renderImages($mysqli, $items_per_page, $offset) {
    $query = "SELECT * FROM images ORDER BY id DESC LIMIT $items_per_page OFFSET $offset";
    $result = $mysqli->query($query);

    $images = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $images[] = [
                'id' => $row['id'],
                'url' => $row['url'],
                'path' => $row['path']
            ];
        }
    }
    return $images;
}

function renderPagination($mysqli, $items_per_page, $current_page) {
    $total_pages_query = "SELECT COUNT(id) as total FROM images";
    $total_pages_result = $mysqli->query($total_pages_query);

    if ($total_pages_result) {
        $total_rows = $total_pages_result->fetch_assoc()['total'];
        $total_pages = ceil($total_rows / $items_per_page);
    } else {
        die("查询错误：" . $mysqli->error);
    }

    $max_links = 4;
    $half_max_links = floor($max_links / 2);
    $pagination = '';

    if ($total_pages > 1) {
        $pagination .= '<div class="pagination">';
        if ($current_page > 1) {
            $pagination .= '<a class="page-link" href="?page=' . ($current_page - 1) . '" data-page="' . ($current_page - 1) . '">&laquo;</a> ';
        }

        if ($total_pages <= $max_links) {
            for ($i = 1; $i <= $total_pages; $i++) {
                $pagination .= '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
            }
        } else {
            if ($current_page <= $half_max_links) {
                for ($i = 1; $i <= $max_links - 1 && $i <= $total_pages; $i++) {
                    $pagination .= '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
                if ($total_pages > $max_links) {
                    $pagination .= '<a class="page-link" href="?page=' . $total_pages . '" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
                }
            } elseif ($current_page > $total_pages - $half_max_links) {
                $pagination .= '<a class="page-link" href="?page=1" data-page="1">1</a> ';
                for ($i = $total_pages - $max_links + 2; $i <= $total_pages; $i++) {
                    $pagination .= '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
            } else {
                $pagination .= '<a class="page-link" href="?page=1" data-page="1">1</a> ';
                for ($i = $current_page - $half_max_links + 1; $i <= $current_page + $half_max_links - 1; $i++) {
                    $pagination .= '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
                if ($total_pages > $max_links) {
                    $pagination .= '<a class="page-link" href="?page=' . $total_pages . '" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
                }
            }
        }

        if ($current_page < $total_pages) {
            $pagination .= '<a class="page-link" href="?page=' . ($current_page + 1) . '" data-page="' . ($current_page + 1) . '">&raquo;</a> ';
        }
        $pagination .= '</div>';
    }
    return $pagination;
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 50;
$offset = ($current_page - 1) * $items_per_page;

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    $images = renderImages($mysqli, $items_per_page, $offset);
    $pagination = renderPagination($mysqli, $items_per_page, $current_page);

    header('Content-Type: application/json');
    echo json_encode(['images' => $images, 'pagination' => $pagination]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台</title>
    <link rel="shortcut icon" href="/static/favicon.ico">
    <link rel="stylesheet" type="text/css" href="/static/css/admin.css">
    <link rel="stylesheet" type="text/css" href="/static/css/glightbox.min.css">
</head>
<body>
    <div id="gallery" class="gallery"></div>
    <div id="pagination" class="pagination"></div>
    <div id="loading-indicator" class="loading-indicator">
        <div class="spinner"></div>
        <div class="loading-text">加载中...</div>
    </div>
    <a href="/" class="floating-link"><img src="/static/svg/home.svg" alt="🏠" style="width:30px;height:30px;"></a>
    <a class="top-link" id="scroll-to-top"><img src="/static/svg/top.svg" alt="⬆️" /></a>
    <script type="text/javascript" src="/static/js/admin.js"></script>
    <script type="text/javascript" src="/static/js/ajax.js"></script>
    <script type="text/javascript" src="/static/js/glightbox.min.js"></script>
    <script>const lightbox = GLightbox();</script>
</body>
</html>

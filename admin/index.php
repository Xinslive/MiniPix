<?php
session_start();
require_once __DIR__ . '/../other/core.php';

try {
    $config = minipix_load_config();
    $mysqli = minipix_db($config);
} catch (Exception $e) {
    die('连接数据库失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function minipix_password_matches($password, $storedHash) {
    if (password_get_info($storedHash)['algo'] !== 0) {
        return password_verify($password, $storedHash);
    }

    return hash_equals((string)$storedHash, (string)$password);
}

if (isset($_POST['login'])) {
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (hash_equals((string)($config['adminUser'] ?? ''), $username) && minipix_password_matches($password, (string)($config['adminPass'] ?? ''))) {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "用户名或密码无效。";
    }
}

if (empty($_SESSION['loggedin'])) {
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
                ' . (isset($error) ? '<div class="error-message">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
            </form>
        </div>
        <script type="text/javascript" src="/static/js/cursor.js"></script>
    </body>
    </html>
    ';
    exit;
}

function renderImages($mysqli, $items_per_page, $offset) {
    $stmt = $mysqli->prepare('SELECT id, url, srcName FROM images ORDER BY id DESC LIMIT ? OFFSET ?');
    if (!$stmt) {
        throw new RuntimeException('查询错误：' . $mysqli->error);
    }
    $stmt->bind_param('ii', $items_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = [
            'id' => (int)$row['id'],
            'url' => $row['url'],
            'srcName' => $row['srcName'],
        ];
    }
    $stmt->close();
    return $images;
}

function renderPagination($mysqli, $items_per_page, $current_page) {
    $total_pages_query = 'SELECT COUNT(id) as total FROM images';
    $total_pages_result = $mysqli->query($total_pages_query);

    if ($total_pages_result) {
        $total_rows = (int)$total_pages_result->fetch_assoc()['total'];
        $total_pages = (int)ceil($total_rows / $items_per_page);
    } else {
        throw new RuntimeException('查询错误：' . $mysqli->error);
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

$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$items_per_page = 50;
$offset = ($current_page - 1) * $items_per_page;

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    try {
        $images = renderImages($mysqli, $items_per_page, $offset);
        $pagination = renderPagination($mysqli, $items_per_page, $current_page);
        minipix_json_response(['images' => $images, 'pagination' => $pagination]);
    } catch (Exception $e) {
        minipix_json_response(['images' => [], 'pagination' => '', 'error' => $e->getMessage()], 500);
    }
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
    <link rel="stylesheet" href="/static/css/fancybox.min.css?v=5.0.36">
    <script src="/static/js/fancybox.umd.min.js?v=5.0.36" defer></script>
    <!-- 你可以使用第三方CDN进行加速 当前版本 Fancybox5.0.36 -->
    <!-- <link rel="stylesheet" href="https://cdn.cbd.int/pixpro@1.7.0/static/css/fancybox.min.css?v=5.0.36">
    <script src="https://cdn.cbd.int/pixpro@1.7.0/static/js/fancybox.umd.min.js?v=5.0.36" defer></script> -->

</head>
<body>
<div id="gallery" class="gallery"></div>
<div id="pagination" class="pagination"></div>
<div id="loading-indicator" class="loading-indicator">
    <div class="spinner"></div>
</div>
<a href="/" class="floating-link"><img src="/static/svg/home.svg" alt="🏠" style="width:30px;height:30px;"></a>
<a class="top-link" id="scroll-to-top"><img src="/static/svg/top.svg" alt="⬆️" /></a>
<script type="text/javascript" src="/static/js/admin.js"></script>
<script type="text/javascript" src="/static/js/ajax.js"></script>
</body>
</html>

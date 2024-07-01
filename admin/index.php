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
        <link rel="shortcut icon" href="/static/favicon.ico">
        <link rel="stylesheet" type="text/css" href="/static/css/login.css">
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
        <script type="text/javascript" src="/static/js/cursor.js"></script>
    </body>
    </html>
    ';
    exit;
}

function renderImages($mysqli, $items_per_page, $offset) {
    $query = "SELECT * FROM images ORDER BY id DESC LIMIT $items_per_page OFFSET $offset";
    $result = $mysqli->query($query);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="gallery-item" id="image-' . $row['id'] . '">';
            echo '<img src="' . $row['url'] . '" alt="Image">';
            echo '<button class="delete-btn" data-id="' . $row['id'] . '" data-path="' . $row['path'] . '"><img src="/static/svg/xmark.svg" alt="X" /></button>';
            echo '<button class="copy-btn" data-url="' . $row['url'] . '"><img src="/static/svg/link.svg" alt="Copy" /></button>';
            echo '</div>';
        }
    } else {
        echo '啥也没有';
    }
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

    $max_links = 7;
    $half_max_links = floor($max_links / 2);

    if ($total_pages > 1) {
        if ($current_page > 1) {
            echo '<a class="page-link" href="?page=' . ($current_page - 1) . '" data-page="' . ($current_page - 1) . '">&laquo;</a> ';
        }

        if ($total_pages <= $max_links) {
            for ($i = 1; $i <= $total_pages; $i++) {
                echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
            }
        } else {
            if ($current_page <= $half_max_links) {
                for ($i = 1; $i <= $max_links - 1; $i++) {
                    echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
                echo '... <a class="page-link" href="?page=' . $total_pages . '" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
            } elseif ($current_page > $total_pages - $half_max_links) {
                echo '<a class="page-link" href="?page=1" data-page="1">1</a> ... ';
                for ($i = $total_pages - $max_links + 2; $i <= $total_pages; $i++) {
                    echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
            } else {
                echo '<a class="page-link" href="?page=1" data-page="1">1</a> ... ';
                for ($i = $current_page - $half_max_links + 1; $i <= $current_page + $half_max_links - 1; $i++) {
                    echo '<a class="page-link' . ($i == $current_page ? ' active' : '') . '" href="?page=' . $i . '" data-page="' . $i . '">' . $i . '</a> ';
                }
                echo '... <a class="page-link" href="?page=' . $total_pages . '" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
            }
        }

        if ($current_page < $total_pages) {
            echo '<a class="page-link" href="?page=' . ($current_page + 1) . '" data-page="' . ($current_page + 1) . '">&raquo;</a> ';
        }
    }
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 35;
$offset = ($current_page - 1) * $items_per_page;

if (isset($_GET['page'])) {
    renderImages($mysqli, $items_per_page, $offset);
    renderPagination($mysqli, $items_per_page, $current_page);
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
</head>
<body>
    <div id="gallery" class="gallery">
        <?php renderImages($mysqli, $items_per_page, $offset); ?>
    </div>

    <div class="pagination">
        <?php renderPagination($mysqli, $items_per_page, $current_page); ?>
    </div>

    <a href="/" class="floating-link"><img src="/static/svg/home.svg" alt="🏠" style="width:30px;height:30px;"></a>
    <a class="top-link" id="scroll-to-top"><img src="/static/svg/top.svg" alt="⬆️" /></a>

    <div id="notification" class="notification"></div>

    <script type="text/javascript" src="/static/js/admin.js"></script>
    <script type="text/javascript" src="/static/js/cursor.js"></script>
    <script>
        function deleteImage(id, path) {
            if (confirm('确定删除这张图片吗？')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'del.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.result === 'success') {
                            var notification = document.getElementById('notification');
                            notification.textContent = response.message;
                            notification.classList.add('delete-success');
                            notification.style.display = 'block';
                            setTimeout(function() {
                                notification.classList.add('message-right');
                                setTimeout(function() {
                                    notification.style.display = 'none';
                                }, 1000);
                            }, 1500);
                        var imageElement = document.getElementById('image-' + id);
                            if (imageElement) {
                                imageElement.parentNode.removeChild(imageElement);
                            }
                        } else {
                            alert('删除图片失败。');
                        }
                    } else {
                        alert('错误：' + xhr.status);
                    }
                };
                xhr.onerror = function() {
                    alert('请求失败。');
                };
                xhr.send('path=' + encodeURIComponent(path) + '&id=' + encodeURIComponent(id));
            }
        }

        function copyUrl(url) {
            navigator.clipboard.writeText(url).then(function() {
                var notification = document.getElementById('notification');
                notification.textContent = '图片URL已复制';
                notification.classList.add('copy-success');
                notification.style.display = 'block';
                setTimeout(function() {
                    notification.classList.add('message-right');
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 1000);
                }, 1500);
            }, function(err) {
                alert('复制失败: ' + err);
            });
        }

        function bindImageActions() {
            document.querySelectorAll('.delete-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    var path = this.getAttribute('data-path');
                    deleteImage(id, path);
                });
            });

            document.querySelectorAll('.copy-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    var url = this.getAttribute('data-url');
                    copyUrl(url);
                });
            });
        }

        document.querySelectorAll('.page-link').forEach(function(link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                var page = this.getAttribute('data-page');

                fetch('?page=' + page)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('gallery').innerHTML = html;
                        bindImageActions();
                    });
            });
        });

        bindImageActions();
    </script>
</body>
</html>

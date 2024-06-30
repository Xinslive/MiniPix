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
                                notification.style.display = 'none';
                            }, 3000);
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
                xhr.send('path=' + encodeURIComponent(path));
            }
        }

        function copyUrl(url) {
            navigator.clipboard.writeText(url).then(function() {
                var notification = document.getElementById('notification');
                notification.textContent = '图片URL已复制';
                notification.classList.add('copy-success');
                notification.style.display = 'block';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 3000);
            }, function(err) {
                alert('复制失败: ' + err);
            });
        }
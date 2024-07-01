        function deleteImage(id, path) {
            if (confirm('确定删除这张图片吗？')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'del.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.result === 'success') {
                            var notification = document.createElement('div');
                            notification.classList.add('delete-success');
                            notification.textContent = response.message;
                            document.body.appendChild(notification);
                            setTimeout(function() {
                                notification.classList.add('message-right');
                            setTimeout(function() {
                            notification.parentNode.removeChild(notification);
                            }, 1000);
                        }, 1500);
                            var imageElement = document.getElementById('image-' + id);
                            if (imageElement) {
                                imageElement.parentNode.removeChild(imageElement);
                            }
                        } else {
                            var notification = document.createElement('div');
                            notification.textContent = '图片删除失败';
                            notification.classList.add('delete-success');
                            document.body.appendChild(notification);
                            setTimeout(function() {
                                notification.classList.add('message-right');
                            setTimeout(function() {
                                notification.parentNode.removeChild(notification);
                            }, 1000);
                        }, 1500);
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
                var notification = document.createElement('div');
                notification.textContent = 'URL已复制';
                notification.classList.add('copy-success');
                document.body.appendChild(notification);
                setTimeout(function() {
                    notification.classList.add('message-right');
                setTimeout(function() {
                    notification.parentNode.removeChild(notification);
                }, 1000);
            }, 1500);
            }, function(err) {
                alert('复制失败: ' + err);
            });
        }
    /*平滑滚动到顶部*/
      const button = document.querySelector('#scroll-to-top');
      button.addEventListener('click', () => {
        const scrollTop = window.scrollY;
        const scrollStep = Math.PI / (500 / 15);
        const cosParameter = scrollTop / 2;
        let scrollCount = 0;
        let scrollMargin;
        const scrollInterval = setInterval(() => {
          if (window.scrollY != 0) {
            scrollCount = scrollCount + 1;
            scrollMargin = cosParameter - cosParameter * Math.cos(scrollCount * scrollStep);
            window.scrollTo(0, (scrollTop - scrollMargin));
          } else clearInterval(scrollInterval);
        }, 15);
      });
      // 分页
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

        bindImageActions();
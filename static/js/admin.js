function deleteImage(id, path) {
    var existingConfirm = document.querySelector('.custom-confirm');
    if (existingConfirm) {
        existingConfirm.parentNode.removeChild(existingConfirm);
    }

    var customConfirm = document.createElement('div');
    customConfirm.className = 'custom-confirm';
    customConfirm.innerHTML = `
        <div class="confirm-message">确定删除这张图片吗？</div>
        <div class="confirm-buttons">
        <div class="button-container">
            <button id="confirm-delete" class="confirm-button confirm-button-yes">确认</button>
            <button id="cancel-delete" class="confirm-button confirm-button-no">取消</button>
        </div>
        </div>
    `;
    document.body.appendChild(customConfirm);

    document.getElementById('confirm-delete').addEventListener('click', function confirmDeleteHandler() {
        customConfirm.classList.add('fade-out');
        setTimeout(function() {
            customConfirm.parentNode.removeChild(customConfirm);
        }, 500); // 动画持续时间

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
                    notification.classList.add('delete-success');
                    notification.textContent = response.message;
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

        // 移除事件监听器以避免重复绑定
        document.getElementById('confirm-delete').removeEventListener('click', confirmDeleteHandler);
        document.getElementById('cancel-delete').removeEventListener('click', cancelDeleteHandler);
    });

    document.getElementById('cancel-delete').addEventListener('click', function cancelDeleteHandler() {
        customConfirm.classList.add('fade-out');
        setTimeout(function() {
            customConfirm.parentNode.removeChild(customConfirm);
        }, 500); // 动画持续时间

        // 移除事件监听器以避免重复绑定
        document.getElementById('confirm-delete').removeEventListener('click', confirmDeleteHandler);
        document.getElementById('cancel-delete').removeEventListener('click', cancelDeleteHandler);
    });
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

const button = document.querySelector('#scroll-to-top');
button.addEventListener('click', () => {
    const scrollTop = window.scrollY;
    const scrollStep = Math.PI / (500 / 15);
    const cosParameter = scrollTop / 2;
    let scrollCount = 0;
    let scrollMargin;

    if ('scrollBehavior' in document.documentElement.style) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        return;
    }

    const scrollInterval = setInterval(() => {
        if (window.scrollY !== 0) {
            scrollCount++;
            scrollMargin = cosParameter - cosParameter * Math.cos(scrollCount * scrollStep);
            window.scrollTo(0, (scrollTop - scrollMargin));
        } else {
            clearInterval(scrollInterval);
        }
    }, 15);
});

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

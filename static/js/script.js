const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');
const qualityInput = document.getElementById('qualityInput');
const qualityOutput = document.getElementById('qualityOutput');
const progressBar = document.getElementById('progressBar');
const progressContainer = document.getElementById('progressContainer');
const uploadButton = document.getElementById('uploadButton');
const urlOutput = document.getElementById('urlOutput');
const imageUrl = document.getElementById('imageUrl');
const originalWidth = document.getElementById('originalWidth');
const originalHeight = document.getElementById('originalHeight');
const originalSize = document.getElementById('originalSize');
const compressedWidth = document.getElementById('compressedWidth');
const compressedHeight = document.getElementById('compressedHeight');
const compressedSize = document.getElementById('compressedSize');
const pasteOrUrlInput = document.getElementById('pasteOrUrlInput');
const token = '1c17b11693cb5ec63859b091c5b9c1b2';
const deleteImageButton = document.getElementById('deleteImageButton');
const deleteButtonWrapper = document.getElementById('deleteButtonWrapper');

qualityInput.addEventListener('input', () => {
    qualityOutput.textContent = qualityInput.value;
});

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    handleFile(file);
});

pasteOrUrlInput.addEventListener('paste', (event) => {
    const items = (event.clipboardData || window.clipboardData).items;
    for (let i = 0; i < items.length; i++) {
        if (items[i].kind === 'file') {
            const file = items[i].getAsFile();
            handleFile(file);
        }
    }
});

pasteOrUrlInput.addEventListener('input', () => {
    const url = pasteOrUrlInput.value;
    if (url) {
        const img = new Image();
        img.crossOrigin = "Anonymous";
        img.onload = () => {
            imagePreview.src = url;
            imagePreview.style.display = 'block';
            originalWidth.textContent = img.width;
            originalHeight.textContent = img.height;
            fetch(url).then(response => response.blob()).then(blob => {
                originalSize.textContent = (blob.size / 1024).toFixed(2);
                uploadImage(blob);
            });
        };
        img.onerror = () => {
            alert("无法加载图片，请检查URL是否正确");
        };
        img.src = url;
    }
});

function handleFile(file) {
    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            imagePreview.src = reader.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        originalWidth.textContent = '';
        originalHeight.textContent = '';
        originalSize.textContent = (file.size / 1024).toFixed(2);
        const img = new Image();
        img.onload = () => {
            originalWidth.textContent = img.width;
            originalHeight.textContent = img.height;
        };
        img.src = URL.createObjectURL(file);
        uploadImage(file);
    } else {
        imagePreview.src = '';
        imagePreview.style.display = 'none';
    }
}

function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('quality', qualityInput.value);
    formData.append('token', token);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php', true);
    xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
            const percentComplete = (event.loaded / event.total) * 100;
            progressBar.style.width = percentComplete + '%';
            progressBar.textContent = percentComplete.toFixed(0) + '%';
            progressContainer.style.display = 'block';
        }
    });
    xhr.onreadystatechange = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.url) {
                    imageUrl.value = response.url;
                    let imageName = response.url.split('/').pop();
                    if (imageName.includes('?')) {
                        imageName = imageName.split('?')[0];
                    }
                    if (response.width && response.height && response.size) {
                        compressedWidth.textContent = response.width;
                        compressedHeight.textContent = response.height;
                        compressedSize.textContent = (response.size / 1024).toFixed(2);
                        document.getElementById('htmlUrl').value = `<img src="${response.url}" alt="${imageName}">`;
                        document.getElementById('markdownUrl').value = `![${imageName}](${response.url})`;
                        document.getElementById('markdownLinkUrl').value = `[![${imageName}](${response.url})](${response.url})`;
                        deleteImageButton.style.display = 'block';
                        urlOutput.style.display = 'block';
                    } else {
                        alert("缺少压缩图片的尺寸或大小信息");
                    }
                } else if (response.error) {
                    alert(response.error);
                }
            } else {
                alert('上传失败，请重试。');
            }
            setTimeout(() => {
                progressContainer.style.display = 'none';
                progressBar.style.width = '0%';
                progressBar.textContent = '';
            }, 300);
        }
    };
    xhr.send(formData);
}

document.getElementById('deleteImageButton').addEventListener('click', function(event) {
    event.stopPropagation();
    const imageUrlValue = imageUrl.value;
    if (imageUrlValue) {
        const pathToDelete = getPathFromUrl(imageUrlValue);
        fetch('./admin/del.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `path=${encodeURIComponent(pathToDelete)}`,
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`网络响应错误, 状态码: ${response.status}`);
            }
            return response.json();
        })
        .then((data) => {
                if (data.result === 'success') {
                    var notification = document.createElement('div');
                    notification.textContent = data.message;
                    notification.classList.add('delete-success');
                    document.body.appendChild(notification);
                    setTimeout(function() {
                        notification.classList.add('message-right');
                        setTimeout(function() {
                            notification.parentNode.removeChild(notification);
                        }, 1000);
                    }, 1500);
                    document.getElementById('imagePreview').src = 'static/svg/up.svg';
                    document.getElementById('deleteImageButton').style.display = 'none';
                } else {
                    var notification = document.createElement('div');
                    notification.textContent = data.message;
                    notification.classList.add('delete-success');
                    document.body.appendChild(notification);
                    setTimeout(function() {
                        notification.classList.add('message-right');
                        setTimeout(function() {
                            notification.parentNode.removeChild(notification);
                        }, 1000);
                    }, 1500);
                }
            })
        .catch((error) => {
            console.error('请求删除过程中出现问题:', error);
            alert('删除过程中发生错误，请重试。');
        });
    } else {
        alert('没有图片路径信息，删除操作无法执行');
    }
});

function getPathFromUrl(url) {
    const urlObj = new URL(url);
    let path = urlObj.pathname.substring(1);
    if (path.startsWith('/')) {
        path = path.substring(1);
    }
    return path;
}

document.getElementById("urlOutput").addEventListener("click", function(e) {
    if (e.target.classList.contains("copy-indicator")) {
        var copyText = e.target;
        copyText.select();
        document.execCommand("copy");
        var notification = document.createElement('div');
        notification.textContent = '图片复制成功';
        notification.classList.add('copied-message');
        document.body.appendChild(notification);
        setTimeout(function() {
            notification.classList.add('message-right');
            setTimeout(function() {
                notification.parentNode.removeChild(notification);
            }, 1000);
        }, 1500);
    }
});
});
});

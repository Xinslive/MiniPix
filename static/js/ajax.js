document.addEventListener('DOMContentLoaded', function() {
    Fancybox.bind('[data-fancybox="gallery"]', {
        Toolbar: {
            display: {
                left: ["infobar"],
                middle: [
                    "rotateCCW",
                    "rotateCW",
                    "flipX",
                    "flipY",
                ],
                right: ["thumbs", "close"],
            },
        },
        loop: true,
        protect: true,
        arrows: true,
        buttons: [
            'slideShow',
            'fullScreen',
            'thumbs',
            'close'
        ],
    });

    loadPage(1);

    document.getElementById('pagination').addEventListener('click', function(event) {
        if (event.target.classList.contains('page-link')) {
            event.preventDefault();
            const page = event.target.getAttribute('data-page');
            loadPage(page);
        }
    });

    document.getElementById('scroll-to-top').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', function() {
        const scrollToTop = document.getElementById('scroll-to-top');
        if (window.scrollY > 100) {
            scrollToTop.style.display = 'block';
        } else {
            scrollToTop.style.display = 'none';
        }
    });
});

function loadPage(page) {
    const gallery = document.getElementById('gallery');
    const pagination = document.getElementById('pagination');
    const loadingIndicator = document.getElementById('loading-indicator');

    gallery.innerHTML = '';
    pagination.innerHTML = '';
    loadingIndicator.style.display = 'block';
    gallery.style.display = 'none';

    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'index.php?page=' + page, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            let response = { images: [], pagination: '' };
            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                loadingIndicator.style.display = 'none';
                return;
            }
            if (response.images.length > 0) {
                response.images.forEach(image => {
                    const imageContainer = document.createElement('div');
                    imageContainer.classList.add('gallery-item');
                    imageContainer.id = `image-${image.id}`;
                    const imageLink = document.createElement('a');
                    imageLink.href = image.url;
                    imageLink.setAttribute('data-fancybox', 'gallery');
                    const img = document.createElement('img');
                    img.src = image.url;
                    img.alt = image.srcName;
                    imageLink.appendChild(img);

                    const deleteButton = document.createElement('button');
                    deleteButton.className = 'delete-btn';
                    deleteButton.setAttribute('data-id', image.id);
                    deleteButton.setAttribute('data-path', image.srcName);
                    const deleteIcon = document.createElement('img');
                    deleteIcon.src = '/static/svg/xmark.svg';
                    deleteIcon.alt = 'X';
                    deleteButton.appendChild(deleteIcon);

                    const copyButton = document.createElement('button');
                    copyButton.className = 'copy-btn';
                    copyButton.setAttribute('data-url', image.url);
                    const copyIcon = document.createElement('img');
                    copyIcon.src = '/static/svg/link.svg';
                    copyIcon.alt = 'Copy';
                    copyButton.appendChild(copyIcon);

                    imageContainer.appendChild(imageLink);
                    imageContainer.appendChild(deleteButton);
                    imageContainer.appendChild(copyButton);
                    gallery.appendChild(imageContainer);
                });
                setTimeout(() => {
                    gallery.style.display = 'block';
                    pagination.innerHTML = response.pagination;
                    Fancybox.bind('[data-fancybox="gallery"]');
                    bindImageActions();
                    loadingIndicator.style.display = 'none';
                }, 100);
            } else {
                gallery.style.display = 'none';
                loadingIndicator.style.display = 'none';
            }
        }
    };
    xhr.send();
}

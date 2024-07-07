document.addEventListener('DOMContentLoaded', function() {
    loadPage(1);

    document.getElementById('pagination').addEventListener('click', function(event) {
        if (event.target.classList.contains('page-link')) {
            event.preventDefault();
            const page = event.target.getAttribute('data-page');
            loadPage(page);
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
            const response = JSON.parse(xhr.responseText);
            if (response.images.length > 0) {
                response.images.forEach(image => {
                    const imageContainer = document.createElement('div');
                    imageContainer.classList.add('gallery-item');
                    imageContainer.id = `image-${image.id}`;
                    imageContainer.innerHTML = `
                        <a href="${image.url}" class="glightbox">
                            <img src="${image.url}" alt="Image">
                        </a>
                        <button class="delete-btn" data-id="${image.id}" data-path="${image.path}"><img src="/static/svg/xmark.svg" alt="X" /></button>
                        <button class="copy-btn" data-url="${image.url}"><img  src="/static/svg/link.svg" alt="Copy" /></button>
                    `;
                    gallery.appendChild(imageContainer);
                });
                setTimeout(() => {
                    gallery.style.display = 'block';
                    pagination.innerHTML = response.pagination;
                    lightbox.reload();
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

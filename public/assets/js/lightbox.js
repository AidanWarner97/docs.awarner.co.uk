// Click-to-enlarge lightbox for images inside rendered Markdown content, with
// a thumbnail strip and prev/next navigation when a page has multiple images.
(function () {
    var overlay = document.getElementById('lightbox-overlay');
    var overlayImg = document.getElementById('lightbox-image');
    var strip = document.getElementById('lightbox-strip');
    var prevBtn = document.getElementById('lightbox-prev');
    var nextBtn = document.getElementById('lightbox-next');
    if (!overlay || !overlayImg || !strip || !prevBtn || !nextBtn) {
        return;
    }

    var images = [];
    var currentIndex = -1;

    function show(index) {
        if (images.length === 0) {
            return;
        }
        currentIndex = (index + images.length) % images.length;
        overlayImg.src = images[currentIndex].src;
        overlayImg.alt = images[currentIndex].alt || '';

        var thumbs = strip.querySelectorAll('img');
        for (var i = 0; i < thumbs.length; i++) {
            thumbs[i].classList.toggle('active', i === currentIndex);
        }
        if (thumbs[currentIndex]) {
            thumbs[currentIndex].scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    }

    function buildStrip() {
        strip.innerHTML = '';
        var showNav = images.length > 1;
        prevBtn.hidden = !showNav;
        nextBtn.hidden = !showNav;
        if (!showNav) {
            return;
        }

        images.forEach(function (img, i) {
            var thumb = document.createElement('img');
            thumb.src = img.src;
            thumb.alt = img.alt || '';
            thumb.addEventListener('click', function (event) {
                event.stopPropagation();
                show(i);
            });
            strip.appendChild(thumb);
        });
    }

    function open(clickedImg) {
        images = Array.prototype.slice.call(document.querySelectorAll('.markdown-body img'));
        buildStrip();
        show(images.indexOf(clickedImg));
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.hidden = true;
        overlayImg.src = '';
        strip.innerHTML = '';
        images = [];
        currentIndex = -1;
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target.tagName === 'IMG' && target.closest('.markdown-body')) {
            open(target);
        } else if (target === overlay || target === overlayImg) {
            close();
        }
    });

    prevBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        show(currentIndex - 1);
    });

    nextBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        show(currentIndex + 1);
    });

    document.addEventListener('keydown', function (event) {
        if (overlay.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowLeft') {
            show(currentIndex - 1);
        } else if (event.key === 'ArrowRight') {
            show(currentIndex + 1);
        }
    });
})();

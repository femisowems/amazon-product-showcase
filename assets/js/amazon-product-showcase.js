// Amazon Product Showcase — Read more / Read less for long descriptions.
// Vanilla JS, event delegation, no dependencies. State lives in the DOM:
// the rest of the description starts `hidden`; aria-expanded tracks it.
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.aps__readmore') : null;
    if (!btn) { return; }
    var wrap = btn.closest('.aps__desc');
    if (!wrap) { return; }
    var rest = wrap.querySelector('.aps__desc-rest');
    if (!rest) { return; }
    var expand = rest.hidden;
    rest.hidden = !expand;
    btn.setAttribute('aria-expanded', expand ? 'true' : 'false');
    btn.textContent = expand ? 'Read less' : 'Read more';
});

// Image enlarge: clicking a product image opens it in a lightbox overlay.
// Vanilla JS, event delegation, no dependencies. Esc / backdrop / close
// button dismiss it; focus returns to the image button afterwards.
(function () {
    var lb = null, lastFocus = null;
    function ensure() {
        if (lb) { return lb; }
        lb = document.createElement('div');
        lb.className = 'aps__lightbox';
        lb.hidden = true;
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.setAttribute('aria-label', 'Enlarged product image');
        lb.innerHTML = '<button type="button" class="aps__lb-close" aria-label="Close enlarged image">&times;</button><img alt="">';
        document.body.appendChild(lb);
        return lb;
    }
    function open(img) {
        var box = ensure();
        var big = box.querySelector('img');
        big.src = img.currentSrc || img.src;
        big.alt = img.alt || '';
        box.hidden = false;
        document.body.style.overflow = 'hidden';
        lastFocus = document.activeElement;
        box.querySelector('.aps__lb-close').focus();
    }
    function close() {
        if (!lb || lb.hidden) { return; }
        lb.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    document.addEventListener('click', function (e) {
        var z = e.target.closest ? e.target.closest('[data-aps-zoom]') : null;
        if (z) {
            var img = z.querySelector('img');
            if (img) { open(img); }
            return;
        }
        if (lb && !lb.hidden && (e.target === lb || (e.target.closest && e.target.closest('.aps__lb-close')))) { close(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(); }
    });
})();

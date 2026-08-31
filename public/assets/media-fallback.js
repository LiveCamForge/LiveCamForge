document.querySelectorAll('img[data-fallback-src]').forEach((image) => {
    const retryWithProxy = () => {
        if (image.dataset.fallbackTried === '1' || !image.dataset.fallbackSrc) return;
        image.dataset.fallbackTried = '1';
        image.src = image.dataset.fallbackSrc;
    };

    image.addEventListener('error', retryWithProxy);
    if (image.complete && image.naturalWidth === 0) retryWithProxy();
});

document.querySelectorAll('[data-live-player]').forEach((player) => {
    const frame = player.querySelector('[data-player-frame]');
    const primaryVideo = player.querySelector('[data-player-video]');
    const fallbackVideo = player.querySelector('[data-player-fallback-video]');
    const fallback = player.querySelector('[data-player-fallback]');
    const timeout = Number.parseInt(player.dataset.timeout || '8000', 10);
    const fallbackTimeout = Number.parseInt(player.dataset.fallbackTimeout || player.dataset.timeout || '8000', 10);

    let state = 'loading';
    let timer = null;
    let hls = null;

    const clearTimer = () => {
        if (timer !== null) window.clearTimeout(timer);
        timer = null;
    };

    const stopVideo = (video) => {
        if (!video) return;
        video.pause();
        video.removeAttribute('src');
        video.load();
    };

    const destroyHls = () => {
        if (!hls) return;
        hls.destroy();
        hls = null;
    };

    const showFinalFallback = () => {
        if (state === 'ready' || state === 'failed') return;
        state = 'failed';
        clearTimer();
        window.removeEventListener('message', handleFrameMessage);
        player.classList.add('player-failed');
        if (frame) frame.removeAttribute('src');
        destroyHls();
        stopVideo(primaryVideo);
        stopVideo(fallbackVideo);
        if (fallback) fallback.hidden = false;
    };

    const showPlayer = (activePlayer) => {
        if (state === 'ready' || state === 'failed') return;
        state = 'ready';
        clearTimer();
        window.removeEventListener('message', handleFrameMessage);
        if (frame && activePlayer !== frame) frame.hidden = true;
        if (primaryVideo && activePlayer !== primaryVideo) primaryVideo.hidden = true;
        if (fallbackVideo && activePlayer !== fallbackVideo) fallbackVideo.hidden = true;
        player.classList.add('player-ready');
    };

    const startHls = (video, source, failureHandler) => {
        const beginPlayback = () => {
            const promise = video.play();
            if (promise && typeof promise.catch === 'function') promise.catch(() => {});
        };

        video.addEventListener('loadeddata', () => showPlayer(video), { once: true });
        video.addEventListener('playing', () => showPlayer(video), { once: true });
        video.addEventListener('error', failureHandler, { once: true });

        if (window.Hls && window.Hls.isSupported()) {
            destroyHls();
            hls = new window.Hls({ enableWorker: true, lowLatencyMode: true });
            hls.on(window.Hls.Events.MANIFEST_PARSED, beginPlayback);
            hls.on(window.Hls.Events.ERROR, (_event, data) => {
                if (data?.fatal) failureHandler();
            });
            hls.loadSource(source);
            hls.attachMedia(video);
            return;
        }

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source;
            video.addEventListener('loadedmetadata', beginPlayback, { once: true });
            return;
        }

        failureHandler();
    };

    const startHlsFallback = () => {
        if (state === 'fallback' || state === 'ready' || state === 'failed') return;
        if (!fallbackVideo?.dataset.src) {
            showFinalFallback();
            return;
        }

        state = 'fallback';
        clearTimer();
        window.removeEventListener('message', handleFrameMessage);
        if (frame) {
            frame.removeAttribute('src');
            frame.hidden = true;
        }
        stopVideo(primaryVideo);
        if (primaryVideo) primaryVideo.hidden = true;
        fallbackVideo.hidden = false;
        player.classList.add('player-using-fallback');
        timer = window.setTimeout(showFinalFallback, Math.max(2000, fallbackTimeout));
        startHls(fallbackVideo, fallbackVideo.dataset.src, showFinalFallback);
    };

    function handleFrameMessage(event) {
        if (!frame || event.source !== frame.contentWindow) return;
        if (event.data?.source === 'livecamforge-player' && event.data.status === 'failed') {
            startHlsFallback();
            return;
        }
        showPlayer(frame);
    }

    if (primaryVideo?.dataset.src) {
        timer = window.setTimeout(showFinalFallback, Math.max(2000, timeout));
        startHls(primaryVideo, primaryVideo.dataset.src, showFinalFallback);
        return;
    }

    if (!frame?.dataset.src) {
        startHlsFallback();
        return;
    }

    // An iframe load is not enough to prove that a cross-origin player works.
    // The local provider wrapper confirms readiness with postMessage.
    window.addEventListener('message', handleFrameMessage);
    timer = window.setTimeout(startHlsFallback, Math.max(2000, timeout));
    frame.addEventListener('load', () => {
        if (state === 'loading') player.classList.add('player-loaded');
    }, { once: true });
    frame.addEventListener('error', startHlsFallback, { once: true });
    frame.src = frame.dataset.src;
});

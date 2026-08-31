<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($performer['display_name']) ?></title>
    <style>
        html, body { width:100%;height:100%;margin:0;overflow:hidden;background:#0b0b13;color:#fff; }
        body > iframe { display:block;width:100% !important;height:100% !important;border:0; }
        #object_container { position:absolute;inset:0;width:100%;height:100%;min-height:100%; }
        #object_container > * { width:100% !important;height:100% !important;max-width:none !important;max-height:none !important; }
        #object_container iframe { width:100% !important;height:100% !important;max-width:none !important;max-height:none !important;border:0; }
    </style>
</head>
<body>
<?php if ($resolvedPlayer->mode === \LiveCamForge\Providers\ProviderPlayer::MODE_SCRIPT): ?>
    <div id="object_container" style="width:100%;height:100%"></div>
    <script>
        (() => {
            const container = document.getElementById('object_container');
            const widgetSrc = <?= json_encode(
                $resolvedPlayer->url,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
            ) ?>;
            const fillPlayer = () => {
                if (!container) return;

                const fill = (element) => {
                    element.style.setProperty('width', '100%', 'important');
                    element.style.setProperty('height', '100%', 'important');
                    element.style.setProperty('max-width', 'none', 'important');
                    element.style.setProperty('max-height', 'none', 'important');
                };

                Array.from(container.children).forEach(fill);
                container.querySelectorAll('iframe').forEach((frame) => {
                    frame.removeAttribute('width');
                    frame.removeAttribute('height');
                    fill(frame);

                    let wrapper = frame.parentElement;
                    while (wrapper && wrapper !== container) {
                        fill(wrapper);
                        wrapper = wrapper.parentElement;
                    }
                });
            };
            const notifyReady = () => {
                if (!container || container.childElementCount === 0) return;
                fillPlayer();
                window.parent.postMessage({source:'livecamforge-player',status:'ready'}, '*');
            };
            const observer = new MutationObserver(notifyReady);
            observer.observe(container, {childList:true,subtree:true});
            window.addEventListener('resize', fillPlayer);
            window.addEventListener('load', notifyReady, {once:true});
            [0, 250, 1000, 2500].forEach((delay) => window.setTimeout(fillPlayer, delay));
            window.setTimeout(() => observer.disconnect(), 5000);

            let previousSize = '';
            let stableFrames = 0;
            let attempts = 0;
            const startWidgetWhenSized = () => {
                const rect = container.getBoundingClientRect();
                const currentSize = `${Math.round(rect.width)}x${Math.round(rect.height)}`;
                stableFrames = currentSize === previousSize && rect.width > 0 && rect.height > 0
                    ? stableFrames + 1
                    : 0;
                previousSize = currentSize;
                attempts += 1;

                if (stableFrames < 2 && attempts < 60) {
                    window.requestAnimationFrame(startWidgetWhenSized);
                    return;
                }

                fillPlayer();
                const widgetScript = document.createElement('script');
                widgetScript.src = widgetSrc;
                widgetScript.async = true;
                widgetScript.onerror = () => window.parent.postMessage(
                    {source:'livecamforge-player',status:'failed'},
                    '*'
                );
                document.body.appendChild(widgetScript);
            };

            if (document.readyState === 'complete') {
                window.requestAnimationFrame(startWidgetWhenSized);
            } else {
                window.addEventListener(
                    'load',
                    () => window.requestAnimationFrame(startWidgetWhenSized),
                    {once:true}
                );
            }
        })();
    </script>
<?php else: ?>
    <iframe
        src="<?= e($resolvedPlayer->url) ?>"
        title="<?= e($performer['display_name']) ?>"
        allow="autoplay; fullscreen"
        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="strict-origin-when-cross-origin"
        onload="window.parent.postMessage({source:'livecamforge-player',status:'ready'},'*')"
        onerror="window.parent.postMessage({source:'livecamforge-player',status:'failed'},'*')"
    ></iframe>
<?php endif; ?>
</body>
</html>

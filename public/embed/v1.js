/**
 * Funnel-Builder Embed v1 -- siehe docs/funnel-builder/embed-schnittstelle.md.
 *
 * <script src="https://…/embed/v1.js" data-funnel="01J8Z…" async></script>
 *
 * v1 wird nur erweitert, nie veraendert: Fremde Seiten tragen diese URL in
 * ihrem Quelltext, wir bekommen sie dort nie wieder heraus. Unvertraegliches
 * bekommt eine v2 unter eigener URL.
 */
(function () {
    'use strict';

    var SOURCE = 'widileads-funnel';
    var VERSION = 1;
    var script = document.currentScript;

    if (!script) {
        return;
    }

    var token = script.getAttribute('data-funnel');

    if (!token) {
        return; // Ohne Token nichts zu tun -- und still, damit die fremde Seite nicht stoert.
    }

    var attr = function (name, fallback) {
        return script.getAttribute(name) || fallback;
    };

    var mode = attr('data-mode', 'inline') === 'overlay' ? 'overlay' : 'inline';
    var minHeight = parseInt(attr('data-height', '600'), 10);
    var base = attr('data-base', script.src.replace(/\/embed\/v\d+\.js.*$/, ''));
    var frameOrigin = base.replace(/^(https?:\/\/[^/]+).*$/, '$1');

    function createFrame(height) {
        var frame = document.createElement('iframe');

        frame.src = base + '/f/' + encodeURIComponent(token)
            + '?embed=1&origin=' + encodeURIComponent(window.location.origin);
        frame.title = attr('data-title', 'Funnel');
        frame.loading = 'lazy';
        frame.style.cssText = 'width:100%;border:0;display:block;transition:height 150ms ease-out;height:'
            + height + 'px';

        return frame;
    }

    function inline() {
        var frame = createFrame(minHeight);
        script.parentNode.insertBefore(frame, script.nextSibling);

        return { frame: frame, hide: null };
    }

    function overlay() {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = attr('data-button-label', 'Jetzt starten');
        button.style.cssText = 'min-height:44px;cursor:pointer';

        var backdrop = document.createElement('div');
        backdrop.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.6);'
            + 'display:none;align-items:center;justify-content:center;padding:16px';

        var shell = document.createElement('div');
        shell.style.cssText = 'position:relative;width:100%;max-width:640px;max-height:90vh;'
            + 'overflow:auto;background:#fff;border-radius:12px';

        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', attr('data-close-label', 'Schliessen'));
        close.textContent = '×';
        close.style.cssText = 'position:absolute;top:8px;right:8px;min-width:44px;min-height:44px;'
            + 'font-size:24px;line-height:1;background:transparent;border:0;cursor:pointer';

        var frame = createFrame(Math.min(minHeight, Math.round(window.innerHeight * 0.9)));

        shell.appendChild(close);
        shell.appendChild(frame);
        backdrop.appendChild(shell);
        document.body.appendChild(backdrop);
        script.parentNode.insertBefore(button, script.nextSibling);

        function hide() {
            backdrop.style.display = 'none';
            document.documentElement.style.overflow = '';
        }

        button.addEventListener('click', function () {
            backdrop.style.display = 'flex';
            document.documentElement.style.overflow = 'hidden';
        });
        close.addEventListener('click', hide);
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) {
                hide();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hide();
            }
        });

        return { frame: frame, hide: hide };
    }

    var embed = mode === 'overlay' ? overlay() : inline();

    window.addEventListener('message', function (event) {
        var data = event.data;

        // Nur aus dem eigenen iFrame und nur mit unserer Kennung.
        if (event.origin !== frameOrigin || event.source !== embed.frame.contentWindow) {
            return;
        }

        if (!data || data.source !== SOURCE || data.version !== VERSION || data.token !== token) {
            return;
        }

        if (data.type === 'resize' && typeof data.height === 'number') {
            embed.frame.style.height = Math.max(data.height, 120) + 'px';
        }

        if (data.type === 'close' && embed.hide) {
            embed.hide();
        }

        // Weiterreichen, damit die einbettende Seite ihr eigenes Tracking anhaengen kann.
        document.dispatchEvent(new CustomEvent('funnel:' + data.type, { detail: data }));
    });
})();

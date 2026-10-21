/*
 * Angelma admin scripts: confirmation dialogs, slug filling, Markdown preview,
 * image picker and the custom scheme preview. Plain JavaScript, no libraries.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Forms with data-confirm ask before submitting (every delete button).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Selects with data-autosubmit submit their form when changed.
    document.addEventListener('change', function (event) {
        var el = event.target;
        if (el.hasAttribute && el.hasAttribute('data-autosubmit') && el.form) {
            el.form.submit();
        }
    });

    // Slug filling: an input with data-slug-target fills that field from its text,
    // until the slug field is edited by hand.
    var slugMap = {
        'æ': 'ae', 'ø': 'o', 'å': 'a', 'ä': 'a', 'ö': 'o', 'ü': 'u', 'ß': 'ss', 'é': 'e', 'è': 'e',
        'ê': 'e', 'ë': 'e', 'á': 'a', 'à': 'a', 'â': 'a', 'í': 'i', 'ì': 'i', 'î': 'i', 'ï': 'i',
        'ó': 'o', 'ò': 'o', 'ô': 'o', 'õ': 'o', 'ú': 'u', 'ù': 'u', 'û': 'u', 'ý': 'y', 'ñ': 'n',
        'ç': 'c', 'ð': 'd', 'þ': 'th', 'œ': 'oe'
    };

    function slugify(text) {
        return text.toLowerCase()
            .replace(/[^\u0000-\u007f]/g, function (ch) { return slugMap[ch] || ch; })
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80)
            .replace(/-+$/, '');
    }

    document.querySelectorAll('[data-slug-target]').forEach(function (source) {
        var target = document.getElementById(source.getAttribute('data-slug-target'));
        if (!target) {
            return;
        }
        var manual = target.value !== '' && target.value !== slugify(source.value);
        target.addEventListener('input', function () {
            manual = target.value !== '';
        });
        source.addEventListener('input', function () {
            if (!manual) {
                target.value = slugify(source.value);
            }
        });
    });

    // Live Markdown preview: a textarea with data-preview-target posts its text to
    // /admin/preview 500 ms after the last keystroke and shows the result.
    document.querySelectorAll('textarea[data-preview-target]').forEach(function (area) {
        var target = document.getElementById(area.getAttribute('data-preview-target'));
        var timer = null;
        var sequence = 0;
        if (!target) {
            return;
        }
        area.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var mine = ++sequence;
                var body = new FormData();
                body.append('markdown', area.value);
                body.append('_csrf', csrfToken());
                fetch('/admin/preview', { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (response) {
                        return response.ok ? response.text() : Promise.reject(response.status);
                    })
                    .then(function (html) {
                        if (mine === sequence) {
                            target.innerHTML = html;
                        }
                    })
                    .catch(function () { /* keep the last preview */ });
            }, 500);
        });
    });

    window.Angelma = { csrfToken: csrfToken, slugify: slugify };
}());

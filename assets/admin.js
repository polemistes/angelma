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

    window.Angelma = { csrfToken: csrfToken, slugify: slugify };
}());

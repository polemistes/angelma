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

    // The site's subfolder ('' at the web root), for addresses built in JavaScript.
    function basePath() {
        var meta = document.querySelector('meta[name="base-path"]');
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
                fetch(basePath() + '/admin/preview', { method: 'POST', body: body, credentials: 'same-origin' })
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

    // Image picker in the image box form: search reloads the list from
    // /admin/images/picker; choosing an image updates the "current" preview.
    document.querySelectorAll('[data-picker]').forEach(function (picker) {
        var list = picker.querySelector('[data-picker-list]');
        var search = picker.querySelector('[data-picker-search]');
        var preview = picker.querySelector('[data-picker-preview]');
        var name = picker.querySelector('[data-picker-name]');
        var field = picker.getAttribute('data-picker-field') || 'image_id';
        var selected = '';
        var timer = null;
        var checked = picker.querySelector('input[name="' + field + '"]:checked');
        if (checked) {
            selected = checked.value;
        }

        picker.addEventListener('change', function (event) {
            var radio = event.target;
            if (radio.name !== field) {
                return;
            }
            selected = radio.value;
            var src = radio.getAttribute('data-src');
            if (preview) {
                preview.src = src || '';
                preview.hidden = !src;
            }
            if (name) {
                name.textContent = radio.getAttribute('data-name');
                name.className = src ? '' : 'muted';
            }
        });

        if (search && list) {
            // Enter in the search field must not submit the box form.
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
            search.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    var url = picker.getAttribute('data-picker') + '?q=' + encodeURIComponent(search.value)
                        + '&selected=' + encodeURIComponent(selected) + '&field=' + encodeURIComponent(field);
                    fetch(url, { credentials: 'same-origin' })
                        .then(function (response) { return response.ok ? response.text() : Promise.reject(); })
                        .then(function (html) { list.innerHTML = html; })
                        .catch(function () { /* keep the list */ });
                }, 300);
            });
        }
    });

    // Colour schemes on the settings page: the preview box follows the chosen scheme,
    // and for "Custom" it follows the colour inputs as they change.
    document.querySelectorAll('[data-schemes]').forEach(function (container) {
        var schemes = JSON.parse(container.getAttribute('data-schemes') || '{}');
        var preview = container.querySelector('[data-scheme-preview]');
        var inputs = container.querySelectorAll('[data-color-key]');
        var copyButton = container.querySelector('[data-copy-scheme]');

        function chosen() {
            var radio = container.querySelector('input[name="scheme"]:checked');
            return radio ? radio.value : 'custom';
        }

        function customColors() {
            var colors = {};
            inputs.forEach(function (input) {
                colors[input.getAttribute('data-color-key')] = input.value;
            });
            return colors;
        }

        function update() {
            var id = chosen();
            var colors = id === 'custom' ? customColors() : schemes[id];
            if (!colors || !preview) {
                return;
            }
            Object.keys(colors).forEach(function (key) {
                preview.style.setProperty('--' + key.replace(/_/g, '-'), colors[key]);
            });
            var custom = customColors();
            container.querySelectorAll('[data-custom-swatch]').forEach(function (swatch) {
                swatch.style.background = custom[swatch.getAttribute('data-custom-swatch')];
            });
        }

        container.addEventListener('input', update);
        container.addEventListener('change', update);

        if (copyButton) {
            copyButton.addEventListener('click', function (event) {
                var id = chosen();
                var source = schemes[id];
                event.preventDefault();
                if (source) {
                    inputs.forEach(function (input) {
                        input.value = source[input.getAttribute('data-color-key')];
                    });
                }
                var customRadio = container.querySelector('input[name="scheme"][value="custom"]');
                if (customRadio) {
                    customRadio.checked = true;
                }
                update();
            });
        }
    });

    window.Angelma = { csrfToken: csrfToken, slugify: slugify };
}());

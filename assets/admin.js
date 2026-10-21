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

    window.Angelma = { csrfToken: csrfToken };
}());

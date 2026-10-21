// Installer: check that URL rewriting works by requesting a path that only the
// front controller answers.
document.addEventListener('DOMContentLoaded', function () {
    var item = document.getElementById('rewrite-check');
    if (!item) {
        return;
    }
    function show(ok) {
        item.className = ok ? 'ok' : 'warn';
        item.textContent = item.getAttribute(ok ? 'data-ok' : 'data-fail');
    }
    fetch('rewrite-check', { cache: 'no-store' })
        .then(function (response) {
            return response.ok ? response.text() : '';
        })
        .then(function (text) {
            show(text.trim() === 'ok');
        })
        .catch(function () {
            show(false);
        });
});

/*
 * Angelma site script: the "Menu" button on narrow screens (PLAN.md section 6.1).
 * It shows the button and hides the page list until the button is pressed. Without
 * this script the list is simply shown, so nothing depends on it.
 */
(function () {
    'use strict';

    var nav = document.querySelector('.site-menu');
    var button = nav ? nav.querySelector('.menu-button') : null;
    if (!nav || !button) {
        return;
    }
    nav.classList.add('has-js');
    button.hidden = false;
    button.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}());

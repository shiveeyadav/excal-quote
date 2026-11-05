/**
 * Excal_Quote: "Download PDF / Print" button (CSP-safe, no inline handlers).
 */
define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function () {
            window.print();
        });
    };
});

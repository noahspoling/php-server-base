/*
 * Font Awesome CDN fallback.
 *
 * The vendored copy in /static/font-awesome is the primary source. A browser
 * will not retry a stylesheet that failed to load, so this checks whether the
 * Font Awesome rules actually applied and pulls the CDN copy if they did not.
 *
 * The integrity hash is the SHA-384 of the vendored file, which came from this
 * same cdnjs URL, so the two are byte-identical. If cdnjs ever serves something
 * else the browser refuses it rather than running unverified third-party CSS.
 */
(function () {
    'use strict';

    var CDN = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css';
    var INTEGRITY = 'sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN';

    /*
     * Reads the computed font-family of a throwaway .fa element. When the
     * stylesheet is present this is FontAwesome; when it failed the element
     * inherits whatever the page's body font is.
     */
    function fontAwesomeApplied() {
        var probe = document.createElement('i');

        probe.className = 'fa';
        probe.style.position = 'absolute';
        probe.style.left = '-9999px';
        probe.style.visibility = 'hidden';

        document.body.appendChild(probe);

        var family = window.getComputedStyle(probe).fontFamily || '';

        document.body.removeChild(probe);

        return family.toLowerCase().indexOf('fontawesome') !== -1;
    }

    function loadFromCdn() {
        var link = document.createElement('link');

        link.rel = 'stylesheet';
        link.href = CDN;
        link.integrity = INTEGRITY;
        link.crossOrigin = 'anonymous';

        document.head.appendChild(link);
    }

    function check() {
        if (!fontAwesomeApplied()) {
            loadFromCdn();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', check);
    } else {
        check();
    }
})();

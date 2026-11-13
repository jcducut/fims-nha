<?php
/**
 * Sub-folder deployment shim (e.g. https://host/fims-nha/).
 *
 * Loaded through PHP's auto_prepend_file from nginx, which also sets the
 * FIMS_SUBPATH fastcgi param (e.g. "/fims-nha"). The app hardcodes root-based
 * paths ("/login", "/api/...", "/app/fims-css/..."); this prefixes them in
 * the HTML/JS/CSS output and in Location redirect headers so the app works
 * under a sub-folder without editing every page.
 *
 * Does nothing when FIMS_SUBPATH is not set (normal root/Docker deployment).
 */
(function () {
    $prefix = $_SERVER['FIMS_SUBPATH'] ?? '';
    if ($prefix === '' || PHP_SAPI === 'cli') {
        return;
    }
    $prefix = '/' . trim($prefix, '/');
    $already = substr($prefix, 1) . '/';

    // Redirects: Location: /xyz  ->  Location: /fims-nha/xyz
    header_register_callback(function () use ($prefix) {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') === 0) {
                $v = trim(substr($h, 9));
                if (isset($v[0]) && $v[0] === '/' && substr($v, 0, 2) !== '//'
                    && strpos($v, $prefix . '/') !== 0 && $v !== $prefix) {
                    header('Location: ' . $prefix . $v, true);
                }
            }
        }
    });

    // Body: only rewrite text-ish responses.
    ob_start(function ($buf) use ($prefix, $already) {
        $type = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $type = strtolower($h);
            }
        }
        if ($type !== '' && !preg_match('~text/html|text/css|javascript~', $type)) {
            return $buf; // JSON, PDF, images, downloads, ...
        }
        // "/word, '/word, `/word  (not "//", not already prefixed).
        // Skips regex literals like /'/g or /"/gi: quote preceded by "/" or
        // followed by only regex flags. Also skips string fragments that are
        // concatenated onto a base (apiBase + '/chart-of-accounts'):
        // the base already carries the prefix (API.FIMS_URL=/fims-nha/api in .env).
        $buf = preg_replace(
            '~(?<![/+])(?<!\+ )(?<!\+  )(["\'`])/(?=[A-Za-z_])(?![dgimsuvy]{1,3}(?![\w\-./?#]))(?!' . preg_quote($already, '~') . ')~',
            '$1' . $prefix . '/',
            $buf
        );
        // JSON-escaped form: "\/word"  (json_encode escapes slashes)
        $buf = preg_replace(
            '~(["\'])\\\\/(?=[A-Za-z_])(?!' . preg_quote(substr($prefix, 1), '~') . '\\\\\\\\/)~',
            '$1\\\\/' . substr($prefix, 1) . '\\\\/',
            $buf
        );
        // css url(/word) / url("/word")
        $buf = preg_replace(
            '~url\(\s*(["\']?)/(?=[A-Za-z_])(?!' . preg_quote($already, '~') . ')~',
            'url($1' . $prefix . '/',
            $buf
        );
        // Safety net: collapse any accidental double prefix (plain or escaped).
        $name = preg_quote(substr($prefix, 1), '~');
        $buf = preg_replace('~/' . $name . '(?:/' . $name . ')+/~', $prefix . '/', $buf);
        $buf = preg_replace('~\\\\/' . $name . '(?:\\\\/' . $name . ')+\\\\/~', '\\\\/' . substr($prefix, 1) . '\\\\/', $buf);
        return $buf;
    });
})();

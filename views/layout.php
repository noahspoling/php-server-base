<?php

/**
 * @var string          $title
 * @var string          $content Already-rendered HTML from the page template.
 * @var App\View\Assets $assets
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <!--
        htmx injects a <style> element for .htmx-indicator at startup, which CSP
        blocks without 'unsafe-inline'. Turning it off here and defining the
        rules in base.css keeps the policy tight. A meta tag carries the config
        because an inline <script> would be blocked for the same reason.
    -->
    <meta name="htmx-config" content='{"includeIndicatorStyles":false}'>
    <?= $assets->head() ?>
</head>
<body>
<main>
    <?= $content ?>
</main>
</body>
</html>

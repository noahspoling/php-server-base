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
    <?= $assets->head() ?>
</head>
<body>
<main>
    <?= $content ?>
</main>
</body>
</html>

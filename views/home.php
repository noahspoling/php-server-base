<?php

/**
 * @var string $phpVersion
 */
?>
<h1>php-server-base</h1>

<p>
    A LAMP base where application logic and presentation logic are separate:
    controllers return a view name and data, and only the view layer turns that
    into HTML.
</p>

<h2>Runtime</h2>

<p>PHP <?= e($phpVersion) ?></p>

<h2>Try it</h2>

<p>
    The <a href="/users">users page</a> adds rows over htmx: the form posts to
    <code>/users</code>, and the same controller answers with just the table
    body because the request carries <code>HX-Request</code>.
</p>

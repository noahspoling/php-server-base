<?php

/**
 * @var list<array{id: int|string, name: string, email: string, created_at: string}> $users
 */
?>
<h1>Users</h1>

<p><a href="/">Back to home</a></p>

<!--
    hx-post sends the form and swaps the response over #user-rows. The server
    answers with users/rows.php alone, because the request carries HX-Request.

    The _ attribute is hyperscript. Behaviour lives here rather than in htmx's
    hx-on, which needs new Function and is blocked by this app's CSP.
-->
<form hx-post="/users"
      hx-target="#user-rows"
      hx-swap="outerHTML"
      _="on htmx:afterRequest call me.reset() then call me.querySelector('input').focus()">
    <label>
        Name
        <input name="name" required maxlength="50" autocomplete="name">
    </label>
    <label>
        Email
        <input type="email" name="email" required maxlength="100" autocomplete="email">
    </label>
    <button type="submit">Add user</button>
</form>

<table>
    <caption>Everyone in the database</caption>
    <thead>
        <tr>
            <th scope="col">ID</th>
            <th scope="col">Name</th>
            <th scope="col">Email</th>
            <th scope="col">Created</th>
        </tr>
    </thead>
    <?php require __DIR__ . '/rows.php'; ?>
</table>

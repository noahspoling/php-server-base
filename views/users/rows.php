<?php

/**
 * The htmx fragment. Rendered alone for an htmx request, and included by
 * users/index.php for a full page, so a row is described in exactly one place.
 *
 * @var list<array{id: int|string, name: string, email: string, created_at: string}> $users
 */
?>
<tbody id="user-rows">
<?php if ($users === []): ?>
    <tr>
        <td colspan="4">No users yet.</td>
    </tr>
<?php endif; ?>
<?php foreach ($users as $user): ?>
    <tr>
        <td><?= e($user['id']) ?></td>
        <td><?= e($user['name']) ?></td>
        <td><?= e($user['email']) ?></td>
        <td><?= e($user['created_at']) ?></td>
    </tr>
<?php endforeach; ?>
</tbody>

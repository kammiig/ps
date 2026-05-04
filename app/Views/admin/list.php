<section class="admin-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($createUrl)): ?><a class="btn btn-primary" href="<?= e($createUrl) ?>">Add New</a><?php endif; ?>
</section>

<section class="admin-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <?php
                            $value = $row[$key] ?? '';
                            if (str_starts_with($key, 'is_')) {
                                $value = (int) $value === 1 ? 'Yes' : 'No';
                            }
                            ?>
                            <td><?= e(strlen((string) $value) > 90 ? substr((string) $value, 0, 87) . '...' : $value) ?></td>
                        <?php endforeach; ?>
                        <td class="table-actions">
                            <?php if (!empty($editBase)): ?><a class="btn btn-light" href="<?= e(url($editBase . '/' . $row['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
                            <?php if (!empty($deleteBase)): ?>
                                <form action="<?= e(url($deleteBase . '/' . $row['id'] . '/delete')) ?>" method="post" onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($columns) + 1 ?>">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

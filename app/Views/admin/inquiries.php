<section class="admin-page-head">
    <div>
        <h1>Contact Inquiries</h1>
        <p>Messages submitted through the website contact form are stored here.</p>
    </div>
</section>

<section class="admin-card inquiry-list">
    <?php foreach ($inquiries as $inquiry): ?>
        <article class="inquiry-item">
            <div>
                <h2><?= e($inquiry['full_name']) ?></h2>
                <p><strong>Email:</strong> <a href="mailto:<?= e($inquiry['email']) ?>"><?= e($inquiry['email']) ?></a></p>
                <p><strong>Phone:</strong> <?= e($inquiry['phone']) ?></p>
                <p><strong>Service:</strong> <?= e($inquiry['service']) ?></p>
                <p><strong>Submitted:</strong> <?= e($inquiry['created_at']) ?></p>
                <p><?= nl2br(e($inquiry['message'])) ?></p>
            </div>
            <div class="inquiry-actions">
                <form action="<?= e(url('/admin/inquiries/' . $inquiry['id'] . '/status')) ?>" method="post">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <select name="status">
                        <?php foreach (['new', 'read', 'replied', 'closed'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $inquiry['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline" type="submit">Update</button>
                </form>
                <form action="<?= e(url('/admin/inquiries/' . $inquiry['id'] . '/delete')) ?>" method="post" onsubmit="return confirm('Delete this inquiry?');">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button class="btn btn-danger" type="submit">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (empty($inquiries)): ?><p>No inquiries yet.</p><?php endif; ?>
</section>

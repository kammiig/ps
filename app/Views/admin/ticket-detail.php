<section class="admin-page-head">
    <div>
        <h1><?= e($ticket['subject'] ?? 'Support ticket') ?></h1>
        <p>#<?= e($ticket['public_ref'] ?? $ticket['id']) ?> · <?= e($ticket['department'] ?? 'General Support') ?> · <?= e($ticket['customer_email'] ?? '') ?></p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/admin/tickets')) ?>">Back to Tickets</a>
</section>

<?php if (!empty($errors)): ?>
    <div class="flash error" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="admin-card ticket-summary-card">
    <div class="admin-card-head">
        <h2>Ticket Details</h2>
        <span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span>
    </div>
    <dl class="ticket-meta-grid">
        <div><dt>Customer</dt><dd><?= e(trim((string) ($ticket['customer_name'] ?? '')) ?: 'Customer') ?></dd></div>
        <div><dt>Priority</dt><dd><?= e($ticket['priority'] ?? 'Medium') ?></dd></div>
        <div><dt>Created</dt><dd><?= e(substr((string) ($ticket['created_at'] ?? ''), 0, 16)) ?></dd></div>
        <div><dt>Updated</dt><dd><?= e(substr((string) ($ticket['updated_at'] ?? ''), 0, 16)) ?></dd></div>
        <div><dt>Related item</dt><dd><?= e(($ticket['related_label'] ?? '') ?: 'Not specified') ?></dd></div>
        <div><dt>WHMCS client</dt><dd><?= e(($ticket['whmcs_client_id'] ?? '') ?: 'Not linked') ?></dd></div>
    </dl>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <h2>Conversation</h2>
    </div>
    <div class="ticket-thread">
        <?php foreach ($messages as $message): ?>
            <?php $messageId = (int) ($message['id'] ?? 0); ?>
            <article class="ticket-message <?= ($message['author_type'] ?? '') === 'admin' ? 'from-admin' : 'from-customer' ?>">
                <div class="ticket-message-head">
                    <strong><?= e($message['author_name'] ?? 'Support') ?></strong>
                    <span><?= e(substr((string) ($message['created_at'] ?? ''), 0, 16)) ?></span>
                </div>
                <p><?= nl2br(e($message['message'] ?? '')) ?></p>
                <?php if (!empty($attachmentsByMessage[$messageId])): ?>
                    <div class="ticket-attachments">
                        <?php foreach ($attachmentsByMessage[$messageId] as $attachment): ?>
                            <a href="<?= e(upload_url($attachment['stored_path'] ?? '')) ?>" target="_blank" rel="noopener"><?= e($attachment['original_name'] ?? 'Attachment') ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <h2>Reply or Update Status</h2>
    </div>
    <form class="ticket-form" action="<?= e(url('/admin/tickets/' . (int) $ticket['id'] . '/reply')) ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="ticket-form-grid">
            <label class="field">
                <span>Status</span>
                <select name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($ticket['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Attachment</span>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.doc,.docx">
            </label>
            <label class="field wide">
                <span>Reply</span>
                <textarea name="message" rows="7"></textarea>
            </label>
        </div>
        <button class="btn btn-primary" type="submit">Update Ticket</button>
    </form>
</section>

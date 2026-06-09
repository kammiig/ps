<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Support Ticket</span>
                <h1><?= e($ticket['subject'] ?? 'Support ticket') ?></h1>
                <p>#<?= e($ticket['public_ref'] ?? $ticket['id']) ?> · <?= e($ticket['department'] ?? 'General Support') ?></p>
            </div>

            <?php if (!empty($created)): ?>
                <div class="notice success" role="status">Your ticket was submitted successfully.</div>
            <?php endif; ?>
            <?php if (!empty($replySaved)): ?>
                <div class="notice success" role="status">Your reply was sent successfully.</div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="account-card ticket-summary-card">
                <div class="section-head compact">
                    <div>
                        <span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span>
                        <h2>Ticket details</h2>
                    </div>
                    <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets')) ?>">Back to Tickets</a>
                </div>
                <dl class="ticket-meta-grid">
                    <div><dt>Priority</dt><dd><?= e($ticket['priority'] ?? 'Medium') ?></dd></div>
                    <div><dt>Created</dt><dd><?= e(substr((string) ($ticket['created_at'] ?? ''), 0, 16)) ?></dd></div>
                    <div><dt>Updated</dt><dd><?= e(substr((string) ($ticket['updated_at'] ?? ''), 0, 16)) ?></dd></div>
                    <div><dt>Related item</dt><dd><?= e(($ticket['related_label'] ?? '') ?: 'Not specified') ?></dd></div>
                </dl>
            </section>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Conversation</span>
                        <h2>Messages</h2>
                    </div>
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

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Reply</span>
                        <h2>Add a reply</h2>
                    </div>
                </div>
                <form class="ticket-form" action="<?= e(url('/account/tickets/' . (int) $ticket['id'] . '/reply')) ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <label class="field">
                        <span>Message</span>
                        <textarea name="message" rows="6" required></textarea>
                    </label>
                    <label class="field">
                        <span>Attachment</span>
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.doc,.docx">
                    </label>
                    <button class="btn btn-primary" type="submit">Send Reply <?= icon('arrow') ?></button>
                </form>
            </section>
        </main>
    </div>
</section>

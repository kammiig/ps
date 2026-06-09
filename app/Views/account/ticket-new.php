<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Support Tickets</span>
                <h1>Open a ticket</h1>
                <p>Send a support request to Planetic Support from your customer account.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Request details</span>
                        <h2>Tell us what happened</h2>
                    </div>
                    <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets')) ?>">Back to Tickets</a>
                </div>

                <?php if (!empty($old['related_label'])): ?>
                    <div class="dns-help-card">
                        <strong>Related item</strong>
                        <p><?= e($old['related_label']) ?></p>
                    </div>
                <?php endif; ?>

                <form class="ticket-form" action="<?= e(url('/account/tickets/new')) ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="related_type" value="<?= e($old['related_type'] ?? '') ?>">
                    <input type="hidden" name="related_label" value="<?= e($old['related_label'] ?? '') ?>">
                    <input type="hidden" name="related_reference" value="<?= e($old['related_reference'] ?? '') ?>">

                    <div class="ticket-form-grid">
                        <label class="field">
                            <span>Department</span>
                            <select name="department">
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= e($department) ?>" <?= ($old['department'] ?? '') === $department ? 'selected' : '' ?>><?= e($department) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            <span>Priority</span>
                            <select name="priority">
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= e($priority) ?>" <?= ($old['priority'] ?? '') === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field wide">
                            <span>Subject</span>
                            <input type="text" name="subject" value="<?= e($old['subject'] ?? '') ?>" maxlength="190" required>
                        </label>

                        <label class="field wide">
                            <span>Message</span>
                            <textarea name="message" rows="8" required><?= e($old['message'] ?? '') ?></textarea>
                        </label>

                        <label class="field wide">
                            <span>Attachment</span>
                            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.doc,.docx">
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Submit Ticket <?= icon('arrow') ?></button>
                        <a class="btn btn-outline" href="<?= e(url('/account/tickets')) ?>">Cancel</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</section>

<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Support Tickets</span>
                <h1>Support tickets</h1>
                <p>Open a ticket for domains, hosting, website development, billing, or general account help.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="account-stat-grid">
                <article><span>Open tickets</span><strong><?= e($openTicketCount ?? 0) ?></strong></article>
                <article><span>Total tickets</span><strong><?= e(count($tickets ?? [])) ?></strong></article>
                <article><span>Departments</span><strong>5</strong></article>
                <article><span>Channel</span><strong>Local</strong></article>
            </div>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Your requests</span>
                        <h2>Ticket history</h2>
                    </div>
                    <a class="btn btn-primary btn-small" href="<?= e(url('/account/tickets/new')) ?>">Open Ticket <?= icon('arrow') ?></a>
                </div>

                <?php if (!empty($tickets)): ?>
                    <div class="account-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <a class="account-row ticket-row-link" href="<?= e(url('/account/tickets/' . (int) $ticket['id'])) ?>">
                                <div>
                                    <strong><?= e($ticket['subject'] ?? 'Support ticket') ?></strong>
                                    <span>#<?= e($ticket['public_ref'] ?? $ticket['id']) ?> · <?= e($ticket['department'] ?? 'General Support') ?> · <?= e((string) ($ticket['message_count'] ?? 0)) ?> messages</span>
                                </div>
                                <div class="account-row-actions">
                                    <span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span>
                                    <span><?= e(substr((string) ($ticket['updated_at'] ?? ''), 0, 10) ?: 'Recently') ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-account-state">
                        <h2>No support tickets yet</h2>
                        <p>When you need help, open a ticket and replies from Planetic Support will appear here.</p>
                        <a class="btn btn-primary" href="<?= e(url('/account/tickets/new')) ?>">Open Ticket <?= icon('arrow') ?></a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

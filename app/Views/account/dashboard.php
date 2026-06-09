<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">My account</span>
                <h1>Welcome, <?= e($account['first_name']) ?></h1>
                <p>Manage your Planetic Solutions purchases, invoices and project updates from one place.</p>
                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new')) ?>">Open Ticket</a>
            </div>

            <div class="account-stat-grid">
                <article><span>Domains</span><strong><?= e($domainCount) ?></strong></article>
                <article><span>Hosting</span><strong><?= e($hostingCount) ?></strong></article>
                <article><span>Website Development</span><strong><?= e($websiteProjectCount) ?></strong></article>
                <article><span>Open tickets</span><strong><?= e($openTicketCount ?? 0) ?></strong></article>
                <article><span>Unpaid invoices</span><strong><?= e($unpaidCount) ?></strong></article>
            </div>

            <div class="account-grid two">
                <section class="account-card">
                    <div class="section-head compact">
                        <div><span class="section-kicker">Recent activity</span><h2>Account updates</h2></div>
                    </div>
                    <?php if ($recentActivity): ?>
                        <div class="account-list">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="account-row">
                                    <div><strong><?= e($activity['label'] ?? 'Account update') ?></strong><span><?= e($activity['detail'] ?? '') ?></span></div>
                                    <span><?= e(substr((string) ($activity['date'] ?? ''), 0, 10) ?: 'Recently') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No recent activity yet.</p>
                    <?php endif; ?>
                </section>

                <section class="account-card">
                    <div class="section-head compact">
                        <div><span class="section-kicker">Recent invoices</span><h2>Billing</h2></div>
                        <a class="text-link" href="<?= e(url('/account/billing')) ?>">View billing <?= icon('arrow') ?></a>
                    </div>
                    <?php if ($invoices): ?>
                        <div class="account-list">
                            <?php foreach (array_slice($invoices, 0, 4) as $invoice): ?>
                                <div class="account-row">
                                    <div><strong>Invoice #<?= e($invoice['id'] ?? $invoice['invoiceid'] ?? '') ?></strong><span><?= e($invoice['date'] ?? '') ?></span></div>
                                    <div><strong><?= e(money($invoice['total'] ?? $invoice['balance'] ?? '0.00')) ?></strong><span class="status-pill"><?= e($invoice['status'] ?? 'Pending') ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No invoices are available yet.</p>
                    <?php endif; ?>
                </section>

                <section class="account-card">
                    <div class="section-head compact">
                        <div><span class="section-kicker">Support</span><h2>Tickets</h2></div>
                        <a class="text-link" href="<?= e(url('/account/tickets')) ?>">View tickets <?= icon('arrow') ?></a>
                    </div>
                    <?php if (!empty($recentTickets)): ?>
                        <div class="account-list">
                            <?php foreach ($recentTickets as $ticket): ?>
                                <a class="account-row ticket-row-link" href="<?= e(url('/account/tickets/' . (int) $ticket['id'])) ?>">
                                    <div><strong><?= e($ticket['subject'] ?? 'Support ticket') ?></strong><span>#<?= e($ticket['public_ref'] ?? $ticket['id']) ?></span></div>
                                    <span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No support tickets yet.</p>
                        <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new')) ?>">Open Ticket</a>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</section>

<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">My account</span>
                <h1>Welcome, <?= e($account['first_name']) ?></h1>
                <p>Manage your Planetic Solutions purchases, invoices and project updates from one place.</p>
            </div>

            <div class="account-stat-grid">
                <article><span>Domains</span><strong><?= e($domainCount) ?></strong></article>
                <article><span>Hosting</span><strong><?= e($hostingCount) ?></strong></article>
                <article><span>Website Development</span><strong><?= e($websiteProjectCount) ?></strong></article>
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
            </div>
        </main>
    </div>
</section>

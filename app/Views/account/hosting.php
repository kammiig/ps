<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Hosting</span>
                <h1>Your hosting</h1>
                <p>View hosting packages, connected domains, renewal details and setup progress.</p>
            </div>

            <section class="account-card">
                <?php if ($hosting): ?>
                    <div class="service-account-grid account-detail-grid">
                        <?php foreach ($hosting as $service): ?>
                            <article>
                                <span class="status-pill"><?= e($service['hosting_status'] ?? 'Setup in Progress') ?></span>
                                <h3><?= e($service['package_name'] ?? 'Hosting Package') ?></h3>
                                <p><?= e($service['connected_domain'] ?? 'No domain connected yet') ?></p>
                                <?php if (!empty($service['setup_issue'])): ?>
                                    <p class="account-friendly-note"><?= e($service['setup_issue']) ?></p>
                                <?php endif; ?>
                                <dl>
                                    <div><dt>Billing cycle</dt><dd><?= e($service['billing_cycle'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Start date</dt><dd><?= e($service['start_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Next due date</dt><dd><?= e($service['next_due_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Renewal amount</dt><dd><?= e($service['renewal_amount'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Server status</dt><dd><?= e($service['server_status'] ?? 'Setup in Progress') ?></dd></div>
                                </dl>
                                <?php if (!empty($service['cpanel_url'])): ?>
                                    <a class="btn btn-outline btn-small" href="<?= e($service['cpanel_url']) ?>" rel="noopener">Open cPanel</a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-account-state">
                        <h2>No hosting yet</h2>
                        <p>Hosting purchased through Planetic Solutions will appear here after checkout.</p>
                        <a class="btn btn-primary" href="<?= e(url('/hosting')) ?>">View Hosting Plans <?= icon('arrow') ?></a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

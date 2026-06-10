<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Domains</span>
                <h1>Your domains</h1>
                <p>Track registrations, renewals and nameservers for domains purchased through Planetic Solutions.</p>
                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Domain%20Support&subject=Domain%20support%20request')) ?>">Open Ticket</a>
            </div>

            <section class="account-card">
                <?php if ($domains): ?>
                    <div class="service-account-grid account-detail-grid">
                        <?php foreach ($domains as $domain): ?>
                            <article>
                                <span class="status-pill"><?= e($domain['registration_status'] ?? 'Processing') ?></span>
                                <h3><?= e($domain['domain_name'] ?? 'Domain') ?></h3>
                                <?php if (!empty($domain['setup_issue'])): ?>
                                    <p class="account-friendly-note"><?= e($domain['setup_issue']) ?></p>
                                <?php endif; ?>
                                <dl>
                                    <div><dt>Registration date</dt><dd><?= e($domain['registration_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Expiry date</dt><dd><?= e($domain['expiry_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Renewal date</dt><dd><?= e($domain['renewal_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Renewal amount</dt><dd><?= e($domain['renewal_amount'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Setup status</dt><dd><?= e($domain['registrar_status'] ?? 'Processing') ?></dd></div>
                                    <div><dt>Cloudflare DNS</dt><dd><?= e($domain['cloudflare_status'] ?: 'Pending') ?></dd></div>
                                </dl>
                                <div class="nameserver-list">
                                    <strong>Nameservers</strong>
                                    <?php if (!empty($domain['nameservers'])): ?>
                                        <?php foreach ($domain['nameservers'] as $nameserver): ?>
                                            <span><?= e($nameserver) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span>Not available yet</span>
                                    <?php endif; ?>
                                </div>
                                <div class="account-row-actions">
                                    <a class="btn btn-outline btn-small" href="<?= e($domain['dns_url'] ?? url('/account/dns')) ?>">Manage DNS</a>
                                    <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Domain%20Support&subject=' . rawurlencode('Help with ' . (string) ($domain['domain_name'] ?? 'domain')) . '&related_type=domain&related_label=' . rawurlencode((string) ($domain['domain_name'] ?? 'Domain')))) ?>">Open Ticket</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-account-state">
                        <h2>No domains yet</h2>
                        <p>Domains purchased through Planetic Solutions will appear here after checkout.</p>
                        <a class="btn btn-primary" href="<?= e(url('/domains')) ?>">Search Domains <?= icon('arrow') ?></a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Services</span>
                <h1>Your services</h1>
                <p>View hosting packages and domains connected to your Planetic Solutions account.</p>
            </div>

            <section class="account-card">
                <h2>Hosting and website services</h2>
                <?php if ($services): ?>
                    <div class="service-account-grid">
                        <?php foreach ($services as $service): ?>
                            <article>
                                <span class="status-pill"><?= e($service['friendly_status'] ?? $service['status'] ?? 'Processing') ?></span>
                                <h3><?= e($service['name'] ?? $service['productname'] ?? 'Service') ?></h3>
                                <p><?= e($service['domain'] ?? 'No domain connected yet') ?></p>
                                <dl>
                                    <div><dt>Billing cycle</dt><dd><?= e($service['billingcycle'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Start date</dt><dd><?= e($service['regdate'] ?? $service['registrationdate'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Next due date</dt><dd><?= e($service['nextduedate'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Amount due</dt><dd><?= e(money($service['recurringamount'] ?? $service['amount'] ?? '0.00')) ?></dd></div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No active services are linked to this account yet.</p>
                <?php endif; ?>
            </section>

            <section class="account-card">
                <h2>Domains</h2>
                <?php if ($domains): ?>
                    <div class="account-list">
                        <?php foreach ($domains as $domain): ?>
                            <div class="account-row">
                                <div><strong><?= e($domain['domainname'] ?? $domain['domain'] ?? 'Domain') ?></strong><span>Renewal: <?= e($domain['nextduedate'] ?? 'Not available') ?></span></div>
                                <div><span class="status-pill"><?= e($domain['status'] ?? 'Processing') ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No domain records are available yet.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

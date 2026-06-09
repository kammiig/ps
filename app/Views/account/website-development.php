<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Website Development</span>
                <h1>Website development</h1>
                <p>Follow purchased website packages, onboarding and project progress.</p>
                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Website%20Development&subject=Website%20development%20support%20request')) ?>">Open Ticket</a>
            </div>

            <section class="account-card">
                <?php if ($projects): ?>
                    <div class="service-account-grid account-detail-grid">
                        <?php foreach ($projects as $project): ?>
                            <article>
                                <span class="status-pill"><?= e($project['project_status'] ?? 'Payment Pending') ?></span>
                                <h3><?= e($project['package_name'] ?? 'Website Development') ?></h3>
                                <?php if (!empty($project['customer_note'])): ?>
                                    <p class="account-friendly-note"><?= e($project['customer_note']) ?></p>
                                <?php endif; ?>
                                <dl>
                                    <div><dt>Order reference</dt><dd>#<?= e($project['whmcs_invoice_id'] ?? '') ?></dd></div>
                                    <div><dt>Purchase date</dt><dd><?= e($project['purchase_date'] ?? 'Not available') ?></dd></div>
                                    <div><dt>Payment status</dt><dd><?= e($project['payment_status'] ?? 'Payment Pending') ?></dd></div>
                                    <div><dt>Included domain</dt><dd><?= e(($project['domain_name'] ?? '') ?: 'Not selected') ?></dd></div>
                                    <div><dt>Included hosting</dt><dd><?= e(($project['hosting_plan_slug'] ?? '') ? ucwords(str_replace('-', ' ', (string) $project['hosting_plan_slug'])) : 'Not selected') ?></dd></div>
                                    <div><dt>Onboarding</dt><dd><?= e($project['onboarding_status'] ?? 'Awaiting Client Details') ?></dd></div>
                                    <div><dt>Next step</dt><dd><?= e($project['estimated_next_step'] ?? 'Our team will contact you shortly.') ?></dd></div>
                                    <?php if (!empty($project['completed_at'])): ?>
                                        <div><dt>Completion date</dt><dd><?= e(substr((string) $project['completed_at'], 0, 10)) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Website%20Development&subject=' . rawurlencode('Help with ' . (string) ($project['package_name'] ?? 'website project')) . '&related_type=website_project&related_label=' . rawurlencode((string) ($project['package_name'] ?? 'Website Development')) . '&related_reference=' . rawurlencode((string) ($project['id'] ?? '')))) ?>">Open Ticket</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-account-state">
                        <h2>You have not purchased a website development package yet.</h2>
                        <p>Your website package and project progress will appear here after checkout.</p>
                        <a class="btn btn-primary" href="<?= e(url('/website-development')) ?>">View Website Packages <?= icon('arrow') ?></a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

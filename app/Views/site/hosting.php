<section class="page-hero">
    <div class="container page-hero-grid">
        <div>
            <span class="section-kicker">Hosting plans</span>
            <h1>Hosting Plans Built for Business Websites, WordPress and Resellers</h1>
            <p>Choose a cPanel, WordPress or reseller hosting plan and continue to checkout through your connected WHMCS billing flow.</p>
            <div class="actions">
                <a class="btn btn-primary" href="#plans">Compare Plans <?= icon('arrow') ?></a>
                <a class="btn btn-outline" href="<?= e($whmcs->clientAreaUrl()) ?>">Client Login</a>
            </div>
        </div>
        <aside class="page-hero-card">
            <span class="mini-label">Included essentials</span>
            <ul class="feature-list">
                <li><?= icon('check') ?> Free SSL</li>
                <li><?= icon('check') ?> cPanel access</li>
                <li><?= icon('check') ?> WordPress installer</li>
                <li><?= icon('check') ?> Cloudflare CDN support</li>
            </ul>
        </aside>
    </div>
</section>

<section class="section" id="plans">
    <div class="container">
        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <div id="<?= e($plan['plan_type'] === 'reseller' ? 'reseller-hosting' : $plan['slug']) ?>">
                    <?php require APP_PATH . '/Views/partials/plan-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section muted">
    <div class="container split">
        <div>
            <span class="section-kicker">Included with plans</span>
            <h2>cPanel, SSL, installer support and Cloudflare-ready performance</h2>
            <p>Every plan can be edited in the admin dashboard, including price, storage, bandwidth, email accounts, WHMCS checkout URL, featured badge and button text.</p>
        </div>
        <div class="included-panel">
            <ul class="feature-list columns">
                <li><?= icon('check') ?> Free SSL certificates</li>
                <li><?= icon('check') ?> cPanel management</li>
                <li><?= icon('check') ?> WordPress installer</li>
                <li><?= icon('check') ?> Free Cloudflare CDN setup</li>
                <li><?= icon('check') ?> WHMCS order links</li>
                <li><?= icon('check') ?> Upgrade-friendly structure</li>
            </ul>
        </div>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

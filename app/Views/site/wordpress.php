<section class="page-hero">
    <div class="container narrow">
        <span class="section-kicker">WordPress hosting</span>
        <h1>Fast WordPress Hosting with SSL, cPanel and Cloudflare CDN</h1>
        <p>Launch WordPress with one-click installer support, LiteSpeed/cache-ready wording, Elementor-friendly resources and an optional £200 website development add-on.</p>
        <div class="actions">
            <a class="btn btn-primary" href="<?= e(url('/hosting')) ?>">View WordPress Plans <?= icon('arrow') ?></a>
            <a class="btn btn-outline" href="<?= e(url('/website-development')) ?>">Get Website for £200</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container feature-grid">
        <?php foreach ([['Fast WordPress hosting', 'Performance-minded setup for business sites, portfolios and landing pages.', 'bolt'], ['Free SSL', 'HTTPS helps customers trust your website from day one.', 'shield'], ['cPanel included', 'Manage files, databases, emails and installs in a familiar panel.', 'panel'], ['WordPress auto installer', 'Get WordPress online without manual database setup.', 'check'], ['LiteSpeed/cache-ready', 'Built for cache plugins and fast delivery through modern hosting stacks.', 'cloud'], ['Elementor-friendly', 'A practical fit for Elementor websites and page builder workflows.', 'code']] as $feature): ?>
            <article>
                <div class="icon-pill"><?= icon($feature[2]) ?></div>
                <h3><?= e($feature[0]) ?></h3>
                <p><?= e($feature[1]) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($plans)): ?>
<section class="section muted">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Plans</span>
                <h2>WordPress hosting options</h2>
            </div>
        </div>
        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <?php require APP_PATH . '/Views/partials/plan-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section website-offer">
    <div class="container split">
        <div>
            <span class="section-kicker">Website add-on</span>
            <h2>Need the website built too?</h2>
            <p class="lead">Order a complete business website for £200 with hosting setup, domain registration support, basic SEO setup, contact forms, responsive design and Cloudflare CDN.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('/website-development')) ?>">View Website Package <?= icon('arrow') ?></a>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

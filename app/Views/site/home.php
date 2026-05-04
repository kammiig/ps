<?php
$heroTitle = $settings['home_hero_title'] ?? 'Fast, Secure & Affordable Web Hosting for Your Business';
$heroSubtitle = $settings['home_hero_subtitle'] ?? 'Planetic Solutions provides WHMCS-connected reseller hosting, WordPress/cPanel hosting, domain registration and complete business websites delivered with clean setup and support.';
$primaryText = $settings['home_primary_cta_text'] ?? 'Search Domain';
$primaryUrl = $settings['home_primary_cta_url'] ?? '#domain-search';
$secondaryText = $settings['home_secondary_cta_text'] ?? 'View Hosting Plans';
$secondaryUrl = $settings['home_secondary_cta_url'] ?? url('/hosting');
$websiteText = $settings['home_website_cta_text'] ?? 'Get Website for £200';
$websiteUrl = $settings['home_website_cta_url'] ?? url('/website-development');
$packageFeatures = json_decode($package['features_json'] ?? '[]', true) ?: [];
$packageOrderUrl = !empty($package['inquiry_mode']) ? url('/contact') : (($package['cta_url'] ?? '') ?: url('/website-development'));
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="section-kicker">Premium hosting, domains and websites</span>
            <h1><?= e($heroTitle) ?></h1>
            <p><?= e($heroSubtitle) ?></p>
            <div id="domain-search" class="hero-search">
                <?php require APP_PATH . '/Views/partials/domain-search.php'; ?>
                <div class="tld-strip">
                    <?php foreach (array_slice($tlds, 0, 5) as $tld): ?>
                        <span><?= e($tld['extension']) ?> <strong><?= e($tld['price']) ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= e($primaryUrl) ?>"><?= e($primaryText) ?> <?= icon('arrow') ?></a>
                <a class="btn btn-outline" href="<?= e($secondaryUrl) ?>"><?= e($secondaryText) ?></a>
                <a class="btn btn-light" href="<?= e($websiteUrl) ?>"><?= e($websiteText) ?></a>
            </div>
        </div>
        <div class="hero-visual" aria-label="Hosting platform overview">
            <div class="server-stack">
                <div class="server-row"><span></span><span></span><strong>cPanel</strong></div>
                <div class="server-row"><span></span><span></span><strong>WHMCS</strong></div>
                <div class="server-row"><span></span><span></span><strong>SSL</strong></div>
            </div>
            <div class="metric-card metric-one"><strong>99.9%</strong><span>Uptime-ready setup</span></div>
            <div class="metric-card metric-two"><strong>48h</strong><span>Website delivery</span></div>
            <div class="orbit-card"><?= icon('cloud') ?> Cloudflare CDN</div>
        </div>
    </div>
</section>

<section class="trust-band">
    <div class="container trust-grid">
        <?php foreach ($trustBadges as $badge): ?>
            <div>
                <strong><?= e($badge[0]) ?></strong>
                <span><?= e($badge[1]) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Services</span>
                <h2>Everything your business needs to launch and grow online</h2>
            </div>
            <a class="text-link" href="<?= e(url('/hosting')) ?>">Compare plans <?= icon('arrow') ?></a>
        </div>
        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <article class="service-card">
                    <div class="icon-pill"><?= icon($service[2] ?? 'check') ?></div>
                    <h3><?= e($service[0]) ?></h3>
                    <p><?= e($service[1]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section muted">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Hosting Plans</span>
                <h2>Start small, scale into reseller hosting when you are ready</h2>
            </div>
            <a class="text-link" href="<?= e(url('/hosting')) ?>">View all hosting <?= icon('arrow') ?></a>
        </div>
        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <?php require APP_PATH . '/Views/partials/plan-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section feature-band">
    <div class="container feature-grid">
        <?php foreach ($featureSections as $feature): ?>
            <article>
                <div class="icon-pill"><?= icon($feature[2] ?? 'check') ?></div>
                <h3><?= e($feature[0]) ?></h3>
                <p><?= e($feature[1]) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section website-offer">
    <div class="container split">
        <div>
            <span class="section-kicker">Website development offer</span>
            <h2><?= e($package['title'] ?? 'Complete Website in Just £200') ?></h2>
            <p class="lead">Get a complete business website for just £200, including hosting setup, domain registration support, premium design tools where legally licensed, Envato templates/assets where legally licensed, stock images, basic content writing, SEO setup and free Cloudflare CDN. Delivery in just 48 hours.</p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= e($packageOrderUrl) ?>"><?= e(($package['cta_text'] ?? '') ?: 'Order Website Package') ?> <?= icon('arrow') ?></a>
                <a class="btn btn-outline" href="<?= e(url('/website-development')) ?>">See what is included</a>
            </div>
        </div>
        <div class="included-panel">
            <h3>Included in the package</h3>
            <ul class="feature-list columns">
                <?php foreach ($packageFeatures as $feature): ?><li><?= icon('check') ?> <?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<?php if (!empty($testimonials)): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Testimonials</span>
                <h2>Placeholder client stories ready for your real reviews</h2>
            </div>
        </div>
        <div class="testimonial-grid">
            <?php foreach ($testimonials as $testimonial): ?>
                <article class="testimonial-card">
                    <p>&ldquo;<?= e($testimonial['quote']) ?>&rdquo;</p>
                    <div>
                        <strong><?= e($testimonial['name']) ?></strong>
                        <span><?= e(trim(($testimonial['role'] ?? '') . ' ' . ($testimonial['company'] ?? ''))) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

<?php
$heroTitle = $settings['home_hero_title'] ?? 'Fast, Secure & Affordable Web Hosting for Your Business';
$heroSubtitle = $settings['home_hero_subtitle'] ?? 'Planetic Solutions provides reliable hosting, domain registration and complete business websites with WHMCS-powered billing, cPanel access and Cloudflare CDN support.';
$primaryText = $settings['home_primary_cta_text'] ?? 'Search Domain';
$primaryUrl = $settings['home_primary_cta_url'] ?? '#domain-search';
$secondaryText = $settings['home_secondary_cta_text'] ?? 'View Hosting Plans';
$secondaryUrl = $settings['home_secondary_cta_url'] ?? url('/hosting');
$websiteText = $settings['home_website_cta_text'] ?? 'Get Website for £200';
$websiteUrl = $settings['home_website_cta_url'] ?? url('/website-development');
$packageOrderUrl = !empty($package['inquiry_mode']) ? url('/contact') : (($package['cta_url'] ?? '') ?: url('/website-development'));
$websiteChecklist = [
    'Professional business website',
    'Free hosting setup',
    'Domain registration support',
    'Elementor/page builder setup where legally licensed',
    'Envato templates/assets where legally licensed',
    'Stock images',
    'Basic content writing',
    'Basic SEO setup',
    'Cloudflare CDN',
    'Delivery in 48 hours',
];
?>
<section class="hero hero-premium">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="section-kicker">Premium Hosting, Domains &amp; Websites</span>
            <h1><?= e($heroTitle) ?></h1>
            <p class="hero-lede"><?= e($heroSubtitle) ?></p>
            <div id="domain-search" class="hero-search">
                <?php require APP_PATH . '/Views/partials/domain-search.php'; ?>
                <div class="tld-strip">
                    <?php foreach (array_slice($tlds, 0, 4) as $tld): ?>
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
        <div class="hosting-visual" aria-label="Planetic hosting platform visual">
            <div class="hero-console">
                <div class="console-dashboard">
                    <div class="console-topbar">
                        <div class="window-dots"><span></span><span></span><span></span></div>
                        <strong>Planetic Cloud Console</strong>
                    </div>
                    <div class="console-status">
                        <div>
                            <span class="mini-label">Domain + Hosting</span>
                            <strong>planeticsolution.com</strong>
                        </div>
                        <em>Live</em>
                    </div>
                    <div class="console-stack">
                        <div class="console-row">
                            <span class="console-icon"><?= icon('panel') ?></span>
                            <div><strong>cPanel</strong><small>&mdash; Website, email and files</small></div>
                        </div>
                        <div class="console-row">
                            <span class="console-icon"><?= icon('shield') ?></span>
                            <div><strong>SSL Active</strong><small>&mdash; HTTPS ready</small></div>
                        </div>
                        <div class="console-row">
                            <span class="console-icon"><?= icon('cloud') ?></span>
                            <div><strong>Cloudflare CDN</strong><small>&mdash; Speed and protection</small></div>
                        </div>
                        <div class="console-row">
                            <span class="console-icon"><?= icon('globe') ?></span>
                            <div><strong>WHMCS Billing</strong><small>&mdash; Orders and invoices</small></div>
                        </div>
                    </div>
                    <div class="console-meter">
                        <div class="server-title"><strong>Hosting stack</strong><small>Optimised for business</small></div>
                        <div class="server-bars"><span></span><span></span><span></span></div>
                    </div>
                </div>
                <div class="floating-card uptime-card"><strong>99.9%</strong><span>Uptime-ready setup</span></div>
                <div class="floating-card delivery-card"><strong>48h</strong><span>Website delivery</span></div>
                <div class="floating-card cdn-card"><?= icon('cloud') ?><span>Cloudflare CDN</span></div>
            </div>
        </div>
    </div>
</section>

<section class="trust-band">
    <div class="container trust-grid">
        <?php foreach ($trustBadges as $badge): ?>
            <div>
                <span class="trust-icon"><?= icon('check') ?></span>
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
                <?php
                $serviceUrl = $service[3] ?: '/hosting';
                $serviceHref = str_starts_with($serviceUrl, 'http') ? $serviceUrl : url($serviceUrl);
                ?>
                <article class="service-card">
                    <div class="icon-pill"><?= icon($service[2] ?? 'check') ?></div>
                    <h3><?= e($service[0]) ?></h3>
                    <p><?= e($service[1]) ?></p>
                    <a class="card-link" href="<?= e($serviceHref) ?>">Explore service <?= icon('arrow') ?></a>
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

<section class="section website-offer">
    <div class="container split">
        <div>
            <span class="section-kicker">Website development offer</span>
            <h2>Complete Business Website in Just £200</h2>
            <p class="lead">Get a professional business website with hosting setup, domain registration support, premium design tools where legally licensed, Envato templates/assets where legally licensed, stock images, basic SEO setup and Cloudflare CDN. Delivery in just 48 hours.</p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= e($packageOrderUrl) ?>"><?= e(($package['cta_text'] ?? '') ?: 'Order Website Package') ?> <?= icon('arrow') ?></a>
                <a class="btn btn-outline" href="<?= e(url('/website-development')) ?>">See what is included</a>
            </div>
        </div>
        <div class="included-panel">
            <h3>Included in the package</h3>
            <ul class="feature-list columns">
                <?php foreach ($websiteChecklist as $feature): ?><li><?= icon('check') ?> <?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head centered">
            <div>
                <span class="section-kicker">Why choose us</span>
                <h2>Premium hosting essentials with a business-first support flow</h2>
            </div>
        </div>
        <div class="feature-grid">
            <?php foreach ($featureSections as $feature): ?>
                <article>
                    <div class="icon-pill"><?= icon($feature[2] ?? 'check') ?></div>
                    <h3><?= e($feature[0]) ?></h3>
                    <p><?= e($feature[1]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($testimonials)): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Testimonials</span>
                <h2>Client review placeholders ready for your real testimonials</h2>
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
<?php
$title = 'Ready to launch your website?';
$text = 'Start with hosting, search your domain, or order a complete business website package through a clean WHMCS-connected flow.';
$primaryText = 'Get Started';
$primaryUrl = $settings['default_order_url'] ?: $whmcs->cartUrl();
$secondaryText = 'Search Domain';
$secondaryUrl = '#domain-search';
?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

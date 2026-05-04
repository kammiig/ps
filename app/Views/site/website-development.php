<?php
$features = json_decode($package['features_json'] ?? '[]', true) ?: [];
$packageOrderUrl = !empty($package['inquiry_mode']) ? url('/contact') : (($package['cta_url'] ?? '') ?: url('/contact'));
?>
<section class="page-hero">
    <div class="container narrow">
        <span class="section-kicker">Website development package</span>
        <h1>Complete Business Website in Just £200</h1>
        <p>Professional business website setup with hosting support, domain registration support, mobile responsive design, Cloudflare CDN and 48 hour delivery.</p>
        <div class="actions">
            <a class="btn btn-primary" href="<?= e($packageOrderUrl) ?>"><?= e(($package['cta_text'] ?? '') ?: 'Order Now') ?> <?= icon('arrow') ?></a>
            <a class="btn btn-outline" href="<?= e(url('/contact')) ?>">Ask a Question</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container split">
        <div>
            <span class="section-kicker">What is included</span>
            <h2>Premium setup without the premium agency bill</h2>
            <p class="lead">Get a complete business website for just £200, including hosting setup, domain registration support, premium design tools where legally licensed, Envato templates/assets where legally licensed, stock images, basic content writing, SEO setup and free Cloudflare CDN. Delivery in just 48 hours.</p>
        </div>
        <div class="included-panel">
            <ul class="feature-list columns">
                <?php foreach ($features as $feature): ?><li><?= icon('check') ?> <?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section class="section muted">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Process</span>
                <h2>How it works</h2>
            </div>
        </div>
        <div class="steps-grid">
            <article><span>01</span><h3>Choose package</h3><p>Order through WHMCS or submit an inquiry with your business details.</p></article>
            <article><span>02</span><h3>Share content</h3><p>Send logo, pages, service details and any brand references you already have.</p></article>
            <article><span>03</span><h3>Build and connect</h3><p>The site is built, connected to hosting, secured with SSL and prepared for Cloudflare CDN.</p></article>
            <article><span>04</span><h3>Launch</h3><p>Review the website, request practical edits and go live with a clean business presence.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Portfolio</span>
                <h2>Demo placeholders you can replace from the CMS</h2>
            </div>
        </div>
        <div class="portfolio-grid">
            <article><div></div><h3>Local Service Website</h3><p>Responsive business site with contact form and SEO basics.</p></article>
            <article><div></div><h3>Professional Portfolio</h3><p>Elegant service pages, gallery sections and inquiry CTA.</p></article>
            <article><div></div><h3>Startup Landing Site</h3><p>Conversion-focused homepage with hosting and CDN setup.</p></article>
        </div>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

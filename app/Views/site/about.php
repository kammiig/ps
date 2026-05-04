<section class="page-hero">
    <div class="container page-hero-grid">
        <div>
            <span class="section-kicker">About Planetic Solutions</span>
            <h1><?= e($page['title'] ?? 'Hosting, Domains and Websites with Practical Support') ?></h1>
            <p>Planetic Solutions helps businesses get online with reliable hosting, domain registration, website development and digital support.</p>
        </div>
        <aside class="page-hero-card">
            <span class="mini-label">What we do</span>
            <strong>Hosting, domains and websites</strong>
            <p>Practical digital setup for businesses that need a dependable online foundation.</p>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container narrow content-body">
        <?= safe_html($page['body'] ?? '<p>Planetic Solutions provides hosting, domains, websites and digital support for businesses that need a dependable online foundation.</p>') ?>
    </div>
</section>

<section class="section muted">
    <div class="container feature-grid">
        <article><div class="icon-pill"><?= icon('cloud') ?></div><h3>Hosting</h3><p>cPanel, WordPress and reseller hosting with WHMCS-connected order flows.</p></article>
        <article><div class="icon-pill"><?= icon('globe') ?></div><h3>Domains</h3><p>Domain search and registration through a central client area and billing platform.</p></article>
        <article><div class="icon-pill"><?= icon('code') ?></div><h3>Websites</h3><p>Affordable business website packages with responsive design and SEO foundations.</p></article>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/cta.php'; ?>

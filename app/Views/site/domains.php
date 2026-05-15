<section class="page-hero">
    <div class="container page-hero-grid">
        <div>
            <span class="section-kicker">Domain registration</span>
            <h1>Search and Register Domains Through WHMCS</h1>
            <p>Use the domain search below to check availability on this website, then continue securely to WHMCS checkout for registration and billing.</p>
            <?php $id = 'domain-page-search'; require APP_PATH . '/Views/partials/domain-search.php'; ?>
        </div>
        <aside class="page-hero-card">
            <span class="mini-label">Popular choices</span>
            <div class="hero-tld-mini">
                <?php foreach (array_slice($tlds, 0, 4) as $tld): ?>
                    <span><?= e($tld['extension']) ?> <strong><?= e($tld['price']) ?></strong></span>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">Popular extensions</span>
                <h2>Manual display prices with WHMCS checkout links</h2>
            </div>
        </div>
        <div class="tld-grid">
            <?php foreach ($tlds as $tld): ?>
                <article>
                    <h3><?= e($tld['extension']) ?></h3>
                    <strong><?= e($tld['price']) ?></strong>
                    <p>Registration prices are confirmed at checkout using WHMCS rates.</p>
                    <a class="btn btn-outline" href="<?= e($tld['whmcs_url'] ?: $whmcs->domainSearchUrl()) ?>">Search <?= e($tld['extension']) ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section muted">
    <div class="container feature-grid">
        <article><div class="icon-pill"><?= icon('globe') ?></div><h3>Domain search</h3><p>Show custom availability results first, then send visitors into the WHMCS registration flow when they choose a domain.</p></article>
        <article><div class="icon-pill"><?= icon('shield') ?></div><h3>Billing handled in WHMCS</h3><p>Orders, renewals, invoices and client account management remain in the WHMCS client area.</p></article>
        <article><div class="icon-pill"><?= icon('panel') ?></div><h3>Editable TLD cards</h3><p>Update displayed extensions, fallback prices and WHMCS URLs in the admin dashboard.</p></article>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

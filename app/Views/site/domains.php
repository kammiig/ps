<section class="page-hero">
    <div class="container narrow">
        <span class="section-kicker">Domain registration</span>
        <h1>Search and Register Domains Through WHMCS</h1>
        <p>Use the domain search below to continue into the WHMCS domain checker/cart. Display prices can be edited here, while checkout and live billing rates remain controlled by WHMCS.</p>
        <?php $id = 'domain-page-search'; require APP_PATH . '/Views/partials/domain-search.php'; ?>
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
        <article><div class="icon-pill"><?= icon('globe') ?></div><h3>Domain search</h3><p>Send visitors directly into the WHMCS domain registration flow with their search pre-filled.</p></article>
        <article><div class="icon-pill"><?= icon('shield') ?></div><h3>Billing handled in WHMCS</h3><p>Orders, renewals, invoices and client account management remain in the WHMCS client area.</p></article>
        <article><div class="icon-pill"><?= icon('panel') ?></div><h3>Editable TLD cards</h3><p>Update displayed extensions, fallback prices and WHMCS URLs in the admin dashboard.</p></article>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/faqs.php'; ?>
<?php require APP_PATH . '/Views/partials/cta.php'; ?>

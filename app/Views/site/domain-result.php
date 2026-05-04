<section class="page-hero">
    <div class="container narrow">
        <span class="section-kicker">Domain result</span>
        <h1><?= e($domain) ?> is <?= !empty($result['available']) ? 'available' : 'not available' ?></h1>
        <p>Continue to WHMCS to complete registration, transfer, billing and client account setup.</p>
        <div class="actions">
            <?php if (!empty($result['available'])): ?>
                <a class="btn btn-primary" href="<?= e($registerUrl) ?>">Register Domain <?= icon('arrow') ?></a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e($transferUrl) ?>">Try Domain Transfer <?= icon('arrow') ?></a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('/domains')) ?>">Search Again</a>
        </div>
    </div>
</section>

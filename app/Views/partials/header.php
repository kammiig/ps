<?php
$clientArea = $whmcs->clientAreaUrl();
$getStarted = $settings['default_order_url'] ?: $whmcs->cartUrl();
$logo = upload_url($settings['logo_url'] ?? '');
?>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header" data-header>
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($settings['company_name'] ?? 'Planetic Solutions') ?> home">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($settings['company_name'] ?? 'Planetic Solutions') ?> logo" width="160" height="42">
            <?php else: ?>
                <span class="brand-mark">PS</span>
                <span class="brand-text"><?= e($settings['company_name'] ?? 'Planetic Solutions') ?></span>
            <?php endif; ?>
        </a>
        <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>
        <nav class="site-nav" aria-label="Main navigation" data-nav>
            <a class="<?= e(is_active('/domains')) ?>" href="<?= e(url('/domains')) ?>">Domains</a>
            <a class="<?= e(is_active('/hosting')) ?>" href="<?= e(url('/hosting')) ?>">Hosting</a>
            <a class="<?= e(is_active('/website-development')) ?>" href="<?= e(url('/website-development')) ?>">Website Development</a>
            <a class="<?= e(is_active('/blog')) ?>" href="<?= e(url('/blog')) ?>">Blog</a>
            <a class="<?= e(is_active('/contact')) ?>" href="<?= e(url('/contact')) ?>">Contact</a>
        </nav>
        <div class="nav-actions">
            <a class="btn btn-ghost" href="<?= e($clientArea) ?>">Client Login</a>
            <a class="btn btn-primary" href="<?= e($getStarted) ?>">Get Started <?= icon('arrow') ?></a>
        </div>
    </div>
</header>

<?php
$clientArea = $whmcs->clientAreaUrl();
$whatsapp = trim((string) ($settings['whatsapp_number'] ?? ''));
$whatsappUrl = $whatsapp ? 'https://wa.me/' . preg_replace('/\D+/', '', $whatsapp) : '';
?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand footer-brand" href="<?= e(url('/')) ?>">
                <span class="brand-mark">PS</span>
                <span class="brand-text"><?= e($settings['company_name'] ?? 'Planetic Solutions') ?></span>
            </a>
            <p><?= e($settings['tagline'] ?? 'Hosting, domains, website development and digital support for growing businesses.') ?></p>
            <div class="social-links">
                <?php foreach (['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn', 'x_url' => 'X'] as $key => $label): ?>
                    <?php if (!empty($settings[$key])): ?><a href="<?= e($settings[$key]) ?>"><?= e($label) ?></a><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h2>Services</h2>
            <a href="<?= e(url('/hosting')) ?>">cPanel Hosting</a>
            <a href="<?= e(url('/wordpress-hosting')) ?>">WordPress Hosting</a>
            <a href="<?= e(url('/hosting')) ?>#reseller-hosting">Reseller Hosting</a>
            <a href="<?= e(url('/domains')) ?>">Domain Registration</a>
            <a href="<?= e(url('/website-development')) ?>">Website Development</a>
        </div>
        <div>
            <h2>Support</h2>
            <a href="<?= e($clientArea) ?>">Client Login</a>
            <a href="<?= e($whmcs->cartUrl()) ?>">Billing & Orders</a>
            <a href="<?= e(url('/contact')) ?>">Contact Support</a>
            <?php if ($whatsappUrl): ?><a href="<?= e($whatsappUrl) ?>">WhatsApp</a><?php endif; ?>
        </div>
        <div>
            <h2>Legal</h2>
            <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a>
            <a href="<?= e(url('/terms-and-conditions')) ?>">Terms and Conditions</a>
            <a href="<?= e(url('/refund-policy')) ?>">Refund Policy</a>
            <a href="<?= e(url('/acceptable-use-policy')) ?>">Acceptable Use Policy</a>
        </div>
        <div>
            <h2>Contact</h2>
            <?php if (!empty($settings['admin_email'])): ?><a href="mailto:<?= e($settings['admin_email']) ?>"><?= e($settings['admin_email']) ?></a><?php endif; ?>
            <?php if (!empty($settings['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $settings['phone'])) ?>"><?= e($settings['phone']) ?></a><?php endif; ?>
            <?php if (!empty($settings['address'])): ?><p><?= nl2br(e($settings['address'])) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= e($settings['company_name'] ?? 'Planetic Solutions') ?>. All rights reserved.</p>
        <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
    </div>
</footer>
<?php if ($whatsappUrl): ?>
    <a class="whatsapp-float" href="<?= e($whatsappUrl) ?>" aria-label="Contact Planetic Solutions on WhatsApp">WhatsApp</a>
<?php endif; ?>

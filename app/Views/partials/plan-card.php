<?php
$features = json_decode($plan['features_json'] ?? '[]', true) ?: [];
$orderUrl = $plan['whmcs_url'] ?: $whmcs->cartUrl();
?>
<article class="pricing-card <?= !empty($plan['is_highlighted']) ? 'is-featured' : '' ?>">
    <?php if (!empty($plan['badge'])): ?><span class="badge"><?= e($plan['badge']) ?></span><?php endif; ?>
    <div class="pricing-head">
        <h3><?= e($plan['title']) ?></h3>
        <p><?= e($plan['description']) ?></p>
    </div>
    <div class="price">
        <span><?= e(money($plan['monthly_price'])) ?></span>
        <small>/month</small>
    </div>
    <?php if (!empty($plan['yearly_price'])): ?><p class="yearly"><?= e(money($plan['yearly_price'])) ?> yearly</p><?php endif; ?>
    <dl class="plan-specs">
        <div><dt>Storage</dt><dd><?= e($plan['storage']) ?></dd></div>
        <div><dt>Bandwidth</dt><dd><?= e($plan['bandwidth']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($plan['email_accounts']) ?></dd></div>
    </dl>
    <ul class="feature-list">
        <li><?= icon('check') ?> Free SSL</li>
        <li><?= icon('check') ?> cPanel included</li>
        <li><?= icon('check') ?> WordPress installer</li>
        <li><?= icon('check') ?> Free Cloudflare CDN</li>
        <?php foreach ($features as $feature): ?><li><?= icon('check') ?> <?= e($feature) ?></li><?php endforeach; ?>
    </ul>
    <a class="btn <?= !empty($plan['is_highlighted']) ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($orderUrl) ?>"><?= e($plan['button_text'] ?: 'Order Now') ?> <?= icon('arrow') ?></a>
</article>

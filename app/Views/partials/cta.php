<section class="final-cta">
    <div class="container cta-panel">
        <div>
            <span class="section-kicker">Ready when you are</span>
            <h2><?= e($title ?? ($settings['home_final_cta_title'] ?? 'Launch with hosting that keeps business moving')) ?></h2>
            <p><?= e($text ?? ($settings['home_final_cta_text'] ?? 'Choose hosting, register a domain, or order a complete business website through a WHMCS-connected flow.')) ?></p>
        </div>
        <div class="cta-actions">
            <a class="btn btn-primary" href="<?= e($primaryUrl ?? url('/hosting')) ?>"><?= e($primaryText ?? 'View Hosting Plans') ?> <?= icon('arrow') ?></a>
            <a class="btn btn-light" href="<?= e($secondaryUrl ?? url('/contact')) ?>"><?= e($secondaryText ?? 'Talk to Us') ?></a>
        </div>
    </div>
</section>

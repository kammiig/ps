<section
    class="checkout-page"
    data-payment-status-page
    data-payment-status-url="<?= e(url('/api/payment-status?order=' . rawurlencode((string) $order['public_token']))) ?>"
    data-payment-initial-status="<?= e((string) $order['payment_status']) ?>"
>
    <?php $statusLabels = ['pending' => 'Pending Payment', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Payment Failed']; ?>
    <div class="container narrow">
        <div class="payment-result-card" aria-live="polite">
            <?php if ($order['payment_status'] === 'paid'): ?>
                <span class="result-badge" data-payment-badge>Paid</span>
                <h1 data-payment-title>Payment Confirmed</h1>
                <p data-payment-message>Your payment has been confirmed and recorded on your invoice.</p>
            <?php else: ?>
                <span class="result-badge muted" data-payment-badge>Confirming</span>
                <h1 data-payment-title>Your payment is being confirmed</h1>
                <p data-payment-message>Please wait a moment or view the latest status in your account.</p>
            <?php endif; ?>

            <dl class="payment-result-details">
                <div><dt>Invoice reference</dt><dd>#<?= e($order['whmcs_invoice_id']) ?></dd></div>
                <div><dt>Amount</dt><dd><?= e($amountLabel) ?></dd></div>
                <div><dt>Payment status</dt><dd data-payment-status-text><?= e($statusLabels[$order['payment_status']] ?? ucwords((string) $order['payment_status'])) ?></dd></div>
            </dl>

            <?php if ($order['payment_status'] !== 'paid'): ?>
                <p class="payment-result-helper" data-payment-helper>We are checking for Stripe confirmation automatically. You can also refresh this page in a moment.</p>
            <?php endif; ?>

            <a class="btn btn-primary" href="<?= e(url('/account/dashboard')) ?>">Go to My Account <?= icon('arrow') ?></a>
        </div>
    </div>
</section>

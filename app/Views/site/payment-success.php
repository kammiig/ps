<section class="checkout-page">
    <?php $statusLabels = ['pending' => 'Pending Payment', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Payment Failed']; ?>
    <div class="container narrow">
        <div class="payment-result-card">
            <?php if ($order['payment_status'] === 'paid'): ?>
                <span class="result-badge">Paid</span>
                <h1>Payment Confirmed</h1>
                <p>Your payment has been confirmed and recorded on your invoice.</p>
            <?php else: ?>
                <span class="result-badge muted">Confirming</span>
                <h1>Your payment is being confirmed</h1>
                <p>Please wait a moment or view the latest status in your account.</p>
            <?php endif; ?>

            <dl class="payment-result-details">
                <div><dt>Invoice reference</dt><dd>#<?= e($order['whmcs_invoice_id']) ?></dd></div>
                <div><dt>Amount</dt><dd><?= e($amountLabel) ?></dd></div>
                <div><dt>Payment status</dt><dd><?= e($statusLabels[$order['payment_status']] ?? ucwords((string) $order['payment_status'])) ?></dd></div>
            </dl>

            <a class="btn btn-primary" href="<?= e(url('/account/dashboard')) ?>">Go to My Account <?= icon('arrow') ?></a>
        </div>
    </div>
</section>

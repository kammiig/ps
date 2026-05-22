<section class="checkout-page">
    <div class="container narrow">
        <div class="payment-result-card">
            <span class="result-badge muted">Payment not completed</span>
            <h1>Payment Unsuccessful</h1>
            <p><?= e($message ?? 'Your payment was not completed. You can retry securely using the same invoice.') ?></p>

            <?php if (!empty($order['public_token'])): ?>
                <a class="btn btn-primary" href="<?= e(url('/checkout/payment/' . $order['public_token'])) ?>">Retry Payment <?= icon('arrow') ?></a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('/contact')) ?>">Contact Support</a>
        </div>
    </div>
</section>

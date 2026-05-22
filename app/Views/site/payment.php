<section class="checkout-page payment-page">
    <?php $statusLabels = ['pending' => 'Pending Payment', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Payment Failed']; ?>
    <div class="container payment-layout">
        <div class="payment-panel">
            <span class="section-kicker">Secure payment</span>
            <h1>Complete your payment</h1>
            <p>Pay invoice #<?= e($order['whmcs_invoice_id']) ?> securely without leaving Planetic Solutions.</p>

            <div class="payment-amount-card">
                <span>Today’s payment</span>
                <strong><?= e($amountLabel) ?></strong>
            </div>

            <div id="payment-message" class="notice error" role="alert" hidden></div>
            <div
                id="stripe-payment"
                data-stripe-payment
                data-publishable-key="<?= e($publishableKey) ?>"
                data-client-secret="<?= e($clientSecret) ?>"
                data-return-url="<?= e(rtrim((string) ($settings['app_url'] ?? env('APP_URL', '')), '/') . url('/checkout/success?order=' . $order['public_token'])) ?>"
                data-failed-url="<?= e(rtrim((string) ($settings['app_url'] ?? env('APP_URL', '')), '/') . url('/checkout/payment-failed?order=' . $order['public_token'])) ?>"
            ></div>

            <form id="stripe-payment-form" class="stripe-payment-form">
                <div id="payment-element"></div>
                <button id="stripe-submit" class="btn btn-primary" type="submit">Pay <?= e($amountLabel) ?> Securely</button>
                <p class="checkout-help">Secure payment powered by Stripe. Your card information is handled securely and is not stored by Planetic Solutions.</p>
            </form>
        </div>

        <aside class="checkout-summary-card payment-summary">
            <span class="section-kicker">Order summary</span>
            <h2>Invoice #<?= e($order['whmcs_invoice_id']) ?></h2>
            <div class="order-summary-totals">
                <div><span>Amount due</span><strong><?= e($amountLabel) ?></strong></div>
                <div><span>Status</span><strong><?= e($statusLabels[$order['payment_status']] ?? ucwords((string) $order['payment_status'])) ?></strong></div>
            </div>
            <a class="btn btn-outline" href="<?= e(url('/account/billing')) ?>">View Billing</a>
        </aside>
    </div>
</section>
<script src="https://js.stripe.com/v3/"></script>

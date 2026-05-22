<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">My account</span>
                <h1>Welcome, <?= e($account['first_name']) ?></h1>
                <p>Manage your Planetic Solutions services, invoices and profile from one place.</p>
            </div>

            <div class="account-stat-grid">
                <article><span>Active services</span><strong><?= e($activeCount) ?></strong></article>
                <article><span>Unpaid invoices</span><strong><?= e($unpaidCount) ?></strong></article>
                <article><span>Next due date</span><strong><?= e($nextDueDate ?: 'Not available') ?></strong></article>
            </div>

            <div class="account-grid two">
                <section class="account-card">
                    <div class="section-head compact">
                        <div><span class="section-kicker">Recent invoices</span><h2>Billing</h2></div>
                        <a class="text-link" href="<?= e(url('/account/billing')) ?>">View billing <?= icon('arrow') ?></a>
                    </div>
                    <?php if ($invoices): ?>
                        <div class="account-list">
                            <?php foreach (array_slice($invoices, 0, 4) as $invoice): ?>
                                <div class="account-row">
                                    <div><strong>Invoice #<?= e($invoice['id'] ?? $invoice['invoiceid'] ?? '') ?></strong><span><?= e($invoice['date'] ?? '') ?></span></div>
                                    <div><strong><?= e(money($invoice['total'] ?? $invoice['balance'] ?? '0.00')) ?></strong><span class="status-pill"><?= e($invoice['status'] ?? 'Pending') ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No invoices are available yet.</p>
                    <?php endif; ?>
                </section>

                <section class="account-card">
                    <div class="section-head compact">
                        <div><span class="section-kicker">Payment status</span><h2>Latest payment</h2></div>
                    </div>
                    <?php if ($recentPayments): ?>
                        <?php $payment = $recentPayments[0]; ?>
                        <?php $statusLabels = ['pending' => 'Pending Payment', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Payment Failed']; ?>
                        <div class="payment-status-block">
                            <strong><?= e($statusLabels[$payment['payment_status']] ?? ucwords(str_replace('_', ' ', (string) $payment['payment_status']))) ?></strong>
                            <span>Invoice #<?= e($payment['whmcs_invoice_id']) ?></span>
                            <p><?= e(money($payment['invoice_amount'])) ?> <?= e($payment['currency']) ?></p>
                        </div>
                    <?php else: ?>
                        <p>No payment activity yet.</p>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</section>

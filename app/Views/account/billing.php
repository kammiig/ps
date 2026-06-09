<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Billing</span>
                <h1>Invoices and payments</h1>
                <p>Review invoices and pay outstanding balances securely on this website.</p>
                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Billing&subject=Billing%20support%20request')) ?>">Open Ticket</a>
            </div>

            <section class="account-card">
                <h2>Invoices</h2>
                <?php if ($invoices): ?>
                    <div class="invoice-table-wrap">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Date</th>
                                    <th>Due date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <?php
                                    $invoiceId = (int) ($invoice['id'] ?? $invoice['invoiceid'] ?? 0);
                                    $status = (string) ($invoice['status'] ?? 'Pending');
                                    $statusKey = strtolower($status);
                                    $isUnpaid = in_array($statusKey, ['unpaid', 'payment pending', 'pending payment', 'payment failed'], true);
                                    $amount = in_array($statusKey, ['paid', 'payment failed'], true)
                                        ? ($invoice['total'] ?? $invoice['balance'] ?? '0.00')
                                        : ($invoice['balance'] ?? $invoice['total'] ?? '0.00');
                                    ?>
                                    <tr>
                                        <td>#<?= e($invoiceId) ?></td>
                                        <td><?= e($invoice['date'] ?? '') ?></td>
                                        <td><?= e($invoice['duedate'] ?? '') ?></td>
                                        <td><?= e(money($amount)) ?></td>
                                        <td><span class="status-pill"><?= e($status) ?></span></td>
                                        <td>
                                            <?php if ($isUnpaid && $invoiceId > 0): ?>
                                                <form action="<?= e(url('/account/billing/' . $invoiceId . '/pay')) ?>" method="post">
                                                    <?= \App\Core\Csrf::field() ?>
                                                    <button class="btn btn-primary btn-small" type="submit">Pay Now</button>
                                                </form>
                                            <?php else: ?>
                                                <a class="btn btn-outline btn-small" href="<?= e(url('/account/tickets/new?department=Billing&subject=' . rawurlencode('Question about invoice #' . $invoiceId) . '&related_type=invoice&related_label=' . rawurlencode('Invoice #' . $invoiceId) . '&related_reference=' . rawurlencode((string) $invoiceId))) ?>">Open Ticket</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No invoices are available yet.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</section>

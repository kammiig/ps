<section class="admin-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Update customer-facing project progress while keeping internal notes private.</p>
    </div>
</section>

<section class="admin-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Package</th>
                    <th>Invoice</th>
                    <th>Status</th>
                    <th>Next Step</th>
                    <th>Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($projects): ?>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <strong><?= e($project['customer_name'] ?: 'Customer') ?></strong><br>
                            <span class="muted-text"><?= e($project['customer_email'] ?? '') ?></span>
                        </td>
                        <td><?= e($project['package_name'] ?? 'Website Development') ?></td>
                        <td>#<?= e($project['whmcs_invoice_id'] ?? '') ?></td>
                        <td><span class="status-pill"><?= e($project['project_status'] ?? 'Payment Pending') ?></span></td>
                        <td><?= e($project['estimated_next_step'] ?? '') ?></td>
                        <td><?= e(substr((string) ($project['updated_at'] ?? ''), 0, 10)) ?></td>
                        <td>
                            <a class="btn btn-outline btn-small" href="<?= e(url('/admin/website-projects/' . (int) $project['id'] . '/edit')) ?>">Update</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No website projects have been created yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

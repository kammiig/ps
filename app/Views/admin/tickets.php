<section class="admin-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Reply to customer support tickets and track open requests.</p>
    </div>
</section>

<section class="admin-stats">
    <article><span><?= e($counts['open'] ?? 0) ?></span><strong>Open tickets</strong></article>
    <article><span><?= e($counts['customer_reply'] ?? 0) ?></span><strong>Customer replies</strong></article>
    <article><span><?= e($counts['total'] ?? 0) ?></span><strong>Total tickets</strong></article>
    <article><span><?= e(count($tickets ?? [])) ?></span><strong>Visible rows</strong></article>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <h2>Tickets</h2>
        <form class="admin-inline-filter" action="<?= e(url('/admin/tickets')) ?>" method="get">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($selectedStatus ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-small" type="submit">Filter</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Customer</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td>
                            <strong><?= e($ticket['subject'] ?? 'Support ticket') ?></strong><br>
                            <span class="muted-text">#<?= e($ticket['public_ref'] ?? $ticket['id']) ?> · <?= e((string) ($ticket['message_count'] ?? 0)) ?> messages</span>
                        </td>
                        <td>
                            <?= e(trim((string) ($ticket['customer_name'] ?? '')) ?: 'Customer') ?><br>
                            <span class="muted-text"><?= e($ticket['customer_email'] ?? '') ?></span>
                        </td>
                        <td><?= e($ticket['department'] ?? 'General Support') ?></td>
                        <td><?= e($ticket['priority'] ?? 'Medium') ?></td>
                        <td><span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span></td>
                        <td><?= e(substr((string) ($ticket['updated_at'] ?? ''), 0, 16)) ?></td>
                        <td><a class="btn btn-outline btn-small" href="<?= e(url('/admin/tickets/' . (int) $ticket['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No support tickets match this view.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

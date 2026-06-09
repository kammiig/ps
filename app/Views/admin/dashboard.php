<section class="admin-page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Quick overview of the CMS content and latest inquiries.</p>
    </div>
</section>

<section class="admin-stats">
    <article><span><?= e($counts['plans']) ?></span><strong>Hosting plans</strong></article>
    <article><span><?= e($counts['website_projects'] ?? 0) ?></span><strong>Website projects</strong></article>
    <article><span><?= e($counts['open_support_tickets'] ?? $ticketCounts['open'] ?? 0) ?></span><strong>Open tickets</strong></article>
    <article><span><?= e($counts['posts']) ?></span><strong>Blog posts</strong></article>
    <article><span><?= e($counts['inquiries']) ?></span><strong>Total inquiries</strong></article>
    <article><span><?= e($counts['new_inquiries']) ?></span><strong>New inquiries</strong></article>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <h2>Latest Support Tickets</h2>
        <a class="btn btn-outline" href="<?= e(url('/admin/tickets')) ?>">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ticket</th><th>Customer</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><strong><?= e($ticket['subject'] ?? 'Support ticket') ?></strong><br><span class="muted-text">#<?= e($ticket['public_ref'] ?? $ticket['id']) ?></span></td>
                        <td><?= e(trim((string) ($ticket['customer_name'] ?? '')) ?: 'Customer') ?><br><span class="muted-text"><?= e($ticket['customer_email'] ?? '') ?></span></td>
                        <td><span class="status-pill"><?= e($ticket['status'] ?? 'Open') ?></span></td>
                        <td><?= e(substr((string) ($ticket['updated_at'] ?? ''), 0, 16)) ?></td>
                        <td><a class="btn btn-outline btn-small" href="<?= e(url('/admin/tickets/' . (int) $ticket['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No support tickets yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <h2>Latest Inquiries</h2>
        <a class="btn btn-outline" href="<?= e(url('/admin/inquiries')) ?>">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Service</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($inquiries as $inquiry): ?>
                <tr>
                    <td><?= e($inquiry['full_name']) ?></td>
                    <td><?= e($inquiry['email']) ?></td>
                    <td><?= e($inquiry['service']) ?></td>
                    <td><?= e($inquiry['status']) ?></td>
                    <td><?= e($inquiry['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Quick overview of the CMS content and latest inquiries.</p>
    </div>
</section>

<section class="admin-stats">
    <article><span><?= e($counts['plans']) ?></span><strong>Hosting plans</strong></article>
    <article><span><?= e($counts['posts']) ?></span><strong>Blog posts</strong></article>
    <article><span><?= e($counts['inquiries']) ?></span><strong>Total inquiries</strong></article>
    <article><span><?= e($counts['new_inquiries']) ?></span><strong>New inquiries</strong></article>
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

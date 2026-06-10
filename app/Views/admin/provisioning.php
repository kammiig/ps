<section class="admin-page-head">
    <div>
        <h1><?= e($title ?? 'Provisioning') ?></h1>
        <p>Review domain, hosting, WHM and Cloudflare automation status.</p>
    </div>
</section>

<section class="admin-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>cPanel</th>
                    <th>Cloudflare</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $status = (string) ($item['hosting_setup_status'] ?? $item['domain_registration_status'] ?? $item['provisioning_status'] ?? 'Processing');
                    $cloudflareStatus = (string) ($item['cloudflare_status'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($item['customer_name'] ?: 'Customer') ?></strong><br>
                            <span><?= e($item['customer_email'] ?? '') ?></span>
                        </td>
                        <td>
                            <strong><?= e(ucfirst((string) ($item['item_type'] ?? 'item'))) ?></strong><br>
                            <span><?= e($item['display_name'] ?? '') ?></span>
                        </td>
                        <td><?= e($item['domain_name'] ?? '') ?></td>
                        <td><span class="status-pill"><?= e($status) ?></span></td>
                        <td>
                            <?= e($item['cpanel_username'] ?? '') ?><br>
                            <span><?= e($item['server_ip'] ?? '') ?></span>
                        </td>
                        <td>
                            <?= e($cloudflareStatus ?: 'Not started') ?><br>
                            <span><?= e($item['cloudflare_zone_id'] ?? '') ?></span>
                        </td>
                        <td><?= e(substr((string) ($item['updated_at'] ?? ''), 0, 19)) ?></td>
                        <td class="table-actions">
                            <?php if (in_array((string) ($item['item_type'] ?? ''), ['domain', 'hosting'], true)): ?>
                                <form method="post" action="<?= e(url('/admin/provisioning/' . (int) $item['id'] . '/retry')) ?>" onsubmit="return confirm('Retry this provisioning step?');">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button class="btn btn-light" type="submit">Retry</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8">No provisioning records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card">
    <div class="section-head compact">
        <div>
            <span class="section-kicker">Audit log</span>
            <h2>Recent provisioning events</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e(substr((string) ($log['created_at'] ?? ''), 0, 19)) ?></td>
                        <td><?= e($log['item_type'] ?? '') ?></td>
                        <td><?= e($log['event'] ?? '') ?></td>
                        <td><span class="status-pill"><?= e($log['status'] ?? '') ?></span></td>
                        <td><?= e($log['message'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5">No provisioning log entries yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

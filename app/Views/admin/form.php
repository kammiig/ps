<section class="admin-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
</section>

<form class="admin-card admin-form" action="<?= e($action) ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <div class="form-grid">
        <?php foreach ($fields as $field): ?>
            <?php
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $value = $values[$name] ?? '';
            $label = $field['label'] ?? '';
            $readonly = !empty($field['readonly']) ? 'readonly' : '';
            $wide = in_array($type, ['textarea', 'file', 'hidden'], true) ? 'wide' : '';
            ?>
            <?php if ($type === 'hidden'): ?>
                <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                <?php continue; ?>
            <?php endif; ?>
            <label class="<?= e($wide) ?>">
                <span><?= e($label) ?></span>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= e($name) ?>" rows="<?= e($field['rows'] ?? 5) ?>" <?= $readonly ?>><?= e($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select name="<?= e($name) ?>">
                        <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                            <option value="<?= e($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'checkbox'): ?>
                    <input type="hidden" name="<?= e($name) ?>" value="0">
                    <span class="switch-row"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= (string) $value === '1' ? 'checked' : '' ?>> Enabled</span>
                <?php elseif ($type === 'file'): ?>
                    <input type="file" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp,image/gif,image/x-icon">
                    <?php if (!empty($field['current'])): ?>
                        <small>Current: <?= e($field['current']) ?></small>
                        <img class="admin-preview" src="<?= e(upload_url($field['current'])) ?>" alt="">
                    <?php endif; ?>
                <?php else: ?>
                    <input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $readonly ?>>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a class="btn btn-light" href="<?= e($_SERVER['HTTP_REFERER'] ?? url('/admin')) ?>">Cancel</a>
    </div>
</form>

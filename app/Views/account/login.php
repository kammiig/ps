<section class="account-page">
    <div class="container account-auth-wrap">
        <div class="account-auth-card">
            <span class="section-kicker">Client login</span>
            <h1>Welcome back</h1>
            <p>Log in to manage your services, invoices and profile on the Planetic Solutions website.</p>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('/account/login')) ?>" method="post">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
                <label><span>Email</span><input name="email" type="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>" required></label>
                <label class="password-field">
                    <span>Password</span>
                    <input id="account-login-password" name="password" type="password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" data-password-toggle data-password-target="account-login-password" aria-label="Show password" aria-pressed="false">Show</button>
                </label>
                <button class="btn btn-primary" type="submit">Login <?= icon('arrow') ?></button>
            </form>

            <div class="account-auth-links">
                <a href="<?= e(url('/account/register')) ?>">Create an account</a>
                <a href="<?= e(url('/account/password-reset')) ?>">Forgot password?</a>
            </div>
        </div>
    </div>
</section>

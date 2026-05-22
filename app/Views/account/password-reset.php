<section class="account-page">
    <div class="container account-auth-wrap">
        <div class="account-auth-card">
            <span class="section-kicker">Password reset</span>
            <h1>Reset your password</h1>
            <p>Enter your account email and we’ll send a secure reset link if the account exists.</p>

            <?php if ($sent): ?>
                <div class="notice success" role="status"><p>If an account exists, a password reset link has been sent.</p></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('/account/password-reset')) ?>" method="post">
                <?= \App\Core\Csrf::field() ?>
                <label><span>Email</span><input name="email" type="email" autocomplete="email" required></label>
                <button class="btn btn-primary" type="submit">Send Reset Link <?= icon('arrow') ?></button>
            </form>

            <div class="account-auth-links">
                <a href="<?= e(url('/account/login')) ?>">Back to login</a>
            </div>
        </div>
    </div>
</section>

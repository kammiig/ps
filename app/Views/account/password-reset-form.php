<section class="account-page">
    <div class="container account-auth-wrap">
        <div class="account-auth-card">
            <span class="section-kicker">Choose password</span>
            <h1>Create a new password</h1>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($valid)): ?>
                <form action="<?= e(url('/account/password-reset/' . $token)) ?>" method="post">
                    <?= \App\Core\Csrf::field() ?>
                    <label><span>New password</span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
                    <label><span>Confirm password</span><input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
                    <button class="btn btn-primary" type="submit">Update Password <?= icon('arrow') ?></button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('/account/password-reset')) ?>">Request New Link</a>
            <?php endif; ?>
        </div>
    </div>
</section>

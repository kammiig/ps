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
                    <label class="password-field">
                        <span>New password</span>
                        <input id="account-reset-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="password-toggle" type="button" data-password-toggle data-password-target="account-reset-password" aria-label="Show new password" aria-pressed="false">Show</button>
                    </label>
                    <label class="password-field">
                        <span>Confirm password</span>
                        <input id="account-reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="password-toggle" type="button" data-password-toggle data-password-target="account-reset-password-confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button>
                    </label>
                    <button class="btn btn-primary" type="submit">Update Password <?= icon('arrow') ?></button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('/account/password-reset')) ?>">Request New Link</a>
            <?php endif; ?>
        </div>
    </div>
</section>

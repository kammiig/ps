<section class="account-page">
    <div class="container account-auth-wrap wide">
        <div class="account-auth-card">
            <span class="section-kicker">Create account</span>
            <h1>Start your Planetic account</h1>
            <p>Your account lets you manage invoices, services and payment status without leaving this website.</p>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('/account/register')) ?>" method="post">
                <?= \App\Core\Csrf::field() ?>
                <div class="checkout-form-grid">
                    <label><span>First name</span><input name="first_name" autocomplete="given-name" value="<?= e($old['first_name'] ?? '') ?>" required></label>
                    <label><span>Last name</span><input name="last_name" autocomplete="family-name" value="<?= e($old['last_name'] ?? '') ?>" required></label>
                    <label><span>Email</span><input name="email" type="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>" required></label>
                    <label><span>Phone</span><input name="phone" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>" required></label>
                    <label class="wide"><span>Company name</span><input name="company_name" autocomplete="organization" value="<?= e($old['company_name'] ?? '') ?>"></label>
                    <label class="wide"><span>Address</span><input name="address" autocomplete="address-line1" value="<?= e($old['address'] ?? '') ?>" required></label>
                    <label><span>City</span><input name="city" autocomplete="address-level2" value="<?= e($old['city'] ?? '') ?>" required></label>
                    <label><span>County/state</span><input name="state" autocomplete="address-level1" value="<?= e($old['state'] ?? '') ?>" required></label>
                    <label><span>Postcode</span><input name="postcode" autocomplete="postal-code" value="<?= e($old['postcode'] ?? '') ?>" required></label>
                    <label>
                        <span>Country</span>
                        <select name="country" autocomplete="country" required>
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?= e($code) ?>" <?= (($old['country'] ?? 'GB') === $code) ? 'selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="password-field">
                        <span>Password</span>
                        <input id="account-register-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="password-toggle" type="button" data-password-toggle data-password-target="account-register-password" aria-label="Show password" aria-pressed="false">Show</button>
                    </label>
                    <label class="password-field">
                        <span>Confirm password</span>
                        <input id="account-register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="password-toggle" type="button" data-password-toggle data-password-target="account-register-password-confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Create Account <?= icon('arrow') ?></button>
            </form>

            <div class="account-auth-links">
                <a href="<?= e(url('/account/login')) ?>">Already have an account?</a>
            </div>
        </div>
    </div>
</section>

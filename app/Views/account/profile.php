<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">Profile</span>
                <h1>Your details</h1>
                <p>Keep your contact and billing details up to date.</p>
            </div>

            <section class="account-card">
                <?php if ($saved): ?><div class="notice success" role="status"><p>Your profile has been updated.</p></div><?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="notice error" role="alert">
                        <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="<?= e(url('/account/profile')) ?>" method="post">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="checkout-form-grid">
                        <label><span>First name</span><input name="first_name" autocomplete="given-name" value="<?= e($account['first_name'] ?? '') ?>" required></label>
                        <label><span>Last name</span><input name="last_name" autocomplete="family-name" value="<?= e($account['last_name'] ?? '') ?>" required></label>
                        <label><span>Email</span><input name="email" type="email" autocomplete="email" value="<?= e($account['email'] ?? '') ?>" required></label>
                        <label><span>Phone</span><input name="phone" autocomplete="tel" value="<?= e($account['phone'] ?? '') ?>" required></label>
                        <label class="wide"><span>Company name</span><input name="company_name" autocomplete="organization" value="<?= e($account['company_name'] ?? '') ?>"></label>
                        <label class="wide"><span>Address</span><input name="address" autocomplete="address-line1" value="<?= e($account['address'] ?? '') ?>" required></label>
                        <label><span>City</span><input name="city" autocomplete="address-level2" value="<?= e($account['city'] ?? '') ?>" required></label>
                        <label><span>County/state</span><input name="state" autocomplete="address-level1" value="<?= e($account['state'] ?? '') ?>" required></label>
                        <label><span>Postcode</span><input name="postcode" autocomplete="postal-code" value="<?= e($account['postcode'] ?? '') ?>" required></label>
                        <label>
                            <span>Country</span>
                            <select name="country" autocomplete="country" required>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= e($code) ?>" <?= (($account['country'] ?? 'GB') === $code) ? 'selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Update Profile <?= icon('arrow') ?></button>
                </form>
            </section>
        </main>
    </div>
</section>

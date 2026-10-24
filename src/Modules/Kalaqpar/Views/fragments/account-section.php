<?php
$escape = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$accountUrl = $base_path . '/account';
$reset = $page === 'reset-password';
$formPage = in_array($page, ['register', 'login', 'forgot-password', 'reset-password'], true);
$showForm = $formPage && !($page === 'register' && $registered) && !($reset && !$valid_token) && !($page === 'forgot-password' && (!$recovery_available || $message));
$fieldError = static fn ($keys) => $error && in_array($error_key, $keys, true) ? ' aria-invalid="true" aria-describedby="account-error"' : '';
?>
<section id="account" class="kq-card kq-account-section" aria-labelledby="account-title" tabindex="-1">
    <h2 id="account-title" tabindex="-1"><?= $escape(['register' => 'حساب من', 'login' => 'ورود به حساب', 'forgot-password' => $message ? 'درخواست بازیابی ثبت شد' : 'بازیابی رمز عبور', 'reset-password' => $valid_token ? 'تغییر رمز عبور' : 'این لینک قابل استفاده نیست'][$page] ?? 'حساب من') ?></h2>
    <?php if ($error && $page !== 'name'): ?><div id="account-error" class="kq-note <?= $error_key === 'credentials' ? 'kq-red' : 'kq-yellow' ?>" role="alert" tabindex="-1"><?= $escape($error) ?></div><?php endif ?>
    <?php if (!$csrf || (!$user && !$reset && $page !== 'forgot-password')): ?>
        <p>سرویس حساب فعلاً در دسترس نیست. برای ادامه به بازی برگرد و دوباره تلاش کن.</p><a class="kq-button kq-retry" href="<?= $escape($accountUrl) ?>">تلاش دوباره</a>
    <?php elseif ($reset && !$valid_token): ?>
        <div class="kq-reset-symbol" aria-hidden="true">×</div><p>ممکنه منقضی شده باشه یا قبلاً استفاده شده باشه.</p><a class="kq-button kq-retry" href="<?= $escape($accountUrl) ?>?view=forgot-password#account">درخواست لینک جدید</a>
    <?php elseif ($page === 'forgot-password' && $message): ?>
        <p>اگر ایمیل به دستت نرسید، پوشهٔ هرزنامه را هم بررسی کن. درخواست دوباره به معنی ارسال یا تحویل قطعی نیست.</p><a class="kq-button kq-retry" href="<?= $escape($accountUrl) ?>?view=login#account">بازگشت به ورود</a>
    <?php elseif ($page === 'forgot-password' && !$recovery_available): ?>
        <div class="kq-note kq-yellow" role="status"><p>بازیابی رمز فعلاً در دسترس نیست. کمی بعد دوباره تلاش کن. بازی مهمان همچنان در دسترس است.</p></div><a class="kq-button kq-retry" href="<?= $escape($accountUrl) ?>?view=forgot-password#account">تلاش دوباره</a>
    <?php elseif ($showForm): ?>
        <?php if ($page === 'register'): ?>
            <p>ثبت‌نام همین حساب، شناسه، نام، امتیازها و تاریخچه‌ات را حفظ می‌کند.</p>
        <?php elseif ($page === 'login'): ?>
            <p id="login-notice">با ورود به حساب قبلی، امتیازها و تاریخچهٔ مهمان فعلی به آن حساب منتقل یا با آن ادغام نمی‌شود.</p>
        <?php elseif ($page === 'forgot-password'): ?>
            <p>ایمیل حسابت را وارد کن. اگر حسابی با این ایمیل وجود داشته باشه، راهنمای بازیابی رمز برای اون ارسال می‌شه.</p>
        <?php else: ?>
            <div class="kq-note kq-lavender"><p>یک رمز جدید انتخاب کن. همهٔ نشست‌های قبلی این حساب باطل می‌شوند؛ بعد از تغییر رمز دوباره وارد شو.</p></div>
        <?php endif ?>
        <form method="post" action="<?= $escape($base_path . '/' . $page) ?>" data-pending="در حال بررسی…"<?= $page === 'register' ? ' aria-describedby="email-notice password-policy"' : ($page === 'login' ? ' aria-describedby="login-notice"' : '') ?>>
            <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
            <?php if ($reset): ?><input type="hidden" name="token" value="<?= $escape($token) ?>"><?php else: ?>
                <label for="account-email">ایمیل<?= $page === 'register' ? ' شخصی' : ' حساب' ?></label>
                <input id="account-email" name="email" type="email" dir="ltr" maxlength="254" autocomplete="<?= $page === 'login' ? 'username' : 'email' ?>" autocapitalize="none" spellcheck="false" value="<?= $escape($email) ?>" required<?= $fieldError(['email', 'registration', 'credentials']) ?>>
            <?php endif ?>
            <?php if ($page !== 'forgot-password'): ?>
                <label for="account-password"><?= $reset ? 'رمز عبور جدید' : 'رمز عبور' ?></label>
                <div class="kq-password"><input id="account-password" name="password" type="password" autocomplete="<?= $page === 'login' ? 'current-password' : 'new-password' ?>"<?= $page !== 'login' ? ' minlength="12"' : '' ?> required<?= $fieldError(['password', 'credentials']) ?>><button type="button" class="kq-password-toggle" data-password="account-password" aria-controls="account-password" aria-label="نمایش رمز عبور" aria-pressed="false" hidden>نمایش</button></div>
                <?php if ($page !== 'login'): ?>
                    <p id="password-policy">حداقل ۱۲ نویسه و حداکثر ۷۲ بایت UTF-8؛ فاصله‌ها حفظ می‌شوند.</p>
                    <label for="account-password-confirmation">تکرار رمز عبور<?= $reset ? ' جدید' : '' ?></label>
                    <div class="kq-password"><input id="account-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required<?= $fieldError(['password']) ?>><button type="button" class="kq-password-toggle" data-password="account-password-confirmation" aria-controls="account-password-confirmation" aria-label="نمایش تکرار رمز عبور" aria-pressed="false" hidden>نمایش</button></div>
                <?php endif ?>
            <?php endif ?>
            <?php if ($page === 'register'): ?><div id="email-notice" class="kq-note kq-yellow"><p>ایمیل خودت را وارد کن. تأیید ایمیل برای ثبت‌نام و بازی لازم نیست. بازیابی به دسترسی به همین صندوق ایمیل وابسته است؛ صاحب این ایمیل می‌تواند رمز حساب را بازنشانی کند.</p></div><?php endif ?>
            <?php if ($page === 'login'): ?><a class="kq-link" href="<?= $escape($accountUrl) ?>?view=forgot-password#account">رمزت را فراموش کردی؟</a><?php endif ?>
            <button class="kq-button" type="submit"><?= $escape(['register' => 'ثبت‌نام و حفظ همین حساب', 'login' => 'ورود به حساب', 'forgot-password' => 'درخواست لینک بازیابی', 'reset-password' => 'ذخیره رمز جدید'][$page]) ?></button>
            <p class="kq-submit-status" role="status" aria-live="polite"></p>
        </form>
        <?php if ($page === 'register'): ?><a class="kq-link" href="<?= $escape($accountUrl) ?>?view=login#account">قبلاً حساب داری؟ ورود به حساب</a><?php endif ?>
        <?php if (!$reset): ?><a class="kq-link" href="<?= $escape($accountUrl) ?>#account">بستن فرم و بازگشت به پروفایل</a><?php endif ?>
    <?php elseif ($registered): ?>
        <?php if ($credential): ?><p>ایمیل حساب</p><bdi class="kq-account-email" dir="ltr"><?= $escape($credential['email']) ?></bdi><span class="kq-pill kq-unverified">ایمیل تأیید نشده</span><p>بازیابی رمز به دسترسی به همین صندوق ایمیل وابسته است؛ صاحب آن می‌تواند رمز حساب را بازنشانی کند.</p><?php else: ?><p>اطلاعات حساب ثبت‌شدهٔ قبلی حفظ شده است. این حساب از مسیر ثبت‌نام مهمان تغییر نمی‌کند.</p><?php endif ?>
        <a class="kq-link kq-logout-link" href="<?= $escape($accountUrl) ?>?view=logout#account">خروج از حساب</a>
    <?php else: ?>
        <h3>این امتیازها رو برای خودت نگه دار</h3><p>الان مهمان هستی و بازی بدون ثبت‌نام آزاده. با پاک‌شدن کوکی مهمان، دسترسی به این حساب قابل بازیابی نیست.</p>
        <a class="kq-button kq-retry" href="<?= $escape($accountUrl) ?>?view=register#account">ثبت‌نام و حفظ همین حساب</a><p class="kq-help">با ایمیل و رمز، به همین شناسه و سابقه برگرد.</p><a class="kq-link" href="<?= $escape($accountUrl) ?>?view=login#account">قبلاً حساب داری؟ ورود به حساب</a>
    <?php endif ?>
</section>
<?php if (!$reset && $csrf && ($page === 'logout' || ($page === 'login' && $blocked))): ?>
    <dialog class="kq-account-dialog" open data-account-dialog aria-labelledby="account-dialog-title" aria-describedby="account-dialog-description">
        <h2 id="account-dialog-title"><?= $blocked ? 'اول از بازی فعلی خارج شو' : 'از حساب خارج می‌شی؟' ?></h2>
        <p id="account-dialog-description"><?= $blocked ? 'هنوز در صف یا اتاق حضور داری. تغییر حساب یا خروج نباید تو را بی‌خبر از بازی خارج کند. به بازی برگرد و صریحاً از صف یا اتاق خارج شو.' : 'حساب، امتیازها و تاریخچه‌ات حذف نمی‌شوند. برای ورود دوباره به ایمیل و رمز نیاز داری. بعد از خروج می‌توانی به‌عنوان مهمان بازی کنی.' ?></p>
        <?php if ($blocked): ?><div class="kq-note kq-yellow"><p>خروج از دور فعال، امتیازها و پیامدهای بازی را پاک نمی‌کند.</p></div><a class="kq-button kq-retry" href="<?= $escape($base_path) ?>/">بازگشت به بازی / اتاق</a>
        <?php else: ?><form method="post" action="<?= $escape($base_path) ?>/logout" data-pending="در حال خروج…"><input type="hidden" name="csrf" value="<?= $escape($csrf) ?>"><button class="kq-button kq-danger" type="submit">خروج از حساب</button><p class="kq-submit-status" role="status"></p></form><?php endif ?>
        <a class="kq-link" data-dialog-cancel href="<?= $escape($accountUrl) ?>#account" autofocus><?= $blocked ? 'فعلاً منصرف شدم' : 'در حساب می‌مانم' ?></a>
    </dialog>
<?php endif ?>

<?php
use Src\Modules\Kalaqpar\Services\PublicUrl;
$escape = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fa = static fn ($v) => $v === null ? '—' : strtr((string) $v, array_combine(range(0, 9), preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY)));
$accountUrl = PublicUrl::local(url('account', 'kalaqpar.tolio.ir'));
$reset = $page === 'reset-password';
$this->start('content');
?>
<main class="kq kq-account-page">
    <?php if (!$reset && $blocked && $user): ?>
        <div id="kq-account-presence" data-base="<?= $escape($base_path) ?>" data-csrf="<?= $escape($user['csrf']) ?>"><p class="kq-note kq-yellow" role="status" hidden></p></div>
    <?php endif ?>
    <header class="kq-header">
        <a class="kq-icon kq-back-link" href="<?= $escape(PublicUrl::local(url('', 'kalaqpar.tolio.ir'))) ?>" aria-label="بازگشت به بازی">›</a>
        <div><h1><?= $reset ? 'بازیابی رمز عبور' : 'پروفایل من' ?></h1><p><?= $reset ? 'انتخاب رمز تازه برای حساب' : 'امتیاز، نام نمایشی و حساب تو، همه همین‌جا' ?></p></div>
    </header>
    <?php if (!$reset && $profile): ?>
        <section class="kq-profile-identity" aria-label="هویت بازیکن">
            <span class="kq-avatar" data-color="<?= $escape($profile['avatar']) ?>" aria-hidden="true"><?= $escape(preg_split('//u', $profile['name'], -1, PREG_SPLIT_NO_EMPTY)[0] ?? 'م') ?></span>
            <div class="kq-grow"><h2><?= $escape($profile['name']) ?></h2><p>شناسه بازیکن: <bdi><?= $escape($profile['id']) ?></bdi></p></div>
            <span class="kq-pill"><?= $registered ? 'حساب ثبت‌شده' : 'مهمان' ?></span>
        </section>
        <div class="kq-stats">
            <?php foreach ([[$profile['wins'], 'برد'], [$profile['weekly_score'], 'امتیاز هفته'], [$profile['total_score'], 'امتیاز کل']] as [$value, $label]): ?>
                <div class="kq-stat"><strong><?= $escape($fa($value)) ?></strong><small><?= $escape($label) ?></small></div>
            <?php endforeach ?>
        </div>
        <form class="kq-name-form" method="post" action="<?= $escape(PublicUrl::local(url('account/name', 'kalaqpar.tolio.ir'))) ?>" data-pending="در حال ذخیره…">
            <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
            <label for="profile-name">نام نمایشی</label>
            <div class="kq-name-row"><input id="profile-name" name="name" value="<?= $escape($profile['name']) ?>" required maxlength="30" autocomplete="nickname"<?= $page === 'name' && $error ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                <button class="kq-button" type="submit">ذخیره نام</button></div>
            <p class="kq-submit-status" role="status" aria-live="polite"></p>
            <?php if ($page === 'name' && $error): ?><p id="name-error" class="kq-field-error" role="alert"><?= $escape($error) ?></p><?php endif ?>
            <details><summary>رنگ آواتار و آمار بیشتر</summary>
                <fieldset class="kq-avatar-options"><legend>رنگ آواتار</legend>
                    <?php for ($i = 0; $i < 6; $i++): ?><label><input type="radio" name="avatar" value="<?= $i ?>"<?= $i === (int) $profile['avatar'] ? ' checked' : '' ?>><span class="kq-avatar" data-color="<?= $i ?>"><?= $escape($fa($i + 1)) ?></span><span class="visually-hidden">رنگ <?= $escape($fa($i + 1)) ?></span></label><?php endfor ?>
                </fieldset>
                <dl class="kq-more-stats">
                    <?php foreach ([[$profile['completed'], 'مسابقه کامل'], [$profile['best'], 'بهترین امتیاز'], [$profile['accuracy'], 'دقت ٪']] as [$value, $label]): ?><div><dt><?= $escape($label) ?></dt><dd><?= $escape($fa($value)) ?></dd></div><?php endforeach ?>
                </dl>
                <p>عضویت: <time data-date="<?= $escape($profile['created_at']) ?>" datetime="<?= $escape(gmdate('c', (int) ($profile['created_at'] / 1000))) ?>"><?= $escape($fa(gmdate('Y/m/d', (int) ($profile['created_at'] / 1000)))) ?></time></p>
            </details>
        </form>
        <?php $latest = $profile['history'][0] ?? null; ?>
        <?php if ($latest && $history_page === 1): ?>
            <section class="kq-card kq-latest"><div><p>آخرین مسابقه · <?= $escape($latest['name']) ?></p><small><?= $latest['phase'] === 'technical_cancelled' ? 'لغو فنی · بدون ثبت برد' : 'رتبه ' . $escape($fa($latest['rank'])) . ' · ' . $escape($fa($latest['total'])) . ' دور' ?></small></div><a href="<?= $escape(PublicUrl::local(url('?match=' . rawurlencode((string) $latest['match_id']), 'kalaqpar.tolio.ir'))) ?>"><?= $escape($fa($latest['score'])) ?> امتیاز</a></section>
        <?php endif ?>
    <?php endif ?>
    <?php if ($message): ?><div class="kq-note kq-green" role="status"><strong><?= $escape($message) ?></strong></div><?php endif ?>
    <?php $this->fragment('fragments/account-section'); ?>
    <?php if (!$reset && $profile): ?>
        <details class="kq-history"<?= $history_page > 1 ? ' open' : '' ?>><summary>تاریخچه مسابقه‌ها</summary>
            <?php if (!$profile['history']): ?><p>هنوز مسابقه‌ای تمام نکرده‌ای.</p><?php endif ?>
            <?php foreach ($profile['history'] as $match): ?>
                <article class="kq-card"><h3><?= $escape($match['name']) ?></h3><p><?= $match['phase'] === 'technical_cancelled' ? 'لغو فنی · بدون ثبت برد' : 'رتبه ' . $escape($fa($match['rank'])) . ' · ' . $escape($fa($match['total'])) . ' دور' ?> · <?= $escape($fa($match['score'])) ?> امتیاز</p><p><time data-date="<?= $escape($match['ended_at']) ?>" datetime="<?= $escape(gmdate('c', (int) ($match['ended_at'] / 1000))) ?>"><?= $escape($fa(gmdate('Y/m/d', (int) ($match['ended_at'] / 1000)))) ?></time></p><a href="<?= $escape(PublicUrl::local(url('?match=' . rawurlencode((string) $match['match_id']), 'kalaqpar.tolio.ir'))) ?>">دیدن نتیجه</a></article>
            <?php endforeach ?>
            <?php if ($profile['history_pages'] > 1): ?><nav class="kq-pagination" aria-label="صفحه‌های تاریخچه"><?php if ($history_page > 1): ?><a href="<?= $escape(PublicUrl::local(url('account?history=' . ($history_page - 1), 'kalaqpar.tolio.ir'))) ?>">قبلی</a><?php endif ?><span><?= $escape($fa($history_page)) ?> / <?= $escape($fa($profile['history_pages'])) ?></span><?php if ($history_page < $profile['history_pages']): ?><a href="<?= $escape(PublicUrl::local(url('account?history=' . ($history_page + 1), 'kalaqpar.tolio.ir'))) ?>">بعدی</a><?php endif ?></nav><?php endif ?>
        </details>
        <nav id="kq-nav" aria-label="ناوبری اصلی"><a href="<?= $escape(PublicUrl::local(url('', 'kalaqpar.tolio.ir'))) ?>"><span aria-hidden="true">⌂</span>خانه</a><a href="<?= $escape(PublicUrl::local(url('?view=leaders', 'kalaqpar.tolio.ir'))) ?>"><span aria-hidden="true">♜</span>رتبه‌بندی</a><a class="active" aria-current="page" href="<?= $escape($accountUrl) ?>"><span aria-hidden="true">♙</span>پروفایل</a></nav>
    <?php else: ?><p class="kq-help"><a href="<?= $escape(PublicUrl::local(url('account?view=login#account', 'kalaqpar.tolio.ir'))) ?>">بازگشت به ورود</a> · <a href="<?= $escape(PublicUrl::local(url('', 'kalaqpar.tolio.ir'))) ?>">ادامه بازی</a></p><?php endif ?>
</main>
<?php $this->end() ?>

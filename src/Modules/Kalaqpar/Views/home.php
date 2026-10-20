<?php
/**
 * Lobby page: open rooms, create/join, profile, global leaderboard.
 * Data contract from LobbyHandler::home(): $rooms, $me, $csrf, $leaderboard.
 */
?>
<?php $this->start('title') ?>
کلاغ‌پر | Tolio Tools
<?php $this->end() ?>

<?php $this->start('content') ?>
<main class="container-xl py-4 klq-page">

    <header class="klq-hero text-center mb-4">
        <div class="klq-logo" aria-hidden="true">🐦‍⬛</div>
        <h1 class="h3 mb-1">کلاغ‌پر</h1>
        <p class="text-secondary mb-0 small">
            بازی عمومی حدس کلمه‌ی فارسی؛ حرف‌های کلمه مثل پرهای کلاغ یکی‌یکی پیدا می‌شوند و
            هر که زودتر کلمه را بگوید امتیاز بیشتر می‌برد.
        </p>
    </header>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="card klq-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">اتاق‌های باز</h2>

                    <div id="klq-rooms" class="klq-rooms list-unstyled mb-0"
                         data-rooms="<?= $this->e(json_encode(
                             array_map(static fn (array $r): array => [
                                 'code' => $r['code'],
                                 'name' => $r['name'],
                                 'status' => $r['status'],
                                 'rounds_total' => $r['rounds_total'],
                                 'current_round_number' => $r['current_round_number'],
                                 'players' => $r['player_count'],
                             ], $rooms),
                             JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                         )) ?>">
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="card klq-card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">نام بازیکن</h2>
                    <form id="klq-profile-form" autocomplete="off" novalidate>
                        <div class="input-group">
                            <input type="text" class="form-control" id="klq-profile-name"
                                   name="display_name" maxlength="24"
                                   value="<?= $this->e((string) $me['display_name']) ?>"
                                   aria-label="نام بازیکن" required>
                            <button class="btn btn-warning klq-btn" type="submit">ذخیره</button>
                        </div>
                        <p class="form-text mb-0 small">۲ تا ۲۴ حرف. این نام برای همه بازیکنان دیده می‌شود.</p>
                    </form>
                </div>
            </section>

            <section class="card klq-card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">اتاق جدید</h2>
                    <form id="klq-create-form" autocomplete="off" novalidate>
                        <label class="form-label small mb-1" for="klq-create-name">نام اتاق</label>
                        <input type="text" class="form-control mb-2" id="klq-create-name"
                               name="name" maxlength="40" placeholder="مثلاً: بازی با رفقا">
                        <label class="form-label small mb-1" for="klq-create-rounds">تعداد دورها</label>
                        <select class="form-select mb-3" id="klq-create-rounds" name="rounds">
                            <option value="3">۳ دور</option>
                            <option value="5" selected>۵ دور</option>
                            <option value="7">۷ دور</option>
                        </select>
                        <button class="btn btn-warning klq-btn w-100" type="submit">ساخت اتاق و پیوستن</button>
                    </form>
                </div>
            </section>

            <section class="card klq-card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">پیوستن با کد</h2>
                    <form id="klq-join-form" autocomplete="off" novalidate>
                        <div class="input-group">
                            <input type="text" class="form-control klq-code-input text-center"
                                   id="klq-join-code" name="room" maxlength="6"
                                   placeholder="کد ۶حرفی اتاق" aria-label="کد اتاق" required>
                            <button class="btn btn-warning klq-btn" type="submit">پیوستن</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card klq-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">جدول امتیاز کل</h2>
                    <?php if ($leaderboard === []): ?>
                        <p class="text-secondary small mb-0">
                            هنوز امتیازی ثبت نشده؛ اولین برد را تو بزن!
                        </p>
                    <?php else: ?>
                        <table class="table table-dark table-sm mb-0 align-middle klq-table">
                            <caption class="small text-secondary">ده بازیکن برتر همه‌ی اتاق‌ها</caption>
                            <thead>
                            <tr><th scope="col" class="ps-3">#</th><th scope="col">بازیکن</th><th scope="col">برد</th><th scope="col">امتیاز</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($leaderboard as $index => $row): ?>
                                <tr>
                                    <td class="klq-rank<?= $index === 0 ? ' klq-gold' : '' ?>"><?= $index + 1 ?></td>
                                    <td><?= $this->e((string) $row['display_name']) ?></td>
                                    <td><?= (int) $row['wins'] ?></td>
                                    <td class="fw-bold"><?= (int) $row['total'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <footer class="text-center text-secondary small mt-5">
        کلاغ‌پر — بازی مهمان (بدون نیاز به ثبت‌نام). به کارنامه‌ی ابزارهای دیگر سایت ربطی ندارد.
    </footer>
</main>

<script>
    window.__KALAQPAR__ = {
        mode: 'lobby',
        csrf: <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        guest: <?= json_encode((int) $me['id'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        strings: {
            join: 'پیوستن',
            empty: 'هنوز اتاقی باز نشده؛ یکی تو بساز!'
        }
    };
</script>
<?php $this->end() ?>

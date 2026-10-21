<?php
/**
 * Room page (the game board). Data contract from LobbyHandler::room():
 * $room (row), $state (GameState::forRoom snapshot), $me, $csrf, $justJoined.
 * The secret word never reaches this template; only revealed letters do.
 */
$room = $state['room'];
?>
<?php $this->start('title') ?>
<?= $this->e((string) $room['name']) ?> | کلاغ‌پر
<?php $this->end() ?>

<?php $this->start('content') ?>
<main class="container-xl py-3 py-md-4 klq-page" id="klq-game"
      data-room-code="<?= $this->e((string) $room['code']) ?>">

    <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-light klq-back" href="/guessing" aria-label="بازگشت به برگه‌ی اصلی">بازگشت</a>
            <h1 class="h5 mb-0"><?= $this->e((string) $room['name']) ?></h1>
            <span class="badge klq-badge" id="klq-room-code" title="این کد را برای دعوت دوستان بفرست"><?= $this->e((string) $room['code']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3 small">
            <span id="klq-round-meta" class="text-secondary">
                دور <bdi id="klq-round-number">–</bdi> از <?= (int) $room['rounds_total'] ?>
            </span>
            <span id="klq-timer" class="klq-timer" aria-live="off">—</span>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-12 col-lg-8 order-lg-1 order-2">
            <section class="card klq-card">
                <div class="card-body d-flex flex-column align-items-center justify-content-center"
                     style="min-height: 18rem">

                    <!-- Status / feedback line -->
                    <p class="klq-status mb-3" id="klq-status" role="status">
                        <?php if ($state['ok'] && $room['status'] === 'lobby'): ?>
                            آماده‌ی بازی! بازیکن‌ها بپیوندند، میزبان شروع می‌کند.
                        <?php elseif ($state['ok'] && $room['status'] === 'closed'): ?>
                            این اتاق بسته شده است.
                        <?php endif; ?>
                    </p>

                    <!-- Word slots -->
                    <div class="klq-word" id="klq-word" dir="rtl" aria-label="کلمه‌ی پنهان"></div>

                    <!-- Guess composer -->
                    <form id="klq-guess-form" class="klq-guess mt-4 w-100" autocomplete="off" novalidate>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg klq-guess-input"
                                   id="klq-guess-input" dir="rtl" maxlength="64"
                                   placeholder="حدسی که می‌خواهی بزنی؟" aria-label="حدس خودت" disabled>
                            <button class="btn btn-warning btn-lg klq-btn" type="submit" id="klq-guess-btn" disabled>
                                بگو!
                            </button>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-secondary">
                            <span id="klq-guess-budget">حدس‌های باقی‌مانده: —</span>
                            <span id="klq-reveals">حرف‌های پیدا شده: ۰</span>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-4 order-lg-2 order-1">
            <section class="card klq-card mb-3">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">بازیکنان اتاق</h2>
                        <button class="btn btn-sm btn-outline-light klq-btn-ghost" id="klq-leave-btn" type="button">
                            ترک اتاق
                        </button>
                    </div>
                    <ul class="list-unstyled mb-0 klq-players" id="klq-players"></ul>
                    <div class="mt-3 d-grid" id="klq-host-controls">
                        <button class="btn btn-warning klq-btn" type="button" id="klq-start-btn" disabled>
                            شروع بازی
                        </button>
                    </div>
                    <p class="small text-secondary mb-0 mt-2" id="klq-join-hint"></p>
                </div>
            </section>

            <section class="card klq-card">
                <div class="card-body py-3">
                    <h2 class="h6 mb-2">امتیاز این اتاق</h2>
                    <ol class="mb-0 small klq-room-scores" id="klq-room-scores" aria-live="polite"></ol>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
    window.__GUESSING__ = {
        mode: 'room',
        roomCode: <?= json_encode((string) $room['code'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        initialVersion: <?= json_encode((int) $room['version'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        me: <?= json_encode((int) $me['id'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        justJoined: <?= json_encode((bool) $justJoined, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        strings: {
            waiting: 'منتظر دور بعد…',
            youWin: 'درست بود! چیزی که می‌خواستیم.',
            correct: 'درست زدی! منتظر شروع دور بعدی باش.',
            solving: 'یک نفر کلمه را ساخت!',
                        joinAuto: 'به اتاق پیوستی؛ منتظر شروع بازی باش.',
            closed: 'این اتاق بسته شده است.',
            timeLeft: 'زمان باقی‌مانده',
            roundStarted: 'دور شروع شد! حرف‌ها دونه‌دونه پیدا می‌شوند.'
        }
    };
</script>
<?php $this->end() ?>

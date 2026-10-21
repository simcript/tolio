<?php /** Module error page (missing rooms). Contract: $message. */ ?>
<?php $this->start('title') ?>
کلاغ‌پر | خطا
<?php $this->end() ?>

<?php $this->start('content') ?>
<main class="container py-5 text-center klq-page">
    <div class="klq-logo" aria-hidden="true">🐦‍⬛</div>
    <h1 class="h4 mt-3">کلاغ‌پر</h1>
    <p class="text-secondary"><?= $this->e((string) $message) ?></p>
    <a class="btn btn-warning klq-btn mt-2" href="/guessing">بازگشت به برگه‌ی اصلی</a>
</main>
<?php $this->end() ?>

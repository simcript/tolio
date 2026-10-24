<?php
namespace Src\Modules\Kalaqpar\Services;

use PHPMailer\PHPMailer\PHPMailer;

final class AccountMailer
{
    public function __construct(private array $settings) {}
    public function configured(): bool
    {
        $s = $this->settings;
        $transport = [(int) ($s['smtp_port'] ?? 465), $s['smtp_encryption'] ?? 'smtps'];
        return ($s['recovery_enabled'] ?? false) && class_exists(PHPMailer::class) && extension_loaded('openssl') &&
            in_array($transport, [[465, 'smtps'], [25, 'starttls']], true) &&
            is_string($s['smtp_password'] ?? null) && $s['smtp_password'] !== '' && $s['smtp_password'] !== 'replace-with-hosting-secret' &&
            is_string($s['smtp_host'] ?? null) && preg_match('/^[a-z0-9.-]+$/iD', $s['smtp_host']) &&
            filter_var($s['smtp_username'] ?? '', FILTER_VALIDATE_EMAIL) &&
            filter_var($s['mail_from'] ?? '', FILTER_VALIDATE_EMAIL) &&
            !preg_match('/[\r\n\x00]/', ($s['mail_from_name'] ?? '') . $s['smtp_username'] . $s['mail_from']);
    }
    public function send(string $email, string $url): void
    {
        if (!$this->configured()) { throw new \RuntimeException('Recovery SMTP unavailable'); }
        $s = $this->settings;
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $s['smtp_host'];
        $mail->Port = (int) $s['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $s['smtp_username'];
        $mail->Password = $s['smtp_password'];
        $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = false; // Explicit mandatory transport; never downgrade or try another port.
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false, 'peer_name' => $s['smtp_host']]];
        $mail->SMTPDebug = 0;
        $mail->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 20;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($s['mail_from'], $s['mail_from_name']);
        $mail->addAddress(Accounts::email($email));
        $mail->Subject = 'بازیابی رمز عبور کلاغ‌پر';
        $text = "برای انتخاب رمز عبور تازه، پیوند زیر را باز کنید. این پیوند فقط ۱۵ دقیقه اعتبار دارد و یک بار قابل استفاده است.\n\n" . $url .
            "\n\nاگر درخواست بازیابی نداده‌اید، این پیام را نادیده بگیرید. رمز فعلی بدون استفاده از پیوند تغییر نمی‌کند. این پیام تأیید مالکیت ایمیل نیست.\nکلاغ‌پر";
        $mail->isHTML(true);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f8f5ed;color:#262535;font-family:Tahoma,sans-serif;line-height:1.9">' .
            '<div style="max-width:480px;margin:24px auto;padding:28px;background:#fff;border-radius:22px;text-align:right">' .
            '<p style="color:#80808e">کلاغ‌پر · بازیابی رمز عبور</p><div style="text-align:center"><span style="display:inline-block;border-radius:50%;background:#eee8ff;padding:12px;font-size:32px" aria-hidden="true">↑</span>' .
            '<h1 style="font-size:22px">برگرد به بازی!</h1></div><p>سلام!<br>برای حساب تو در کلاغ‌پر درخواست تغییر رمز ثبت شده. برای انتخاب رمز جدید، دکمهٔ زیر را بزن.</p>' .
            '<p style="text-align:center;margin:26px 0"><a href="' . $safeUrl . '" style="display:block;padding:12px;background:#6948ff;color:#fff;border-radius:12px;text-decoration:none">انتخاب رمز عبور جدید</a></p>' .
            '<p style="text-align:center;color:#80808e">این لینک فقط ۱۵ دقیقه اعتبار دارد و یک بار قابل استفاده است.</p>' .
            '<div style="background:#fff5d2;border-radius:12px;padding:16px">اگر درخواست بازیابی نداده‌ای، این پیام را نادیده بگیر. رمز فعلی بدون استفاده از لینک تغییر نمی‌کند.</div>' .
            '<p style="color:#80808e;font-size:12px">این پیام تأیید مالکیت ایمیل نیست.<br>کلاغ‌پر · با هم بیشتر خوش می‌گذره</p></div></body></html>';
        $mail->AltBody = $text;
        if (!$mail->send()) { throw new \RuntimeException('SMTP delivery failed'); }
    }
}

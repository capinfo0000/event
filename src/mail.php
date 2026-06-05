<?php

/**
 * メール送信ヘルパー（最小実装）。
 *
 * PHP の mail() を使い、併せて logs/mail.log にも追記する（運用・開発確認用）。
 * 本番で確実に届けたい場合は、サーバーの MTA 設定、または SMTP/外部送信サービスへの
 * 差し替えを推奨（この関数 1 か所を変えれば全体に反映される）。
 */

declare(strict_types=1);

/**
 * メールを送信する。送信可否に関わらず logs/mail.log に記録する。
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $fromAddr = env('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = env('MAIL_FROM_NAME', 'イベント事前決済');

    // 監査・開発用にログへ記録（個人情報を含むため logs/ は公開領域外・gitignore）
    $logLine = sprintf("[%s] to=%s subject=%s\n%s\n---\n", date('c'), $to, $subject, $body);
    @file_put_contents(APP_ROOT . '/logs/mail.log', $logLine, FILE_APPEND | LOCK_EX);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $encodedFromName . ' <' . $fromAddr . '>',
    ];

    // CLI やメール未設定環境では mail() が失敗することがあるが、ログには残る
    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

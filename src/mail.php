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

    // 監査・開発用にログへ記録（個人情報を含むため logs/ は公開領域外・gitignore）。
    // ただしパスワード再設定リンク等の秘密トークンはログに残さない（漏洩時の悪用防止）。
    $logBody = mask_secrets_for_log($body);
    $logLine = sprintf("[%s] to=%s subject=%s\n%s\n---\n", date('c'), $to, $subject, $logBody);
    $logPath = APP_ROOT . '/logs/mail.log';
    rotate_log_if_large($logPath);
    @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

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

/**
 * ログ記録用に秘密情報をマスクする。
 * - パスワード再設定リンク（reset.php?token=...）や ?token=/&token= の値を伏せ字に。
 */
function mask_secrets_for_log(string $text): string
{
    // クエリの token=... を伏せる（URL中・本文中いずれも）
    $text = preg_replace('/([?&]token=)[^\s&"\']+/i', '$1***', $text);
    // reset.php への完全なリンクを伏せる（token を付けない形でも）
    $text = preg_replace('#https?://\S*reset\.php\S*#i', '[再設定リンク（ログ非記録）]', $text);
    return $text;
}

/**
 * ログファイルが上限を超えていたら 1 世代だけローテーションする（.1 に退避）。
 * 上限は MAIL_LOG_MAX_BYTES（既定 5MB）。0 以下なら無効。
 */
function rotate_log_if_large(string $path): void
{
    $max = (int) env('MAIL_LOG_MAX_BYTES', '5242880'); // 5MB
    if ($max <= 0 || !is_file($path)) {
        return;
    }
    if (filesize($path) >= $max) {
        @rename($path, $path . '.1'); // 既存の .1 は上書き（保持は1世代）
    }
}

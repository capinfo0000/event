<?php

/**
 * TOTP（RFC 6238）による2段階認証。外部ライブラリ非依存の最小実装。
 * 秘密鍵は base32 文字列で扱い、DB には APP_KEY で暗号化して保存する（src/crypto.php）。
 */

declare(strict_types=1);

/** バイト列を base32（RFC 4648, パディング無し）へ。 */
function base32_encode_bytes(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $bits = 0;
    $val = 0;
    $len = strlen($bytes);
    for ($i = 0; $i < $len; $i++) {
        $val = ($val << 8) | ord($bytes[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $out .= $alphabet[($val >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($val << (5 - $bits)) & 31];
    }
    return $out;
}

/** base32 文字列をバイト列へ。 */
function base32_decode_str(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $out = '';
    $bits = 0;
    $val = 0;
    $len = strlen($b32);
    for ($i = 0; $i < $len; $i++) {
        $val = ($val << 5) | strpos($alphabet, $b32[$i]);
        $bits += 5;
        if ($bits >= 8) {
            $out .= chr(($val >> ($bits - 8)) & 0xFF);
            $bits -= 8;
        }
    }
    return $out;
}

/** 新しい TOTP 秘密鍵（base32・160bit）を生成する。 */
function totp_generate_secret(): string
{
    return base32_encode_bytes(random_bytes(20));
}

/** 指定時刻の6桁コードを返す。 */
function totp_code(string $secretB32, ?int $time = null, int $period = 30, int $digits = 6): string
{
    $time = $time ?? time();
    $key = base32_decode_str($secretB32);
    $counter = intdiv($time, $period);
    $bin = pack('N*', 0, $counter); // 8バイト（上位32bitは0）
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
    $part = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    $code = $part % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

/** コードを検証（前後 $window ステップの時刻ずれを許容）。 */
function totp_verify(string $secretB32, string $code, int $window = 1): bool
{
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $t = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretB32, $t + $i * 30), $code)) {
            return true;
        }
    }
    return false;
}

/** 認証アプリ登録用の otpauth:// URI。 */
function totp_uri(string $secretB32, string $account, string $issuer = 'イベント決済'): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secretB32
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

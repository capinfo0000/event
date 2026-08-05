<?php

/**
 * 旧：キャンセルポリシー単独編集ページ。
 * 規約・ポリシー設定（legal_edit.php）に統合したため、そちらへ転送する。
 * 既存のブックマーク・リンク互換のために残している。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_tenant();
header('Location: legal_edit.php#sec-cancel');
exit;

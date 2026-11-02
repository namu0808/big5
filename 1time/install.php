<?php
/**
 * 일회성 설치 스크립트 — 서버의 /big5/ 폴더(앱 루트)에 올려 브라우저로 1회 실행한 뒤 반드시 삭제한다.
 *   (저장소에서는 1time/ 에 보관. 업로드 위치는 /big5/install.php)
 * 하는 일: 모든 big5_ 테이블 생성(멱등), 초기 계정(sonbm=관리자 / user=사용자, 비밀번호 visang),
 *          includes/·data/·tests/ 의 .htaccess(Deny from all) 생성.
 * CLI 실행도 된다: php install.php
 */
declare(strict_types=1);

// 저장소의 1time/ 에서 직접 실행해도, 앱 루트로 옮겨 실행해도 동작하게 앱 루트를 찾는다
$big5Root = is_dir(__DIR__ . '/includes') ? __DIR__ : dirname(__DIR__);

require_once $big5Root . '/includes/db_config.php';
require_once $big5Root . '/includes/util.php';
require_once $big5Root . '/includes/big5_schema.php';

ini_set('display_errors', '0');

$ok = true;
$messages = [];

if ($pdo === null) {
    $ok = false;
    $messages[] = 'DB에 연결할 수 없습니다. 웹루트 밖 {홈}/config/big5_db.php 설정을 확인한 뒤 다시 실행해 주세요.';
} else {
    try {
        $tables = big5_ensure_schema($pdo);
        $messages[] = '테이블 준비 완료: ' . implode(', ', $tables);
        $messages = array_merge($messages, big5_seed_accounts($pdo));
    } catch (Throwable $e) {
        error_log('다이얼 install 오류: ' . $e->getMessage());
        $ok = false;
        $messages[] = '테이블·계정 준비 중 오류가 발생했습니다. 서버 오류 로그를 확인해 주세요.';
    }
}

foreach (['includes', 'data', 'tests'] as $dir) {
    $path = $big5Root . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    if (@file_put_contents($path . '/.htaccess', "Deny from all\n") === false) {
        error_log("다이얼 install: {$dir}/.htaccess 쓰기 실패");
        $ok = false;
        $messages[] = "{$dir}/.htaccess 파일을 만들 수 없습니다. 폴더 권한을 확인해 주세요.";
    } else {
        $messages[] = "{$dir}/.htaccess 생성(갱신)";
    }
}

if (PHP_SAPI === 'cli') {
    foreach ($messages as $m) {
        echo $m, PHP_EOL;
    }
    echo $ok ? '설치 완료' : '설치 실패', PHP_EOL;
    exit($ok ? 0 : 1);
}
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>다이얼 설치</title></head>
<body style="font-family:system-ui,sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.7;color:#222">
<h1 style="font-size:1.3rem">다이얼 설치 <?= $ok ? '완료' : '실패' ?></h1>
<ul>
<?php foreach ($messages as $m): ?>
  <li><?= h($m) ?></li>
<?php endforeach; ?>
</ul>
<?php if ($ok): ?>
<p><strong>이 install.php 파일을 서버에서 바로 삭제해 주세요.</strong> 그다음 초기 계정(sonbm, user)의 비밀번호를 바꿔 주세요.</p>
<p><a href="<?= h(big5_url('login.php')) ?>">로그인 화면으로</a> · <a href="<?= h(big5_url('admin/login.php')) ?>">관리자 로그인</a></p>
<?php endif; ?>
</body></html>

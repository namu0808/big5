<?php
/**
 * CLI 일괄 검증 — 모든 PHP 문법 검사(php -l) + tests/*_test.php 실행.
 * 실행: php tests/run.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;

echo "== PHP 문법 검사\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || str_contains($p, '/.git/') || str_contains($p, '/node_modules/')) {
        continue;
    }
    $n++;
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $fail++;
        echo '  FAIL  ' . substr($p, strlen($root) + 1) . "\n" . implode("\n", $out) . "\n";
    }
    $out = [];
}
echo "  {$n}개 파일 확인\n";

foreach (glob(__DIR__ . '/*_test.php') as $t) {
    echo "\n== " . basename($t) . "\n";
    passthru('php ' . escapeshellarg($t), $code);
    if ($code !== 0) {
        $fail++;
    }
}

echo "\n" . ($fail === 0 ? '전체 통과' : "실패 {$fail}건") . "\n";
exit($fail === 0 ? 0 : 1);

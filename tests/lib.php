<?php
/** CLI 테스트 공통 — t_ok(조건, 이름), t_done() */
declare(strict_types=1);

$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = 0;

function t_ok(bool $cond, string $name): void
{
    if ($cond) {
        $GLOBALS['t_pass']++;
        echo "  PASS  {$name}\n";
    } else {
        $GLOBALS['t_fail']++;
        echo "  FAIL  {$name}\n";
    }
}

function t_done(): void
{
    $p = $GLOBALS['t_pass'];
    $f = $GLOBALS['t_fail'];
    echo "\n결과: PASS {$p} / FAIL {$f}\n";
    exit($f === 0 ? 0 : 1);
}

<?php
/**
 * 리포트 차트 — 외부 라이브러리 없이 서버에서 만든다 (승인된 변경 3).
 *  - big5_bar_html(): 구간 배경(낮은 쪽·가운데·높은 쪽)이 깔린 가로 막대 (HTML/CSS)
 *  - big5_radar_svg(): 다섯 요인 레이더 (인라인 SVG) — 인쇄·PDF 에 그대로 찍힌다
 * 모든 글자는 h() 로 이스케이프한다.
 */
declare(strict_types=1);

/** 0~100 막대. $size: 'lg'(요인) | 'sm'(하위 요인) */
function big5_bar_html(float $pomp, string $color, string $label, string $size = 'lg', ?float $compare = null): string
{
    $p = max(0.0, min(100.0, $pomp));
    $html = '<div class="dial-bar dial-bar-' . h($size) . '" role="img" aria-label="' . h($label . ' ' . number_format($p, 1) . '점') . '">'
        . '<span class="zone zone-low"></span><span class="zone zone-mid"></span><span class="zone zone-high"></span>'
        . '<span class="dial-fill" style="width:' . h(number_format($p, 1, '.', '')) . '%;background:' . h($color) . '"></span>'
        . '<span class="dial-mark" style="left:' . h(number_format($p, 1, '.', '')) . '%;border-color:' . h($color) . '"></span>';
    if ($compare !== null) {
        $c = max(0.0, min(100.0, $compare));
        $html .= '<span class="dial-compare" style="left:' . h(number_format($c, 1, '.', '')) . '%"></span>';
    }
    return $html . '</div>';
}

/**
 * 레이더. $points = [['label' => '성실성', 'value' => 0~100, 'color' => '#..'], ...] (시계 방향, 12시부터)
 */
function big5_radar_svg(array $points, int $size = 320): string
{
    $n = count($points);
    if ($n < 3) {
        return '';
    }
    $cx = $size / 2;
    $cy = $size / 2;
    $r = $size / 2 - 52; // 바깥 글자 자리
    $pt = static function (int $i, float $v) use ($n, $cx, $cy, $r): array {
        $a = -M_PI / 2 + 2 * M_PI * $i / $n;
        return [$cx + cos($a) * $r * $v / 100, $cy + sin($a) * $r * $v / 100];
    };
    $f = static fn(float $x): string => number_format($x, 1, '.', '');
    $poly = static function (float $v) use ($n, $pt, $f): string {
        $s = [];
        for ($i = 0; $i < $n; $i++) {
            [$x, $y] = $pt($i, $v);
            $s[] = $f($x) . ',' . $f($y);
        }
        return implode(' ', $s);
    };
    $svg = '<svg class="radar" viewBox="0 0 ' . $size . ' ' . $size . '" width="100%" role="img" aria-label="다섯 다이얼 레이더 차트" xmlns="http://www.w3.org/2000/svg">';
    // 구간 경계(35·65)와 바깥선(100)
    $svg .= '<polygon points="' . $poly(100) . '" class="radar-ring radar-outer"/>';
    $svg .= '<polygon points="' . $poly(65) . '" class="radar-ring"/>';
    $svg .= '<polygon points="' . $poly(35) . '" class="radar-ring"/>';
    for ($i = 0; $i < $n; $i++) {
        [$x, $y] = $pt($i, 100);
        $svg .= '<line x1="' . $f($cx) . '" y1="' . $f($cy) . '" x2="' . $f($x) . '" y2="' . $f($y) . '" class="radar-axis"/>';
    }
    $vals = [];
    foreach (array_values($points) as $i => $p) {
        [$x, $y] = $pt($i, (float) $p['value']);
        $vals[] = $f($x) . ',' . $f($y);
    }
    $svg .= '<polygon points="' . implode(' ', $vals) . '" class="radar-shape"/>';
    foreach (array_values($points) as $i => $p) {
        [$x, $y] = $pt($i, (float) $p['value']);
        $svg .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r="4.5" fill="' . h((string) $p['color']) . '"/>';
        [$lx, $ly] = $pt($i, 124);
        $anchor = abs($lx - $cx) < 8 ? 'middle' : ($lx > $cx ? 'start' : 'end');
        $svg .= '<text x="' . $f($lx) . '" y="' . $f($ly) . '" text-anchor="' . $anchor . '" class="radar-label">'
            . '<tspan x="' . $f($lx) . '" dy="-0.2em">' . h((string) $p['label']) . '</tspan>'
            . '<tspan x="' . $f($lx) . '" dy="1.2em" class="radar-value">' . h(number_format((float) $p['value'], 1)) . '</tspan></text>';
    }
    return $svg . '</svg>';
}

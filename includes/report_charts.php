<?php
/**
 * 리포트 차트 — 외부 라이브러리 없이 서버에서 만든다 (승인된 변경 3).
 *  - big5_bar_html(): 구간 배경(낮은 쪽·가운데·높은 쪽)이 깔린 가로 막대 (HTML/CSS)
 *  - big5_radar_svg(): 다섯 요인 레이더 (인라인 SVG) — 인쇄·PDF 에 그대로 찍힌다. 두 번째 계열(점선)을 겹칠 수 있다
 *  - big5_dumbbell_html(): 나(채운 점)와 남(빈 점)을 한 줄에 놓고 선으로 잇는 '아령' 그림 (9장)
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
 * 아령 그림 (9장). $row = big5_others_section() 의 rows[요인].
 *  구간 배경 위에: 남들 점수의 범위 띠(2명 이상) → 나와 남을 잇는 선 → 나(채운 점)·남(빈 점).
 *  $showRelations 이면 아래 줄에 관계별 평균을 글자 표식(가·친·동·기)으로 놓는다.
 */
function big5_dumbbell_html(array $row, string $color, string $label, bool $showRelations = false): string
{
    $clip = static fn($v): float => max(0.0, min(100.0, (float) $v));
    $pc = static fn(float $v): string => number_format($v, 1, '.', '');
    $me = $clip($row['self']);
    $ot = $clip($row['others']);
    $aria = $label . ': 나 ' . number_format($me, 1) . '점, 남 ' . number_format($ot, 1) . '점';
    $html = '<div class="db" role="img" aria-label="' . h($aria) . '" style="--c:' . h($color) . '">'
        . '<div class="db-track">'
        . '<span class="zone zone-low"></span><span class="zone zone-mid"></span><span class="zone zone-high"></span>';
    $lo = $clip($row['min'] ?? $ot);
    $hi = $clip($row['max'] ?? $ot);
    if ($hi > $lo) {
        $html .= '<span class="db-range" style="left:' . $pc($lo) . '%;width:' . $pc($hi - $lo) . '%"></span>';
    }
    $html .= '<span class="db-link" style="left:' . $pc(min($me, $ot)) . '%;width:' . $pc(abs($ot - $me)) . '%"></span>'
        . '<span class="db-dot db-others" style="left:' . $pc($ot) . '%"></span>'
        . '<span class="db-dot db-self" style="left:' . $pc($me) . '%"></span>'
        . '</div>';
    if ($showRelations && !empty($row['by_relation'])) {
        // 점수가 가까운 관계(3.5점 안)는 한 표식에 글자를 나란히 적는다 — 겹쳐서 가려지지 않게
        $rels = [];
        foreach ($row['by_relation'] as $key => $g) {
            $rels[] = ['keys' => [(string) $key], 'pos' => $clip($g['avg']), 'letters' => mb_substr((string) $g['label'], 0, 1),
                'title' => $g['label'] . ' ' . number_format((float) $g['avg'], 1)];
        }
        usort($rels, static fn($a, $b) => $a['pos'] <=> $b['pos']);
        $groups = [];
        foreach ($rels as $r) {
            $last = count($groups) - 1;
            if ($last >= 0 && $r['pos'] - $groups[$last]['end'] < 3.5) {
                $groups[$last]['keys'] = array_merge($groups[$last]['keys'], $r['keys']);
                $groups[$last]['letters'] .= '·' . $r['letters'];
                $groups[$last]['title'] .= ', ' . $r['title'];
                $groups[$last]['end'] = $r['pos'];
            } else {
                $groups[] = $r + ['start' => $r['pos'], 'end' => $r['pos']];
            }
        }
        $html .= '<div class="db-rels">';
        foreach ($groups as $g) {
            $cls = implode(' ', array_map(static fn($k) => 'db-rel-' . $k, $g['keys']));
            $html .= '<span class="db-rel ' . h($cls) . '" style="left:' . $pc(($g['start'] + $g['end']) / 2) . '%" title="' . h($g['title']) . '">' . h($g['letters']) . '</span>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/**
 * 레이더. $points = [['label' => '성실성', 'value' => 0~100, 'color' => '#..'], ...] (시계 방향, 12시부터)
 * $compare = 같은 순서의 두 번째 계열 값(점선 윤곽). 주면 바깥 글자에서 점수는 뺀다(두 점수는 아래 그림에서 읽는다).
 */
function big5_radar_svg(array $points, int $size = 320, ?array $compare = null): string
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
    if ($compare !== null && count($compare) === $n) {
        $cv = [];
        $dots = '';
        foreach (array_values($compare) as $i => $v) {
            [$x, $y] = $pt($i, max(0.0, min(100.0, (float) $v)));
            $cv[] = $f($x) . ',' . $f($y);
            $dots .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r="4" class="radar-dot2"/>';
        }
        $svg .= '<polygon points="' . implode(' ', $cv) . '" class="radar-shape2"/>' . $dots;
    } else {
        $compare = null;
    }
    foreach (array_values($points) as $i => $p) {
        [$x, $y] = $pt($i, (float) $p['value']);
        $svg .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r="4.5" fill="' . h((string) $p['color']) . '"/>';
        [$lx, $ly] = $pt($i, 124);
        $anchor = abs($lx - $cx) < 8 ? 'middle' : ($lx > $cx ? 'start' : 'end');
        $svg .= '<text x="' . $f($lx) . '" y="' . $f($ly) . '" text-anchor="' . $anchor . '" class="radar-label">'
            . '<tspan x="' . $f($lx) . '" dy="' . ($compare === null ? '-0.2em' : '0.35em') . '">' . h((string) $p['label']) . '</tspan>'
            . ($compare === null ? '<tspan x="' . $f($lx) . '" dy="1.2em" class="radar-value">' . h(number_format((float) $p['value'], 1)) . '</tspan>' : '')
            . '</text>';
    }
    return $svg . '</svg>';
}

<?php
/**
 * 찾을 수 없음(404) 화면 — 남의 검사 id 처럼 볼 권한이 없는 경우에도 같은 화면을 보여 준다
 * (있는지 없는지 알려 주지 않는다). 호출 후 종료.
 */
declare(strict_types=1);

function big5_not_found(): void
{
    global $pdo, $layout_area; // 관리자 화면에서 부르면 관리자 머리말 그대로
    http_response_code(404);
    $page_title = '찾을 수 없음';
    require __DIR__ . '/header.php';
    if (($layout_area ?? 'user') === 'admin') { // 관리자 화면 규격(0.15.0)
        echo '<div class="adm"><header class="adm-head"><div class="adm-head-text"><h1 class="adm-title">찾을 수 없습니다</h1>'
            . '<p class="adm-lead">주소가 바르지 않거나 볼 수 없는 기록입니다.</p></div></header>'
            . '<p class="adm-links"><a class="adm-btn adm-btn-sm" href="' . h(big5_url('admin/index.php')) . '">한눈에 보기</a></p></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    echo '<section class="form-card"><h1>찾을 수 없습니다</h1><p>주소가 바르지 않거나 볼 수 없는 기록입니다.</p>'
        . '<p><a href="' . h(big5_url('index.php')) . '">처음으로</a></p></section>';
    require __DIR__ . '/footer.php';
    exit;
}

<?php
/**
 * SBM 공통 계정 서비스 훅 — 사이트 공통 /account/ 가 부른다(core/services.php 에 등록).
 * 세션·화면 없이 PDO 만으로 동작한다. 이 파일은 bootstrap 을 싣지 않는다.
 */
declare(strict_types=1);

require_once __DIR__ . '/user_delete.php';

/** 내 계정 화면의 한 줄 요약 (기록이 없으면 null) */
function big5_sbm_summary(PDO $pdo, int $userId): ?string
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM big5_sessions WHERE user_id = ? AND status = 'completed'");
    $st->execute([$userId]);
    $done = (int) $st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id WHERE i.user_id = ? AND r.status = 'completed'");
    $st->execute([$userId]);
    $rated = (int) $st->fetchColumn();
    if ($done === 0 && $rated === 0) {
        return null;
    }
    $parts = ["완료한 검사 {$done}회"];
    if ($rated > 0) {
        $parts[] = "받은 타인 평가 {$rated}건";
    }
    return implode(' · ', $parts);
}

/** 계정 전체 삭제 때 — 기록은 은퇴한 계정에 남기고(실제 삭제 없음) 초대 링크만 만료 (트랜잭션은 공통 계정 쪽) */
function big5_sbm_delete(PDO $pdo, int $userId): void
{
    big5_user_detach_data($pdo, $userId);
}

/**
 * 통합 관리자(sbm.ai.kr /admin/) 첫 화면 숫자 — 다이얼 묶음(0.12.0). 가벼운 COUNT 만, 숫자만.
 * 쿼리는 includes/admin_stats.php big5_admin_counts() 와 같다(회원 수는 통합 관리자 '회원' 묶음이 센다).
 * $since = 최근 7일 기준 시각(KST), $base = 앱 주소('/big5/'). 이 파일은 bootstrap·util 을 싣지 않는다.
 */
function big5_sbm_admin_summary(PDO $pdo, string $since, string $base = '/big5/'): array
{
    require_once __DIR__ . '/version.php';
    $base = rtrim($base, '/') . '/';
    $one = static function (string $sql, array $p = []) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    };
    $n = static fn(int $v): string => number_format($v);
    $full = $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = 'full120'");
    $mini = $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND test_type = 'mini20'");
    $last7 = $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'completed' AND completed_at >= ?", [$since]);
    $going = $one("SELECT COUNT(*) FROM big5_sessions WHERE status = 'in_progress'");
    $ratings = $one("SELECT COUNT(*) FROM big5_ratings WHERE status = 'completed'");
    return [
        'version' => BIG5_VERSION,
        'groups' => [[
            'title' => '다이얼',
            'tiles' => [
                ['label' => '정식 검사 완료(120문항)', 'value' => $n($full)],
                ['label' => '간이 검사 완료(20문항)', 'value' => $n($mini)],
                ['label' => '최근 7일 완료', 'value' => $n($last7), 'note' => '진행 중 ' . $n($going)],
                ['label' => '타인 평가 완료', 'value' => $n($ratings)],
            ],
            'links' => [
                ['label' => '다이얼 관리', 'url' => $base . 'admin/'],
                ['label' => '내보내기(CSV)', 'url' => $base . 'admin/export.php'],
            ],
        ]],
    ];
}

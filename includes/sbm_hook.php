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

/** 계정 전체 삭제 때 다이얼 데이터 삭제 (트랜잭션은 공통 계정 쪽) */
function big5_sbm_delete(PDO $pdo, int $userId): void
{
    big5_user_delete_data($pdo, $userId);
}

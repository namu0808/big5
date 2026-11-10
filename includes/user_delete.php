<?php
/**
 * 다이얼 기록 '지우기' (PLAN 8장 삭제권) — 사용자에게서 기록을 떼어 낸다. DB 에서는 실제로 지우지 않는다
 * (대표 지시 2026-11-10: 익명 기록은 검사 개선·규준 자료로 남긴다).
 * - 계정 전체 삭제(사이트 공통 /account/ → 훅 big5_sbm_delete): 계정 행이 은퇴(로그인 불가·익명)하므로 기록은 그 계정에 그대로 두고,
 *   보낸 초대 링크만 만료시켜 남이 더 평가하지 못하게 한다.
 * - 다이얼 기록만 지우기(account.php, 계정은 남는다): 빈 은퇴 계정(sbm_user_tombstone_create)을 만들어 검사·초대·성장 행동을
 *   그쪽으로 옮긴다. 응답·점수·받은 평가는 검사·초대 id 로 따라가므로 다른 화면의 조회는 그대로 둔다.
 * 트랜잭션은 부르는 쪽이 맡는다(big5_user_delete_data_tx 또는 공통 계정의 전체 삭제).
 */
declare(strict_types=1);

/**
 * 사용자에게서 다이얼 기록 떼어 내기 — 초대 만료, $toUserId 가 있으면 기록을 그 계정(은퇴 계정)으로 옮긴다.
 * 이 파일은 계정 훅에서 PDO 만으로 싣기도 하므로 다른 함수에 기대지 않고 시각을 직접 만든다(KST).
 */
function big5_user_detach_data(PDO $pdo, int $userId, ?int $toUserId = null): void
{
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s');
    $pdo->prepare('UPDATE big5_invites SET expires_at = ? WHERE user_id = ? AND expires_at > ?')->execute([$now, $userId, $now]);
    if ($toUserId === null) {
        return;
    }
    foreach (['big5_sessions', 'big5_invites', 'big5_growth_choices'] as $t) {
        $pdo->prepare("UPDATE {$t} SET user_id = ? WHERE user_id = ?")->execute([$toUserId, $userId]);
    }
}

/** 다이얼 기록만 지우기 (한 트랜잭션, 계정은 남는다) — 기록은 새 은퇴 계정으로 옮겨 보존. 돌려주는 값: 옮긴 계정 id */
function big5_user_delete_data_tx(PDO $pdo, int $userId): int
{
    $pdo->beginTransaction();
    try {
        $tomb = sbm_user_tombstone_create($pdo);
        big5_user_detach_data($pdo, $userId, $tomb);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $tomb;
}

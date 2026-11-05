<?php
/**
 * 다이얼 데이터 삭제 (PLAN 8장 삭제권) — 본인 검사·응답·점수, 초대, 받은 평가·응답·점수, 성장 행동.
 * 계정(sbm_users)은 지우지 않는다 — 계정 전체 삭제는 사이트 공통 /account/ 가 서비스 훅(includes/sbm_hook.php)으로 이 함수를 부른다.
 * big5_responses·big5_scores 는 다형 참조(외래 키 없음)라 여기서 직접 지운다. 되돌릴 수 없다.
 * 트랜잭션은 부르는 쪽이 맡는다(big5_user_delete_data_tx 또는 공통 계정의 전체 삭제).
 */
declare(strict_types=1);

function big5_user_delete_data(PDO $pdo, int $userId): void
{
    $sessions = $pdo->prepare('SELECT id FROM big5_sessions WHERE user_id = ?');
    $sessions->execute([$userId]);
    $ratings = $pdo->prepare('SELECT r.id FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id WHERE i.user_id = ?');
    $ratings->execute([$userId]);
    $delResp = $pdo->prepare('DELETE FROM big5_responses WHERE owner_type = ? AND owner_id = ?');
    $delScore = $pdo->prepare('DELETE FROM big5_scores WHERE owner_type = ? AND owner_id = ?');
    foreach ([['session', $sessions->fetchAll(PDO::FETCH_ASSOC)], ['rating', $ratings->fetchAll(PDO::FETCH_ASSOC)]] as [$type, $rows]) {
        foreach ($rows as $r) {
            $delResp->execute([$type, (int) $r['id']]);
            $delScore->execute([$type, (int) $r['id']]);
        }
    }
    $pdo->prepare('DELETE FROM big5_ratings WHERE invite_id IN (SELECT id FROM big5_invites WHERE user_id = ?)')->execute([$userId]);
    $pdo->prepare('DELETE FROM big5_invites WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM big5_growth_choices WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM big5_sessions WHERE user_id = ?')->execute([$userId]);
}

/** 다이얼 데이터만 삭제 (한 트랜잭션, 계정은 남는다) */
function big5_user_delete_data_tx(PDO $pdo, int $userId): void
{
    $pdo->beginTransaction();
    try {
        big5_user_delete_data($pdo, $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

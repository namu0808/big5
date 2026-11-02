<?php
/**
 * 내 데이터 전체 삭제 (PLAN 8장 삭제권) — 계정, 본인 검사·응답·점수, 초대, 받은 평가·응답·점수, 성장 행동.
 * big5_responses·big5_scores 는 다형 참조(외래 키 없음)라 여기서 직접 지운다. 되돌릴 수 없다.
 */
declare(strict_types=1);

function big5_user_delete(PDO $pdo, int $userId): void
{
    $pdo->beginTransaction();
    try {
        $sessions = $pdo->prepare('SELECT id FROM big5_sessions WHERE user_id = ?');
        $sessions->execute([$userId]);
        $ratings = $pdo->prepare('SELECT r.id FROM big5_ratings r JOIN big5_invites i ON i.id = r.invite_id WHERE i.user_id = ?');
        $ratings->execute([$userId]);
        $delResp = $pdo->prepare('DELETE FROM big5_responses WHERE owner_type = ? AND owner_id = ?');
        $delScore = $pdo->prepare('DELETE FROM big5_scores WHERE owner_type = ? AND owner_id = ?');
        foreach ([['session', $sessions->fetchAll()], ['rating', $ratings->fetchAll()]] as [$type, $rows]) {
            foreach ($rows as $r) {
                $delResp->execute([$type, (int) $r['id']]);
                $delScore->execute([$type, (int) $r['id']]);
            }
        }
        $pdo->prepare('DELETE FROM big5_ratings WHERE invite_id IN (SELECT id FROM big5_invites WHERE user_id = ?)')->execute([$userId]);
        $pdo->prepare('DELETE FROM big5_invites WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM big5_growth_choices WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM big5_sessions WHERE user_id = ?')->execute([$userId]);
        $st = $pdo->prepare('SELECT username FROM big5_users WHERE id = ?');
        $st->execute([$userId]);
        $username = $st->fetchColumn();
        if ($username !== false) {
            $pdo->prepare('DELETE FROM big5_login_attempts WHERE username = ?')->execute([(string) $username]);
        }
        $pdo->prepare('DELETE FROM big5_users WHERE id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

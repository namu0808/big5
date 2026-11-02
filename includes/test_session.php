<?php
/**
 * 본인 검사(big5_sessions) 도우미 — 소유자 확인, 진행 중 검사 찾기, 완료 처리.
 */
declare(strict_types=1);

require_once __DIR__ . '/items.php';
require_once __DIR__ . '/scoring.php';
require_once __DIR__ . '/quality.php';

/** 내 검사 1건 (다른 사람 것이면 null — URL 의 id 만 바꿔 남의 결과를 볼 수 없게) */
function big5_session_get(PDO $pdo, int $id, int $userId): ?array
{
    $st = $pdo->prepare('SELECT * FROM big5_sessions WHERE id = ? AND user_id = ?');
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

/** 같은 종류의 진행 중 검사 (가장 최근) */
function big5_session_in_progress(PDO $pdo, int $userId, string $testType): ?array
{
    $st = $pdo->prepare(
        "SELECT * FROM big5_sessions WHERE user_id = ? AND test_type = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$userId, $testType]);
    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

/** 진행 중 검사와 그 응답을 지운다 (새로 시작할 때) */
function big5_session_discard(PDO $pdo, int $sessionId): void
{
    $pdo->prepare("DELETE FROM big5_responses WHERE owner_type = 'session' AND owner_id = ?")->execute([$sessionId]);
    $pdo->prepare('DELETE FROM big5_sessions WHERE id = ?')->execute([$sessionId]);
}

/** 새 검사 시작 (동의 시각 기록) → 새 id */
function big5_session_start(PDO $pdo, int $userId, string $testType): int
{
    $items = big5_items($testType);
    $now = big5_now();
    $pdo->prepare(
        "INSERT INTO big5_sessions (user_id, test_type, item_version, status, started_at, consent_at)
         VALUES (?, ?, ?, 'in_progress', ?, ?)"
    )->execute([$userId, $testType, (string) ($items['version'] ?? ''), $now, $now]);
    return (int) $pdo->lastInsertId();
}

/** 두 시각 문자열 사이 초 */
function big5_seconds_between(string $from, string $to): int
{
    return max(0, strtotime($to) - strtotime($from));
}

/**
 * 채점·품질 점검 후 완료 처리. 반환: ['ok' => true] 또는 ['ok' => false, 'code' => 'missing', 'missing' => [번호...]]
 */
function big5_session_finish(PDO $pdo, array $session): array
{
    $items = big5_items((string) $session['test_type']);
    if ($items === null) {
        return ['ok' => false, 'code' => 'items'];
    }
    $resp = big5_responses_load($pdo, 'session', (int) $session['id']);
    $missing = [];
    foreach ($items['items'] as $it) {
        if (!isset($resp[(int) $it['no']])) {
            $missing[] = (int) $it['no'];
        }
    }
    if ($missing !== []) {
        return ['ok' => false, 'code' => 'missing', 'missing' => $missing];
    }
    $now = big5_now();
    $duration = big5_seconds_between((string) $session['started_at'], $now);
    $scored = big5_score($items['items'], $resp);
    $quality = big5_quality($items['items'], $resp, $duration);

    $pdo->beginTransaction();
    try {
        big5_scores_save($pdo, 'session', (int) $session['id'], $scored);
        $pdo->prepare(
            "UPDATE big5_sessions SET status = 'completed', completed_at = ?, duration_sec = ?, quality_flags = ?, scoring_version = ?
             WHERE id = ?"
        )->execute([$now, $duration, $quality['flags'] === [] ? null : implode(',', $quality['flags']), BIG5_SCORING_VERSION, (int) $session['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true];
}

/** 저장된 품질 플래그 → 배열 */
function big5_flags(?string $s): array
{
    return $s === null || $s === '' ? [] : array_values(array_filter(explode(',', $s)));
}

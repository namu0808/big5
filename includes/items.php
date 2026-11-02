<?php
/**
 * 문항 데이터 로더 — data/items_120.json(정식), data/items_20.json(간이).
 * 문항 JSON 은 tests/build_items.php 가 data/source/master_*.csv 로만 만든다(손으로 고치지 않음).
 */
declare(strict_types=1);

const BIG5_TEST_TYPES = ['full120', 'mini20'];

/** 검사 종류 → 문항 데이터 (요청 안에서 캐시). 잘못된 종류·파일 오류면 null */
function big5_items(string $testType): ?array
{
    static $cache = [];
    if (isset($cache[$testType])) {
        return $cache[$testType];
    }
    $file = ['full120' => 'items_120.json', 'mini20' => 'items_20.json'][$testType] ?? null;
    if ($file === null) {
        return null;
    }
    $path = dirname(__DIR__) . '/data/' . $file;
    try {
        $data = json_decode((string) @file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('다이얼 문항 파일 읽기 실패: ' . $path . ' ' . $e->getMessage());
        return null;
    }
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return null;
    }
    return $cache[$testType] = $data;
}

/** 검사 종류 표시 이름 */
function big5_test_label(string $testType): string
{
    return $testType === 'full120' ? '정식 검사 (120문항)' : '간이 검사 (20문항)';
}

/** 요인 코드 목록 — 리포트 소개 순서(원고 3장): 성실성 → 외향성 → 친화성 → 개방성 → 신경성 */
function big5_domain_order(): array
{
    return ['C', 'E', 'A', 'O', 'N'];
}

<?php
declare(strict_types=1);
require_once '../includes/admin_check.php';

/**
 * 관리자 — 회원의 실제 결과지 보기(읽기 전용). 사용자가 보는 report.php 를 그대로 싣는다(같은 결과지, 삭제한 계정의 보존 기록 포함).
 * 익명 검사(이름·연락처 없음) — 검사 개선에 쓴다(대표 지시 2026-11-11). 주소: admin/report.php?id=검사 id
 */
define('BIG5_ADMIN_REPORT', true);
require __DIR__ . '/../report.php';

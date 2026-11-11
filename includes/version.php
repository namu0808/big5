<?php
/** 버전 단일 원본 — 기능 추가 MINOR, 고침·문구 PATCH, 큰 개편 MAJOR. 날짜는 KST */
declare(strict_types=1);

const BIG5_VERSION = '0.12.2';   // 0.12.2: 시작 화면 검색어 보강('성격검사', 구조화 데이터) · 0.12.1: 상단 줄을 사이트 디자인 '측정 기록지'로(홈 0.10.0과 함께)
const BIG5_VERSION_DATE = '2026-11-11';

function big5_version_label(): string
{
    return 'ver ' . BIG5_VERSION;
}

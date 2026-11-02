<?php
/** 버전 단일 원본 — 기능 추가 MINOR, 고침·문구 PATCH, 큰 개편 MAJOR. 날짜는 KST */
declare(strict_types=1);

const BIG5_VERSION = '0.1.0';
const BIG5_VERSION_DATE = '2026-11-02';

function big5_version_label(): string
{
    return 'ver ' . BIG5_VERSION;
}

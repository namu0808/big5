# 다이얼(Dial) — Claude Code 작업 규칙

프로젝트: 빅파이브 성격 검사·결과 리포트 웹 프로그램 | 테이블 접두어: `big5_` | 저장소: `namu0808/big5`
**계획서·작업 지시서: `PLAN.md`** · 원고 『다섯 개의 다이얼』: 루트 `빅파이브 Big 5.md`(해석 문구·Mini-IPIP 번역·용어 사전의 근거) — 작업 전에 해당 장을 읽는다. PLAN 0.1의 '고정' 항목은 바꾸지 않는다(바꿀 필요가 보이면 이유를 들어 먼저 묻는다).
승인된 작업 계획(변경 제안 포함)은 아래 '승인된 변경' 절에 정리한다.

## 핵심 원칙 (PLAN 1.2)
1. UI는 심플하게 — 검사 화면은 문항에만 집중
2. 결과지는 풍부하게
3. 유형이 아니라 다이얼 — 'OO형' 금지, 높고 낮음을 좋고 나쁨으로 말하지 않음. 규준 비활성 동안 백분위 표현 금지

## 환경
- 기준 시각 한국 시간(KST). 날짜를 쓰기 전 `TZ=Asia/Seoul date` 확인. 코드는 `big5_now()`(Asia/Seoul)
- PHP 8.x 바닐라(Composer 불가), MariaDB 10.x `utf8mb4_unicode_ci`, 카페24 FTP 배포, 빌드 없음
- 서버 sonbm.com, 설치 위치 **`{홈}/www/big5/`** (https://sonbm.com/big5/). 루트는 WordPress
- UI: 바닐라 CSS(`assets/style.css`) + 바닐라 JS, Pretendard CDN(막혀도 시스템 폰트로 대체). 차트는 CSS 막대 + PHP 생성 SVG(외부 차트 라이브러리 없음)

## 파일 구조 원칙
- DB 접속 정보: 웹루트 밖 `{홈}/config/big5_db.php` (견본 `doc/big5_db.sample.php`). `includes/db_config.php`는 로더(심) — 접속 정보 넣지 않음. 계약: 전역 `$pdo`(PDO|null) + `BIG5_DB_*`, `BIG5_EXPORT_SALT`, (선택) `BIG5_BASE_URL`
- **경로는 모두 `big5_url('경로')`** (`includes/util.php`). `/admin/...` 같은 루트 기준 절대 경로 하드코딩 금지. 루트 `.htaccess`가 `RewriteEngine On`으로 WordPress 재작성 규칙 상속을 끊는다
- 화면 첫 require: 공개 화면 `includes/bootstrap.php`, 사용자 화면 `includes/auth_check.php`(`$big5User`), 관리자 화면 `includes/admin_check.php`(`$big5Admin`)
- `/admin` 모든 파일은 `declare(strict_types=1);` 바로 다음 첫 문장이 `require_once '../includes/admin_check.php';` (예외: `admin/login.php`)
- 세션: `big5_session_begin()`(이름 `BIG5SESSID`, 쿠키 경로 `/big5/`). 사용자 `$_SESSION['user_id']`, 관리자 `$_SESSION['admin_id']` 분리
- `api.php` 같은 전용 API 파일 만들지 않음 — 각 화면이 자기에게 POST. 새로고침 없는 동작만 `ajax=1` fetch POST → JSON `{ok, code, error, ...}` (`big5_json()`)
- 모든 POST는 CSRF 검사 (`big5_csrf_field()` / `big5_csrf_check()`)
- 스키마 단일 원본 `includes/big5_schema.php` (MariaDB·SQLite DDL 나란히, 멱등). 설치 `1time/install.php`(서버 `/big5/install.php`로 올려 1회 실행 후 삭제)
- 앱 쿼리는 표준 SQL만 (SQLite 검증이 돌아야 함). `NOW()` 대신 `big5_now()`
- 해석 문구는 `data/content/*.json`, 문항은 `data/items_*.json` — 코드와 분리. 문항 JSON 은 `data/source/master_*.csv`(원문 대조 확정본, `php tests/source_compare.php`)로만 만든다. `data/`·`includes/`·`tests/`는 `Deny from all`
- 버전 단일 원본 `includes/version.php` — 기능 추가 MINOR, 고침·문구 PATCH

## 코딩 원칙
1. PDO + Prepared Statements만 (mysqli·SQL 문자열 연결 금지)
2. `password_hash()` / `password_verify()`
3. 출력은 `h()`(htmlspecialchars ENT_QUOTES UTF-8). JSON 문구도 이스케이프, 줄바꿈만 `h_lines()`. JS에서 사용자 텍스트 innerHTML 금지
4. 에러는 `error_log()`만, 화면 출력 금지. `eval()` 금지
5. 한국어 주석, `declare(strict_types=1)`, LF
6. 모바일 우선 (검사 화면 선택지 버튼은 엄지 크기)
7. 문항은 `data/source/`의 IPIP 원문 키를 근거로만 — 기억으로 짓지 않음

## 검증
- 일괄: `node doc/pw/run.js` (CLI + 브라우저) · `--cli` · `--pw`
  - CLI: `php tests/run.php` (모든 PHP `php -l` + `tests/*_test.php`: 스키마·인증, 채점·문항 구조·원문 키 일치)
  - 원문 대조: `php tests/source_compare.php [--write-master]` → `data/source/master_*.csv` (A·D 추출본이 있으면 그 값 우선)
  - 문항 JSON 빌드: `php tests/build_items.php` (master + `data/translate/*.csv` → `data/items_*.json`, `data/review_items.md`). 문항 JSON 을 손으로 고치지 않는다
  - 해석 문구: `data/content/*.json` 7개(키 구조는 `includes/content_loader.php` 머리 주석). 점검 `includes/content_check.php`(CLI `tests/content_test.php`·관리자 `admin/content.php` 공유), 검수본 `php tests/build_review_content.php` → `data/review_content.md`. 문구를 고치면 둘 다 다시 돌린다
  - 브라우저: `doc/pw/*-smoke.js` — 개발 서버를 스스로 띄움(`BIG5_DB_CONFIG=doc/dev-db-sqlite.php php -S 127.0.0.1:8093 doc/dev-router.php`, 주소 `http://127.0.0.1:8093/big5/`). 라우터가 운영 .htaccess 의 403 차단을 흉내 낸다
  - 손으로 띄울 때: `php doc/dev-sqlite-setup.php` 후 위 서버 명령
- 전건 PASS 후 커밋. 커밋 메시지·코드에 모델 식별자 넣지 않음

## Git
- 작업 브랜치에 커밋·푸시 → 검증 통과 시 `main`에 fast-forward 병합까지 (사용자 지시 2026-11-02, 단계마다)
- 단계가 끝나면 PLAN 10장 완료 기준을 보고하고 다음 단계로

## 배포
- 제외: `doc/`, `tests/`, `1time/`(설치 때만 install.php 를 앱 루트로), `data/source/`, `data/review_*.md`, `PLAN.md`, `CLAUDE.md`, `README.md`, 원고 `빅파이브 Big 5.md`, 점(.)으로 시작하는 파일(단 `.htaccess`는 올린다)
- 작업 끝에 FTP 배포 파일 목록을 적는다

## 승인된 변경 (PLAN 참고 항목, 2026-11-02 승인)
1. `api.php` 없음 — 자기 페이지 POST/ajax
2. 설치 스크립트 `1time/install.php`, 스키마 `includes/big5_schema.php`
3. 차트: CSS 막대 + 서버 SVG 레이더 (Chart.js 없음, 인쇄 변환 불필요)
4. 개발·검증 DB: SQLite (`BIG5_DB_CONFIG`)
5. 스키마 보강: `scoring_version`, `big5_ratings.test_type·item_version`, `big5_login_attempts`(15분 내 아이디 5회·IP 20회 실패 차단), 초대 토큰은 SHA-256 `token_hash`만 저장
6. 관리자 CSV 식별값: `hash_hmac('sha256', user_id, BIG5_EXPORT_SALT)`
7. 루트 `.htaccess`로 WordPress 충돌 방지
8. 이 CLAUDE.md
9. 문구 검사 로직 공유(`includes/content_check.php` → CLI·관리자 화면)
10. 역문항 일관성: 정·역 문항이 섞인 하위 요인마다 (+문항 평균)과 (−문항 역채점 평균) 차이 ≥ 2.0 이 해당 하위 요인의 30% 이상이면 `inconsistent` (Mini-IPIP는 요인 단위)
11. `compare.php`는 이전 vs 지금만, 나 vs 타인은 리포트 9장

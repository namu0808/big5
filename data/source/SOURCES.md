# 원문 자료 출처 기록 (data/source)

- 기록일: 2026-11-02 12:56 KST
- 이 폴더는 `data/.htaccess`(Deny from all)로 웹에서 직접 열 수 없다. FTP 배포 대상도 아니다.
- 내려받기는 이 저장소의 클라우드 작업 환경에서 했다. 이 환경의 네트워크 허용 목록에 없는 주소는 받지 못했다(아래 '실패').

## 요약

| 자료 | 역할 | 결과 | 라이선스·이용 조건 |
|---|---|---|---|
| [A] Johnson (2014) 원논문 PDF | 120문항 기준본 | **실패** — 네트워크 정책이 호스트 차단 | 논문 저작권은 출판사·저자. 문항 자체는 IPIP 공유 저작물 |
| [B] bigfive-org 문항 데이터 | 작업용(영문·한국어 참고) | **성공** | MIT (`bigfive_org/LICENSE`) |
| [C] UGA 문항지·채점 키 docx | 보조 | **실패** — 네트워크 정책이 호스트 차단 | 문항은 IPIP 공유 저작물 |
| [D] IPIP Mini-IPIP 공식 페이지 | 20문항 기준본 | **실패** — 네트워크 정책이 호스트 차단 | IPIP 공유 저작물 |
| 원고 부록 A | 20문항 실시 순서·역채점 대조 | 확보 (저장소 루트 `빅파이브 Big 5.md`) | 손병목 원고 |

## [A] Johnson (2014) IPIP-NEO-120 원논문 — 실패

- 원본 URL: https://bpb-us-e1.wpmucdn.com/sites.psu.edu/dist/1/163537/files/2023/07/IPIPNEO120.pdf
- 저장 예정 경로: `data/source/johnson2014_ipip_neo_120.pdf`
- 시도: 2026-11-02 12:50 KST 무렵
  - `curl`: 프록시가 연결 거부(CONNECT 403)
  - 웹 가져오기 도구: 호스트 이름 조회 실패(허용 도메인 밖)
- 파일 크기·SHA-256: 없음 (받지 못함)

## [B] bigfive-org 문항 데이터 — 성공

- GitHub: https://github.com/rubynor/bigfive-web (얕은 clone, 커밋 `e8f3caca49edafb9bc6b927eff7fbc1264312c24`, 2026-09-01)
- npm: `@bigfive-org/questions` 1.2.1 — `npm pack` 으로 받은 `bigfive-org-questions-1.2.1.tgz` (94,808바이트, SHA-256 `ae78bcf90def048f4ad447d46ec0ea548c0b2786f66ee5c7d7bfb80303774a0d`)
- 복사한 파일은 npm 게시본에서 가져왔다. 같은 내용이 GitHub 소스(`packages/questions/src/data/{en,ko}/questions.ts`)에도 있다. 두 판의 120문항(id·문구·방향·요인·하위 요인)을 영어·한국어 모두 비교했고 차이는 0건이다.
- 이 패키지에는 Mini-IPIP(20문항)가 **없다.** 120문항(영문, 한국어 포함 31개 언어)만 있다.
- 라이선스: MIT License, Copyright (c) 2024 B5 Holding AS

| 파일 | 바이트 | SHA-256 |
|---|---:|---|
| `bigfive_org/LICENSE` | 1,071 | `2d96822370f7d783a27103e1dbb8b759d1ae44701a399c2cdac4922714ccc01b` |
| `bigfive_org/package.json` | 1,000 | `e7dbc279541f0533334b7febc029e277ce7e887b4a1a68b5e6a883cbcc7343ec` |
| `bigfive_org/en/questions.json` | 18,689 | `a2c3eb00be6292454519aa6b84e04c0e67cae7195323faf79cefb6f6d1c8af1c` |
| `bigfive_org/en/choices.js` | 847 | `261687679857bdf1994527b1a27741671e38dc6d2a8aff4ff9693a5938f43719` |
| `bigfive_org/ko/questions.json` | 22,335 | `7eda50708eae53eab10c77bdd43cf1f18caa2759e9351527a3509b26d7523ce0` |
| `bigfive_org/ko/choices.js` | 801 | `9b0ff30d4ee9477bb8d6bfa75f618ea0d79916363b352b2c3f984d36b1a06cc5` |

## [C] UGA 인쇄용 문항지·채점 키 — 실패

- 원본 URL: https://www.psychology.uga.edu/sites/default/files/CVs/IPIP_120.docx
- 저장 예정 경로: `data/source/uga_ipip_120.docx`
- 시도 결과: [A]와 같음 (프록시 연결 거부, 호스트 이름 조회 실패)

## [D] IPIP Mini-IPIP 공식 페이지 — 실패

- 원본 URL: https://ipip.ori.org/MiniIPIPKey.htm
- 저장 예정 경로: `data/source/ipip_mini_ipip_key.html`
- 시도 결과: [A]와 같음

## 추출 파일

| 파일 | 만든 방법 | 바이트 | SHA-256 |
|---|---|---:|---|
| `extracted_B_bigfiveorg.csv` | `php tests/source_compare.php` (B의 en·ko JSON에서 기계 추출) | 15,901 | `26cdb5eb0b601f52436d0df00d8e60b1263c2256bdf86b33222fe96fc6a97c87` |
| `extracted_appendixA_mini.csv` | 같은 스크립트 (원고 부록 A의 문항표·뒤집는 문항·다이얼별 문항 표에서 기계 추출) | 1,043 | `a0ba606b50027cab0293bf42b9aa3a051eb093d948ca01de736ec5181c4860f2` |

## 받지 못한 자료를 채우는 방법

다음 중 하나를 하면 이어서 추출·대조한다.

1. **이 환경의 네트워크 허용 목록에 호스트 추가**: 세션 제목 표시줄의 클라우드 환경 메뉴 → Edit → Network access → Allowed domains에 아래 세 호스트를 추가한다. 패키지 관리자 허용 상자는 체크된 채로 둔다. (https://code.claude.com/docs/en/cloud-environments#network-access)
   - `bpb-us-e1.wpmucdn.com`
   - `www.psychology.uga.edu`
   - `ipip.ori.org`
2. **직접 올리기**: 대표님 컴퓨터에서 세 자료를 받아 GitHub 웹에서 `data/source/`에 위 파일 이름으로 올린다. 그 뒤 SHA-256을 이 문서에 기록한다.

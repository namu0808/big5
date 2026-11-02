# 원문 자료 출처 기록 (data/source)

> **status: provisional** — 120문항 기준 파일 `master_120.csv`는 [B]로 만들었고, 하위 요인·방향·문구는 [A'] IPIP 공식 120문항 페이지와 모두 일치함을 확인했다(2026-11-02). 실시 번호 순서만 원논문 [A] 표 1 대조가 남았다. 20문항 `master_20.csv`는 원고 부록 A로 만들었고, `text_en`은 비어 있다([D] 대기). 원논문 대조가 끝나면 status: final 로 바꾼다. (승인 2026-11-02)

- 기록일: 2026-11-02 12:56 KST (갱신 13:05 KST)
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

## 재시도 기록

- 2026-11-02 13:00 KST 무렵: 대표님이 환경 허용 목록에 세 호스트를 추가했다고 알려 주셔서 다시 받았다. [A]·[C]·[D] 모두 여전히 프록시가 연결을 거부했다(CONNECT 403).
  - 이 세션이 시작될 때의 네트워크 정책이 그대로 적용되는 것으로 보인다. 새 세션에서 다시 시도하거나, 대표님이 직접 올린다.

## [A'] IPIP 공식 사이트 120문항 페이지 — 확보 (대표님 업로드)

- 원본 URL: https://ipip.ori.org/30FacetNEO-PI-RItems.htm (페이지 제목 "Johnson IPIP-NEO-120")
- 확보: 2026-11-02 21:00 KST 무렵, 대표님이 브라우저로 저장해 올린 HTML(문자 인코딩 windows-1252, 저장한 그대로 보관)
- 저장: `data/source/ipip_neo120_items.html` — 16,450바이트, SHA-256 `01bae0a670f714435be5f9e6f06c405d094e5d90d1662eaadc3b18ea40b65840`
- 내용: 하위 요인 30개 × 4문항, 정·역채점 표시('+ keyed'/'– keyed'는 그 방향의 첫 문항에만 붙음). **실시 번호(1~120)는 없다.**
- 이용 조건: IPIP 문항은 공유 저작물

## [D'] IPIP 공식 사이트 Mini-IPIP 요약표 — 확보 (대표님 업로드)

- 원본 URL: https://ipip.ori.org/MiniIPIPTable.htm (페이지 제목 "Mini-IPIP Shortened Measures of the Big-Five Domains")
- 저장: `data/source/ipip_mini_ipip_table.html` — 15,032바이트, SHA-256 `a04f724f76652d49a15dd225855627b401925b3c2505e79fd81ccd568a07f0a4`
- 내용: 요인별 문항 수(+/−)·신뢰도·각주. **문항 문장은 없다.** 문장과 번호가 있는 페이지는 [D] `MiniIPIPKey.htm`이다(아직 미확보).

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
| `bigfive_org/results_en/neuroticism.ts` | 4,860 | `d34781883fb134075cba478ac20d8257a96b1581bb6fa293f2ea642d5bd33034` |
| `bigfive_org/results_en/extraversion.ts` | 4,142 | `455b1554c803eeadd3f03609e95d56b47ff14fa3fdfd5ffe78a9c85d9755a43b` |
| `bigfive_org/results_en/openness_to_experience.ts` | 5,779 | `6449b3f27d0c3d3eb86052102b8df4d39e869a352744158d501532af51f07309` |
| `bigfive_org/results_en/agreeableness.ts` | 4,508 | `fba5fc20d37ea6598b9bdd99b965a430114073e8a9d3ea0ae0de9a31c8738dbe` |
| `bigfive_org/results_en/conscientiousness.ts` | 6,077 | `3dc42112036af804507f198f88438be990237f7aee60b7f43d01f8fa36aed4f0` |

- `results_en/*.ts`는 같은 저장소 `packages/results/src/data/en/`에서 복사했다(커밋 `e8f3cac`). 요인·하위 요인의 영문 이름(예: N1 Anxiety)을 문항 JSON에 넣는 데만 쓴다.

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
| `extracted_A_ipip120page.csv` | 같은 스크립트 ([A'] HTML에서 기계 추출, 열: facet_code, keyed, text_en, order_in_facet) | 4,272 | `f03e8448d22a31af0ae7140a955be29553eb9cba3c9a3b868929f09f5f76779c` |
| `extracted_appendixA_mini.csv` | 같은 스크립트 (원고 부록 A의 문항표·뒤집는 문항·다이얼별 문항 표에서 기계 추출) | 1,043 | `a0ba606b50027cab0293bf42b9aa3a051eb093d948ca01de736ec5181c4860f2` |

## 기준 파일 (확정 전)

| 파일 | status | 만든 방법 | 바이트 | SHA-256 |
|---|---|---|---:|---|
| `master_120.csv` | provisional | `php tests/source_compare.php --write-master` — [B] 추출본을 그대로 옮김, [A'] 와 일치 확인 표시 | 5,033 | `59f62f9986d1a6f6cd577e4cb5c2ad020698a0f3694543d570082daacdd6c9f9` |
| `master_20.csv` | provisional | 같은 명령 — 원고 부록 A 추출본, `text_en` 빈칸 | 1,425 | `95f642019ecd2f3b6b8ee2b105a63307c6cb005c2fee857c5f58c4c43d6c063c` |

문항 JSON(`data/items_120.json`, `data/items_20.json`)은 `php tests/build_items.php`가 위 두 파일과 번역 초안(`data/translate/`)으로만 만든다.

## 받지 못한 자료를 채우는 방법

다음 중 하나를 하면 이어서 추출·대조한다.

1. **이 환경의 네트워크 허용 목록에 호스트 추가**: 세션 제목 표시줄의 클라우드 환경 메뉴 → Edit → Network access → Allowed domains에 아래 세 호스트를 추가한다. 패키지 관리자 허용 상자는 체크된 채로 둔다. (https://code.claude.com/docs/en/cloud-environments#network-access)
   - `bpb-us-e1.wpmucdn.com`
   - `www.psychology.uga.edu`
   - `ipip.ori.org`
2. **직접 올리기**: 대표님 컴퓨터에서 세 자료를 받아 GitHub 웹에서 `data/source/`에 위 파일 이름으로 올린다. 그 뒤 SHA-256을 이 문서에 기록한다.

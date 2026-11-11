# 다이얼(Dial) — 빅파이브 성격 검사·진단 웹 프로그램
## 사전 계획서 및 Claude Code 작업 지시서 v1.0

> 작성일: 2026.11.02 · 작성자: 손병목
> 대상 도구: Claude Code
> 프로젝트 접두어: **`big5_`**
> 서버: **sonbm.com** (카페24) · 설치 위치: **하위 폴더 `/big5/`** (예: https://sonbm.com/big5/)
> 연계 원고: 『다섯 개의 다이얼 — 처음 만나는 빅파이브 성격 이야기』
>
> **변경 기록 2026-11-04**: 서비스 위치 변경 sonbm.com/big5/ → **https://www.sbm.ai.kr/big5/** (대표님 지시, 같은 카페24 환경, 새로 설치). 0.1의 '고정: 설치 위치'와 본문의 sonbm.com 표기는 이 기록으로 갱신된 것으로 본다.

---

## 0. 이 문서 사용법

- 이 문서는 프로젝트 루트에 `PLAN.md`로 두고, Claude Code에게 **"PLAN.md를 읽고 작업하라"**고 지시한다.
- 1~9장은 **무엇을 만드는가(계획서)**, 10~12장은 **어떻게 만드는가(작업 지시서)**다.
- 13장의 **첫 바이브**를 Claude Code에 붙여 넣어 시작한다. Claude Code는 먼저 단계별 작업 계획을 제시하고, 승인 후 착수한다.
- 서버·보안·파일 구조는 **기존 바이브코딩 규칙을 그대로 따른다.** (11장에 요약)

### 0.1 이 문서의 구속력 — 무엇이 고정이고 무엇이 참고인가
Claude Code는 이 문서를 처음부터 끝까지 정독한 뒤, **더 좋은 방법이 있다면 계획을 수정해 제안할 수 있다.** 이 문서는 정답지가 아니라 출발점이다.

| 구분 | 해당 내용 | 다루는 법 |
|---|---|---|
| **고정 (바꾸지 않음)** | 1.2 핵심 원칙 세 가지, 설치 위치 sonbm.com/big5/, 2.1~2.2 문항 출처와 원문 근거 원칙, 3.1 채점 계산, 3.2 백분위 표현 금지, 5.1 리포트에 담길 내용의 범위, 5.2의 '2. 일곱 가지 주의점', 6.3 문구 작성 원칙, 8장 개인정보와 윤리, 11장 기존 코딩 규칙, DB 접두어 `big5_`, 서버 sonbm.com | 그대로 지킨다. 바꿀 필요가 보이면 이유를 들어 먼저 묻는다 |
| **참고 (더 나은 방법이 있으면 바꿀 수 있음)** | 2.4·6.1·6.2 JSON 형식, 4장 화면 목록과 파일 구성, 7장 DB 스키마, 9.2 파일 구조, 10장 단계 구분, 3.3 구간 경계값, 3.4 품질 점검 기준, 4.1 색상 | 더 단순하거나 견고한 설계가 있으면 바꿔도 된다. 단, 작업 계획을 제시할 때 **무엇을 왜 바꾸는지** 목록으로 먼저 보고하고 승인을 받는다 |

수정 제안의 기준은 세 가지다. ① 더 단순한가 ② 더 안전한가(보안·개인정보) ③ 해석 문구를 고치고 늘리기 더 쉬운가.

---

## 1. 프로젝트 개요

### 1.1 목적
누구나 웹에서 빅파이브 성격 검사(120문항)를 하고, **빅파이브를 처음 접하는 사람도 충분히 이해할 수 있는 풍부한 결과 리포트**를 받는 프로그램.

### 1.2 핵심 원칙 세 가지
1. **UI는 심플하게.** 검사 화면은 문항 하나하나에만 집중하게 한다. 장식·애니메이션 최소화.
2. **결과지는 풍부하게.** 빅파이브 소개, 해석 전 주의점, 다섯 다이얼 상세, 하위 요인 30개, 조합 읽기, 일터·관계·성장 제안, 점수 계산법까지 한 리포트에 담는다.
3. **유형이 아니라 다이얼.** 결과를 'OO형'으로 부르지 않는다. 모든 결과는 연속된 위치로 보여 주고, 높고 낮음을 좋고 나쁨으로 말하지 않는다.

### 1.3 범위 (2단계)
| 포함 | 제외 (다음 단계) |
|---|---|
| 120문항 정식 검사, 20문항 간이 검사 | 아동용·부모 평정판 |
| 풍부한 결과 리포트 (웹 + 인쇄/PDF) | 한국인 표준 규준(백분위) — 데이터 축적 후 |
| 재검사 및 이전 결과 비교 | 팀 단위 분석 리포트 |
| 타인 평가(가족·동료가 나를 평가) 및 비교 | 결제·상용 서비스 기능 |
| 관리자: 통계, 사용자 관리, 익명 데이터 내보내기, 규준 생성 준비 | 외부 API 연동 |

### 1.4 사용자
- **응답자(사용자)**: 회원가입 후 검사. 본인 결과만 열람.
- **평가자(타인)**: 로그인 없이 초대 링크로 들어와 '그 사람'에 대해 응답. 결과는 볼 수 없음.
- **관리자**: 전체 통계, 사용자 관리, 데이터 내보내기, 문항·해석 문구 확인.

---

## 2. 검사 도구

### 2.1 사용 문항
| 검사 | 문항 수 | 출처 | 측정 범위 | 용도 |
|---|---|---|---|---|
| **IPIP-NEO-120** | 120 | Johnson (2014), IPIP | 5요인 + 하위 요인 30개 (요인당 6개, 하위 요인당 4문항) | 정식 검사 |
| **Mini-IPIP** | 20 | Donnellan 외 (2006), IPIP | 5요인 (요인당 4문항) | 간이 검사, 타인 평가 기본값 |

- IPIP 문항은 **공유 저작물(public domain)**이며, 상업적·비상업적 용도 모두 허락이 필요 없다. (출처: ipip.ori.org)
- NEO-PI-R/NEO-PI-3, BFI/BFI-2의 문항은 **사용하지 않는다.** (저작권 있음)

### 2.2 문항 데이터 준비 절차 (중요)
문항은 반드시 **원문 키(key)와 함께** 준비한다. 기억에 의존해 문항을 만들지 않는다.

1. 손병목이 ipip.ori.org에서 IPIP-NEO-120 문항표(영문, 하위 요인 및 정·역채점 표시 포함)를 내려받아 `data/source/ipip_neo_120_en.(csv|txt)`로 둔다.
2. Claude Code는 이 원문을 읽어 `data/items_120.json`을 만든다. 영문 원문, 하위 요인 코드, 채점 방향은 원문 그대로 옮기고, **한국어 번역 초안**을 붙인다.
3. 번역 원칙 (2.3) 적용 → 손병목이 검수 → 확정.
4. Mini-IPIP 20문항은 원고 부록 A의 번역본을 출발점으로 `data/items_20.json`을 만든다.
5. 타인 평가용 문항(`text_ko_other`)은 "나는 ~" 문장을 "이 사람은 ~"으로 바꾼 별도 필드로 만든다.

### 2.3 번역 원칙
- 짧고 일상적인 한국어. 한 문항에 한 가지 행동만.
- 영어 관용구는 뜻을 옮긴다. (예: "Am the life of the party" → "모임에서 분위기를 띄우는 편이다")
- 문항 끝은 "~한다 / ~하는 편이다"로 통일한다.
- 이중 부정 금지. 번역 때문에 정·역채점 방향이 뒤집히지 않았는지 반드시 확인한다.
- 정치적으로 읽힐 수 있는 문항(원문 '가치 개방/Liberalism' 하위 요인)은 특히 중립적으로 옮긴다.
- 번역본은 **검증되지 않은 한국어판**임을 리포트에 명시한다.

### 2.4 문항 JSON 형식 (참고용 예시)
```json
{
  "version": "ipip-neo-120-ko-v0.1",
  "scale": ["전혀 아니다", "아닌 편이다", "보통이다", "그런 편이다", "매우 그렇다"],
  "items": [
    {
      "no": 1,
      "facet": "N1",
      "keyed": "+",
      "text_en": "(원문 그대로)",
      "text_ko": "(번역)",
      "text_ko_other": "(타인 평가용 번역)"
    }
  ]
}
```

### 2.5 요인과 하위 요인 코드 (원고와 같은 용어)
| 요인 | 코드 | 하위 요인 (1~6) |
|---|---|---|
| 신경성 (Neuroticism) | N | 불안, 분노, 우울, 자의식, 충동성, 취약성 |
| 외향성 (Extraversion) | E | 따뜻함, 사교성, 주장성, 활동성, 자극 추구, 긍정 정서 |
| 개방성 (Openness) | O | 상상, 심미성, 감정 개방, 행동 개방, 사고 개방, 가치 개방 |
| 친화성 (Agreeableness) | A | 신뢰, 솔직함, 이타성, 협조, 겸손, 온정 |
| 성실성 (Conscientiousness) | C | 유능감, 질서, 의무감, 성취 추구, 자기 규율, 신중함 |

> 하위 요인의 실제 순서·정의는 원문 IPIP-NEO-120 키를 따른다. 위 명칭은 화면 표시용 한국어 이름이다.
> 리포트에서 요인을 소개하는 순서는 원고 3장을 따른다: **성실성 → 외향성 → 친화성 → 개방성 → 신경성**.

---

## 3. 채점 로직

### 3.1 기본 계산
- 응답값: 1~5
- 역채점 문항: `6 - 응답값`
- 하위 요인 원점수 = 해당 4문항 합 (4~20)
- 요인 원점수 = 하위 요인 6개 합 (24~120) / Mini-IPIP는 4문항 합 (4~20)

### 3.2 표시 점수: 0~100 환산 (POMP)
- `표시점수 = (원점수 - 최소) / (최대 - 최소) × 100`, 소수 첫째 자리 반올림
- **규준(백분위)이 없는 동안에는 이 점수만 쓴다.** 리포트 문구에서 "상위 ○%", "100명 가운데 ○번째" 같은 표현을 **쓰지 않는다.**

### 3.3 구간 (해석 문구 선택용)
| 표시점수 | 구간 | 화면 표기 |
|---|---|---|
| 0 ~ 34.9 | low | 낮은 쪽 |
| 35 ~ 65 | mid | 가운데 |
| 65.1 ~ 100 | high | 높은 쪽 |

- 구간 경계 ±5점 이내면 리포트에 "경계 근처" 표시와 함께 양쪽 해석을 모두 짧게 보여 준다.
- 구간은 해석을 돕는 어림일 뿐 기준선이 아님을 리포트에 명시한다.

### 3.4 응답 품질 점검 (리포트 상단 안내용, 결과를 막지는 않음)
- **소요 시간**: 120문항을 4분 미만에 끝내면 "너무 빨리 답했을 수 있다"는 안내.
- **한 가지 답 반복**: 같은 응답값이 전체의 70% 이상이면 안내.
- **역문항 일관성**: 같은 하위 요인 안에서 정채점과 역채점 문항 응답이 정반대로 어긋나는 비율이 높으면 안내. (기준은 Claude Code가 제안 → 손병목 승인)
- 품질 플래그는 DB에 저장하고, 관리자 통계·규준 생성에서 제외할 수 있게 한다.

### 3.5 규준 (다음 단계 준비)
- `big5_norms` 테이블과 관리자 '규준 생성' 기능의 뼈대만 만든다.
- 품질 플래그 없는 정식 검사 완료 데이터가 **300건 이상** 쌓이면 요인·하위 요인별 평균·표준편차를 계산해 저장할 수 있게 한다.
- 규준이 활성화되면 리포트에 "참고 백분위(이 사이트 응답자 기준)"를 추가로 보여 준다. 기본값은 비활성.

---

## 4. 화면 설계 (UI는 심플하게)

> 4.2·4.4의 화면 목록과 파일 이름은 **참고용**이다. 원칙(심플한 UI, 사용자/관리자 분리, 본인만 열람, 타인 평가는 로그인 없이)만 지키면 화면을 합치거나 나누어도 된다.

### 4.1 디자인 원칙
- 흰 바탕, 넉넉한 여백, 한 가지 강조색. 폰트는 Pretendard(CDN).
- 다섯 요인 색은 고정한다. 차트·막대·제목 아이콘에서 같은 색을 쓴다.
  - 성실성 #2F6F5E / 외향성 #D9822B / 친화성 #C2547A / 개방성 #4C6EB1 / 신경성 #7A6A9B (톤 다운된 색, 변경 가능)
- 모바일 우선. 검사 화면의 선택지 버튼은 엄지로 누르기 쉬운 크기.
- 장식 이미지 없음. 아이콘은 최소.

### 4.2 사용자 화면 목록
| 파일 | 화면 | 내용 |
|---|---|---|
| `index.php` | 시작 | 한 문단 소개, [정식 검사 120문항 · 약 15분] [간이 검사 20문항 · 약 3분] 버튼, 내 기록 바로가기 |
| `register.php` / `login.php` | 가입·로그인 | 아이디, 비밀번호, 이름(표시용). 만 14세 이상 확인 체크 |
| `consent.php` | 검사 전 안내 | 이 검사가 무엇이고 무엇이 아닌지, 데이터 저장 방식, 동의 체크 |
| `test.php` | 검사 | 한 화면에 10문항, 상단 진행 막대, 5점 버튼, [이전][다음]. 자동 저장(중간에 나가도 이어 하기) |
| `report.php?id=` | 결과 리포트 | 5장 구조. [인쇄/PDF 저장] 버튼 |
| `history.php` | 내 기록 | 검사 목록, 두 결과 선택해 비교 |
| `compare.php` | 비교 | 이전 결과 vs 지금 / 나 vs 타인 평가 |
| `invite.php` | 타인 평가 초대 | 초대 링크 생성(유효기간 14일), 관계 선택(가족·친구·동료·기타), 받은 응답 수 |
| `rate.php?t=` | 타인 평가 응답 | 로그인 없음. 안내 → Mini-IPIP(기본) 또는 120문항 타인용. 완료 화면에서 결과는 보여 주지 않음 |
| `account.php` | 계정 | 비밀번호 변경, **내 데이터 전체 삭제** |

### 4.3 검사 화면 세부
- 문항 번호, 문항 문장, 선택지 5개만 보인다.
- 상단 안내 한 줄 고정: "오늘의 기분보다 평소의 나를 떠올려 답해 주세요."
- 모든 문항에 답해야 [다음]이 활성화된다.
- 마지막 페이지에서 [결과 보기] → 채점 → 리포트로 이동.

### 4.4 관리자 화면 목록 (`/admin`)
| 파일 | 내용 |
|---|---|
| `admin/login.php` | 관리자 로그인 |
| `admin/index.php` | 응답 수(정식/간이/타인), 품질 플래그 비율, 요인별 평균 분포 차트 |
| `admin/users.php` | 사용자 목록, 검색, 비활성화 |
| `admin/items.php` | 문항 JSON 조회(읽기 전용), 버전 표시 |
| `admin/content.php` | 해석 문구 JSON 조회 및 누락 항목 점검표 |
| `admin/norms.php` | 규준 생성 뼈대 (300건 미만이면 비활성 안내) |
| `admin/export.php` | 익명 응답 데이터 CSV 내보내기 (이름·아이디 제외, 응답자 ID는 해시) |

---

## 5. 결과 리포트 설계 (결과지는 풍부하게) ★핵심

### 5.1 리포트 전체 구조
리포트는 한 페이지 안에서 위에서 아래로 읽는다. 상단에 목차(앵커 링크)를 둔다. 인쇄 시 장마다 페이지를 나눈다.

```
표지
0. 이 리포트를 읽는 법
1. 빅파이브란 무엇인가
2. 결과를 읽기 전에: 꼭 알아야 할 일곱 가지
3. 한눈에 보는 나의 다섯 다이얼
4. 다이얼별 상세 읽기 (5개 장)
   4-1 성실성 · 4-2 외향성 · 4-3 친화성 · 4-4 개방성 · 4-5 신경성
5. 다이얼의 조합으로 읽기
6. 일터에서의 나
7. 관계 속의 나
8. 다이얼은 움직인다: 성장 제안
9. 다른 사람이 본 나 (타인 평가가 있을 때)
10. 지난번의 나와 비교 (재검사가 있을 때)
11. 부록: 점수는 어떻게 계산되었나 · 용어 사전 · 더 읽을거리 · 출처
```

### 5.2 섹션별 내용 명세

**표지**
- 제목 "나의 다섯 다이얼", 이름, 검사일, 검사 종류(정식 120 / 간이 20), 문항 버전
- 다섯 요인 가로 막대 미니 요약
- 응답 품질 안내(해당 시)

**0. 이 리포트를 읽는 법** (고정 문구)
- 리포트 구성 안내, 처음 읽는 사람은 1~2장을 먼저 읽기를 권함
- 소요 시간 안내(약 20~30분)

**1. 빅파이브란 무엇인가** (고정 문구, 원고 1~2장·4장 요약)
- 성격을 다섯 개의 다이얼로 보는 이유: 사람은 칸에 들어가는 존재가 아니라 눈금 위에 놓인 존재
- 어휘 가설: 사람들이 서로를 묘사하는 말을 모았더니 다섯 갈래로 묶였다
- 다섯 요인 한 줄 정의 표
- 특성은 '기후', 그날의 기분은 '날씨'
- MBTI와 무엇이 다른가 (한 문단 + 작은 비교표: 자르기 vs 재기, 신경성 유무, 재검사 안정성)

**2. 결과를 읽기 전에: 꼭 알아야 할 일곱 가지** (고정 문구, 리포트 상단 고정 링크도 둠)
1. 높고 낮음은 좋고 나쁨이 아니라 방향이다.
2. 대부분의 사람은 가운데 어디쯤에 있다. 가운데는 애매한 것이 아니라 가장 흔한 자리다.
3. 이 점수는 '지금의 나'다. 성격은 나이와 경험, 습관에 따라 천천히 움직인다.
4. 이 검사는 스스로 답한 결과다. 내가 나를 보는 방식이 담겨 있고, 남이 보는 나와 다를 수 있다.
5. 점수는 다른 사람과 비교한 순위가 아니다. (규준 활성화 전) 0~100은 나의 응답을 환산한 위치다.
6. 이 검사는 진단 도구가 아니다. 정신 건강을 판정하지 않는다. 걱정과 우울이 오래 이어져 일상이 힘들다면 전문가와 상담하길 권한다.
7. 이 결과를 채용·평가·인사 판단이나 남을 판정하는 데 쓰지 않는다. 한국어 번역판은 아직 표준화 검증 전이다.

**3. 한눈에 보는 나의 다섯 다이얼**
- 차트 1: 다섯 요인 가로 막대(0~100), 구간 배경색(낮은 쪽/가운데/높은 쪽)
- 차트 2: 레이더 차트(보조)
- 요인별 한 줄 요약 (구간별 문구, `summary_line`)
- "가장 두드러진 다이얼" 두 개: 50점에서 가장 멀리 떨어진 요인 두 개를 골라 짧게 소개 (높든 낮든)

**4. 다이얼별 상세 읽기** (요인마다 같은 틀, 원고 3장 순서)
각 요인 장의 구성:
1. **이 다이얼은 무엇을 재나** (고정, 원고 3장 요약, 4~6문장)
2. **나의 위치**: 막대 + 표시점수 + 구간 표기
3. **이 위치의 사람들은 대체로** (구간별 본문, 3~5문단)
4. **강점** (구간별, 3~4개 문장)
5. **주의할 점** (구간별, 2~3개 문장)
6. **하위 요인 6개**: 작은 막대 6개 + 하위 요인별 구간 문구 1~2문장. 하위 요인 간 편차가 큰 경우(최고-최저 ≥ 40점) "같은 다이얼 안에서도 결이 다르다"는 안내 문구
7. **흔한 오해** (고정, 요인당 1~2개. 예: "내향성은 수줍음이 아니다", "신경성은 병이 아니다", "친화성이 낮다고 생각이 깊은 것은 아니다")
8. **한 줄로 남기면** (구간별 마무리 문장)

**5. 다이얼의 조합으로 읽기**
- 규칙 기반으로 해당하는 조합 카드만 보여 준다. 최소 2개, 최대 5개.
- 조합 규칙 예시 (각 조합에 high/low 조건, 제목, 본문 2~3문단):
  - 성실성 높음 × 신경성 높음: 꼼꼼하지만 늘 혹시를 걱정하는 사람
  - 성실성 높음 × 신경성 낮음: 해내고 털고 일어나는 사람
  - 외향성 높음 × 친화성 높음: 사람을 모으는 사람
  - 외향성 높음 × 친화성 낮음: 사람을 이끄는, 때로 밀어내는 사람
  - 외향성 낮음 × 신경성 낮음: 조용하지만 단단한 사람
  - 외향성 낮음 × 신경성 높음: 혼자 생각이 깊어지는 사람
  - 개방성 높음 × 성실성 낮음: 아이디어는 넘치지만 마무리가 숙제인 사람
  - 개방성 높음 × 성실성 높음: 구상하고 끝까지 데려가는 사람
  - 개방성 낮음 × 성실성 높음: 검증된 길을 정확하게 걷는 사람
  - 친화성 높음 × 신경성 높음: 남의 마음을 살피느라 내 마음이 지치는 사람
  - 다섯 요인 모두 가운데: 상황에 따라 다르게 움직이는 사람 (프롤로그 메시지)
- 조합 카드 제목도 '유형 이름'처럼 굳지 않도록 "~한 쪽으로 기우는 사람" 같은 서술형으로 쓴다.

**6. 일터에서의 나** (원고 2부 기반)
- 일하는 방식: 성실성·개방성 중심 문구
- 잘 맞는 일의 결: 원고 9장(기획, 영업, 콘텐츠, 개발, 고객 응대, 운영)의 경향을 바탕으로 "기운이 나는 일 / 기운이 닳는 일" 형태로 제시. **직업 추천이나 적성 판정처럼 쓰지 않는다.**
- 팀 안에서의 나: 친화성·외향성 중심 (회의에서의 모습, 반대 의견을 대하는 방식)
- 압박과 회복: 신경성·외향성 중심 (원고 12장의 회복 제안 연결)
- 리더라면: 원고 11장 (자기 다이얼과 반대 방향으로 움직이는 연습)

**7. 관계 속의 나**
- 가까운 사람과 있을 때, 갈등이 생길 때, 기운을 얻는 만남의 방식
- 외향성·친화성·신경성 조합 문구

**8. 다이얼은 움직인다: 성장 제안**
- 원고 6장 요약 2문단(성숙 원칙, 작은 행동의 반복이 다이얼을 움직인다)
- 사용자가 고르는 방식: "더 키우고 싶은 다이얼"과 "덜 쓰고 싶은 그림자"를 고르면 작은 행동 제안 3개를 보여 준다 (요인 × 방향별 행동 목록에서 선택)
- 선택한 행동을 저장해 두고 재검사 때 다시 보여 준다

**9. 다른 사람이 본 나** (타인 평가 1건 이상일 때)
- 나의 점수와 타인 평균을 나란히 막대로
- 차이가 15점 이상인 요인 표시: "내가 보는 나와 남이 보는 나가 다른 곳"
- 해석 안내(고정): 누가 맞다는 뜻이 아니다. 남에게 잘 보이는 모습과 속에서 느끼는 모습이 다를 수 있다.
- 평가자 2명 이상일 때만 평균 표시, 1명이면 "한 사람의 시선"으로 표기. 평가자 개인은 식별되지 않게 관계 범주만 표시
- **확장(0.13.0, 2026-11-11 대표 승인)** — 계산 `includes/others.php` `big5_others_section()`, 문구 `fixed.json` 의 `others`
  1. 한 줄 요약 + 세 묶음(차이 8점 미만 '비슷하게 보는 곳' / 8~15 '조금 다른 곳' / 15 이상 '다르게 보는 곳') + 겹친 레이더(나 = 채운 도형, 남 = 점선)
  2. 다이얼별 아령 그림: 나(채운 점)·남(빈 점)·잇는 선, 평가자 2명 이상이면 점수 범위 띠, 관계 2종류 이상이면 관계별 평균 표식. 구간이 달라지면 "나는 가운데, 남은 높은 쪽"
  3. 해설: ① 전체 모양(높낮이 순서가 같은가, 다섯 가운데 넷 이상이 한쪽으로 쏠렸는가 = 눈금 차이) ② 차이 × 남들끼리의 일치(범위 20점 이상이면 '갈림') ③ '다르게 보는 곳'마다 요인 × 방향(남이 더 높게·낮게) 해설과 '이 다이얼이 남에게 보이는 정도' ④ 관계에 따라 15점 이상 다르게 보이는 곳
  4. 얼마나 믿을까(평가자 수·간이 평가의 흔들림·문항 수가 다를 때·품질 표시가 붙어 뺀 평가) → 대화로 이어 가기(장면을 묻는 질문)
  - '남에게 보이는 정도'는 원고 밖 성격 연구(Vazire 2010, Connelly & Ones 2010)를 쓴다 — 부록 출처에 한 줄
  - 관계별 평균은 그 관계의 평가자가 1명뿐이어도 보여 준다("한 사람" 표기, 초대 화면에 안내)
  - 이번에는 요인 5개만. 하위 요인 비교와 검사 회차별 묶음은 다음 단계

**10. 지난번의 나와 비교** (재검사 시)
- 요인·하위 요인 변화 막대 (이전 → 지금)
- 10점 이상 변한 곳만 강조
- 고정 안내: 작은 변화는 측정 오차일 수 있다. 몇 달 이상의 간격에서 같은 방향의 변화가 이어질 때 의미가 있다.
- 8장에서 골랐던 성장 행동 다시 보여 주기

**11. 부록**
- 점수는 어떻게 계산되었나 (3장 채점 로직을 쉽게 설명)
- 용어 사전 (원고 부록 C 발췌)
- 더 읽을거리 (원고 부록 D)
- 출처와 고지: IPIP 공유 저작물, 한국어 번역판 미검증, 검사 버전

### 5.3 인쇄·PDF
- 별도 PDF 라이브러리 없이 **인쇄 전용 CSS(@media print)**로 구현하고 [인쇄/PDF 저장] 버튼은 `window.print()`.
- A4 세로, 장마다 `page-break-before`, 차트는 인쇄 시에도 보이게 (Chart.js 캔버스 → 인쇄 전 이미지 변환).
- 인쇄본 꼬리말: 이름 · 검사일 · "진단 도구가 아니며 채용·평가에 사용하지 않습니다."

---

## 6. 해석 문구(콘텐츠) 설계

### 6.1 파일 구조 (참고용)
해석 문구는 코드와 분리해 `data/content/` 아래 JSON으로 관리한다. 문구를 고쳐도 코드는 바꾸지 않는다.

```
data/content/
  fixed.json          # 0~2장, 각 요인 '무엇을 재나', '흔한 오해', 부록 등 고정 문구
  domains.json        # 요인 × 구간(low/mid/high): summary_line, body, strengths, cautions, closing
  facets.json         # 하위 요인 30개 × 구간: 1~2문장 + 하위 요인 정의
  combos.json         # 조합 규칙과 문구
  work.json           # 일터 섹션 문구 (요인 × 구간)
  relations.json      # 관계 섹션 문구
  growth.json         # 성장 행동 목록 (요인 × 키우기/그림자 줄이기, 각 5~8개)
```

### 6.2 domains.json 형식 (참고용)
```json
{
  "C": {
    "name": "성실성",
    "color": "#2F6F5E",
    "low":  { "summary_line": "", "body": [""], "strengths": [""], "cautions": [""], "closing": "" },
    "mid":  { "summary_line": "", "body": [""], "strengths": [""], "cautions": [""], "closing": "" },
    "high": { "summary_line": "", "body": [""], "strengths": [""], "cautions": [""], "closing": "" }
  }
}
```

### 6.3 문구 작성 원칙 (Claude Code가 초안을 쓸 때 반드시 지킨다)
- **문체**: 독자에게 말을 거는 "~합니다/~해요" 존댓말. 문장은 짧게. 미려한 한국어, 번역투 금지.
- **판정하지 않고 해석한다**: "당신은 ~한 사람입니다" 대신 "~하는 쪽으로 기우는 편입니다", "~할 때가 많을 수 있어요".
- **모든 구간에 강점과 주의점이 함께 있다.** 낮은 구간의 문구가 결함 목록이 되지 않게 한다.
- **경향의 언어**를 쓴다: "대체로", "~하는 경우가 많다", "~할 수 있다". 단정 금지.
- **예측과 결정을 구분한다**: "성실성이 높으면 성과가 좋다" (X) → "성실성은 일의 성과와 꾸준히 이어지는 다이얼로 알려져 있어요" (O)
- **신경성 문구는 특히 조심한다**: 병리 용어(장애, 질환, 증상) 금지. 높은 구간에도 '경보 장치'의 쓸모를 함께 쓴다. 높은 구간 문구 끝에는 도움 받을 곳 안내 한 줄을 둔다.
- **유형 이름 금지**: "성실형", "리더형", "OO타입" 같은 이름을 만들지 않는다.
- 금지 표현: ~를 통해, ~함으로써, ~에 있어서, ~로 인해, 다양한, 효과적인, 긍정적인, 중요한, 올바른, 무너지다, 갇히다.
- 원고 『다섯 개의 다이얼』의 용어·비유(다이얼, 날씨와 기후, 경보 장치, 출발선과 걸음)를 일관되게 쓴다.
- 모든 문구 초안은 손병목 검수 후 확정한다. 초안 단계의 파일에는 `"status": "draft"`를 표시한다.

### 6.4 예시 문구 (성실성, 형식과 결을 보여 주는 견본)
```json
"high": {
  "summary_line": "계획하고, 맡은 일을 끝까지 해내는 쪽으로 분명하게 기울어 있어요.",
  "body": [
    "약속한 일은 지키고, 시작한 일은 끝내는 편입니다. 해야 할 일이 보이면 미루기보다 먼저 손을 대고, 일을 맡기는 사람 입장에서는 '맡기면 끝나 있는 사람'으로 보이기 쉬워요.",
    "다만 이 다이얼이 높을수록 계획이 틀어질 때 마음이 크게 불편해질 수 있어요. 80점이면 충분한 일에도 100점을 내려고 시간을 쓰거나, 다른 사람에게도 같은 기준을 기대하게 되는 순간이 있습니다."
  ],
  "strengths": [
    "맡은 일을 끝까지 데려가는 힘",
    "실수를 줄이고 약속을 지키는 믿음직함",
    "긴 목표를 작은 단계로 나누어 꾸준히 해내는 힘"
  ],
  "cautions": [
    "완벽을 놓지 못해 정작 급한 일이 밀릴 수 있어요.",
    "믿고 맡기는 일이 몰리면 가장 먼저 지칠 수 있어요. 거절도 성실함의 일부입니다."
  ],
  "closing": "끝까지 해내는 힘이 당신의 출발선입니다. 그 힘을 오래 쓰려면 멈출 때를 아는 것도 필요해요."
},
"low": {
  "summary_line": "계획에 얽매이기보다 그때그때 상황에 맞춰 움직이는 쪽으로 기울어 있어요.",
  "body": [
    "정해진 순서보다 지금 필요한 것을 먼저 하는 편이고, 상황이 바뀌면 방향을 빨리 트는 유연함이 있어요. 즉흥적인 기회를 잘 잡고, 계획에 없던 일에도 덜 당황합니다.",
    "대신 마감이나 자잘한 일을 놓칠 때가 있을 수 있어요. 이것은 의지가 약해서라기보다, 기억하고 챙기는 일을 머릿속에만 맡겨 두기 때문인 경우가 많습니다."
  ],
  "strengths": ["변화에 빠르게 맞추는 유연함", "계획에 없던 기회를 알아보는 눈", "틀에 얽매이지 않는 가벼움"],
  "cautions": [
    "마감과 약속을 놓쳐 믿음을 잃을 수 있어요.",
    "시작한 일을 끝내지 못한 채 다음 일로 넘어가기 쉬워요."
  ],
  "closing": "성실성은 머릿속이 아니라 바깥에 둘 수 있어요. 달력과 알림, 작은 첫 단계가 당신의 성실성이 되어 줍니다."
}
```

---

## 7. 데이터베이스 설계 (참고용 스키마)

> 아래 스키마는 필요한 데이터를 보여 주는 **참고안**이다. 접두어 `big5_`만 지키면 테이블 구성·컬럼·정규화 방식은 더 나은 설계로 바꿔도 된다. 단, 원응답을 보존해 채점 버전이 바뀌어도 다시 계산할 수 있어야 한다는 원칙은 지킨다.

```sql
-- 모든 테이블 접두어: big5_

CREATE TABLE IF NOT EXISTS big5_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  display_name  VARCHAR(50)  NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  age_confirmed TINYINT(1)   NOT NULL DEFAULT 0,      -- 만 14세 이상 확인
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 검사 1회 (본인 검사)
CREATE TABLE IF NOT EXISTS big5_sessions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT          NOT NULL,
  test_type      ENUM('full120','mini20') NOT NULL,
  item_version   VARCHAR(40)  NOT NULL,
  status         ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  started_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at   DATETIME     NULL,
  duration_sec   INT          NULL,
  quality_flags  VARCHAR(255) NULL,                    -- 예: 'fast,straightline'
  consent_at     DATETIME     NOT NULL,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 문항별 응답 (본인·타인 공통)
CREATE TABLE IF NOT EXISTS big5_responses (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  owner_type  ENUM('session','rating') NOT NULL,
  owner_id    INT      NOT NULL,
  item_no     SMALLINT NOT NULL,
  value       TINYINT  NOT NULL,                       -- 1~5
  answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_owner_item (owner_type, owner_id, item_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 채점 결과 (요인·하위 요인)
CREATE TABLE IF NOT EXISTS big5_scores (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  owner_type  ENUM('session','rating') NOT NULL,
  owner_id    INT         NOT NULL,
  level       ENUM('domain','facet') NOT NULL,
  code        VARCHAR(4)  NOT NULL,                    -- 'C' 또는 'C3'
  raw_score   SMALLINT    NOT NULL,
  pomp        DECIMAL(5,1) NOT NULL,                   -- 0~100
  band        ENUM('low','mid','high') NOT NULL,
  UNIQUE KEY uq_score (owner_type, owner_id, level, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 타인 평가 초대
CREATE TABLE IF NOT EXISTS big5_invites (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  token       CHAR(64)     NOT NULL UNIQUE,           -- random_bytes 기반
  relation    ENUM('family','friend','colleague','other') NOT NULL,
  test_type   ENUM('full120','mini20') NOT NULL DEFAULT 'mini20',
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 타인 평가 1건
CREATE TABLE IF NOT EXISTS big5_ratings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  invite_id     INT      NOT NULL,
  status        ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at  DATETIME NULL,
  duration_sec  INT      NULL,
  quality_flags VARCHAR(255) NULL,
  FOREIGN KEY (invite_id) REFERENCES big5_invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 성장 행동 선택
CREATE TABLE IF NOT EXISTS big5_growth_choices (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT         NOT NULL,
  session_id  INT         NOT NULL,
  domain      CHAR(1)     NOT NULL,
  direction   ENUM('grow','shadow') NOT NULL,
  action_key  VARCHAR(40) NOT NULL,
  created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES big5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 규준 (다음 단계, 기본 비활성)
CREATE TABLE IF NOT EXISTS big5_norms (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  norm_set    VARCHAR(40)  NOT NULL,                   -- 예: 'site-2027-01'
  level       ENUM('domain','facet') NOT NULL,
  code        VARCHAR(4)   NOT NULL,
  n           INT          NOT NULL,
  mean_raw    DECIMAL(6,2) NOT NULL,
  sd_raw      DECIMAL(6,2) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_norm (norm_set, level, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `big5_responses`·`big5_scores`의 owner_id는 다형 참조라 외래 키를 걸지 않는다. 대신 사용자 삭제 시 애플리케이션 코드에서 관련 세션·평가의 응답과 점수를 함께 지운다.
- 채점 결과는 저장해 두되, **문항·채점 버전이 바뀌면 원응답에서 다시 계산**할 수 있게 한다.

---

## 8. 개인정보와 윤리

- **동의**: 검사 전 `consent.php`에서 수집 항목(아이디, 표시 이름, 응답, 소요 시간), 목적(본인 결과 제공, 익명 통계·규준 연구), 보관 기간(탈퇴 시까지), 삭제 방법을 안내하고 동의를 받는다.
- **만 14세 미만은 이용할 수 없다.** 가입 시 확인 체크. (이 검사는 성인·청소년 이상용이다)
- **열람 권한**: 리포트는 본인만. `report.php`는 세션 소유자 확인 필수. URL의 id만 바꿔 남의 결과를 볼 수 없게 한다.
- **타인 평가 익명성**: 평가자의 이름·연락처를 받지 않는다. 결과에는 관계 범주만 표시한다. 평가자가 1명일 때 개인 응답이 그대로 드러나는 점을 초대 화면에 안내한다.
- **삭제권**: `account.php`에서 내 데이터 전체 삭제(계정, 세션, 응답, 점수, 초대, 받은 평가). 되돌릴 수 없음을 확인받는다.
- **내보내기**: 관리자 CSV에는 아이디·이름을 넣지 않는다. 사용자 식별값은 해시로 바꾼다.
- **사용 제한 고지**: 시작 화면·리포트·인쇄본에 "진단 도구가 아니며, 채용·평가·인사 판단에 사용하지 않습니다"를 표시한다.
- **사회적 책임 문구**: 신경성 높은 구간 결과에는 도움 받을 곳 안내 한 줄을 둔다. (예: 정신건강 상담 전화 1577-0199, 운영 전 최신 번호 재확인)

---

## 9. 기술 스택과 파일 구조

### 9.1 기술 스택
- PHP 8.x (바닐라), MariaDB 10.x (utf8mb4_unicode_ci)
- UI: 바닐라 CSS(단일 `assets/style.css`) + 바닐라 JS, Pretendard CDN
- 차트: Chart.js CDN (막대, 레이더)
- 호스팅: sonbm.com (카페24, FTP 배포, Composer 불가)

### 9.2 파일 구조 (참고용, C. 시각화·리포트형 기반)
```
PLAN.md                     # 이 문서
install.php
index.php                   # 시작
register.php
login.php
logout.php
consent.php
test.php                    # 본인 검사
report.php                  # 결과 리포트
history.php
compare.php
invite.php
rate.php                    # 타인 평가 (로그인 없음, 토큰)
account.php
api.php                     # 응답 자동 저장, 채점 호출 등 AJAX

includes/
  .htaccess                 # Deny from all
  db_config.php             # 기존 규칙: 비밀정보 없는 로더(심)
  header.php
  footer.php
  auth_check.php
  admin_check.php
  scoring.php               # 채점 함수 (순수 함수, DB 의존 최소화)
  report_builder.php        # 점수 + content JSON → 리포트 데이터 구성
  content_loader.php        # data/content/*.json 로딩·검증
  quality.php               # 응답 품질 점검

data/
  .htaccess                 # Deny from all (JSON 직접 열람 차단)
  source/                   # IPIP 원문 (손병목이 넣음)
  items_120.json
  items_20.json
  content/                  # 6장 해석 문구 JSON

admin/
  login.php
  index.php
  users.php
  items.php
  content.php
  norms.php
  export.php
  api.php

assets/
  style.css
  print.css                 # @media print 전용
  admin.css
  test.js                   # 검사 화면 (페이지 넘김, 자동 저장)
  report.js                 # 차트 렌더링, 인쇄 전 차트 이미지 변환

tests/
  scoring_test.php          # 채점 검증용 (CLI 실행)
```

---

## 10. 작업 단계 (Claude Code 작업 지시서)

> 단계 구분과 순서는 참고안이다. 더 나은 순서가 있으면 작업 계획에서 제안한다.
> 각 단계가 끝날 때마다 **동작 확인 → 손병목 확인 → 깃허브 푸시** 후 다음 단계로 간다.

### 1단계 — 기반 공사
- install.php, includes 공통 파일, 로그인/가입/로그아웃, 관리자 로그인
- 7장 테이블 전체 생성, 초기 계정 생성 (기존 규칙)
- **완료 기준**: 로컬에서 install.php 실행 → 사용자·관리자 로그인 성공, includes/·data/ 직접 접근 403

### 2단계 — 문항 데이터와 채점 엔진
- `data/source/`의 원문을 읽어 `items_120.json` 생성 (원문 키 그대로 + 번역 초안 + 타인용 문장), `items_20.json` 생성
- `includes/scoring.php`: 역채점, 하위 요인·요인 원점수, POMP, 구간 계산
- `tests/scoring_test.php`: 다음 경우를 모두 통과해야 한다
  - 모든 문항 3점 → 모든 요인·하위 요인 50.0, 구간 mid
  - 정채점 5 / 역채점 1 → 모든 점수 100.0
  - 정채점 1 / 역채점 5 → 모든 점수 0.0
  - 하위 요인당 문항 정확히 4개, 요인당 하위 요인 6개, 120개 문항 번호 중복·누락 없음
- **완료 기준**: 테스트 전부 통과, 번역 초안 검수용 표(문항번호·하위요인·방향·원문·번역)를 `data/review_items.md`로 출력

### 3단계 — 검사 화면
- consent → test(10문항/페이지, 진행 막대, 자동 저장, 이어 하기) → 채점 → 리포트 이동
- 응답 품질 점검(quality.php) 저장
- **완료 기준**: 휴대폰 화면에서 120문항을 끝까지 진행 가능, 중간에 브라우저를 닫았다 열어도 이어 하기

### 4단계 — 해석 문구 초안
- 6장 원칙에 따라 `data/content/*.json` 전체 초안 작성 (`status: draft`)
- 고정 문구(1장 빅파이브 소개, 2장 일곱 가지 주의점 등)는 이 문서 5.2의 내용을 바탕으로 쓴다
- `admin/content.php`에 누락 점검표 (요인 5 × 구간 3, 하위 요인 30 × 구간 3, 조합 규칙 수 등)
- **완료 기준**: 누락 0건, 금지 표현 검색 결과 0건, `data/review_content.md`로 검수용 출력

### 5단계 — 결과 리포트
- `report_builder.php` + `report.php` + `report.js` + `print.css`
- 5.2의 섹션 0~8, 11 구현 (9·10은 6단계)
- 경계 근처 표시, 하위 요인 편차 안내, 가장 두드러진 다이얼, 조합 카드 선택 로직
- **완료 기준**: 샘플 응답 3종(전부 3점 / 무작위 / 극단값)으로 리포트가 깨지지 않고 생성, 인쇄 미리보기에서 장마다 페이지가 나뉘고 차트가 보임

### 6단계 — 재검사·비교·타인 평가
- history, compare, invite, rate
- 리포트 9·10장 연결
- **완료 기준**: 초대 링크로 다른 브라우저(로그인 없음)에서 평가 완료 → 본인 리포트에 반영, 만료 링크 접근 차단, 다른 사용자 리포트 id 접근 차단

### 7단계 — 관리자
- 4.4 화면 전체, 익명 CSV 내보내기, 규준 생성 뼈대(300건 미만 비활성)
- **완료 기준**: CSV에 아이디·이름이 없음, 품질 플래그 응답 제외 옵션 동작

### 8단계 — 점검과 배포
- 12장 점검표 전체 확인
- sonbm.com(카페24) 배포, install.php 삭제
- 손병목 본인 검사 1회 → 리포트 검수 → 문구 수정 반영

---

## 11. 코딩 규칙 (기존 규칙 요약)

- **기존 바이브코딩 규칙을 그대로 따른다.** 아래는 이 프로젝트에서 특히 확인할 것.
- 서버는 sonbm.com(카페24)이고, 프로그램은 **하위 폴더 `/big5/`**에 설치한다. sonbm.com 루트에서 운영 중인 사이트(WordPress)와 충돌하지 않게, 모든 링크·리다이렉트·자산 경로는 설정값 `BASE_URL`(기본 `/big5`)을 기준으로 만들고 `/admin/login.php`처럼 루트 기준 절대 경로를 하드코딩하지 않는다. 루트의 WordPress `.htaccess` 재작성 규칙이 `/big5/` 요청을 가로채지 않는지 배포 전에 확인한다.
- DB 접속 실제 값은 www 밖 `{홈}/config/big5_db.php`에 둔다. `includes/db_config.php`는 비밀정보 없는 로더로 두고, 가까운 상위 폴더부터 `config/`를 찾아 require한다. 이후 전역 `$pdo`는 PDO 또는 null.
- PDO + Prepared Statements만 사용, mysqli 금지, SQL 문자열 직접 연결 금지
- 비밀번호 `password_hash()` / `password_verify()`
- 출력은 모두 `htmlspecialchars()`; JSON 문구를 HTML로 넣을 때도 이스케이프 (줄바꿈만 허용)
- 모든 POST에 CSRF 토큰
- 사용자 세션 `$_SESSION['user_id']`, 관리자 세션 `$_SESSION['admin_id']` 분리, `/admin` 모든 파일 상단 `admin_check.php`
- 초대 토큰은 `bin2hex(random_bytes(32))`
- 에러는 화면 출력 금지, `error_log()`만
- `includes/.htaccess`, `data/.htaccess` 모두 `Deny from all` (install.php가 자동 생성)
- 초기 계정: 관리자 `sonbm` / `visang`, 사용자 `user` / `visang`
- 한국어 주석
- 작업이 끝나면 항상 깃허브에 푸시

---

## 12. 완료 점검표

**채점**
- [ ] scoring_test.php 전부 통과
- [ ] 번역으로 채점 방향이 뒤집힌 문항 없음 (검수표 확인)

**검사 화면**
- [ ] 모바일·태블릿·PC에서 정상
- [ ] 이어 하기 동작
- [ ] 모든 문항 응답 전 [다음] 비활성

**리포트**
- [ ] 1장 빅파이브 소개, 2장 일곱 가지 주의점 포함
- [ ] 'OO형' 같은 유형 이름이 어디에도 없음
- [ ] "상위 ○%" 같은 백분위 표현 없음 (규준 비활성 상태)
- [ ] 모든 구간 문구에 강점과 주의점이 함께 있음
- [ ] 신경성 높은 구간에 도움 안내 한 줄
- [ ] 인쇄/PDF 정상, 꼬리말 고지 포함

**보안·개인정보**
- [ ] 다른 사용자 리포트 접근 불가
- [ ] includes/, data/ 직접 접근 차단
- [ ] 내 데이터 전체 삭제 동작
- [ ] CSV 익명화
- [ ] install.php 삭제

---

## 13. 첫 바이브 (Claude Code에 붙여 넣기)

```
다이얼(Dial) — 빅파이브 성격 검사·진단 웹 프로그램 개발을 시작합니다.
DB 테이블 접두어: big5_

## 목적
누구나 웹에서 빅파이브 성격 검사(IPIP-NEO-120 / Mini-IPIP 20)를 하고,
빅파이브를 처음 접하는 사람도 충분히 이해할 수 있는 풍부한 결과 리포트를 받는 프로그램.
UI는 심플하게, 결과지는 풍부하게.

## 가장 먼저 할 일
프로젝트 루트의 PLAN.md를 처음부터 끝까지 읽으세요.
PLAN.md가 이 프로젝트의 계획서이자 작업 지시서입니다.
정독한 뒤 더 좋은 방법이 보이면 계획을 수정해 제안해 주세요. (PLAN.md 0.1 참조)
특히 2장(검사 도구), 3장(채점), 5장(리포트), 6장(해석 문구 원칙), 8장(개인정보)을 정확히 이해하세요.

## 기술 스택
- PHP 8.x (바닐라, 프레임워크 없음)
- MariaDB 10.x (utf8mb4_unicode_ci)
- UI: 바닐라 CSS + 바닐라 JS, Pretendard CDN, Chart.js CDN
- 호스팅: sonbm.com (카페24, FTP 배포, Composer 사용 불가)
- 설치 위치: sonbm.com/big5/ 하위 폴더 (루트의 WordPress와 충돌하지 않게 BASE_URL 기준 경로 사용)

## 파일 구조와 DB
PLAN.md 9장 파일 구조와 7장 스키마, 2·6장 JSON 형식, 4장 화면 목록은 참고안입니다.
PLAN.md 0.1의 '고정' 항목은 지키되, '참고' 항목은 더 좋은 방법이 있으면 바꿔도 됩니다.
바꾸려는 것이 있으면 작업 계획에 '변경 제안' 목록(무엇을, 왜)으로 먼저 보여 주세요.

## 코딩 규칙
기존 바이브코딩 규칙을 따르세요. (PLAN.md 11장에 이 프로젝트에서 확인할 항목을 요약해 두었습니다.)
- DB 실제 접속 정보는 www 밖 {홈}/config/big5_db.php, includes/db_config.php는 로더
- 사용자/관리자 영역과 세션 분리, install.php 포함, includes/·data/ 접근 차단
- 초기 계정: 관리자 sonbm/visang, 사용자 user/visang

## 절대 지킬 것
- 문항은 data/source/의 IPIP 원문 키를 근거로만 만들고, 기억으로 문항을 지어내지 않는다.
- 결과에 유형 이름(OO형)을 만들지 않는다. 규준이 없으므로 백분위 표현을 쓰지 않는다.
- 해석 문구는 코드와 분리해 data/content/ JSON으로 관리하고, PLAN.md 6.3 원칙을 지킨다.
- 리포트에는 빅파이브 소개와 해석 전 주의점 일곱 가지를 반드시 포함한다.

## 작업 순서
PLAN.md 10장의 1~8단계 순서로 진행합니다.
각 단계가 끝나면 완료 기준을 확인하고 나에게 보고한 뒤, 깃허브에 푸시하고 다음 단계로 갑니다.

위와 같은 나의 의도를 정확하게 이해한 후, 오류 없는 코드 작성을 위해
단계별 작업 계획을 수립하여 나에게 먼저 제시해 주세요.
내가 작업 계획을 승인하면, 작업을 착수해 주세요.
```

---

## 14. 단계별 후속 프롬프트

**[2단계] 문항과 채점**
```
data/source/에 IPIP-NEO-120 원문을 넣었습니다.
PLAN.md 2장과 3장에 따라 items_120.json, items_20.json, includes/scoring.php, tests/scoring_test.php를 만들어 주세요.
원문의 하위 요인 코드와 채점 방향은 한 글자도 바꾸지 말고, 번역은 2.3 원칙을 따르세요.
테스트를 실행해 결과를 보여 주고, data/review_items.md 검수표를 만들어 주세요.
```

**[4단계] 해석 문구 초안**
```
PLAN.md 5장과 6장에 따라 data/content/ 아래 JSON 해석 문구 초안을 모두 써 주세요.
6.4 성실성 견본의 결과 분량을 기준으로 삼고, 6.3 원칙과 금지 표현을 지키세요.
다 쓰면 금지 표현을 검색해 0건인지 확인하고, data/review_content.md로 검수본을 만들어 주세요.
```

**[5단계] 결과 리포트**
```
PLAN.md 5장 리포트 구조대로 report.php를 만들어 주세요. 섹션 0~8과 11을 먼저 구현합니다.
샘플 응답 3종(전부 3점 / 무작위 / 극단값)을 넣어 리포트를 생성하고, 인쇄 미리보기까지 확인해 주세요.
UI는 심플하게, 내용은 풍부하게. 읽기 편한 줄 간격과 폭(본문 최대 680px)을 지켜 주세요.
```

**[문구 수정 반영]**
```
내가 data/review_content.md에 수정 사항을 적었습니다.
수정 사항을 data/content/ JSON에 반영하고, 반영한 항목의 status를 final로 바꿔 주세요.
같은 원칙이 다른 문구에도 적용되어야 하면 함께 고치고, 무엇을 고쳤는지 목록으로 보고해 주세요.
```

---

## 부록: 참고 출처
- IPIP (International Personality Item Pool) — https://ipip.ori.org (공유 저작물, 상업적 사용 허락 불필요)
- Johnson, J. A. (2014). Measuring thirty facets of the Five Factor Model with a 120-item public domain inventory: Development of the IPIP-NEO-120. *Journal of Research in Personality*, 51, 78–89.
- Donnellan, M. B., Oswald, F. L., Baird, B. M., & Lucas, R. E. (2006). The Mini-IPIP scales. *Psychological Assessment*, 18(2), 192–203.
- 손병목, 『다섯 개의 다이얼 — 처음 만나는 빅파이브 성격 이야기』 원고 (2026)

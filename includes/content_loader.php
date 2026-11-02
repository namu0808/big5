<?php
/**
 * 해석 문구 로더 — data/content/*.json (코드와 분리, 문구를 고쳐도 코드는 바꾸지 않는다)
 *
 * 파일과 키 (5단계 report_builder 가 이 키 이름을 그대로 읽는다):
 *  fixed.json      리포트 0~2장(reading_guide, intro, seven_cautions), glance, detail(소제목·경계 근처·편차 안내),
 *                  domain_about[요인]{tagline, about, misconceptions[]}, combos/work/relations 머리말,
 *                  growth{paragraphs, how_to}, others(9장), retest(10장), appendix(계산법·용어·읽을거리·출처),
 *                  help{line}, print_footer, band_labels
 *  domains.json    [요인]{name, color, low|mid|high{summary_line, body[], strengths[], cautions[], closing, (N.high) help_line}}
 *  facets.json     facets[하위 요인 코드]{name, definition, low, mid, high}
 *  combos.json     selection{min,max,note}, rules[]{id, when{요인: 구간}, title, body[]}
 *  work.json       style·team·pressure·leader {title, by[요인][구간] 문자열}, energy{by[요인][구간]{gain[], drain[]}}
 *  relations.json  close·conflict·energy {title, by[요인][구간] 문자열}
 *  growth.json     domains[요인]{grow|shadow{label, actions[]{key, text}}}
 * 모든 파일에 "status": "draft" | "final".
 */
declare(strict_types=1);

const BIG5_CONTENT_FILES = ['fixed', 'domains', 'facets', 'combos', 'work', 'relations', 'growth'];

/** 해석 문구 전체 (요청 안 캐시). 하나라도 읽지 못하면 그 키는 null */
function big5_content(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $dir = dirname(__DIR__) . '/data/content/';
    $out = [];
    foreach (BIG5_CONTENT_FILES as $name) {
        try {
            $data = json_decode((string) @file_get_contents($dir . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
            $out[$name] = is_array($data) ? $data : null;
        } catch (Throwable $e) {
            error_log("다이얼 해석 문구 읽기 실패: {$name}.json " . $e->getMessage());
            $out[$name] = null;
        }
    }
    return $cache = $out;
}

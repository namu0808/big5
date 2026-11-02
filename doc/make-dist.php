<?php
/**
 * 배포 묶음 만들기 — dist/big5-deploy-YYYYMMDD.zip + doc/배포-파일목록.txt
 *  big5/            → 서버 {홈}/www/big5/ 에 그대로 올린다 (배포 제외 규칙 적용)
 *  install.php      → 설치할 때만 /big5/ 에 올려 1회 실행 후 삭제 (1time/install.php 복사)
 *  config/big5_db.sample.php → {홈}/config/big5_db.php 로 복사해 값을 채운다 (웹루트 밖)
 * 배포 제외(CLAUDE.md): doc/ tests/ 1time/ data/source/ data/translate/ data/review_*.md PLAN.md CLAUDE.md README.md 원고, 점 파일(.htaccess 는 포함)
 * 실행: php doc/make-dist.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$date = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Ymd');

/** 배포 대상인가 (앱 루트 기준 상대 경로) */
function big5_dist_include(string $rel): bool
{
    $parts = explode('/', $rel);
    $base = end($parts);
    if (in_array($parts[0], ['doc', 'tests', '1time', 'dist', 'node_modules'], true)) {
        return false;
    }
    // data/source(원문)·data/translate(번역 원본 CSV)는 빌드 재료라 서버에 필요 없다
    if (str_starts_with($rel, 'data/source/') || str_starts_with($rel, 'data/translate/') || preg_match('#^data/review_.*\.md$#', $rel)) {
        return false;
    }
    if (in_array($rel, ['PLAN.md', 'CLAUDE.md', 'README.md', '빅파이브 Big 5.md'], true)) {
        return false;
    }
    foreach ($parts as $p) {
        if (str_starts_with($p, '.') && $p !== '.htaccess') {
            return false; // .git, .gitignore, .gitattributes, .claude 등
        }
    }
    if (preg_match('/\.(sqlite|sqlite-journal|log|bak|swp)$/', $base)) {
        return false;
    }
    return true;
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    if (big5_dist_include($rel)) {
        $files[] = $rel;
    }
}
sort($files);

@mkdir($root . '/dist', 0775, true);
$zipPath = $root . "/dist/big5-deploy-{$date}.zip";
@unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "zip 을 만들 수 없습니다: {$zipPath}\n");
    exit(1);
}
foreach ($files as $rel) {
    $zip->addFile($root . '/' . $rel, 'big5/' . $rel);
}
$zip->addFile($root . '/1time/install.php', 'install.php');
$zip->addFile($root . '/doc/big5_db.sample.php', 'config/big5_db.sample.php');
$zip->addFromString('읽어 주세요.txt', "다이얼 배포 묶음 ({$date})\n\n"
    . "1. big5/ 폴더의 내용을 서버 {홈}/www/big5/ 에 올립니다 (.htaccess 파일 포함).\n"
    . "2. config/big5_db.sample.php 를 서버 {홈}/config/big5_db.php 로 복사해 DB 접속 정보와 BIG5_EXPORT_SALT 를 채웁니다 (웹루트 밖).\n"
    . "3. install.php 를 {홈}/www/big5/ 에 올려 브라우저로 https://sonbm.com/big5/install.php 를 한 번 연 뒤, 바로 삭제합니다.\n"
    . "자세한 순서는 저장소의 doc/배포-안내.md 를 보세요.\n");
$zip->close();

$list = "# 다이얼 FTP 배포 파일 목록 ({$date}) — 서버 {홈}/www/big5/ 기준, " . count($files) . "개\n"
    . "# 설치 때만: install.php (1time/install.php, 실행 후 삭제) / 웹루트 밖: {홈}/config/big5_db.php (doc/big5_db.sample.php 로 만듦)\n"
    . implode("\n", $files) . "\n";
file_put_contents($root . '/doc/배포-파일목록.txt', $list);

echo "배포 묶음: dist/big5-deploy-{$date}.zip (" . count($files) . "개 + install.php + config 견본), 목록: doc/배포-파일목록.txt\n";

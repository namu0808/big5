<?php
/** 시작 화면 — 한 문단 소개, 검사 시작 버튼 두 개, 내 기록 바로가기 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = big5_current_user($pdo);
$page_title = '';
$nav_active = 'home';
$page_indexable = true;
$meta_description = '다섯 개의 다이얼로 나를 읽는 빅파이브(Big5) 성격 검사. 정식 120문항·간이 20문항, 『초등 공부력』 저자 손병목.';
require __DIR__ . '/includes/header.php';

// 로그인 전이면 검사 버튼은 로그인 화면으로 (로그인 뒤 동의 화면으로 이어진다)
$full = big5_url('consent.php?type=full120');
$mini = big5_url('consent.php?type=mini20');
?>
<section class="hero">
  <h1>나의 다섯 다이얼</h1>
  <p class="lead">사람은 칸에 들어가는 존재가 아니라 눈금 위에 놓인 존재입니다. 다이얼은 성실성·외향성·친화성·개방성·신경성, 다섯 개의 다이얼이 지금 어디쯤 놓여 있는지 보여 주는 빅파이브 성격 검사예요. 유형으로 나누지 않고, 높고 낮음을 좋고 나쁨으로 말하지 않습니다.</p>
  <div class="start-buttons">
    <a class="btn btn-primary btn-lg" href="<?= h($full) ?>">정식 검사<span class="btn-note">120문항 · 약 15분</span></a>
    <a class="btn btn-outline btn-lg" href="<?= h($mini) ?>">간이 검사<span class="btn-note">20문항 · 약 3분</span></a>
  </div>
<?php if ($user !== null): ?>
  <p class="hero-sub"><?= h($user['display_name']) ?>님, 반갑습니다. <a href="<?= h(big5_url('history.php')) ?>">내 기록 보기</a></p>
<?php else: ?>
  <p class="hero-sub">검사 결과를 저장하고 다시 보려면 <a href="<?= h(big5_account_url('login.php')) ?>">로그인</a>하거나 <a href="<?= h(big5_account_url('register.php')) ?>">익명으로 가입</a>해 주세요(이름·연락처 없이 아이디와 비밀번호만). SBM 진단 검사의 한 계정으로 모든 검사를 이용합니다.</p>
<?php endif; ?>
</section>
<section class="notice-box">
  <p><strong>이 검사는 진단 도구가 아닙니다.</strong> 정신 건강을 판정하지 않으며, 채용·평가·인사 판단에 사용하지 않습니다. 만 14세 이상이 이용할 수 있어요.</p>
</section>
<?php require __DIR__ . '/includes/footer.php';

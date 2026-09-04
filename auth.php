<?php
/* ─────────────────────────────────────────────────────────────
   약재검색포털 — 세션 · 로그인 · CSRF · DB 연결 공통
   index.php 와 api.php 가 함께 사용합니다.
   ───────────────────────────────────────────────────────────── */
declare(strict_types=1);

/* PHP 8.0 이상이 필요합니다. 카페24 나의서비스관리에서 PHP 8.2 로 설정하세요.
   ⚠ PHP 8 ↔ 7 변경 시 데이터와 DB가 초기화되므로 설치 시점에 정해야 합니다. */
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('PHP 8.0 이상이 필요합니다. 현재 버전: ' . PHP_VERSION);
}

require_once __DIR__ . '/config.php';

/* ── 세션 시작 (쿠키 보안 옵션 포함) ── */
function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

/* ── 설정이 아직 안 끝났는지 ── */
function is_configured(): bool
{
    /* password_hash() 결과는 알고리즘에 따라 $2y$ · $argon2id$ 등으로 시작합니다 */
    return APP_PASSWORD_HASH !== 'PUT_YOUR_PASSWORD_HASH_HERE'
        && strlen(APP_PASSWORD_HASH) >= 20
        && APP_PASSWORD_HASH[0] === '$';
}

function is_logged_in(): bool
{
    app_session_start();
    if (empty($_SESSION['auth'])) {
        return false;
    }
    /* 마지막 활동 이후 SESSION_LIFETIME 이 지나면 만료 */
    if (time() - (int)($_SESSION['seen'] ?? 0) > SESSION_LIFETIME) {
        logout();
        return false;
    }
    $_SESSION['seen'] = time();
    return true;
}

function login(string $password): bool
{
    app_session_start();
    if (!is_configured() || !password_verify($password, APP_PASSWORD_HASH)) {
        return false;
    }
    session_regenerate_id(true);          /* 세션 고정 공격 방지 */
    $_SESSION['auth'] = true;
    $_SESSION['seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function logout(): void
{
    app_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    app_session_start();
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

/* ── DB 연결 (PDO) ── */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/* ── 로그인 화면 ──
   앱 마크업을 전혀 출력하지 않고 이 화면만 그린 뒤 종료합니다. */
function render_login(?string $error = null): void
{
    $configured = is_configured();
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $msg   = $error !== null ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : '';
    ?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#241109">
<title>약재검색포털</title>
<style>
  :root{
    --wood-1:#4a3421; --wood-2:#31210f; --wood-3:#241109;
    --hanji:#f0e5cd; --hanji-edge:#d8c39a; --ink:#3a2a17;
    --brass:#d0aa57; --danger:#b23a2e;
  }
  *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{height:100%;}
  body{
    font-family:-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo","Noto Sans KR",sans-serif;
    color:var(--ink); display:grid; place-items:center; padding:24px;
    background:
      radial-gradient(120% 80% at 50% -10%, rgba(255,220,160,.10), transparent 60%),
      linear-gradient(180deg,var(--wood-1),var(--wood-2) 40%,var(--wood-3));
    background-attachment:fixed; -webkit-text-size-adjust:100%;
  }
  .box{ width:100%; max-width:360px; text-align:center; }
  .seal{ width:64px; height:64px; margin:0 auto 16px; border-radius:12px; display:grid; place-items:center;
    font-size:32px; font-weight:800; color:#fff5df; background:linear-gradient(145deg,#8a2e26,#651e18);
    border:2px solid #d9a24b; box-shadow:inset 0 1px 2px rgba(255,255,255,.25), 0 3px 10px rgba(0,0,0,.45); }
  h1{ font-size:21px; color:var(--hanji); letter-spacing:.5px; }
  .sub{ font-size:12px; color:#c9b384; margin-top:4px; letter-spacing:2px; margin-bottom:22px; }
  form{ display:flex; flex-direction:column; gap:10px; }
  input{ width:100%; font-size:16px; font-family:inherit; color:var(--ink); text-align:center;
    background:var(--hanji); border:1px solid var(--hanji-edge); border-radius:11px; padding:15px 12px; outline:0;
    box-shadow:inset 0 1px 2px rgba(0,0,0,.08); }
  input::placeholder{ color:#a2916f; }
  button{ width:100%; font-size:15px; font-weight:700; font-family:inherit; color:#3a2a17; cursor:pointer;
    background:linear-gradient(180deg,#f0d488,#c9a24b); border:1px solid #9c7a30; border-radius:11px; padding:14px;
    box-shadow:0 2px 6px rgba(0,0,0,.3); }
  button:active{ transform:translateY(1px); }
  .err{ font-size:13px; color:#ffb4a8; background:rgba(178,58,46,.22); border:1px solid rgba(178,58,46,.5);
    border-radius:9px; padding:10px 12px; margin-bottom:4px; }
  .setup{ font-size:12.5px; line-height:1.7; color:#e4d4ac; text-align:left;
    background:rgba(240,229,205,.10); border:1px solid rgba(208,170,87,.3); border-radius:11px; padding:14px; }
  .setup b{ color:var(--hanji); }
  .setup code{ display:block; margin-top:6px; padding:9px 10px; border-radius:7px; font-size:11.5px;
    background:rgba(0,0,0,.32); color:#f0d488; word-break:break-all; }
</style>
</head>
<body>
  <div class="box">
    <div class="seal">藥</div>
    <h1>약재검색포털</h1>
    <p class="sub">藥材檢索</p>
    <?php if (!$configured): ?>
      <div class="setup">
        <b>설정이 아직 끝나지 않았습니다.</b><br>
        SSH 로 접속해 아래를 실행하고, 출력된 해시를
        <b>config.php</b> 의 <b>APP_PASSWORD_HASH</b> 에 붙여넣으세요.
        <code>php -r "echo password_hash('원하는비밀번호', PASSWORD_DEFAULT), PHP_EOL;"</code>
      </div>
    <?php else: ?>
      <?php if ($msg !== ''): ?><div class="err"><?= $msg ?></div><?php endif; ?>
      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= $token ?>">
        <input type="password" name="password" placeholder="비밀번호" autocomplete="current-password" autofocus required>
        <button type="submit">들어가기</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html><?php
}

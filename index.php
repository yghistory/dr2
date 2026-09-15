<?php
/* ─────────────────────────────────────────────────────────────
   약재검색포털 — 로그인 게이트 + 앱 본체
   비밀번호가 확인되기 전에는 아래 앱 마크업을 전혀 출력하지 않습니다.
   ───────────────────────────────────────────────────────────── */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* 로그아웃 */
if (isset($_GET['logout'])) {
    logout();
    header('Location: index.php');
    exit;
}

/* 로그인 처리 */
$loginError = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $loginError = '보안 토큰이 만료되었습니다. 다시 시도하세요.';
    } elseif (login((string)$_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = '비밀번호가 맞지 않습니다.';
        usleep(400000);                    /* 무차별 대입 완화 */
    }
}

if (!is_logged_in()) {
    render_login($loginError);
    exit;
}

$CSRF = csrf_token();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="약재검색포털">
<meta name="theme-color" content="#241109">
<meta name="format-detection" content="telephone=no">
<title>약재검색포털</title>
<style>
  :root{
    --wood-1:#4a3421; --wood-2:#31210f; --wood-3:#241109;
    --hanji:#f0e5cd; --hanji-2:#e5d5b3; --hanji-edge:#d8c39a;
    --ink:#3a2a17; --ink-soft:#7a6647;
    --brass:#d0aa57; --brass-hi:#f0d488; --brass-lo:#9c7a30;
    --danger:#b23a2e; --warn:#c9862e; --ok:#4a7c59;
    --sheet:#f6efe0;
    --safe-b: env(safe-area-inset-bottom, 0px);
    --safe-t: env(safe-area-inset-top, 0px);
  }
  *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  img{max-width:100%;}
  html,body{height:100%; max-width:100%; overflow-x:clip;} /* 페이지 가로 스크롤 차단(스티키 유지) */
  body{
    font-family:-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo","Noto Sans KR",sans-serif;
    color:var(--ink);
    background:
      radial-gradient(120% 80% at 50% -10%, rgba(255,220,160,.10), transparent 60%),
      linear-gradient(180deg,var(--wood-1),var(--wood-2) 40%,var(--wood-3));
    background-attachment:fixed;
    -webkit-text-size-adjust:100%;
    overscroll-behavior-y:none;
  }
  header{
    position:sticky; top:0; z-index:20;
    padding:calc(var(--safe-t) + 12px) max(14px, calc((100% - 1560px) / 2)) 10px;
    background:linear-gradient(180deg,rgba(36,17,9,.96),rgba(36,17,9,.88));
    backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);
    border-bottom:1px solid rgba(208,170,87,.25);
    box-shadow:0 6px 18px rgba(0,0,0,.35);
  }
  .plate{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
  /* 대표 로고 — 투명 PNG 라 어두운 목재 배경 위에 그대로 얹힌다 */
  .logo{ flex:0 0 auto; height:46px; width:auto; max-width:40vw; object-fit:contain; display:block; }
  .titles{ flex:1 1 auto; min-width:0; }
  .titles h1{ font-size:19px; letter-spacing:.5px; color:var(--hanji); font-weight:800; }
  .titles p{ font-size:12px; color:#c9b384; margin-top:2px; letter-spacing:2px; }
  .impBtn{ flex:0 0 auto; font-size:12.5px; font-weight:700; color:#3a2a17;
    background:linear-gradient(180deg,#f0d488,#c9a24b); border:1px solid #9c7a30; border-radius:10px;
    padding:8px 11px; cursor:pointer; white-space:nowrap; box-shadow:0 2px 5px rgba(0,0,0,.3); }
  .impBtn:active{ transform:translateY(1px); }
  .summary{ display:flex; gap:14px; font-size:12px; color:#d7c49a; margin-bottom:10px; flex-wrap:wrap; }
  .summary b{ color:var(--hanji); font-weight:700; }
  .summary .warnpill{ color:var(--warn); }
  .summary .nopos{ color:#c9a; }
  .search{ display:flex; align-items:center; gap:8px; background:var(--hanji); border-radius:11px;
    padding:18px 12px; border:1px solid var(--hanji-edge); box-shadow:inset 0 1px 2px rgba(0,0,0,.08); }
  .search svg{ flex:0 0 auto; opacity:.55; }
  .search input{ border:0; outline:0; background:transparent; width:100%; font-size:16px; color:var(--ink); font-family:inherit; }
  .search input::placeholder{ color:#a2916f; }
  .chips{ display:flex; gap:7px; overflow-x:auto; margin-top:10px; padding-bottom:2px; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
  .chips::-webkit-scrollbar{ display:none; }
  .chip{ flex:0 0 auto; font-size:13px; padding:6px 12px; border-radius:999px; background:rgba(240,229,205,.14);
    color:#e4d4ac; border:1px solid rgba(208,170,87,.3); white-space:nowrap; display:flex; align-items:center; gap:6px; cursor:pointer; }
  .chip .cdot{ width:8px; height:8px; border-radius:50%; }
  .chip.on{ background:var(--hanji); color:var(--ink); border-color:var(--hanji); font-weight:700; }
  .tabs{ display:flex; gap:6px; margin-bottom:10px; background:rgba(240,229,205,.1); padding:4px; border-radius:12px; border:1px solid rgba(208,170,87,.22); }
  .tab{ flex:1; text-align:center; padding:9px 6px; border-radius:9px; font-size:14px; font-weight:700; color:#e4d4ac; cursor:pointer; border:0; background:transparent; font-family:inherit; }
  .tab.on{ background:var(--hanji); color:var(--ink); box-shadow:0 1px 3px rgba(0,0,0,.3); }

  main{ padding:14px 12px calc(120px + var(--safe-b)); display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px;
    max-width:1560px; margin-inline:auto; }
  @media(min-width:560px){  main{ grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; } } /* 대형 폰·소형 태블릿 */
  @media(min-width:768px){  main{ grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; } } /* 태블릿 */
  @media(min-width:1024px){ main{ grid-template-columns:repeat(5,minmax(0,1fr)); gap:16px; } } /* PC */
  @media(min-width:1400px){ main{ grid-template-columns:repeat(6,minmax(0,1fr)); } }           /* 대형 PC */

  .drawer{ container-type:inline-size; position:relative; text-align:center; cursor:pointer; border:0; font-family:inherit; color:var(--ink);
    padding:16px 10px 16px; border-radius:9px; background:linear-gradient(180deg,var(--hanji),var(--hanji-2));
    box-shadow:inset 0 1px 0 rgba(255,255,255,.6), inset 0 -3px 6px rgba(120,90,50,.18), 0 2px 5px rgba(0,0,0,.35);
    border-top:1px solid #fff7e6; overflow:hidden; transition:transform .12s ease, box-shadow .12s ease; }
  .drawer::before{ content:""; position:absolute; inset:0; opacity:.5; pointer-events:none;
    background:repeating-linear-gradient(90deg, rgba(150,110,60,.06) 0 3px, rgba(150,110,60,0) 3px 9px); }
  .drawer:active{ transform:translateY(2px) scale(.985); box-shadow:inset 0 2px 8px rgba(120,90,50,.35), 0 1px 2px rgba(0,0,0,.3); }
  .drawer .hanja{ font-size:12px; font-size:clamp(11px,3.8cqw,15px); color:var(--ink-soft); letter-spacing:2px; margin-bottom:3px; min-height:14px; }
  .drawer .drow{ display:flex; align-items:center; justify-content:center; width:100%;
    gap:9px; gap:clamp(5px,2.5cqw,11px); min-height:52px; min-height:clamp(42px,15cqw,74px); }
  /* 이름·위치배지: 카드 폭(cqw)에 비례해 확대·축소 → 폰 축소·PC 확대, 서로 겹치지 않음.
     (앞 값은 컨테이너쿼리 미지원 브라우저용 고정 폴백) */
  .drawer .name{ flex:1 1 auto; min-width:0; overflow-wrap:anywhere; font-weight:900; letter-spacing:.5px; line-height:1.1;
    font-size:29px; font-size:clamp(16px,14.5cqw,40px); }
  .drawer .name.n4{ font-size:24px; font-size:clamp(15px,12.5cqw,35px); }
  .drawer .name.n5{ font-size:22px; font-size:clamp(13px,11.5cqw,32px); }
  .drawer .name.n7{ font-size:17px; font-size:clamp(11px,8.5cqw,24px); line-height:1.15; }
  .drawer .posbig{ flex:0 0 auto; max-width:62%; display:flex; flex-direction:column; gap:1px; text-align:center; font-weight:900; line-height:1.16; color:var(--ink); letter-spacing:.5px;
    font-size:22px; font-size:clamp(15px,12cqw,32px);
    padding:9px 15px; padding:clamp(6px,3.4cqw,13px) clamp(9px,5.5cqw,20px);
    border-radius:11px; border-radius:clamp(7px,2.4cqw,14px);
    border:2px solid rgba(90,61,26,.85); background:linear-gradient(180deg,#f8efd6,#e9d9b2);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.65), 0 1px 3px rgba(0,0,0,.22); }
  .drawer .posbig.none{ color:#b0a084; font-size:15px; font-size:clamp(11px,7.5cqw,19px); font-weight:700; letter-spacing:0;
    border:1.5px dashed rgba(156,122,48,.45); background:transparent; box-shadow:none; }
  .drawer .posbig .ppos{ font-size:.58em; font-weight:800; letter-spacing:0; opacity:.9; }
  .drawer .cat{ display:inline-flex; align-items:center; gap:5px; font-size:11px; font-size:clamp(10px,3.4cqw,14px); color:var(--ink-soft); margin-top:6px; }
  .drawer .cat .cdot{ width:8px; height:8px; border-radius:50%; }
  .drawer .ware{ margin-top:7px; font-size:11px; font-size:clamp(10px,3.4cqw,13px); font-weight:700; color:#5a4326;
    background:rgba(120,90,50,.12); border:1px solid rgba(150,110,60,.28); border-radius:7px; padding:3px 7px;
    display:inline-block; max-width:100%; overflow-wrap:anywhere; }
  .drawer .bar{ height:5px; border-radius:3px; margin-top:9px; background:rgba(120,90,50,.2); overflow:hidden; }
  .drawer .bar > i{ display:block; height:100%; border-radius:3px; background:linear-gradient(90deg,#8a6d3b,#c9a24b); }
  .drawer.low .bar > i{ background:linear-gradient(90deg,#b23a2e,#d97a3c); }
  .drawer .qty{ font-size:13px; font-size:clamp(11px,3.8cqw,15px); font-weight:700; margin-top:5px; color:var(--ink); }
  .drawer .qty.none{ color:#b0a084; font-weight:600; }
  .drawer.low .qty{ color:var(--danger); }
  .drawer .handle{ position:absolute; left:50%; bottom:10px; transform:translateX(-50%); width:44px; height:8px; border-radius:5px;
    background:linear-gradient(180deg,var(--brass-hi),var(--brass),var(--brass-lo));
    box-shadow:0 1px 2px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.5); }
  .drawer .lowtag{ position:absolute; top:7px; right:8px; font-size:10px; font-weight:800; color:#fff; background:var(--danger);
    padding:2px 6px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.3); }

  .empty{ grid-column:1/-1; text-align:center; color:#d7c49a; padding:56px 20px; }
  .empty .big{ font-size:40px; margin-bottom:10px; }

  /* ── 전체 배치도(엑셀형 표) ── */
  .mapwrap{ margin:2px 8px calc(24px + var(--safe-b)); overflow:auto; -webkit-overflow-scrolling:touch;
    max-height:calc(100vh - 200px); border-radius:10px; border:1px solid rgba(208,170,87,.3); background:#efe6d0; }
  .maptbl{ border-collapse:separate; border-spacing:0; }
  .maptbl th, .maptbl td{ border-right:1px solid #d6c69f; border-bottom:1px solid #d6c69f; }
  .maptbl thead th{ position:sticky; top:0; z-index:3; background:linear-gradient(180deg,#3a2a1c,#2a1d12); color:var(--hanji);
    font-size:11px; font-weight:700; padding:6px 4px; min-width:70px; }
  .maptbl thead th.corner{ left:0; z-index:4; min-width:42px; width:42px; }
  .maptbl .rowh{ position:sticky; left:0; z-index:2; background:linear-gradient(180deg,#3a2a1c,#2a1d12); color:var(--hanji);
    font-size:12px; font-weight:800; min-width:42px; width:42px; text-align:center; padding:4px; }
  .maptbl td{ vertical-align:bottom; background:linear-gradient(180deg,#f4ecd8,#ece0c4); min-width:70px; }
  .maptbl td.empty{ background:repeating-linear-gradient(45deg,#e9dcc0 0 6px,#e2d4b4 6px 12px); }
  .maptbl .mcell{ display:block; padding:6px 7px; font-size:12.5px; font-weight:600; color:var(--ink); line-height:1.25;
    border-bottom:1px dotted rgba(150,110,60,.35); word-break:keep-all; cursor:pointer; }
  .maptbl .mcell:last-child{ border-bottom:0; }
  .maptbl .mcell:active{ background:rgba(208,170,87,.45); }
  .maptbl .mcell.hit{ background:#ffdf7e; color:#3a2400; font-weight:800; box-shadow:inset 0 0 0 2px #b7862a; }
  .mapzone{ position:sticky; left:0; display:flex; align-items:center; gap:8px; padding:10px 10px 8px; margin-top:6px;
    font-size:14px; font-weight:800; color:var(--ink); background:#e4d4ac; border-bottom:1px solid #cbb98f; }
  .mapzone:first-child{ margin-top:0; }
  .mapzone .zdot{ width:12px; height:12px; border-radius:50%; flex:0 0 auto; }
  .mapback{ position:sticky; left:0; padding:10px 10px 2px; }
  .mapback button{ font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; color:var(--ink);
    background:#f0d488; border:1px solid #9c7a30; border-radius:10px; padding:8px 13px; }

  .fab{ position:fixed; right:18px; bottom:calc(18px + var(--safe-b)); z-index:30; width:60px; height:60px; border-radius:50%;
    border:2px solid #f0d488; background:radial-gradient(120% 120% at 30% 25%,#e8c057,#b7862a); color:#3a2400;
    font-size:34px; font-weight:300; line-height:1; display:grid; place-items:center; cursor:pointer;
    box-shadow:0 8px 20px rgba(0,0,0,.45), inset 0 1px 2px rgba(255,255,255,.5); }
  .fab:active{ transform:scale(.93); }

  .backdrop{ position:fixed; inset:0; z-index:40; background:rgba(20,10,4,.55); opacity:0; visibility:hidden;
    transition:opacity .25s; backdrop-filter:blur(2px); -webkit-backdrop-filter:blur(2px); }
  .backdrop.on{ opacity:1; visibility:visible; }
  .sheet{ position:fixed; left:0; right:0; bottom:0; z-index:50; background:var(--sheet); border-radius:20px 20px 0 0;
    padding:8px 18px calc(22px + var(--safe-b)); max-height:92vh; overflow-y:auto; -webkit-overflow-scrolling:touch;
    transform:translateY(100%); transition:transform .3s cubic-bezier(.32,.72,0,1); box-shadow:0 -10px 40px rgba(0,0,0,.4); }
  .sheet.on{ transform:translateY(0); }
  .grip{ width:42px; height:5px; border-radius:3px; background:#cbb98f; margin:6px auto 12px; }
  .sheet h2{ font-size:19px; margin-bottom:16px; color:var(--ink); }
  .sheet h2 span{ font-size:13px; color:var(--ink-soft); font-weight:500; }
  .field{ margin-bottom:14px; }
  .field label{ display:block; font-size:12px; color:var(--ink-soft); margin-bottom:6px; font-weight:600; }
  .seclabel{ font-size:13px; font-weight:800; color:var(--ink); margin:6px 0 8px; padding-bottom:5px; border-bottom:1px solid #e2d4b3; }
  .field input, .field select, .field textarea{ width:100%; font-size:16px; font-family:inherit; color:var(--ink);
    padding:11px 12px; border:1px solid #d8c8a6; border-radius:11px; background:#fffdf7; outline:0; }
  .field input:focus, .field select:focus, .field textarea:focus{ border-color:var(--brass-lo); }
  .field textarea{ resize:none; line-height:1.5; }
  .row2{ display:flex; gap:10px; } .row2 > *{ flex:1; }
  .stepper{ display:flex; align-items:stretch; gap:8px; }
  .stepper button{ flex:0 0 46px; font-size:26px; font-weight:300; border:1px solid #d8c8a6; background:#fffdf7; border-radius:11px; color:var(--ink); cursor:pointer; }
  .stepper button:active{ background:#efe6d0; }
  .stepper input{ text-align:center; font-weight:700; }
  .actions{ display:flex; gap:10px; margin-top:20px; }
  .btn{ flex:1; padding:14px; border-radius:13px; font-size:16px; font-weight:700; border:0; cursor:pointer; font-family:inherit; }
  .btn.save{ background:linear-gradient(180deg,#3e6b48,#2f5537); color:#fff; box-shadow:0 3px 8px rgba(47,85,55,.4); }
  .btn.save:active{ transform:translateY(1px); }
  .btn.del{ flex:0 0 56px; background:#f3e2df; color:var(--danger); font-size:22px; }
  .btn.del:active{ background:#eccfca; }

  .imp-help{ font-size:13px; line-height:1.6; color:var(--ink-soft); background:#efe6d0; border-radius:11px; padding:11px 13px; margin-bottom:14px; }
  .imp-help b{ color:var(--ink); }
  #imp_text{ min-height:150px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:13px; white-space:pre; }
  .imp-preview{ font-size:13px; color:var(--ink); background:#fffdf7; border:1px solid #e2d4b3; border-radius:11px; padding:11px 13px; margin-top:12px; max-height:150px; overflow:auto; }
  .imp-preview .cnt{ font-weight:800; color:var(--ok); }
  .imp-preview .warn{ color:var(--danger); font-weight:700; }
  .imp-preview ul{ margin-top:6px; padding-left:2px; list-style:none; color:var(--ink-soft); }
  .imp-preview li{ padding:2px 0; } .imp-preview li b{ color:var(--ink); }
  .checkrow{ display:flex; align-items:center; gap:9px; margin-top:14px; font-size:14px; color:var(--ink); }
  .checkrow input{ width:20px; height:20px; }
  .datagrp{ font-size:13px; font-weight:800; color:var(--ink); margin:16px 0 8px; }
  .datagrp:first-of-type{ margin-top:4px; }
  .datagrp em{ display:block; font-size:11.5px; font-weight:500; color:var(--ink-soft); margin-top:2px; }
  .btn2{ width:100%; text-align:left; font-family:inherit; cursor:pointer; color:var(--ink); background:#fffdf7;
    border:1px solid #d8c8a6; border-radius:12px; padding:13px 14px; margin-bottom:9px; font-size:15px; font-weight:700; }
  .btn2 em{ display:block; font-size:12px; font-weight:500; color:var(--ink-soft); margin-top:3px; }
  .btn2:active{ background:#efe6d0; transform:translateY(1px); }

  .toast{ position:fixed; left:50%; bottom:calc(90px + var(--safe-b)); transform:translate(-50%,20px); z-index:60;
    background:rgba(36,17,9,.95); color:var(--hanji); font-size:14px; font-weight:600; padding:11px 18px; border-radius:12px;
    border:1px solid rgba(208,170,87,.4); opacity:0; pointer-events:none; transition:opacity .25s, transform .25s; box-shadow:0 6px 20px rgba(0,0,0,.4); }
  .toast.on{ opacity:1; transform:translate(-50%,0); }

  /* ── 창고위치(구역 카드) ── */
  .zonewrap{ padding:14px 12px calc(120px + var(--safe-b)); max-width:1560px; margin-inline:auto;
    display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; }
  .zonecard{ text-align:left; border:0; font-family:inherit; cursor:pointer; color:var(--ink); overflow:hidden;
    border-radius:14px; background:linear-gradient(180deg,var(--hanji),var(--hanji-2)); border-top:1px solid #fff7e6;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 3px 8px rgba(0,0,0,.35); transition:transform .12s ease; }
  .zonecard:active{ transform:translateY(2px) scale(.99); }
  .zonecard .zhead{ height:64px; background:#e7d8b6; display:grid; place-items:center;
    border-bottom:1px solid rgba(150,110,60,.3); }
  .zonecard .zhead .ph{ font-size:30px; opacity:.5; }
  .zonecard .zbar{ height:6px; }
  .zonecard .zbody{ padding:11px 12px 13px; }
  .zonecard .zname{ font-size:16px; font-weight:900; letter-spacing:.5px; display:flex; align-items:center; gap:7px; }
  .zonecard .zname .zdot{ width:11px; height:11px; border-radius:50%; flex:0 0 auto; }
  .zonecard .zmeta{ font-size:12.5px; color:var(--ink-soft); margin-top:6px; }
  .zonecard .zmeta .low{ color:var(--danger); font-weight:800; }
  .zonewrap .empty{ grid-column:1/-1; }
  .zonebanner{ grid-column:1/-1; display:flex; align-items:center; gap:10px; margin:0 2px 3px;
    background:rgba(240,229,205,.14); border:1px solid rgba(208,170,87,.3); border-radius:11px; padding:9px 12px;
    color:#e4d4ac; font-size:14px; font-weight:700; }
  .zonebanner button{ margin-left:auto; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
    background:var(--hanji); color:var(--ink); border:0; border-radius:9px; padding:7px 12px; }
</style>
</head>
<body>

<header>
  <div class="plate" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,auto);gap:8px;align-items:center;">
    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
      <img class="logo" src="logo.png" alt="삼희건재">
      <div class="titles"><h1>약재검색포털</h1><p>韓 藥 保 管 欌</p></div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:6px;">
      <button class="impBtn" id="dataBtn">⇅ 데이터</button>
      <a class="impBtn" href="?logout=1" style="text-decoration:none;display:inline-flex;align-items:center;">나가기</a>
    </div>
  </div>
  <div class="summary">
    <span>총 <b id="stTotal">0</b>종</span>
    <span class="warnpill">부족 <b id="stLow">0</b>종</span>
    <span class="nopos">위치미정 <b id="stNoPos">0</b>종</span>
  </div>
  <div class="tabs" id="tabs">
    <button class="tab on" data-view="search" type="button">🔍 검색</button>
    <button class="tab" data-view="map" type="button">🗂 전체 배치도</button>
    <!-- 창고위치 탭 — 당장 안 써서 숨김. 다시 쓰려면 style 속성만 지우면 됩니다. -->
    <button class="tab" data-view="zone" type="button" style="display:none">📦 창고위치</button>
  </div>
  <div class="search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#7a6647" stroke-width="2.2">
      <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5" stroke-linecap="round"/></svg>
    <input id="search" type="search" placeholder="이름·초성(ㅌㅅㅈ)·영타(xtw)·위치 검색" autocomplete="off">
  </div>
  <div class="chips" id="chips"></div>
</header>

<main id="grid"></main>
<div id="map" class="mapwrap" style="display:none"></div>
<div id="zone" class="zonewrap" style="display:none"></div>
<button class="fab" id="addBtn" aria-label="약재 추가">+</button>
<div class="backdrop" id="backdrop"></div>

<div class="sheet" id="sheet" role="dialog" aria-modal="true">
  <div class="grip"></div>
  <h2 id="sheetTitle">약재 추가</h2>
  <form id="form" autocomplete="off">
    <input type="hidden" id="f_id">
    <div class="row2">
      <div class="field" style="flex:2"><label>약재명 (한글)</label><input id="f_name" required placeholder="예: 인삼"></div>
      <div class="field"><label>한자</label><input id="f_hanja" placeholder="人蔘"></div>
    </div>
    <div class="field"><label>분류</label><select id="f_cat"></select></div>
    <div class="row2">
      <div class="field" style="flex:2"><label>수량</label>
        <div class="stepper"><button type="button" data-step="-5">−</button>
          <input id="f_qty" type="number" inputmode="numeric" min="0" value="0">
          <button type="button" data-step="5">＋</button></div></div>
      <div class="field"><label>단위</label>
        <select id="f_unit"><option>돈</option><option>g</option><option>근</option><option>첩</option><option>개</option></select></div>
    </div>
    <div class="field"><label>경고 수량</label><input id="f_min" type="number" inputmode="numeric" min="0" value="0"></div>
    <div class="seclabel">🗄 약장(검색) 위치</div>
    <div class="row2">
      <div class="field"><label>열 (세로)</label>
        <select id="f_yeol"><option value="">-</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>R</option></select></div>
      <div class="field"><label>행 (가로)</label><input id="f_haeng" type="number" inputmode="numeric" min="1" max="18" placeholder="1~18"></div>
    </div>
    <!-- 창고 위치 입력 — 창고위치 탭과 함께 숨김. 다시 쓰려면 두 style 속성만 지우면 됩니다.
         입력칸은 그대로 두어야 합니다(값을 읽고 쓰는 코드가 이 id들을 참조하며,
         숨긴 동안에도 기존 창고 위치 값이 저장 시 유지됩니다). -->
    <div class="seclabel" style="display:none">🏬 창고 위치 <span style="color:#a2916f;font-weight:400">· 약장과 별개</span></div>
    <div class="row2" style="display:none">
      <div class="field"><label>구역 (A~Z)</label><input id="f_zone" placeholder="예: C" autocapitalize="characters" maxlength="2"></div>
      <div class="field"><label>열</label><input id="f_wyeol" type="number" inputmode="numeric" min="1" placeholder="1"></div>
      <div class="field"><label>행</label><input id="f_whaeng" type="number" inputmode="numeric" min="1" placeholder="1"></div>
    </div>
    <div class="field"><label>효능 · 메모</label><textarea id="f_note" placeholder="예: 대보원기(大補元氣)."></textarea></div>
    <div class="actions">
      <button type="button" class="btn del" id="delBtn" title="삭제">🗑</button>
      <button type="submit" class="btn save" id="saveBtn">저장</button>
    </div>
  </form>
</div>

<div class="sheet" id="dataSheet" role="dialog" aria-modal="true">
  <div class="grip"></div>
  <h2>데이터 <span>· 가져오기 / 내보내기</span></h2>
  <div class="datagrp">📥 가져오기 <em>엑셀·구글시트에서 표 복사 → 붙여넣기</em></div>
  <button type="button" class="btn2" id="d_impPos">약재위치 가져오기 <em>약장 열·행 배치도</em></button>
  <!-- 약재창고 항목 2개 — 창고위치 탭과 함께 숨김. 다시 쓰려면 style 속성만 지우면 됩니다. -->
  <button type="button" class="btn2" id="d_impWare" style="display:none">약재창고 가져오기 <em>구역·열·행</em></button>
  <div class="datagrp">📤 내보내기 <em>클립보드 복사 → 엑셀에 붙여넣기</em></div>
  <button type="button" class="btn2" id="d_expPos">약재위치 내보내기 <em>약장 배치도</em></button>
  <button type="button" class="btn2" id="d_expWare" style="display:none">약재창고 내보내기 <em>구역별 배치</em></button>
</div>

<div class="sheet" id="importSheet" role="dialog" aria-modal="true">
  <div class="grip"></div>
  <h2 id="imp_title">약재위치 가져오기 <span>· 약장 열·행</span></h2>
  <div class="imp-help" id="imp_help"></div>
  <div class="field"><textarea id="imp_text" placeholder="여기에 붙여넣기…"></textarea></div>
  <div class="imp-preview" id="imp_preview">붙여넣으면 인식 결과가 여기에 표시됩니다.</div>
  <label class="checkrow"><input type="checkbox" id="imp_replace"><span id="imp_replabel">기존 데이터를 <b>전부 교체</b> (해제 시: 이름 같으면 위치만 갱신, 나머지는 추가)</span></label>
  <div class="actions"><button type="button" class="btn save" id="imp_apply">적용</button></div>
</div>

<div class="toast" id="toast"></div>

<script>
"use strict";

/* ── 서버 API ──
   데이터는 MariaDB 에 있고, 저장하면 곧바로 서버에 반영됩니다.
   세션이 끊기면(401) 로그인 화면으로 되돌아갑니다. */
const CSRF = <?= json_encode($CSRF, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

async function api(action, payload){
  const opt = payload
    ? { method:"POST", headers:{"Content-Type":"application/json"},
        body: JSON.stringify({ action, csrf: CSRF, ...payload }) }
    : { method:"GET" };
  const url = payload ? "api.php" : "api.php?action=" + encodeURIComponent(action);
  let res;
  try{ res = await fetch(url, opt); }
  catch(e){ throw new Error("서버에 연결할 수 없습니다"); }
  if(res.status === 401){ location.reload(); throw new Error("로그인이 필요합니다"); }
  let json;
  try{ json = await res.json(); }
  catch(e){ throw new Error("서버 응답을 읽지 못했습니다"); }
  if(!json.ok) throw new Error(json.msg || "저장에 실패했습니다");
  return json;
}

/* 서버 상태로 되돌린다 — 저장 실패 시 화면과 DB가 어긋나지 않게 */
async function resync(){
  try{ state = (await api("list")).data; render(); }catch(e){}
}

const CATS = {
  "보기":{label:"보기 補氣",color:"#c9862e"}, "보혈":{label:"보혈 補血",color:"#a83232"},
  "보음":{label:"보음 補陰",color:"#3d5a80"}, "해표":{label:"해표 解表",color:"#4a7c59"},
  "청열":{label:"청열 淸熱",color:"#3a8891"}, "화담":{label:"화담 化痰",color:"#6b5b7b"},
  "이기":{label:"이기 理氣",color:"#8a6d3b"}, "활혈":{label:"활혈 活血",color:"#c85a3c"},
  "기타":{label:"기타 其他",color:"#7d746a"},
};

/* ── 창고 구역 (A구역·B구역…) ──
   구역은 데이터(각 약재의 zone 값)에서 자동으로 모읍니다. 색은 순서대로 배정. */
const ZONE_PALETTE=["#8a6d3b","#3d5a80","#4a7c59","#a83232","#6b5b7b","#3a8891","#c85a3c","#c9862e"];
function zoneList(){ return [...new Set(state.map(h=>(h.zone||"").trim()).filter(Boolean))].sort(); }
function zoneOf(h){ return (h.zone||"").trim() || "미지정"; }
function zoneDef(key){
  if(key==="미지정") return {key, label:"구역 미지정", color:"#7d746a", emoji:"❔"};
  const i=zoneList().indexOf(key);
  return {key, label:key+"구역", color:ZONE_PALETTE[(i<0?0:i)%ZONE_PALETTE.length], emoji:"📦"};
}

let zoneOpen = null;   // 창고위치 탭에서 펼친 구역

/* 초기 데이터(약장 배치도 315건)는 seed.sql 로 DB에 직접 넣습니다.
   예전에는 이 자리에 GRID / ENRICH / buildSeed() 가 있었고 화면에서 시드를
   올렸지만, 초기 데이터의 출처를 seed.sql 하나로 두기 위해 걷어냈습니다. */


let state = [];
let activeCat = "전체";
let query = "";
let view = "search";
const YEOL_ORDER=["1","2","3","4","5","6","7","8","9","R"];
const HAENG_ORDER=Array.from({length:18},(_,i)=>String(i+1));

function esc(s){ return String(s??"").replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c])); }
function isLow(h){ const q=Number(h.qty)||0; return q>0 && q<=Number(h.min||0); }
function posLabel(h){ return (h.yeol && h.haeng) ? `${h.yeol}열 ${h.haeng}행` : ""; }            // 약장(검색) 위치
function warePos(h){ const z=(h.zone||"").trim(); return (z && h.wyeol && h.whaeng) ? `${z}구역 ${h.wyeol}열 ${h.whaeng}행` : ""; } // 창고 위치

/* ── 초성 검색 ── */
const CHO_LIST="ㄱㄲㄴㄷㄸㄹㅁㅂㅃㅅㅆㅇㅈㅉㅊㅋㅌㅍㅎ";
function chosungOf(str){
  let out="";
  for(const ch of String(str)){
    const c=ch.charCodeAt(0);
    if(c>=0xAC00 && c<=0xD7A3) out+=CHO_LIST[Math.floor((c-0xAC00)/588)]; // 완성형 음절 → 초성
    else out+=ch; // 한자·숫자 등은 그대로
  }
  return out;
}
// 검색어가 한글 자음(ㄱ~ㅎ)으로만 이뤄졌는지
function isChosungQuery(q){ return q.length>0 && [...q].every(ch=>{ const c=ch.charCodeAt(0); return c>=0x3131 && c<=0x314E; }); }

/* ── 영타 → 한글 (두벌식 표준 자판) ── */
const ENG2KO={q:"ㅂ",w:"ㅈ",e:"ㄷ",r:"ㄱ",t:"ㅅ",y:"ㅛ",u:"ㅕ",i:"ㅑ",o:"ㅐ",p:"ㅔ",
 a:"ㅁ",s:"ㄴ",d:"ㅇ",f:"ㄹ",g:"ㅎ",h:"ㅗ",j:"ㅓ",k:"ㅏ",l:"ㅣ",
 z:"ㅋ",x:"ㅌ",c:"ㅊ",v:"ㅍ",b:"ㅠ",n:"ㅜ",m:"ㅡ",
 Q:"ㅃ",W:"ㅉ",E:"ㄸ",R:"ㄲ",T:"ㅆ",O:"ㅒ",P:"ㅖ"};
function engToJamo(s){
  let out="";
  for(const ch of s) out += ENG2KO[ch] ?? ENG2KO[ch.toLowerCase()] ?? ch;
  return out;
}
/* 자모 나열 → 완성형 음절 조합 (xhtkwk → ㅌㅗㅅㅏㅈㅏ → 토사자) */
const JUNG_LIST="ㅏㅐㅑㅒㅓㅔㅕㅖㅗㅘㅙㅚㅛㅜㅝㅞㅟㅠㅡㅢㅣ";
const JONG_LIST=["","ㄱ","ㄲ","ㄳ","ㄴ","ㄵ","ㄶ","ㄷ","ㄹ","ㄺ","ㄻ","ㄼ","ㄽ","ㄾ","ㄿ","ㅀ","ㅁ","ㅂ","ㅄ","ㅅ","ㅆ","ㅇ","ㅈ","ㅊ","ㅋ","ㅌ","ㅍ","ㅎ"];
const VCOMB={"ㅗㅏ":"ㅘ","ㅗㅐ":"ㅙ","ㅗㅣ":"ㅚ","ㅜㅓ":"ㅝ","ㅜㅔ":"ㅞ","ㅜㅣ":"ㅟ","ㅡㅣ":"ㅢ"};
const JCOMB={"ㄱㅅ":"ㄳ","ㄴㅈ":"ㄵ","ㄴㅎ":"ㄶ","ㄹㄱ":"ㄺ","ㄹㅁ":"ㄻ","ㄹㅂ":"ㄼ","ㄹㅅ":"ㄽ","ㄹㅌ":"ㄾ","ㄹㅍ":"ㄿ","ㄹㅎ":"ㅀ","ㅂㅅ":"ㅄ"};
const JSPLIT=Object.fromEntries(Object.entries(JCOMB).map(([k,v])=>[v,k]));
function composeJamo(jamo){
  const isCho=c=>CHO_LIST.includes(c), isJung=c=>JUNG_LIST.includes(c);
  let out="",cho="",jung="",jong="";
  const flush=()=>{
    if(cho&&jung) out+=String.fromCharCode(0xAC00+CHO_LIST.indexOf(cho)*588+JUNG_LIST.indexOf(jung)*28+JONG_LIST.indexOf(jong));
    else out+=cho+jung+jong;
    cho=jung=jong="";
  };
  for(const c of jamo){
    if(isCho(c)){
      if(cho&&jung&&!jong&&JONG_LIST.includes(c)) jong=c;
      else if(cho&&jung&&jong&&JCOMB[jong+c]) jong=JCOMB[jong+c];
      else{ flush(); cho=c; }
    }else if(isJung(c)){
      if(cho&&!jung) jung=c;
      else if(cho&&jung&&!jong&&VCOMB[jung+c]) jung=VCOMB[jung+c];
      else if(cho&&jung&&jong){ // 받침을 다음 음절 초성으로 넘김 (톳+ㅏ → 토사)
        const sp=JSPLIT[jong]; let stolen;
        if(sp){ jong=sp[0]; stolen=sp[1]; } else { stolen=jong; jong=""; }
        flush(); cho=stolen; jung=c;
      }
      else{ flush(); out+=c; }
    }else{ flush(); out+=c; }
  }
  flush();
  return out;
}
/* 입력 하나 → 검색 후보들 (원문 / 영타→초성 / 영타→완성형) */
function buildQueries(raw){
  raw=raw.trim();
  if(!raw) return [];
  const out=[];
  const compact=raw.replace(/\s+/g,"");
  if(isChosungQuery(compact)) out.push({q:compact,cho:true});
  else out.push({q:raw,cho:false});
  if(/[a-z]/i.test(compact)){
    const jamo=engToJamo(compact);
    if(jamo!==compact){
      if(isChosungQuery(jamo)) out.push({q:jamo,cho:true});          // xtw → ㅌㅅㅈ
      else{
        const composed=composeJamo(jamo);                            // xhtkwk → 토사자
        if(composed&&composed!==compact) out.push({q:composed,cho:false});
      }
    }
  }
  return out;
}
// 괄호 안/밖을 각각 별개 이름으로 취급
//  "해백(총백)"    → ["해백","총백"]
//  "계피(육계)"    → ["계피","육계"]
//  "천(화)초(산초)" → ["천초","화","산초"]
function nameParts(name){
  name=String(name||"");
  const strip=s=>s.replace(/\s+/g,"");
  const parts=new Set();
  parts.add(strip(name.replace(/[()]/g,"")));       // 괄호기호만 제거·전체 이어붙임: "해백총백","천화초산초"
  const outside=strip(name.replace(/\([^)]*\)/g,""));// 괄호 내용까지 제거한 본체: "해백","천초"
  if(outside) parts.add(outside);
  const re=/\(([^)]*)\)/g; let m;
  while((m=re.exec(name))){ const s=strip(m[1]||""); if(s) parts.add(s); } // 괄호 안 이름들: "총백","화","산초"
  parts.delete(""); if(!parts.size) parts.add(name);
  return [...parts];
}
// 반환: 0=앞부터 일치(prefix), 1=중간 일치(contains), -1=불일치
// 괄호 안 이름도 각각 판정 → 괄호 안 단어의 '앞 일치'도 0순위라 중간 일치(1)보다 먼저 표시됨
function matchRank(h, q, cho){
  const parts=nameParts(h.name);
  let cand;
  if(cho){ cand=parts.map(chosungOf); }
  else{ q=q.toLowerCase(); cand=parts.map(p=>p.toLowerCase()); cand.push((h.hanja||"").toLowerCase(), posLabel(h).toLowerCase(), warePos(h).toLowerCase()); }
  if(cand.some(s=>s.startsWith(q))) return 0;
  if(cand.some(s=>s.includes(q))) return 1;
  return -1;
}

function renderChips(){
  const wrap=document.getElementById("chips");
  let html=`<div class="chip ${activeCat==="전체"?"on":""}" data-cat="전체">전체</div>`;
  for(const k in CATS) html+=`<div class="chip ${activeCat===k?"on":""}" data-cat="${k}"><span class="cdot" style="background:${CATS[k].color}"></span>${esc(CATS[k].label)}</div>`;
  wrap.innerHTML=html;
  wrap.querySelectorAll(".chip").forEach(c=>{ c.onclick=()=>{ activeCat=c.dataset.cat; renderChips(); render(); }; });
}

function render(){
  document.getElementById("stTotal").textContent=state.length;
  document.getElementById("stLow").textContent=state.filter(isLow).length;
  document.getElementById("stNoPos").textContent=state.filter(h=>!posLabel(h)).length;
  if(view==="map"){ renderMap(); return; }
  if(view==="zone"){ renderZone(); return; }
  const grid=document.getElementById("grid");
  const queries=buildQueries(query);      // 원문 + 영타(두벌식) 변환 후보
  const inScope=h=> (activeCat==="전체"||h.cat===activeCat);
  let list;
  if(!queries.length){
    list=state.filter(inScope);
  }else{
    const scored=[];
    state.forEach((h,idx)=>{
      if(!inScope(h)) return;
      let rank=-1;
      for(const c of queries){
        const r=matchRank(h,c.q,c.cho);
        if(r>=0 && (rank<0 || r<rank)) rank=r;
        if(rank===0) break;
      }
      if(rank>=0) scored.push({h,rank,idx});
    });
    // 앞부터 일치(0) 먼저, 중간 일치(1) 뒤 / 동순위는 배치도 순서 유지
    scored.sort((a,b)=> (a.rank-b.rank) || (a.idx-b.idx));
    list=scored.map(s=>s.h);
  }
  if(!list.length){ grid.innerHTML=`<div class="empty"><div class="big">🌿</div>표시할 약재가 없습니다</div>`; return; }
  grid.innerHTML=list.map(h=>drawerHTML(h,"search")).join("");
  grid.querySelectorAll(".drawer").forEach(d=>{ d.onclick=()=>openSheet(d.dataset.id); });
}

/* 약재 카드 1개 HTML.
   mode="search"(기본) → 약장 위치만 / mode="ware" → 창고 위치만 표시 */
function drawerHTML(h, mode){
  mode = mode || "search";
  const c=CATS[h.cat]||CATS["기타"];
  const q0=Number(h.qty)||0;
  const pct=q0>0?Math.max(4,Math.min(100,Math.round(q0/(h.full||100)*100))):0;
  const low=isLow(h);
  const nlen=[...h.name].length;
  const nameCls=nlen>=7?" n7":(nlen>=5?" n5":(nlen>=4?" n4":""));
  let posHtml;
  if(mode==="ware"){
    const z=(h.zone||"").trim();
    posHtml=(z && h.wyeol && h.whaeng)
      ?`<div class="posbig"><span class="pzone">${esc(z)}구역</span><span class="ppos">${esc(h.wyeol)}열 ${esc(h.whaeng)}행</span></div>`
      :`<div class="posbig none">위치<br>미정</div>`;
  }else{
    posHtml=(h.yeol && h.haeng)
      ?`<div class="posbig"><span class="pzone">${esc(h.yeol)}열</span><span class="ppos">${esc(h.haeng)}행</span></div>`
      :`<div class="posbig none">위치<br>미정</div>`;
  }
  const stockHtml=q0>0?`<div class="bar"><i style="width:${pct}%"></i></div><div class="qty">${q0}${esc(h.unit||"")}</div>`:"";
  return `<button class="drawer ${low?"low":""}" data-id="${h.id}">
      ${low?`<span class="lowtag">부족</span>`:""}
      <div class="hanja">${esc(h.hanja||"")}</div>
      <div class="drow">
        <div class="name${nameCls}">${esc(h.name)}</div>
        ${posHtml}
      </div>
      <div class="cat"><span class="cdot" style="background:${c.color}"></span>${esc(h.cat)}</div>
      ${stockHtml}
    </button>`;
}

/* 현재 검색어로 매칭된 약재 목록(순위 정렬). 검색어 없으면 null */
function searchMatches(){
  const queries=buildQueries(query);
  if(!queries.length) return null;
  const scored=[];
  state.forEach((h,idx)=>{
    let rank=-1;
    for(const c of queries){ const r=matchRank(h,c.q,c.cho); if(r>=0&&(rank<0||r<rank)) rank=r; if(rank===0) break; }
    if(rank>=0) scored.push({h,rank,idx});
  });
  scored.sort((a,b)=>(a.rank-b.rank)||(a.idx-b.idx));
  return scored.map(s=>s.h);
}

/* ── 뷰 전환 ── */
function setView(v){
  view=v;
  document.querySelectorAll(".tab").forEach(t=>t.classList.toggle("on", t.dataset.view===v));
  document.getElementById("grid").style.display = v==="search"?"":"none";
  document.getElementById("map").style.display  = v==="map"?"":"none";
  document.getElementById("zone").style.display = v==="zone"?"":"none";
  document.getElementById("chips").style.display= v==="search"?"":"none";
  render();
}

/* ── 창고위치 탭 렌더 ── 구역 카드 → 클릭 시 그 구역의 창고 배치도 ── */
function renderZone(){
  const el=document.getElementById("zone");
  // 검색어가 있으면 구역이 아니라 매칭된 약재를 바로 표시 (검색 탭과 동일)
  const matches=searchMatches();
  if(matches){
    el.style.display="grid";
    el.innerHTML = matches.length ? matches.map(h=>drawerHTML(h,"ware")).join("")
      : `<div class="empty"><div class="big">🌿</div>표시할 약재가 없습니다</div>`;
    el.querySelectorAll(".drawer").forEach(d=>{ d.onclick=()=>openSheet(d.dataset.id); });
    return;
  }
  if(zoneOpen){ el.style.display="block"; renderZoneDetail(el, zoneOpen); return; }
  el.style.display="grid";
  const stat={};
  state.forEach(h=>{ const k=zoneOf(h); (stat[k]=stat[k]||{total:0,low:0,col:new Set(),row:new Set()});
    stat[k].total++; if(isLow(h)) stat[k].low++; if(h.wyeol) stat[k].col.add(String(h.wyeol)); if(h.whaeng) stat[k].row.add(String(h.whaeng)); });
  const keys=zoneList().concat(stat["미지정"]?["미지정"]:[]);
  const cards=keys.filter(k=>stat[k]&&stat[k].total>0).map(k=>{
    const z=zoneDef(k), s=stat[k];
    const layout=s.col.size?`${s.col.size}열 × ${s.row.size}행`:"위치 미정";
    return `<button class="zonecard" data-zone="${esc(k)}">
      <div class="zhead"><span class="ph">${z.emoji}</span></div>
      <div class="zbar" style="background:${z.color}"></div>
      <div class="zbody">
        <div class="zname"><span class="zdot" style="background:${z.color}"></span>${esc(z.label)}</div>
        <div class="zmeta">${s.total}종 · ${layout}${s.low?` · <span class="low">부족 ${s.low}종</span>`:""}</div>
      </div>
    </button>`;
  }).join("");
  el.innerHTML=cards||`<div class="empty"><div class="big">📦</div>표시할 구역이 없습니다</div>`;
  el.querySelectorAll(".zonecard").forEach(c=>{
    c.onclick=()=>{ zoneOpen=c.dataset.zone; renderZone(); };
  });
}

/* 한 구역의 창고 배치도(창고 열 × 창고 행 — 약장 열/행과 별개) */
function renderZoneDetail(el, zk){
  const queries=buildQueries(query); const has=queries.length>0;
  const hit=h=> has && queries.some(c=>matchRank(h,c.q,c.cho)>=0);
  const z=zoneDef(zk);
  const all=state.filter(h=>zoneOf(h)===zk);
  const zh=all.filter(h=>h.wyeol && h.whaeng);
  let html=`<div class="mapback"><button type="button" id="zoneBack">← 구역 목록</button></div>`;
  html+=`<div class="mapzone"><span class="zdot" style="background:${z.color}"></span>${esc(z.label)} · ${all.length}종</div>`;
  if(zh.length){
    const cols=[...new Set(zh.map(h=>String(h.wyeol)))].sort((a,b)=>(Number(a)||0)-(Number(b)||0)||a.localeCompare(b));
    const rows=[...new Set(zh.map(h=>String(h.whaeng)))].sort((a,b)=>(Number(a)||0)-(Number(b)||0)||a.localeCompare(b));
    const m={}; zh.forEach(h=>{ (m[h.wyeol]=m[h.wyeol]||{}); (m[h.wyeol][h.whaeng]=m[h.wyeol][h.whaeng]||[]).push(h); });
    html+='<div class="mapwrap" style="margin:0 8px 20px"><table class="maptbl"><thead><tr><th class="corner"></th>'+rows.map(x=>`<th>${esc(x)}행</th>`).join("")+'</tr></thead><tbody>';
    cols.forEach(y=>{
      html+=`<tr><th class="rowh">${esc(y)}열</th>`;
      rows.forEach(x=>{
        const cell=(m[y]&&m[y][x])||[];
        html+= cell.length
          ? `<td>${cell.map(h=>`<span class="mcell ${hit(h)?'hit':''}" data-id="${h.id}">${esc(h.name)}</span>`).join("")}</td>`
          : `<td class="empty"></td>`;
      });
      html+='</tr>';
    });
    html+='</tbody></table></div>';
  }else{ html+=`<div class="empty" style="color:var(--ink-soft);padding:40px">창고 위치가 지정된 약재가 없습니다</div>`; }
  el.innerHTML=html;
  document.getElementById("zoneBack").onclick=()=>{ zoneOpen=null; renderZone(); };
  el.querySelectorAll(".mcell").forEach(c=>{ c.onclick=()=>openSheet(c.dataset.id); });
}

/* ── 전체 배치도(약장) 렌더 · 열 1~R × 행 1~18 단일 표 ── */
function renderMap(){
  const el=document.getElementById("map");
  const queries=buildQueries(query);
  const has=queries.length>0;
  const hit=h=> has && queries.some(c=>matchRank(h,c.q,c.cho)>=0);
  const m={}; YEOL_ORDER.forEach(y=>m[y]={});
  state.forEach(h=>{ if(h.yeol&&h.haeng){ (m[h.yeol]=m[h.yeol]||{}); (m[h.yeol][h.haeng]=m[h.yeol][h.haeng]||[]).push(h); } });
  let html='<table class="maptbl"><thead><tr><th class="corner"></th>'+HAENG_ORDER.map(x=>`<th>${x}행</th>`).join("")+'</tr></thead><tbody>';
  YEOL_ORDER.forEach(y=>{
    html+=`<tr><th class="rowh">${y}열</th>`;
    HAENG_ORDER.forEach(x=>{
      const cell=(m[y]&&m[y][x])||[];
      html+= cell.length
        ? `<td>${cell.map(h=>`<span class="mcell ${hit(h)?'hit':''}" data-id="${h.id}">${esc(h.name)}</span>`).join("")}</td>`
        : `<td class="empty"></td>`;
    });
    html+='</tr>';
  });
  el.innerHTML=html+'</tbody></table>';
  el.querySelectorAll(".mcell").forEach(c=>{ c.onclick=()=>openSheet(c.dataset.id); });
  if(has){ const f=el.querySelector(".mcell.hit"); if(f){ const er=el.getBoundingClientRect(), fr=f.getBoundingClientRect();
    el.scrollLeft += (fr.left-er.left)-el.clientWidth/2+fr.width/2;
    el.scrollTop  += (fr.top-er.top)-el.clientHeight/2+fr.height/2; } }
}

const backdrop=document.getElementById("backdrop");
const editSheet=document.getElementById("sheet");
const importSheet=document.getElementById("importSheet");
const dataSheet=document.getElementById("dataSheet");
const F=id=>document.getElementById(id);
function openBackdrop(){ backdrop.classList.add("on"); document.body.style.overflow="hidden"; }
function closeSheet(){ backdrop.classList.remove("on"); editSheet.classList.remove("on"); importSheet.classList.remove("on"); dataSheet.classList.remove("on");
  document.body.style.overflow=""; if(document.activeElement) document.activeElement.blur(); }
backdrop.onclick=closeSheet;

function fillCatSelect(){ F("f_cat").innerHTML=Object.keys(CATS).map(k=>`<option value="${k}">${esc(CATS[k].label)}</option>`).join(""); }
function openSheet(id){
  const h=state.find(x=>x.id===id);
  F("f_id").value=h?h.id:""; F("sheetTitle").innerHTML=h?`약재 수정 <span>· ${esc(h.name)}</span>`:"약재 추가";
  F("f_name").value=h?.name||""; F("f_hanja").value=h?.hanja||""; F("f_cat").value=h?.cat||"기타";
  F("f_qty").value=h?.qty??0; F("f_unit").value=h?.unit||"돈"; F("f_min").value=h?.min??0;
  F("f_yeol").value=h?.yeol||""; F("f_haeng").value=h?.haeng||"";
  F("f_zone").value=h?.zone||""; F("f_wyeol").value=h?.wyeol||""; F("f_whaeng").value=h?.whaeng||"";
  F("f_note").value=h?.note||"";
  F("delBtn").style.display=h?"block":"none"; openBackdrop(); editSheet.classList.add("on");
}
document.getElementById("addBtn").onclick=()=>openSheet(null);
editSheet.querySelectorAll("[data-step]").forEach(b=>{ b.onclick=()=>{ const cur=Number(F("f_qty").value)||0; F("f_qty").value=Math.max(0,cur+Number(b.dataset.step)); }; });
document.getElementById("form").onsubmit=async (e)=>{
  e.preventDefault(); const id=F("f_id").value;
  const data={ name:F("f_name").value.trim()||"무명", hanja:F("f_hanja").value.trim(), cat:F("f_cat").value,
    qty:Math.max(0,Number(F("f_qty").value)||0), unit:F("f_unit").value, min:Math.max(0,Number(F("f_min").value)||0),
    yeol:F("f_yeol").value.trim(), haeng:F("f_haeng").value.trim(),
    zone:F("f_zone").value.trim().toUpperCase(), wyeol:F("f_wyeol").value.trim(), whaeng:F("f_whaeng").value.trim(),
    note:F("f_note").value.trim() };
  const cur=id?state.find(x=>x.id===id):null;
  const item={ id, ...data, full:Math.max(data.qty, cur?(cur.full||50):50) };

  const btn=F("saveBtn"); if(btn) btn.disabled=true;
  try{
    const saved=(await api("upsert",{item})).data;
    if(cur) Object.assign(cur, saved);
    else state.unshift(saved);          // 신규는 목록 맨 앞 (서버 정렬과 동일)
    render(); closeSheet(); toast("저장되었습니다");
  }catch(err){
    toast(err.message); await resync();
  }finally{
    if(btn) btn.disabled=false;
  }
};
document.getElementById("delBtn").onclick=async ()=>{
  const id=F("f_id").value; if(!id) return; const h=state.find(x=>x.id===id);
  if(!confirm(`'${h?.name}' 약재를 삭제할까요?`)) return;
  try{
    await api("delete",{id});
    state=state.filter(x=>x.id!==id);
    render(); closeSheet(); toast("삭제되었습니다");
  }catch(err){
    toast(err.message); await resync();
  }
};
document.getElementById("search").addEventListener("input",e=>{ query=e.target.value; render(); });

/* ── 데이터 메뉴 ── */
let importMode="pos";   // "pos"(약재위치) | "ware"(약재창고)
document.getElementById("dataBtn").onclick=()=>{ openBackdrop(); dataSheet.classList.add("on"); };
document.getElementById("d_impPos").onclick=()=>openImport("pos");
document.getElementById("d_impWare").onclick=()=>openImport("ware");
document.getElementById("d_expPos").onclick=()=>{ copyClip(buildPosTSV(), "약재위치"); closeSheet(); };
document.getElementById("d_expWare").onclick=()=>{ copyClip(buildWareTSV(), "약재창고"); closeSheet(); };

function openImport(mode){
  importMode=mode; dataSheet.classList.remove("on");
  F("imp_title").innerHTML = mode==="ware" ? '약재창고 가져오기 <span>· 구역·열·행</span>' : '약재위치 가져오기 <span>· 약장 열·행</span>';
  F("imp_help").innerHTML = mode==="ware"
    ? '창고 배치표를 붙여넣으세요. <b>"A구역"</b>을 한 줄에 적고 그 아래 <b>머리글(1행~)</b>·<b>열 라벨(1열~)</b> 표를 두면, 이름이 같은 약재의 <b>창고 위치</b>가 갱신됩니다.'
    : '<b>약장 배치도</b>를 붙여넣으세요(머리글 <b>1행~18행</b> 포함). <b>열 라벨(1열~R열)</b> 자동 인식, <b>"-"</b>·빈칸은 건너뜁니다. 이름이 같으면 <b>약장 위치</b>가 갱신됩니다.';
  F("imp_replabel").innerHTML = mode==="ware"
    ? '적용 전 기존 <b>창고 위치</b>를 모두 비우기 (해제 시: 이름 같으면 갱신, 없으면 추가)'
    : '기존 데이터를 <b>전부 교체</b> (해제 시: 이름 같으면 위치만 갱신, 나머지는 추가)';
  F("imp_text").value=""; F("imp_replace").checked=false;
  F("imp_preview").innerHTML="붙여넣으면 인식 결과가 여기에 표시됩니다.";
  openBackdrop(); importSheet.classList.add("on");
}

/* 표(엑셀/시트) 파싱 — wantZone=true면 "A구역" 라벨·구역별 머리글도 인식 */
function parseGrid(text, wantZone){
  if(!text||!text.trim()) return [];
  const lines=text.replace(/\r\n?/g,"\n").split("\n").filter(l=>l.length);
  let rows=lines.map(l=>l.split("\t"));
  if(!rows.some(r=>r.length>1)) rows=lines.map(l=>l.split(/ {2,}|,/));
  const zoneRe=/^([A-Za-z])\s*(?:구역|zone|존)$|^(?:구역|zone)\s*([A-Za-z])$/i;
  const isHeader=r=>{ const f=(r[0]||"").trim(); return !/^(\d+|R)\s*열/.test(f) && r.filter(c=>/(\d+)\s*행/.test(c)).length>=1; };
  const out=[]; let colRow=null, curCol="", curZone="", sawHeader=false;
  rows.forEach(r=>{
    const first=(r[0]||"").trim();
    if(wantZone){ const zm=first.match(zoneRe); if(zm && r.filter(c=>c.trim()).length<=1){ curZone=(zm[1]||zm[2]).toUpperCase(); curCol=""; colRow=null; return; } }
    if(isHeader(r)){ colRow={}; r.forEach((c,ci)=>{ const m=c.match(/(\d+)\s*행/); if(m) colRow[ci]=m[1]; }); sawHeader=true; curCol=""; return; }
    const ym=first.match(/^(\d+|R)\s*열/); if(ym) curCol=ym[1];
    r.forEach((cell,ci)=>{
      let val=(cell||"").trim(); if(!val) return;
      if(/^(\d+|R)\s*열$/.test(val)) return;
      if(/^\d+\s*행$/.test(val)) return;
      if(wantZone && zoneRe.test(val)) return;
      if(["-","–","—","--",".","·"].includes(val)) return;
      let row = colRow?colRow[ci]:null; if(row==null && !sawHeader) row=String(ci); if(row==null) return;
      val.split(/\s*[\/,]\s*|\n/).forEach(nm=>{ nm=nm.trim();
        if(nm && !["-","–","—","--"].includes(nm)){ const o={name:nm, col:curCol, row:String(row)}; if(wantZone) o.zone=curZone; out.push(o); } });
    });
  });
  return out;
}
function parsePos(text){ return parseGrid(text,false).map(o=>({name:o.name, yeol:o.col, haeng:o.row})); }
function parseWare(text){ return parseGrid(text,true).map(o=>({name:o.name, zone:o.zone, wyeol:o.col, whaeng:o.row})); }

function updateImportPreview(){
  const txt=F("imp_text").value; const box=F("imp_preview");
  if(!txt.trim()){ box.innerHTML="붙여넣으면 인식 결과가 여기에 표시됩니다."; return; }
  const ware=importMode==="ware";
  const parsed = ware ? parseWare(txt) : parsePos(txt);
  if(!parsed.length){ box.innerHTML=`<span class="warn">인식된 약재가 없습니다.</span> 머리글(1행~)과 ${ware?"구역·열":"열"} 라벨을 확인하세요.`; return; }
  const fmt = ware
    ? p=>(p.zone&&p.wyeol&&p.whaeng)?`${p.zone}구역 ${p.wyeol}열 ${p.whaeng}행`:"위치미정"
    : p=>(p.yeol&&p.haeng)?`${p.yeol}열 ${p.haeng}행`:"위치미정";
  const bad = parsed.filter(p=> ware ? !(p.zone&&p.wyeol&&p.whaeng) : !(p.yeol&&p.haeng)).length;
  const sample=parsed.slice(0,6).map(p=>`<li><b>${esc(p.name)}</b> → ${esc(fmt(p))}</li>`).join("");
  box.innerHTML=`<span class="cnt">${parsed.length}종</span> 인식됨${bad?` · <span class="warn">위치미정 ${bad}종</span>`:""}<ul>${sample}${parsed.length>6?`<li>… 외 ${parsed.length-6}종</li>`:""}</ul>`;
}
F("imp_text").addEventListener("input",updateImportPreview);

document.getElementById("imp_apply").onclick=async ()=>{
  const txt=F("imp_text").value; const replace=F("imp_replace").checked;
  const ware=importMode==="ware";
  const parsed=ware?parseWare(txt):parsePos(txt);
  if(!parsed.length){ toast("인식된 약재가 없습니다"); return; }
  if(replace && !confirm(ware
      ? `기존 창고 위치를 모두 비우고 ${parsed.length}종을 적용합니다. 계속할까요?`
      : `기존 데이터를 전부 지우고 ${parsed.length}종으로 교체합니다. 계속할까요?`)) return;

  const btn=F("imp_apply"); btn.disabled=true;
  try{
    /* 서버에서 트랜잭션으로 처리하고, 결과 목록을 통째로 받아 화면을 맞춘다 */
    const r=await api("bulk",{ mode:ware?"ware":"pos", replace, items:parsed });
    state=r.data; render(); closeSheet();
    toast(ware
      ? `창고 위치 ${r.updated}종 갱신${r.added?` · ${r.added}종 추가`:""}`
      : `${r.updated+r.added}종 반영 완료`);
  }catch(err){
    toast(err.message); await resync();
  }finally{
    btn.disabled=false;
  }
};

/* ── 내보내기(엑셀 붙여넣기용 TSV) ── */
function buildPosTSV(){
  const placed=state.filter(h=>h.yeol&&h.haeng);
  if(!placed.length) return "";
  const yeols=YEOL_ORDER.filter(y=>placed.some(h=>String(h.yeol)===y));
  const haengs=[...new Set(placed.map(h=>Number(h.haeng)).filter(n=>n))].sort((a,b)=>a-b);
  const m={}; placed.forEach(h=>{ (m[h.yeol]=m[h.yeol]||{}); (m[h.yeol][h.haeng]=m[h.yeol][h.haeng]||[]).push(h.name); });
  const lines=[[""].concat(haengs.map(x=>x+"행")).join("\t")];
  yeols.forEach(y=> lines.push([y+"열"].concat(haengs.map(x=>(m[y]&&m[y][x]?m[y][x].join(", "):""))).join("\t")) );
  return lines.join("\n");
}
function buildWareTSV(){
  const zones=zoneList(); const lines=[];
  zones.forEach(z=>{
    const zh=state.filter(h=>zoneOf(h)===z && h.wyeol && h.whaeng); if(!zh.length) return;
    const cols=[...new Set(zh.map(h=>String(h.wyeol)))].sort((a,b)=>(Number(a)||0)-(Number(b)||0)||a.localeCompare(b));
    const rows=[...new Set(zh.map(h=>Number(h.whaeng)).filter(n=>n))].sort((a,b)=>a-b);
    const m={}; zh.forEach(h=>{ (m[h.wyeol]=m[h.wyeol]||{}); (m[h.wyeol][h.whaeng]=m[h.wyeol][h.whaeng]||[]).push(h.name); });
    lines.push(z+"구역");
    lines.push([""].concat(rows.map(x=>x+"행")).join("\t"));
    cols.forEach(c=> lines.push([c+"열"].concat(rows.map(x=>(m[c]&&m[c][x]?m[c][x].join(", "):""))).join("\t")) );
    lines.push("");
  });
  return lines.join("\n").trim();
}
function copyClip(text, label){
  if(!text){ toast("내보낼 데이터가 없습니다"); return; }
  const done=()=>toast(`${label} 복사됨 · 엑셀에 붙여넣기(Ctrl+V)`);
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(text).then(done).catch(()=>fallbackCopy(text,done));
  }else fallbackCopy(text,done);
}
function fallbackCopy(text, done){
  const ta=document.createElement("textarea"); ta.value=text;
  ta.style.position="fixed"; ta.style.top="-9999px"; document.body.appendChild(ta); ta.focus(); ta.select();
  try{ document.execCommand("copy"); done(); }catch(e){ toast("복사 실패 — 브라우저 권한 확인"); }
  document.body.removeChild(ta);
}

let toastTimer;
function toast(msg){ const t=document.getElementById("toast"); t.textContent=msg; t.classList.add("on");
  clearTimeout(toastTimer); toastTimer=setTimeout(()=>t.classList.remove("on"),1900); }

/* ── 시작 ── */
fillCatSelect(); renderChips();
document.getElementById("tabs").addEventListener("click", e=>{ const t=e.target.closest(".tab"); if(t){ zoneOpen=null; setView(t.dataset.view); } });

(async function boot(){
  try{
    state=(await api("list")).data;
  }catch(err){
    render(); toast(err.message); return;
  }
  render();
  /* 비어 있으면 seed.sql 을 아직 실행하지 않은 것입니다 */
  if(!state.length) toast("데이터가 없습니다 · seed.sql 을 실행하세요");
})();
</script>
</body>
</html>

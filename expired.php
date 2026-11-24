<?php
require __DIR__ . '/lib.php';
$B = brand(); $m = me();
if (!$m) { header('Location: login.php'); exit; }
$st = sub_state($m);
if ($st['s'] !== 'expired') { header('Location: index.php'); exit; }
$av = function ($f) { return @filemtime(__DIR__ . '/assets/' . $f) ?: 1; };
$when = gmdate('j M Y, H:i', (int)$st['exp']) . ' UTC';
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Subscription ended - <?= h($B['name']) ?></title><?php if ($B['logo']): ?><link rel="icon" href="<?= h($B['logo']) ?>"><?php endif; ?>
<style><?= brand_css($B) ?></style>
<link rel="stylesheet" href="assets/auth.css?v=<?= $av('auth.css') ?>">
</head><body class="auth">
<div class="bg" aria-hidden="true"><div class="stars"></div><i class="orb o1"></i><i class="orb o2"></i><i class="orb o3"></i><div class="grid"></div></div>
<main class="stage" style="grid-template-columns:1fr;justify-items:center">
 <section class="panel"><div class="glass center">
  <div class="hg" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#FFB265" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12M6 21h12M7 3c0 5 4 6 5 9-1 3-5 4-5 9M17 3c0 5-4 6-5 9 1 3 5 4 5 9"/></svg></div>
  <h2>Your demo subscription has ended</h2>
  <p class="lead">Hi <?= h($m['name']) ?>, your demo subscription to <b><?= h($B['name']) ?></b> ended on <span id="w" data-ts="<?= (int)$st['exp'] ?>"><?= h($when) ?></span>.</p>
  <p class="lead" style="margin-bottom:18px"><?= $B['contact'] !== '' ? h($B['contact']) : 'Please contact your administrator to continue.' ?></p>
  <a class="btn" href="expired.php">Check again</a>
  <a class="btn ghost" href="logout.php">Sign out</a>
 </div></section>
</main>
<script>{const e=document.getElementById('w'),t=+e.dataset.ts;if(t)e.textContent=new Date(t*1000).toLocaleString([],{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})}</script></body></html>

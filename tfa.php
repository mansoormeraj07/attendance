<?php
define('TFA_PAGE', 1);
require __DIR__ . '/lib.php';
$m = need();
$B = brand(); $P = tfa_policy(); $us = users() ?: [];
$i = -1; foreach ($us as $k => $x) if ($x['username'] === $m['username']) $i = $k;
$msg = ''; $ok = ''; $codes = null; $enrolled = $i >= 0 && tfa_enrolled($us[$i]);
$home = $m['role'] === 'admin' ? 'admin.php' : 'index.php';
$mustNow = !empty($P['on']) && !empty($P['must']) && time() >= (int)$P['from'] && empty($m['tfa_x']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $i >= 0) {
  if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { $msg = 'This page expired. Please reload it.'; }
  elseif (!tfa_active()) { $msg = 'Two-step login is switched off.'; }
  else {
    $act = $_POST['act'] ?? ''; $code = (string)($_POST['code'] ?? '');
    usleep(400000);
    if ($act === 'start') {
      $sec = $_SESSION['tfa_new'] ?? ''; $t = ['s' => $sec, 'lc' => 0, 'rc' => []];
      if ($sec !== '' && !$enrolled && tfa_check($t, $code)) {
        [$plain, $hash] = tfa_recovery();
        $us[$i]['tfa'] = ['s' => $sec, 'on' => 1, 'rc' => $hash, 't' => time(), 'lc' => $t['lc']];
        wj('users.json', $us); unset($_SESSION['tfa_new']); log_event('tfa_on', $m['username']);
        $codes = $plain; $enrolled = true;
      } else $msg = 'That code is not right. Wait for a new code in your app and try again.';
    } elseif ($enrolled && ($act === 'recovery' || $act === 'off')) {
      $t = $us[$i]['tfa'];
      if (!tfa_check($t, $code)) $msg = 'That code is not right.';
      elseif ($act === 'recovery') {
        [$plain, $hash] = tfa_recovery(); $t['rc'] = $hash; $us[$i]['tfa'] = $t; wj('users.json', $us); $codes = $plain;
      } elseif ($mustNow) $msg = 'Two-step login is mandatory now, so it cannot be turned off.';
      else { unset($us[$i]['tfa']); wj('users.json', $us); log_event('tfa_off', $m['username']); $enrolled = false; $ok = 'Two-step login is turned off for your account.'; }
    }
  }
}
$sec = '';
if (!$enrolled && tfa_active()) { if (empty($_SESSION['tfa_new'])) $_SESSION['tfa_new'] = tfa_new_secret(); $sec = $_SESSION['tfa_new']; }
$issuer = $B['name']; $uri = $sec ? 'otpauth://totp/' . rawurlencode($issuer . ':' . $m['username']) . '?secret=' . $sec . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30' : '';
$left = count($us[$i]['tfa']['rc'] ?? []);
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Two-step login - <?= h($B['name']) ?></title><meta name="theme-color" content="#070b16">
<style>
:root{--bg:#070b16;--fg:#e8ecf8;--mut:#9aa7c4;--line:rgba(255,255,255,.12);--ac:#5b8cff;--ac2:#8a5bff;--ok:#2fd39a;--bad:#ff8a80;color-scheme:dark}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:var(--bg) radial-gradient(900px 500px at 15% -10%,rgba(91,140,255,.22),transparent 60%) fixed;color:var(--fg);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:grid;place-items:center;padding:20px max(16px,env(safe-area-inset-left,0px))}
main{width:min(520px,100%);background:rgba(255,255,255,.055);border:1px solid var(--line);border-radius:22px;padding:26px;box-shadow:0 24px 70px rgba(0,0,0,.45)}
h1{margin:0 0 4px;font-size:22px}p{color:var(--mut);margin:6px 0 14px}.lead{margin-top:0}
.step{display:flex;gap:12px;margin:16px 0}.n{flex:none;width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--ac),var(--ac2));display:grid;place-items:center;font-weight:700;font-size:13px}
.qr{background:#fff;border-radius:14px;padding:12px;width:max-content;max-width:100%;margin:8px 0}.qr svg{display:block;width:196px;height:196px;max-width:100%}
code.k{display:block;word-break:break-all;background:rgba(0,0,0,.35);border:1px solid var(--line);border-radius:10px;padding:9px 11px;letter-spacing:.08em;font-size:14px;color:#fff}
input[type=text]{width:100%;font:600 22px system-ui;letter-spacing:.25em;text-align:center;color:#fff;background:rgba(255,255,255,.06);border:1px solid var(--line);border-radius:12px;padding:11px}
input:focus{outline:2px solid var(--ac);outline-offset:1px}
button,a.b{font:inherit;font-weight:600;color:#fff;border:0;border-radius:12px;padding:11px 16px;cursor:pointer;background:linear-gradient(135deg,var(--ac),var(--ac2));text-decoration:none;display:inline-block}
button.g,a.g{background:rgba(255,255,255,.08);border:1px solid var(--line)}
.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.err{background:rgba(255,138,128,.12);border:1px solid rgba(255,138,128,.4);color:#ffb4ab;border-radius:12px;padding:10px 12px;margin:10px 0}
.good{background:rgba(47,211,154,.12);border:1px solid rgba(47,211,154,.4);color:#6fe8bd;border-radius:12px;padding:10px 12px;margin:10px 0}
.warn{background:rgba(255,180,84,.12);border:1px solid rgba(255,180,84,.4);color:#ffc983;border-radius:12px;padding:10px 12px;margin:10px 0}
.rc{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0}.rc code{background:rgba(0,0,0,.35);border:1px solid var(--line);border-radius:10px;padding:8px;text-align:center;font-size:15px;letter-spacing:.06em}
details{margin-top:18px;border-top:1px solid var(--line);padding-top:12px}summary{cursor:pointer;color:var(--mut)}
</style></head><body><main>
<h1>Two-step login</h1>
<?php if (!tfa_active()): ?>
 <p class="lead">Two-step login is switched off by your administrator, so there is nothing to set up right now.</p>
 <div class="row"><a class="b" href="<?= h($home) ?>">Continue</a></div>
<?php elseif ($codes): ?>
 <div class="good">Two-step login is on. Your account is now protected.</div>
 <p class="lead"><b>Save these recovery codes now.</b> Each one works once if you lose your phone. They are shown only this one time. Store them somewhere safe, not on the same phone.</p>
 <div class="rc"><?php foreach ($codes as $c): ?><code><?= h($c) ?></code><?php endforeach; ?></div>
 <div class="row"><button type="button" class="g" id="cp">Copy codes</button><a class="b" href="<?= h($home) ?>">I saved them, continue</a></div>
 <script>document.getElementById('cp').onclick=function(){var t=<?= jx(implode("\n", $codes)) ?>,b=this;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent='Copied'}).catch(function(){b.textContent='Select and copy them by hand'})}</script>
<?php elseif ($enrolled): ?>
 <?php if ($ok): ?><div class="good"><?= h($ok) ?></div><?php endif; ?>
 <?php if ($msg): ?><div class="err" role="alert"><?= h($msg) ?></div><?php endif; ?>
 <div class="good">Two-step login is <b>on</b> for <?= h($m['username']) ?>. <?= $left ?> recovery code<?= $left === 1 ? '' : 's' ?> left.</div>
 <p>To change anything below, enter the current 6 digit code from your authenticator app.</p>
 <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="123456" aria-label="Authenticator code" required>
 <div class="row"><button name="act" value="recovery">Make new recovery codes</button><?php if (!$mustNow): ?><button name="act" value="off" class="g">Turn off</button><?php endif; ?><a class="b g" href="<?= h($home) ?>">Back</a></div></form>
 <?php if ($mustNow): ?><p style="margin-top:14px">Two-step login is mandatory for your account, so it cannot be turned off.</p><?php endif; ?>
<?php else: ?>
 <?php if (isset($_GET['must']) || $mustNow): ?><div class="warn"><b>Two-step login is now required.</b> Finish the 2 steps below to continue to your dashboard.</div>
 <?php elseif (tfa_soon($m)): ?><div class="warn">Two-step login becomes mandatory on <b><?= h(date('j M Y', (int)$P['from'])) ?></b>. Setting it up now takes about 2 minutes.</div><?php endif; ?>
 <?php if ($msg): ?><div class="err" role="alert"><?= h($msg) ?></div><?php endif; ?>
 <p class="lead">After your password, you will type a 6 digit code from an app on your phone. Even if someone learns your password, they cannot get in.</p>
 <div class="step"><span class="n">1</span><div style="min-width:0"><b>Scan this code</b> with Google Authenticator, Microsoft Authenticator, Authy or any authenticator app.
  <div class="qr" id="qr" aria-label="QR code"></div>
  <details open><summary>Cannot scan? Type this key instead</summary><code class="k"><?= h(trim(chunk_split($sec, 4, ' '))) ?></code></details></div></div>
 <form method="post" class="step"><span class="n">2</span><div style="flex:1;min-width:0"><b>Enter the 6 digit code</b> your app shows now.
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="act" value="start">
  <div style="margin-top:8px"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="123456" aria-label="Authenticator code" required autofocus></div>
  <div class="row"><button>Turn on two-step login</button><?php if (!$mustNow): ?><a class="b g" href="<?= h($home) ?>">Not now</a><?php else: ?><a class="b g" href="logout.php">Sign out</a><?php endif; ?></div></div></form>
 <script src="assets/vendor/qrcode.min.js"></script>
 <script>try{var q=qrcode(0,'M');q.addData(<?= jx($uri) ?>);q.make();document.getElementById('qr').innerHTML=q.createSvgTag({cellSize:6,margin:0,scalable:true})}catch(e){document.getElementById('qr').textContent='Use the key below.'}</script>
<?php endif; ?>
</main></body></html>

<?php
require __DIR__ . '/lib.php';
$us = users(); $err = ''; $B = brand(); $step2 = false;
if (isset($_GET['reset'])) unset($_SESSION['pre']);
/* Finishes a sign-in: stamps last login, starts the session, logs it and redirects (or answers the ajax call). */
function finish_login(&$us, $i, $x, $u, $ajax, &$att, $ak) {
  $st = sub_state($x);
  if ($st['s'] === 'pending') { $us[$i]['exp'] = time() + (int)$x['dur']; $us[$i]['startOn'] = null; }
  $us[$i]['prev'] = $x['last'] ?? null; $us[$i]['last'] = date('Y-m-d H:i'); wj('users.json', $us); unset($att[$ak]); wj('attempts.json', $att);
  unset($_SESSION['pre']); session_regenerate_id(true); $_SESSION['u'] = $u; $_SESSION['la'] = time(); log_event('login', $u);
  $to = (($x['role'] === 'admin' && isset($_GET['admin'])) ? 'admin.php' : 'index.php');
  if (tfa_due($us[$i])) $to = 'tfa.php?must=1';
  if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 1, 'to' => $to, 'name' => $x['name'] ?? $u]); exit; }
  header('Location: ' . $to); exit;
}
function say($ajax, $err, $extra = []) { if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 0, 'err' => $err] + $extra); exit; } }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $us) {
  $ajax = isset($_POST['ajax']); $now = time();
  if (isset($_POST['c'])) {
    /* step 2: authenticator code or recovery code */
    $pre = $_SESSION['pre'] ?? null;
    if (!$pre || $now - $pre['t'] > 300) { unset($_SESSION['pre']); $err = 'Your sign-in took too long. Please start again.'; say($ajax, $err, ['reset' => 1]); }
    else {
      usleep(400000); $u = $pre['u']; $ak = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $u);
      $att = array_filter(rj('attempts.json', []), function ($t) use ($now) { return $t['until'] > $now || $now - $t['t'] < 600; });
      if (($att[$ak]['n'] ?? 0) >= 5 && ($att[$ak]['until'] ?? 0) > $now) { $m = (int)ceil(($att[$ak]['until'] - $now) / 60); $err = 'Too many wrong attempts. Please try again in ' . $m . ' minute' . ($m > 1 ? 's' : '') . '.'; unset($_SESSION['pre']); say($ajax, $err, ['reset' => 1]); }
      else {
        $i = -1; foreach ($us as $k => $x) if ($x['username'] === $u) $i = $k;
        if ($i >= 0 && tfa_enrolled($us[$i]) && tfa_check($us[$i]['tfa'], $_POST['c'])) { finish_login($us, $i, $us[$i], $u, $ajax, $att, $ak); }
        else {
          $n = ($att[$ak]['n'] ?? 0) + 1; $att[$ak] = ['n' => $n, 't' => $now, 'until' => $n >= 5 ? $now + 600 : 0]; wj('attempts.json', $att);
          log_event($n >= 5 ? 'lock' : 'fail', $u, $n >= 5 ? '5 wrong codes' : 'wrong 2FA code');
          if ($n >= 5) { unset($_SESSION['pre']); $err = 'Too many wrong attempts. Please try again in 10 minutes.'; say($ajax, $err, ['reset' => 1]); }
          else { $err = 'That code is not right. Check your authenticator app and try again.' . ($n >= 3 ? ' ' . (5 - $n) . ' attempt' . (5 - $n > 1 ? 's' : '') . ' left.' : ''); say($ajax, $err); $step2 = true; }
        }
      }
    }
  } else {
    $u = strtolower(trim($_POST['u'] ?? '')); $p = $_POST['p'] ?? '';
    usleep(700000); $found = false; unset($_SESSION['pre']);
    $ak = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $u);
    $att = array_filter(rj('attempts.json', []), function ($t) use ($now) { return $t['until'] > $now || $now - $t['t'] < 600; });
    if (($att[$ak]['n'] ?? 0) >= 5 && ($att[$ak]['until'] ?? 0) > $now) {
      $m = (int)ceil(($att[$ak]['until'] - $now) / 60); $err = 'Too many wrong attempts. Please try again in ' . $m . ' minute' . ($m > 1 ? 's' : '') . '.'; say($ajax, $err);
    } else {
      foreach ($us as $i => $x) if ($x['username'] === $u && password_verify($p, $x['hash'])) {
        $found = true;
        $st = sub_state($x);
        if ($st['s'] === 'expired') { $err = 'Your demo subscription has ended.' . ($B['contact'] !== '' ? ' ' . $B['contact'] : ' Please contact your administrator to continue.'); break; }
        if (tfa_active() && tfa_enrolled($x)) {
          $_SESSION['pre'] = ['u' => $u, 't' => $now];
          if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 2, 'name' => $x['name'] ?? $u]); exit; }
          $step2 = true; break;
        }
        finish_login($us, $i, $x, $u, $ajax, $att, $ak);
      }
      if (!$found) {
        $n = ($att[$ak]['n'] ?? 0) + 1; $att[$ak] = ['n' => $n, 't' => $now, 'until' => $n >= 5 ? $now + 600 : 0]; wj('attempts.json', $att);
        log_event($n >= 5 ? 'lock' : 'fail', $u, $n >= 5 ? '5 wrong passwords' : 'wrong password');
        $err = $n >= 5 ? 'Too many wrong attempts. Please try again in 10 minutes.' : 'Wrong username or password.' . ($n >= 3 ? ' ' . (5 - $n) . ' attempt' . (5 - $n > 1 ? 's' : '') . ' left.' : '');
      }
      say($ajax, $err);
    }
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $p = $_POST['p'] ?? '';
  if (strlen($p) < 10) $err = 'Use at least 10 characters.';
  elseif (!wj('users.json', [['username'=>'admin','name'=>'Admin','role'=>'admin','dept'=>'','hash'=>password_hash($p, PASSWORD_DEFAULT),'last'=>date('Y-m-d H:i'),'exp'=>null,'dur'=>null,'startOn'=>null]])) $err = 'Cannot write to the data folder. Set its permission to 755.';
  else { session_regenerate_id(true); $_SESSION['u'] = 'admin'; header('Location: admin.php'); exit; }
}
if (!$step2 && !empty($_SESSION['pre']) && time() - $_SESSION['pre']['t'] < 300 && $_SERVER['REQUEST_METHOD'] !== 'POST') $step2 = true;
$ini = strtoupper(substr($B['name'], 0, 1));
$av = function ($f) { return @filemtime(__DIR__ . '/assets/' . $f) ?: 1; };
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in - <?= h($B['name']) ?></title><link rel="manifest" href="manifest.php"><meta name="theme-color" content="#070b16"><link rel="apple-touch-icon" href="assets/icons/icon-192.png"><?php if ($B['logo']): ?><link rel="icon" href="<?= h($B['logo']) ?>"><?php endif; ?>
<style><?= brand_css($B) ?></style>
<link rel="stylesheet" href="assets/auth.css?v=<?= $av('auth.css') ?>">
<script>(function(){var q=location.search,n=navigator,cn=n.connection||{},r=matchMedia('(prefers-reduced-motion: reduce)').matches;
var weak=(n.hardwareConcurrency&&n.hardwareConcurrency<=4)||(n.deviceMemory&&n.deviceMemory<=4)||cn.saveData||/(^|-)(2|3)g$/.test(cn.effectiveType||'')||r;
weak=true;if(/[?&]full=1/.test(q))weak=false;if(weak)document.documentElement.classList.add('lite');
document.addEventListener('visibilitychange',function(){document.documentElement.classList.toggle('paused',document.hidden)})})();</script></head><body class="auth">
<div class="bg" aria-hidden="true"><div class="globe"><i></i><i></i><i></i><i></i></div><div class="stars"></div><i class="orb o1"></i><i class="orb o2"></i><i class="orb o3"></i><div class="grid"></div></div>
<main class="stage">
 <section class="hero">
  <div class="brow"><div class="logo"><div class="ring"></div><div class="tile"><?php if ($B['logo']): ?><img src="<?= h($B['logo']) ?>" alt="<?= h($B['name']) ?> logo"><?php else: ?><b><?= h($ini) ?></b><?php endif; ?></div></div><span class="bn"><?= h($B['name']) ?></span></div>
  <div><h1 class="name hl" id="tag" data-t="<?= h($B['tagline']) ?>"><?= h($B['tagline']) ?></h1><p class="sub">Morning, evening, late, absent and overtime, all in one live view with one-click reports.</p></div>
  <div class="hrow"><div class="dl"><div class="dialbox" aria-label="Live 24 hour shift dial"><svg id="dial" viewBox="0 0 300 300" role="img" aria-label="Shift timings around a 24 hour dial with the current time"></svg>
   <div class="dial-c"><b id="clk">--:--:--</b><span><i></i><em id="now" style="font-style:normal">Live</em></span></div></div>
  <div class="legend"><span><i style="background:var(--m)"></i>Morning</span><span><i style="background:var(--e)"></i>Evening</span></div></div>
  <div class="mockw" id="mockw"><div class="mock" id="mock" aria-hidden="true">
   <div class="mb"><i></i><i></i><i></i><span>Sample preview</span></div>
   <div class="mk3"><div class="mt"><b data-n="94.2" data-d="1" data-s="%">0</b><small>Attendance</small></div><div class="mt"><b data-n="1534">0</b><small>Present days</small></div><div class="mt hot"><b data-n="490">0</b><small>Late arrivals</small></div></div>
   <div class="bars"><?php foreach ([46,62,55,78,70,88,64,92,58,81,74,95] as $k => $v): ?><i style="--h:<?= $v ?>%;--i:<?= $k ?>"></i><?php endforeach; ?></div>
   <div class="heat"><?php for ($k = 0; $k < 28; $k++): ?><i style="--r:<?= [.95,.9,.97,.6,.92,.88,.96,.93,.35,.91,.94,.9,.97,.85,.9,.58,.93,.96,.9,.88,.94,.97,.5,.92,.9,.95,.89,.93][$k] ?>;--i:<?= $k ?>"></i><?php endfor; ?></div>
  </div></div></div>
  <div class="stats"><div><b>24/7</b><small>Live shift view</small></div><div><b>1-click</b><small>PDF &amp; Excel</small></div><div><b>100%</b><small>Private in your browser</small></div></div>
 </section>
 <section class="panel"><form class="glass<?= $err ? " shake" : "" ?>" method="post" id="lf">
  <h2 id="h2t"><?= !$us ? 'First-time setup' : ($step2 ? 'Two-step check' : 'Welcome back') ?></h2>
  <?php if (isset($_GET['idle'])): ?><div class="note" role="status">Your session ended after 5 minutes of inactivity. Please sign in again.</div><?php endif; ?>
  <p class="lead" id="leadt"><?= $us ? ($step2 ? 'Enter the 6 digit code from your authenticator app.' : 'Sign in to open your attendance dashboard.') : 'Choose the password for the admin user (at least 10 characters). You can add more users in the admin panel.' ?></p>
  <?php if ($err): ?><div class="err" role="alert"><?= h($err) ?></div><?php endif; ?>
  <div id="f1"<?= $step2 ? ' hidden' : '' ?>><?php if ($us): ?><div class="f"><label for="u">Username</label><input id="u" name="u" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="your username"<?= $step2 ? ' disabled' : ' required' ?><?= ($err || $step2) ? '' : ' autofocus' ?>></div><?php endif; ?>
  <div class="f"><label for="p">Password</label><input id="p" type="password" name="p" autocomplete="<?= $us ? 'current-password' : 'new-password' ?>" placeholder="your password"<?= $step2 ? ' disabled' : ' required' ?><?= $us ? '' : ' autofocus' ?>><button type="button" class="eye" id="eye" aria-label="Show password">Show</button></div></div>
  <?php if ($us): ?><div id="f2"<?= $step2 ? '' : ' hidden' ?>><div class="f"><label for="c">Authenticator code</label><input id="c" name="c" inputmode="numeric" autocomplete="one-time-code" maxlength="12" spellcheck="false" placeholder="123456"<?= $step2 ? ' required autofocus' : ' disabled' ?>></div><p class="hint2">Lost your phone? Type one of your recovery codes instead.</p><a class="back2" href="login.php?reset=1">Use a different account</a></div><?php endif; ?>
  <button class="btn" id="go"><?= !$us ? 'Save password and continue' : ($step2 ? 'Verify and sign in' : 'Sign in') ?></button>
  <div class="foot"><?php
    $ct = trim((string)$B['contact']); $dg = preg_replace('/\D+/', '', $ct);
    if ($us && $ct !== '') {
      echo 'Forgot your password or need access? <b>' . h($ct) . '</b>';
      if (strlen($dg) >= 8) echo '<a class="wa" target="_blank" rel="noopener" href="https://wa.me/' . h($dg) . '">Message us on WhatsApp</a>';
    } else echo 'Forgot your password or need access? Ask your administrator.';
  ?></div>
 </form></section>
</main>
<div id="wel" hidden><div class="wc"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="23"/><path d="M15 27l8 8 15-17"/></svg><h3>Welcome, <span id="wn"></span></h3><p>Loading your dashboard...</p></div></div>
<script>
const S=<?= jx(settings()) ?>,$=s=>document.querySelector(s);
const RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
/* typewriter tagline */
{const el=$('#tag'),t=el.dataset.t||'';if(!RM&&t){el.textContent='';let i=0;const k=setInterval(()=>{el.textContent=t.slice(0,++i);if(i>=t.length)clearInterval(k)},34)}}
/* show / hide password */
$('#eye').onclick=()=>{const p=$('#p'),s=p.type==='password';p.type=s?'text':'password';$('#eye').textContent=s?'Hide':'Show';$('#eye').setAttribute('aria-label',s?'Hide password':'Show password')};
const lf=$('#lf'),HAS=<?= $us ? 'true' : 'false' ?>;
function tf(on){const e=$('.err');if(e)e.remove();$('#f1').hidden=on;$('#f2').hidden=!on;$('#u').disabled=on;$('#p').disabled=on;$('#u').required=!on;$('#p').required=!on;$('#c').disabled=!on;$('#c').required=on;$('#h2t').textContent=on?'Two-step check':'Welcome back';$('#leadt').textContent=on?'Enter the 6 digit code from your authenticator app.':'Sign in to open your attendance dashboard.';$('#go').textContent=on?'Verify and sign in':'Sign in';if(on){$('#c').value='';$('#c').focus()}else{$('#p').value='';$('#p').focus()}}
{const pw=$('#p'),w=document.createElement('div');w.className='caps';w.hidden=true;w.textContent='Caps Lock is on';pw.parentNode.appendChild(w);const cl=e=>{w.hidden=!(e.getModifierState&&e.getModifierState('CapsLock'))};pw.addEventListener('keydown',cl);pw.addEventListener('keyup',cl);pw.addEventListener('blur',()=>w.hidden=true)}
lf.addEventListener('submit',async e=>{const go=$('#go');if(!HAS||!window.fetch){go.classList.add('busy');return}
 e.preventDefault();go.classList.add('busy');const fd=new FormData(lf);fd.append('ajax','1');
 try{const r=await (await fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})).json();
  if(r.ok==2){go.classList.remove('busy');tf(true);return}
  if(r.reset){tf(false);}
  if(r.ok==1){$('#wn').textContent=r.name;$('#wel').hidden=false;setTimeout(()=>location.href=r.to,RM?300:1500);return}
  go.classList.remove('busy');let er=$('.err');if(!er){er=document.createElement('div');er.className='err';er.setAttribute('role','alert');lf.querySelector('.lead').after(er)}er.textContent=r.err;
  lf.classList.remove('shake');void lf.offsetWidth;lf.classList.add('shake');if($('#f2').hidden){$('#p').value='';$('#p').focus()}else{$('#c').value='';$('#c').focus()}}
 catch(x){lf.submit()}});
/* 3D tilt of the sign-in card and the dashboard preview */
if(!RM&&!document.documentElement.classList.contains('lite')&&matchMedia('(hover:hover)').matches){const tl=(el,k)=>{addEventListener('pointermove',e=>{const x=e.clientX/innerWidth-.5,y=e.clientY/innerHeight-.5;el.style.setProperty('--rx',(-y*k).toFixed(2)+'deg');el.style.setProperty('--ry',(x*k*1.4).toFixed(2)+'deg')})};tl(lf,7);tl($('#mock'),10)}
/* count-up in the preview */
$('#mock').querySelectorAll('[data-n]').forEach(b=>{const n=+b.dataset.n,d=+(b.dataset.d||0),s=b.dataset.s||'';if(RM){b.textContent=n.toLocaleString()+s;return}let t0=null;const f=t=>{t0=t0||t;const k=Math.min(1,(t-t0)/1500),v=n*(1-Math.pow(1-k,3));b.textContent=v.toLocaleString(undefined,{minimumFractionDigits:d,maximumFractionDigits:d})+s;if(k<1)requestAnimationFrame(f)};setTimeout(()=>requestAnimationFrame(f),500)});
/* 24 hour shift dial with live needle */
const CX=150,CY=150,pt=(m,r)=>{const a=(m/1440*360-90)*Math.PI/180;return[CX+r*Math.cos(a),CY+r*Math.sin(a)]};
const arc=(a,b,r,c,w)=>{const[x1,y1]=pt(a,r),[x2,y2]=pt(b,r);return`<path d="M${x1.toFixed(2)} ${y1.toFixed(2)}A${r} ${r} 0 ${(b-a)>720?1:0} 1 ${x2.toFixed(2)} ${y2.toFixed(2)}" fill="none" stroke="${c}" stroke-width="${w}" stroke-linecap="round" style="filter:drop-shadow(0 0 7px ${c})"/>`};
let g='';
g+=`<circle cx="${CX}" cy="${CY}" r="132" fill="none" stroke="rgba(255,255,255,.06)"/><circle cx="${CX}" cy="${CY}" r="112" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="12"/><circle cx="${CX}" cy="${CY}" r="92" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="12"/>`;
for(let h=0;h<24;h++){const[a,b]=pt(h*60,h%6?118:121),[c,d]=pt(h*60,h%6?124:130);g+=`<line x1="${a}" y1="${b}" x2="${c}" y2="${d}" stroke="rgba(255,255,255,${h%6?.18:.45})"/>`}
[[0,'12 AM'],[360,'6 AM'],[720,'12 PM'],[1080,'6 PM']].forEach(([m,l])=>{const[x,y]=pt(m,146);g+=`<text x="${x}" y="${y+3}" text-anchor="middle">${l}</text>`});
g+=arc(S.m.s,S.m.e,112,'#F0B429',12)+arc(S.e.s,S.e.e,92,'#8C9BFF',12);
g+='<g id="needle"><line id="nl" x1="150" y1="74" x2="150" y2="38" stroke="#fff" stroke-opacity=".8" stroke-width="2" stroke-linecap="round"/><circle id="nd" r="6.5" fill="#fff" style="filter:drop-shadow(0 0 8px #fff)"/></g>';
$('#dial').innerHTML=g;
const inS=(m,s,e)=>(m>=s&&m<e)||(m+1440>=s&&m+1440<e);
function tick(){const d=new Date(),m=d.getHours()*60+d.getMinutes()+d.getSeconds()/60;
 const[x,y]=pt(m,112),[x0,y0]=pt(m,78);$('#nl').setAttribute('x1',x0);$('#nl').setAttribute('y1',y0);$('#nl').setAttribute('x2',x);$('#nl').setAttribute('y2',y);$('#nd').setAttribute('cx',x);$('#nd').setAttribute('cy',y);
 $('#clk').textContent=d.toLocaleTimeString('en-GB',{hour12:false});
 const a=inS(m,S.m.s,S.m.e),b=inS(m,S.e.s,S.e.e);
 $('#now').textContent=a&&b?'Both shifts on site':a?'Morning shift on site':b?'Evening shift on site':'Between shifts'}
tick();setInterval(function(){if(!document.hidden)tick()},1000);
</script><script src="assets/pwa.js?v=1"></script></body></html>

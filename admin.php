<?php
require __DIR__ . '/lib.php'; need('admin');
$B = brand();
$d = rj('data.json', ['meta' => null, 'rows' => []]); $m = $d['meta']; $us = users();
$depts = array_values(array_unique(array_column($d['rows'], 2))); sort($depts);
$S = settings();
$U = []; foreach ($us as $x) $U[$x['username']] = ['name' => $x['name'], 'role' => $x['role'], 'sub' => sub_state($x)];
$EV = array_slice(rj('logins.json', []), 0, 300); $ON = rj('online.json', []); $now = time();
$on5 = array_filter($ON, function ($v) use ($now) { return $v['t'] > $now - 300; });
$fail24 = 0; foreach ($EV as $x) if (in_array($x['ev'], ['fail', 'lock'], true) && $x['t'] > $now - 86400) $fail24++;
$ending = 0; foreach ($us as $x) { $s = sub_state($x); if ($s['s'] === 'active' && $s['left'] <= 3 * 86400) $ending++; }
$newloc = 0; foreach ($EV as $x) if (!empty($x['new']) && $x['t'] > $now - 7 * 86400) $newloc++;
$roleName = ['admin' => 'Admin', 'viewer' => 'View-only', 'customer' => 'Customer'];
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Admin - <?= h($B['name']) ?></title><?php if ($B['logo']): ?><link rel="icon" href="<?= h($B['logo']) ?>"><?php endif; ?>
<style>
<?= brand_css($B) ?>

:root{--bg:#F1F4F9;--card:#fff;--ink:#142033;--mut:#5B6878;--line:#D9DFE7;--good:#12805C;--warn:#B26A00;--bad:#B3261E;color-scheme:light}
@media(prefers-color-scheme:dark){:root{--bg:#0E1520;--card:#162030;--ink:#E8EDF4;--mut:#93A0B1;--line:#26334A;--good:#4FD1A1;--warn:#F0B429;--bad:#FF8A80;color-scheme:dark}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.bar{background:var(--acc);background:linear-gradient(135deg,var(--acc),color-mix(in srgb,var(--acc) 65%,#000));color:var(--onacc)}
.bar .in{max-width:760px;margin:auto;padding:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.tile{width:42px;height:42px;border-radius:11px;background:#fff;display:grid;place-items:center;overflow:hidden;flex:none}
.tile img{max-width:34px;max-height:34px}.tile b{color:var(--acc);font-size:20px}
.bar h1{font-size:18px;margin:0;flex:1;min-width:140px}.bar h1 small{display:block;font-weight:400;font-size:12px;opacity:.85}
a.b,button,select,input{font:inherit}
a.b{color:var(--onacc);text-decoration:none;border:1px solid color-mix(in srgb,var(--onacc) 45%,transparent);border-radius:9px;padding:7px 12px;font-size:14px}
main{max-width:760px;margin:auto;padding:6px 14px 56px}
h2{font-size:16px;margin:0 0 4px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin:14px 0}
.g{display:grid;grid-template-columns:1fr 1fr;gap:10px}
label{display:grid;gap:3px;font-size:13px;color:var(--mut)}
label.c{display:flex;gap:8px;align-items:center;margin-top:8px}
input,select{color:var(--ink);background:var(--bg);border:1px solid var(--line);border-radius:9px;padding:9px 10px;width:100%}
input[type=checkbox]{width:auto}input[type=color]{padding:2px;height:40px}
button{color:var(--ink);background:var(--card);border:1px solid var(--line);border-radius:9px;padding:8px 12px;cursor:pointer}
button.p{background:var(--acc);color:var(--onacc);border:0;margin-top:12px;font-weight:600}
button:disabled{opacity:.5;cursor:default}
input:focus-visible,select:focus-visible,button:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
.m{font-size:13px;margin-top:8px;min-height:18px}.s{color:var(--mut);font-size:13px;margin:4px 0 8px}
.lg{display:flex;gap:14px;align-items:center;margin:10px 0}
.lg .prev{width:84px;height:84px;border-radius:16px;border:1px dashed var(--line);display:grid;place-items:center;background:#fff;overflow:hidden;flex:none}
.lg .prev img{max-width:72px;max-height:72px}.lg .prev b{font-size:34px;color:var(--acc)}
.ur{border-top:1px solid var(--line);padding:12px 0}
.pill{font-size:11px;border:1px solid var(--line);border-radius:99px;padding:1px 8px;color:var(--mut);margin-left:4px}
.share{border:1px solid var(--good);border-radius:12px;padding:12px;margin-top:12px;background:color-mix(in srgb,var(--good) 8%,var(--card))}
.share pre{margin:8px 0;white-space:pre-wrap;font:13px/1.5 ui-monospace,monospace}
/* subscriptions */
.chipsrow{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 12px}
.cp{font-size:12.5px;border:1px solid var(--line);border-radius:99px;padding:3px 11px;color:var(--mut);background:var(--bg)}
.cp b{color:var(--ink)}
.sbg{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}
.sb{display:flex;gap:12px;align-items:center;border:1px solid var(--line);border-radius:14px;padding:12px;background:var(--bg);position:relative;overflow:hidden}
.sb:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--sc,var(--line))}
.sb.s-ok{--sc:var(--good)}.sb.s-warn{--sc:var(--warn)}.sb.s-crit{--sc:var(--bad)}.sb.s-ended{--sc:var(--bad)}.sb.s-wait{--sc:var(--acc)}
.sb.s-crit .cd{animation:blinkc 1s steps(2) infinite}
@keyframes blinkc{50%{opacity:.45}}
.rg{width:62px;height:62px;flex:none}.rg circle{fill:none;stroke-width:6}.rg .bgc{stroke:var(--line)}
.rg .fg{stroke:var(--sc,var(--line));stroke-linecap:round;transition:stroke-dashoffset .9s linear}
.sbt{min-width:0;flex:1}.sbt b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cd{font:600 17px/1.25 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.01em}
.sbs{font-size:12.5px;color:var(--mut)}
.sba{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}.sba button{padding:3px 9px;font-size:12.5px;border-radius:99px}
.sbx{border:1px dashed var(--line);border-radius:12px;padding:10px 12px;margin-top:10px}
.sbh{font-size:13px;margin-bottom:6px}.sbh .tm-st{color:var(--mut);margin-left:6px}
.sbc{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.sbc input[type=number]{width:84px}.sbc select{width:auto}.sbc button{font-size:13px;padding:6px 10px}
.empty{color:var(--mut);font-size:14px;padding:6px 0}
@media(max-width:520px){.g{grid-template-columns:1fr}}
.tl{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:12px 0}
.t1{border-radius:12px;padding:12px 10px;background:color-mix(in srgb,var(--ink) 5%,transparent);border:1px solid var(--line);text-align:center}.t1 b{display:block;font-size:26px;line-height:1.1}.t1 span{font-size:12px;color:var(--mut)}
.t1.gd{border-color:var(--good);background:color-mix(in srgb,var(--good) 12%,transparent)}.t1.gd b{color:var(--good)}.t1.bd{border-color:var(--bad);background:color-mix(in srgb,var(--bad) 12%,transparent)}.t1.bd b{color:var(--bad)}.t1.wr{border-color:var(--warn);background:color-mix(in srgb,var(--warn) 12%,transparent)}.t1.wr b{color:var(--warn)}
.onl{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}.chip{display:inline-flex;gap:6px;align-items:center;font-size:12.5px;padding:5px 11px;border-radius:99px;background:color-mix(in srgb,var(--good) 10%,transparent);border:1px solid color-mix(in srgb,var(--good) 40%,transparent)}.chip i{width:8px;height:8px;border-radius:50%;background:var(--good);box-shadow:0 0 0 0 var(--good);animation:pl 1.6s ease-out infinite}@keyframes pl{to{box-shadow:0 0 0 7px transparent}}
.acth{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin:6px 0 8px}.fx{display:flex;gap:6px}.fx button{white-space:nowrap}.fx select,.fx button,.ghost{border:1px solid var(--line);background:var(--card);color:var(--ink);border-radius:9px;padding:6px 10px;font-size:13px;cursor:pointer}
.tw{overflow-x:auto}table.ev{width:100%;border-collapse:collapse;font-size:13px;min-width:640px}table.ev th{text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--mut);padding:6px 8px;border-bottom:1px solid var(--line)}table.ev td:last-child,table.ev td:nth-child(5){white-space:nowrap}table.ev td{padding:8px;border-bottom:1px solid var(--line);vertical-align:top}table.ev tr:hover td{background:color-mix(in srgb,var(--ink) 4%,transparent)}
.bdg{display:inline-block;font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:99px;white-space:nowrap}.bdg.g{color:var(--good);background:color-mix(in srgb,var(--good) 14%,transparent)}.bdg.r{color:var(--bad);background:color-mix(in srgb,var(--bad) 14%,transparent)}.bdg.o{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent)}.bdg.m{color:var(--mut);background:color-mix(in srgb,var(--mut) 15%,transparent)}
.fg{font-size:17px;margin-right:5px}.ghost{margin-top:10px;width:100%}.sm{color:var(--mut);font-size:12px;display:block}
@media(max-width:560px){.tl{grid-template-columns:1fr 1fr}}
</style></head><body>
<div class="bar"><div class="in"><div class="tile"><?php if ($B['logo']): ?><img src="<?= h($B['logo']) ?>" alt=""><?php else: ?><b><?= h(strtoupper(substr($B['name'], 0, 1))) ?></b><?php endif; ?></div>
<h1><?= h($B['name']) ?><small>Admin panel</small></h1><a class="b" href="index.php">Open dashboard</a><a class="b" href="logout.php">Log out</a></div></div>
<main>

<div class="card ov"><h2>Overview</h2><p class="s">What is happening on your portal right now.</p>
<div class="tl"><div class="t1"><b><?= count($us) ?></b><span>Users</span></div><div class="t1 gd"><b><?= count($on5) ?></b><span>Online now</span></div><div class="t1 <?= $fail24 ? 'bd' : '' ?>"><b><?= $fail24 ?></b><span>Failed sign-ins (24h)</span></div><div class="t1 <?= $ending ? 'wr' : '' ?>"><b><?= $ending ?></b><span>Demos ending in 3 days</span></div></div>
<?php if ($on5): ?><div class="onl"><?php foreach ($on5 as $k => $v): ?><span class="chip"><i></i><b><?= h($U[$k]['name'] ?? $k) ?></b> <?= h(trim(($v['cc'] ? '' : '') . ($v['ci'] ? $v['ci'] . ', ' : '') . $v['c'], ', ')) ?: h($v['ip']) ?> · <?= h($v['d']) ?></span><?php endforeach; ?></div><?php endif; ?>
<div class="acth"><h2 style="margin:0">Sign-in activity</h2><div class="fx"><select id="evf" aria-label="Filter"><option value="all">All events</option><option value="login">Sign-ins</option><option value="bad">Failed and locked</option><option value="new">New location</option></select><button id="evcsv" type="button">Export CSV</button></div></div>
<div class="tw"><table class="ev"><thead><tr><th>When</th><th>User</th><th>Event</th><th>Where</th><th>IP address</th><th>Device</th></tr></thead><tbody id="evb"></tbody></table></div>
<button id="evmore" type="button" class="ghost" hidden>Show more</button><p class="s" id="evnone" hidden>No activity yet. Sign-ins will appear here.</p>
<p class="s">Location is estimated from the IP address and can be wrong on mobile data or VPNs. Records are kept for 90 days.</p></div>
<div class="card"><h2>Subscriptions</h2><p class="s">Each customer or viewer can have their own demo timer. When it reaches zero that user is locked out automatically; admins and users without a timer are never affected.</p>
<div class="chipsrow" id="subsum"></div><div class="sbg" id="subtiles"></div></div>

<div class="card"><h2>Branding</h2><p class="s">Your logo and company name appear on the login page, the dashboard and every PDF report.</p>
<div class="lg"><div class="prev" id="prev"><?php if ($B['logo']): ?><img src="<?= h($B['logo']) ?>" alt="Logo"><?php else: ?><b><?= h(strtoupper(substr($B['name'], 0, 1))) ?></b><?php endif; ?></div>
<div><input type="file" id="lf" accept="image/*" hidden><button id="lb">Upload logo</button> <button id="ld"<?= $B['logo'] ? '' : ' hidden' ?>>Remove logo</button>
<div class="s" style="margin:6px 0 0">PNG, JPG or SVG. A square or wide logo on a transparent background works best.</div></div></div>
<div class="m" id="m0"></div>
<div class="g"><label>Company name<input id="bn" maxlength="60" value="<?= h($B['name']) ?>"></label>
<label>Tagline (shown on login page)<input id="bt" maxlength="100" value="<?= h($B['tagline']) ?>"></label>
<label>Message for users whose subscription has ended<input id="bk" maxlength="160" value="<?= h($B['contact']) ?>" placeholder="e.g. WhatsApp +971 50 000 0000"></label>
<label>Brand colour<div style="display:flex;gap:8px"><input type="color" id="bc" value="<?= h($B['accent']) ?>" style="width:64px"><input id="bh" maxlength="7" value="<?= h($B['accent']) ?>"></div></label></div>
<button class="p" id="bs">Save branding</button><div class="m" id="m4"></div></div>

<div class="card"><h2>Saved attendance data</h2>
<p class="s"><?= $m ? 'Current data: ' . h($m['file']) . ' | ' . h($m['from']) . ' to ' . h($m['to']) . ' | ' . (int)$m['count'] . ' records | saved ' . h($m['at']) : 'No data saved yet.' ?></p>
<input type="file" id="f" accept=".xlsx,.xls,.csv">
<label class="c"><input type="checkbox" id="mg"> Keep older records and add or update the days in this file</label>
<div class="m" id="pv"></div><button class="p" id="sv" disabled>Save to dashboard</button><div class="m" id="m1"></div></div>

<div class="card"><h2>Shift timings</h2><p class="s">Applies to everyone using the saved data. Customers can also adjust timings for their own file on the dashboard.</p><div class="g">
<label>Morning starts<input type="time" id="ms"></label><label>Morning ends<input type="time" id="me"></label>
<label>Evening starts<input type="time" id="es"></label><label>Evening ends (next day)<input type="time" id="ee"></label>
<label>Grace for late / early (min)<input type="number" id="gr" min="0"></label><label>Overtime after (min)<input type="number" id="ot" min="0"></label>
<label>Match punch within (min)<input type="number" id="tl" min="30"></label>
<label>Weekly off day<select id="wo"><option value="-1">None</option><option value="0">Sunday</option><option value="5">Friday</option><option value="6">Saturday</option></select></label></div>
<button class="p" id="ss">Save shift timings</button><div class="m" id="m2"></div></div>

<div class="card"><h2>Users</h2>
<p class="s"><b>Admin</b> manages everything. <b>View-only</b> sees the saved data (optionally one department). <b>Customer</b> sees none of your saved data: they upload their own file privately and download PDF or Excel reports.</p>
<div class="sbx" style="margin:0 0 10px"><b>Welcome message</b> <span class="s">shown to a user one time, then it switches itself off.</span><div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"><button id="wall">Show to all users</button><button id="wnone">Clear for all</button></div><div class="m" id="mw"></div></div>
<?php foreach ($us as $x): $r = $x['role']; ?><div class="ur">
<b><?= h($x['name']) ?></b><span class="pill"><?= h($roleName[$r] ?? $r) ?></span> <span class="s">@<?= h($x['username']) ?> | last login: <?= h($x['last'] ?? 'never') ?></span>
<div class="g" style="margin-top:8px"><select class="r"><option value="admin"<?= $r==='admin'?' selected':'' ?>>Admin</option><option value="viewer"<?= $r==='viewer'?' selected':'' ?>>View-only</option><option value="customer"<?= $r==='customer'?' selected':'' ?>>Customer</option></select>
<select class="d"<?= $r!=='viewer'?' disabled':'' ?>><option value="">All departments</option><?php foreach ($depts as $dp): ?><option value="<?= h($dp) ?>"<?= ($x['dept'] ?? '')===$dp?' selected':'' ?>><?= h($dp) ?></option><?php endforeach; ?></select></div>
<label class="c"><input type="checkbox" class="u"<?= !empty($x['up'])?' checked':'' ?><?= $r!=='viewer'?' disabled':'' ?>> Can save new files for everyone (view-only users with no department limit)</label>
<label class="c"><input type="checkbox" class="w"<?= !empty($x['wel'])?' checked':'' ?>> Show the welcome message once on their next login<?= (empty($x['wel']) && !empty($x['seen'])) ? ' <span class="s">(already shown)</span>' : '' ?></label>
<div class="sbx" data-u="<?= h($x['username']) ?>"<?= $r==='admin'?' hidden':'' ?>><div class="sbh"><b>Subscription</b><span class="tm-st" data-u="<?= h($x['username']) ?>"></span></div>
<div class="sbc"><input type="number" class="tv" min="1" value="7" aria-label="Amount of time"><select class="tu" aria-label="Unit"><option value="m">minutes</option><option value="h">hours</option><option value="d" selected>days</option></select>
<button data-t="set">Start now</button><button data-t="setl">Start at first login</button><button data-t="add">Add time</button><button data-t="clear">No limit</button><button data-t="expire">End now</button></div></div>
<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"><button data-a="set" data-u="<?= h($x['username']) ?>">Save access</button><button data-a="pw" data-u="<?= h($x['username']) ?>">Reset password</button><button data-a="del" data-u="<?= h($x['username']) ?>">Delete</button></div><div class="m rm"></div></div><?php endforeach; ?>
<h2 style="margin-top:16px">Add a user</h2><div class="g">
<label>Full name<input id="un"></label><label>Username<input id="uu" autocapitalize="none"></label>
<label>Password (6+ characters)<input type="text" id="up" autocomplete="off"></label>
<label>Access<select id="ur"><option value="customer">Customer</option><option value="viewer">View-only</option><option value="admin">Admin</option></select></label>
<label>Department (view-only)<select id="ud" disabled><option value="">All departments</option><?php foreach ($depts as $dp): ?><option value="<?= h($dp) ?>"><?= h($dp) ?></option><?php endforeach; ?></select></label></div>
<label class="c"><input type="checkbox" id="uup" disabled> Can save new files for everyone</label>
<label class="c"><input type="checkbox" id="uw" checked> Show the welcome message once on their first login</label>
<div class="sbx"><label class="c" style="margin:0"><input type="checkbox" id="nton"> Give this user a demo subscription timer</label>
<div class="sbc" id="ntbox" hidden style="margin-top:8px"><input type="number" id="ntv" min="1" value="7" aria-label="Amount of time"><select id="ntu" aria-label="Unit"><option value="m">minutes</option><option value="h">hours</option><option value="d" selected>days</option></select>
<select id="nts" aria-label="When the timer starts"><option value="now">Start now</option><option value="login">Start at first login</option></select></div></div>
<button class="p" id="ua">Add user</button><div class="m" id="m3"></div>
<div class="share" id="sh" hidden><b>User created.</b> Send these details to the user:<pre id="shp"></pre><button id="shc">Copy</button> <button id="shd">Done</button></div></div>
</main>
<script src="assets/vendor/xlsx.full.min.js"></script>
<script src="assets/parse.js?v=2"></script>
<script>
const ME=<?= jx($_SESSION['u'] ?? '') ?>,CSRF=<?= jx(csrf()) ?>,S=<?= jx($S) ?>,$=s=>document.querySelector(s);let ROWS=[],FN='';
const fm=m=>{m=((m%1440)+1440)%1440;return String(m/60|0).padStart(2,'0')+':'+String(m%60).padStart(2,'0')};
const hm=t=>{const x=String(t).match(/(\d{1,2}):(\d{2})/);return x?+x[1]*60+ +x[2]:null};
const say=(id,ok,t)=>{const e=typeof id=='string'?$(id):id;e.textContent=t;e.style.color=ok?'var(--good)':'var(--bad)'};
async function api(a,b){try{const r=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a,csrf:CSRF,...b})});return await r.json()}catch(e){return{ok:0,msg:'Server error. Please try again.'}}}
$('#ms').value=fm(S.m.s);$('#me').value=fm(S.m.e);$('#es').value=fm(S.e.s);$('#ee').value=fm(S.e.e);
$('#gr').value=S.grace;$('#ot').value=S.ot;$('#tl').value=S.tol;$('#wo').value=S.off;

/* branding */
$('#bc').oninput=e=>{$('#bh').value=e.target.value.toUpperCase()};
$('#bh').oninput=e=>{if(/^#[0-9a-f]{6}$/i.test(e.target.value))$('#bc').value=e.target.value};
$('#bs').onclick=async()=>{const r=await api('brand',{name:$('#bn').value,tagline:$('#bt').value,accent:$('#bh').value,contact:$('#bk').value});say('#m4',r.ok,r.ok?'Branding saved. Reloading...':r.msg);if(r.ok)setTimeout(()=>location.reload(),900)};
$('#lb').onclick=()=>$('#lf').click();
$('#lf').onchange=async e=>{const f=e.target.files[0];e.target.value='';if(!f)return;
 const url=URL.createObjectURL(f),im=new Image();
 im.onerror=()=>say('#m0',0,'That file is not a usable image.');
 im.onload=async()=>{let w=im.naturalWidth||480,h=im.naturalHeight||480;const k=Math.min(1,480/Math.max(w,h,1));
  if(!im.naturalWidth){w=480;h=480}
  const c=document.createElement('canvas');c.width=Math.max(1,Math.round(w*k));c.height=Math.max(1,Math.round(h*k));c.getContext('2d').drawImage(im,0,0,c.width,c.height);URL.revokeObjectURL(url);
  const r=await api('logo',{img:c.toDataURL('image/png')});say('#m0',r.ok,r.ok?'Logo saved. Reloading...':r.msg);if(r.ok)setTimeout(()=>location.reload(),800)};
 im.src=url};
$('#ld').onclick=async()=>{if(!confirm('Remove the logo?'))return;const r=await api('logo_del',{});say('#m0',r.ok,r.msg);if(r.ok)setTimeout(()=>location.reload(),600)};

/* data upload */
$('#f').onchange=async e=>{const f=e.target.files[0];if(!f)return;FN=f.name;$('#sv').disabled=true;ROWS=[];
 try{ROWS=parseAttendance(await f.arrayBuffer());
  if(!ROWS.length)throw 0;
  const ds=ROWS.map(r=>r[3]).sort();say('#pv',1,`${ROWS.length} records | ${new Set(ROWS.map(r=>r[0])).size} employees | ${ds[0]} to ${ds[ds.length-1]}`);$('#sv').disabled=false;
 }catch(x){say('#pv',0,'Could not read that file. It needs the columns Employee ID, First Name, Date, First Punch and Last Punch (Department is optional).')}};
$('#sv').onclick=async()=>{$('#sv').disabled=true;const r=await api('data',{rows:ROWS,file:FN,merge:$('#mg').checked});say('#m1',r.ok,r.msg);if(r.ok)setTimeout(()=>location.reload(),1200);else $('#sv').disabled=false};

/* shifts */
$('#ss').onclick=async()=>{const g=(i,d)=>hm($(i).value)??d,en=(s,e)=>e<=s?e+1440:e;
 const ms=g('#ms',540),es=g('#es',900);
 const r=await api('settings',{s:{m:{s:ms,e:en(ms,g('#me',1080))},e:{s:es,e:en(es,g('#ee',120))},grace:+$('#gr').value,ot:+$('#ot').value,tol:+$('#tl').value,off:+$('#wo').value}});say('#m2',r.ok,r.msg)};

/* users */
const sync=(role,d,u)=>{d.disabled=role!=='viewer';if(role!=='viewer')d.value='';u.disabled=role!=='viewer'||d.value!=='';if(u.disabled)u.checked=false};
$('#ur').onchange=()=>sync($('#ur').value,$('#ud'),$('#uup'));$('#ud').onchange=()=>sync($('#ur').value,$('#ud'),$('#uup'));
document.querySelectorAll('.ur').forEach(r=>{const a=r.querySelector('.r'),d=r.querySelector('.d'),u=r.querySelector('.u');a.onchange=()=>sync(a.value,d,u);d.onchange=()=>sync(a.value,d,u)});
async function ua(a,b,el){const r=await api(a,b);say(el||'#m3',r.ok,r.msg);if(r.ok&&a!='u_add')setTimeout(()=>location.reload(),900);return r}
$('#ua').onclick=async()=>{const b={name:$('#un').value,username:$('#uu').value.trim().toLowerCase(),password:$('#up').value,role:$('#ur').value,dept:$('#ud').value,up:$('#uup').checked,wel:$('#uw').checked,timer:{on:$('#nton').checked&&$('#ur').value!=='admin',value:+$('#ntv').value,unit:$('#ntu').value,start:$('#nts').value}};
 const r=await ua('u_add',b);if(!r.ok)return;
 const url=new URL('login.php',location.href).href;
 $('#shp').textContent=`Login page: ${url}\nUsername: ${b.username}\nPassword: ${b.password}`;$('#sh').hidden=false;$('#sh').scrollIntoView({block:'center'})};
$('#shc').onclick=async()=>{try{await navigator.clipboard.writeText($('#shp').textContent);$('#shc').textContent='Copied'}catch(e){const s=getSelection(),r=document.createRange();r.selectNodeContents($('#shp'));s.removeAllRanges();s.addRange(r);$('#shc').textContent='Press Ctrl+C'}};
$('#shd').onclick=()=>location.reload();
$('#wall').onclick=async()=>{const r=await api('u_welall',{on:true});say('#mw',r.ok,r.msg);if(r.ok)setTimeout(()=>location.reload(),1100)};
$('#wnone').onclick=async()=>{const r=await api('u_welall',{on:false});say('#mw',r.ok,r.msg);if(r.ok)setTimeout(()=>location.reload(),1100)};
document.addEventListener('click',e=>{const b=e.target.closest('[data-a]');if(!b)return;const u=b.dataset.u,a=b.dataset.a,row=b.closest('.ur'),R=row.querySelector('.rm');
 if(a=='pw'){const p=prompt('New password for '+u+' (at least 6 characters)');if(p)ua('u_pw',{username:u,password:p},R)}
 else if(a=='del'){if(confirm(u==ME?'Delete your own account? You will be signed out. Make sure another admin exists.':'Delete '+u+'?'))ua('u_del',{username:u},R)}
 else if(a=='set')ua('u_set',{username:u,role:row.querySelector('.r').value,dept:row.querySelector('.d').value,up:row.querySelector('.u').checked,wel:row.querySelector('.w').checked},R)});

/* ---------- subscriptions ---------- */
const U=<?= jx($U) ?>,SK=Date.now()-<?= time() ?>*1000;
const nowS=()=>Math.floor((Date.now()-SK)/1000),pad2=n=>String(n).padStart(2,'0');
const fmtLeft=s=>{s=Math.max(0,Math.floor(s));const d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);return(d?d+'d ':'')+pad2(h)+':'+pad2(m)+':'+pad2(s%60)};
const fmtDur=s=>s%86400==0?s/86400+(s==86400?' day':' days'):s%3600==0?s/3600+(s==3600?' hour':' hours'):Math.round(s/60)+' min';
const esc=t=>String(t).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function cls(sub){if(sub.s=='pending')return'wait';if(sub.s=='expired')return'ended';if(sub.s!='active')return'none';const l=sub.exp-nowS();return l<=0?'ended':l<=3600?'crit':l<=86400?'warn':'ok'}
const RC=2*Math.PI*26;
function ringOff(sub){if(sub.s=='active'){const l=Math.max(0,sub.exp-nowS()),t=sub.dur||Math.max(l,1);return RC*(1-Math.min(1,l/t))}return sub.s=='pending'?0:RC}
function statusText(sub){const c=cls(sub);
 if(c=='wait')return`Waiting for first login. Starts a ${fmtDur(sub.dur)} timer`;
 if(c=='ended')return'Ended on '+new Date(sub.exp*1000).toLocaleString([],{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
 if(c=='none')return'No limit';
 return'Ends '+new Date(sub.exp*1000).toLocaleString([],{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'})}
const cdText=sub=>sub.s=='active'?(sub.exp-nowS()>0?fmtLeft(sub.exp-nowS()):'Ended'):sub.s=='pending'?fmtDur(sub.dur):sub.s=='expired'?'Ended':'No limit';
const order={ended:0,crit:1,warn:2,ok:3,wait:4,none:5};
function tiles(){
 const ent=Object.entries(U).filter(([k,v])=>v.role!=='admin'),cnt={ok:0,warn:0,crit:0,ended:0,wait:0,none:0};
 ent.forEach(([k,v])=>cnt[cls(v.sub)]++);
 $('#subsum').innerHTML=`<span class="cp"><b>${cnt.ok+cnt.warn+cnt.crit}</b> running</span><span class="cp"><b>${cnt.warn+cnt.crit}</b> ending within 24h</span><span class="cp"><b>${cnt.ended}</b> ended</span><span class="cp"><b>${cnt.wait}</b> waiting for first login</span><span class="cp"><b>${cnt.none}</b> no limit</span>`;
 const timed=ent.filter(([k,v])=>cls(v.sub)!='none').sort((a,b)=>order[cls(a[1].sub)]-order[cls(b[1].sub)]||(a[1].sub.exp||0)-(b[1].sub.exp||0));
 $('#subtiles').innerHTML=timed.length?timed.map(([k,v])=>{const c=cls(v.sub);
  return`<div class="sb s-${c}" data-tile="${esc(k)}"><svg class="rg" viewBox="0 0 62 62" aria-hidden="true"><circle class="bgc" cx="31" cy="31" r="26"/><circle class="fg" cx="31" cy="31" r="26" transform="rotate(-90 31 31)" stroke-dasharray="${RC}" stroke-dashoffset="${ringOff(v.sub)}"/></svg>
  <div class="sbt"><b>${esc(v.name)}</b><div class="cd" data-cd="${esc(k)}">${cdText(v.sub)}</div><div class="sbs">${statusText(v.sub)}</div>
  <div class="sba"><button data-q="${esc(k)}" data-v="1" data-un="d">+1 day</button><button data-q="${esc(k)}" data-v="7" data-un="d">+7 days</button><button data-q="${esc(k)}" data-x="expire">End now</button></div></div></div>`}).join(''):'<div class="empty">No timed subscriptions yet. Add a user with a timer, or open a user below and press Start now.</div>';
 statuses()}
function statuses(){document.querySelectorAll('.tm-st').forEach(e=>{const v=U[e.dataset.u];if(v)e.textContent=' | '+(v.sub.s=='active'&&cls(v.sub)!='ended'?'running, '+cdText(v.sub)+' left':statusText(v.sub))})}
let last={};
function tick(){let flip=false;
 for(const[k,v]of Object.entries(U)){if(v.role==='admin')continue;const c=cls(v.sub);if(last[k]!==c){flip=true}last[k]=c}
 if(flip){tiles();return}
 document.querySelectorAll('[data-cd]').forEach(e=>{const v=U[e.dataset.cd];if(v)e.textContent=cdText(v.sub)});
 document.querySelectorAll('[data-tile]').forEach(e=>{const v=U[e.dataset.tile],f=e.querySelector('.fg');if(v&&f)f.setAttribute('stroke-dashoffset',ringOff(v.sub))});
 statuses()}
async function timerOp(u,op,extra,el){const r=await api('u_timer',Object.assign({username:u,op},extra||{}));
 if(el)say(el,r.ok,r.msg);else if(!r.ok)alert(r.msg);
 if(r.ok&&r.row){U[u].sub=r.row.sub;last={};tiles()}return r}
document.addEventListener('click',e=>{
 const q=e.target.closest('[data-q]');
 if(q){const u=q.dataset.q;if(q.dataset.x=='expire'){if(confirm('End the subscription for '+U[u].name+' now?'))timerOp(u,'expire')}else timerOp(u,'add',{value:+q.dataset.v,unit:q.dataset.un});return}
 const b=e.target.closest('[data-t]');if(!b)return;
 const box=b.closest('.sbx'),u=box.dataset.u,R=box.closest('.ur').querySelector('.rm'),v=+box.querySelector('.tv').value,un=box.querySelector('.tu').value,t=b.dataset.t;
 if(t=='set')timerOp(u,'set',{value:v,unit:un,start:'now'},R);
 else if(t=='setl')timerOp(u,'set',{value:v,unit:un,start:'login'},R);
 else if(t=='add')timerOp(u,'add',{value:v,unit:un},R);
 else if(t=='clear')timerOp(u,'clear',{},R);
 else if(t=='expire'){if(confirm('End the subscription for '+U[u].name+' now?'))timerOp(u,'expire',{},R)}});
$('#nton').onchange=()=>{$('#ntbox').hidden=!$('#nton').checked};
{const o=$('#ur').onchange;$('#ur').onchange=()=>{o();const a=$('#ur').value==='admin';$('#nton').disabled=a;if(a){$('#nton').checked=false;$('#ntbox').hidden=true}}}
tiles();setInterval(tick,1000);
</script>
<script>
(()=>{const EV=<?= jx($EV) ?>,$=s=>document.querySelector(s);let n=12,f='all';
 const flag=c=>c&&c.length==2?String.fromCodePoint(...[...c.toUpperCase()].map(x=>127397+x.charCodeAt(0))):'';
 const T={login:['Signed in','g'],logout:['Signed out','m'],fail:['Wrong password','r'],lock:['Locked (5 wrong)','r'],timeout:['Timed out (idle)','o']};
 const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const ok=e=>f=='all'||(f=='login'&&e.ev=='login')||(f=='bad'&&(e.ev=='fail'||e.ev=='lock'))||(f=='new'&&e.new);
 const when=t=>{const d=new Date(t*1000),a=Date.now()-d,m=Math.floor(a/6e4);return (m<1?'just now':m<60?m+' min ago':m<1440?Math.floor(m/60)+' h ago':d.toLocaleDateString('en-GB',{day:'numeric',month:'short'}))+'<span class="sm">'+d.toLocaleString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'})+'</span>'};
 function draw(){const l=EV.filter(ok);$('#evb').innerHTML=l.slice(0,n).map(e=>{const t=T[e.ev]||[e.ev,'m'],w=e.c||'Unknown';
  return `<tr><td>${when(e.t)}</td><td><b>${esc(e.u)}</b></td><td><span class="bdg ${t[1]}">${t[0]}</span>${e.new?' <span class="bdg o">New location</span>':''}${e.n&&e.ev!='lock'&&e.ev!='fail'?'':''}</td><td><span class="fg">${flag(e.cc)}</span>${esc(w)}${e.ci?'<span class="sm">'+esc(e.ci)+'</span>':''}</td><td>${esc(e.ip)}</td><td>${esc(e.d)}</td></tr>`}).join('');
  $('#evmore').hidden=l.length<=n;$('#evnone').hidden=l.length>0;$('#evmore').textContent='Show more ('+(l.length-n)+' left)'}
 $('#evf').onchange=e=>{f=e.target.value;n=12;draw()};$('#evmore').onclick=()=>{n+=25;draw()};
 $('#evcsv').onclick=()=>{const q=v=>'"'+String(v??'').replace(/"/g,'""')+'"',r=[['Time','User','Event','Country','City','IP','Device','New location']].concat(EV.filter(ok).map(e=>[new Date(e.t*1000).toISOString(),e.u,e.ev,e.c,e.ci,e.ip,e.d,e.new?'yes':'']));
  const b=new Blob(['\ufeff'+r.map(x=>x.map(q).join(',')).join('\r\n')],{type:'text/csv'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='sign-in-activity.csv';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),2000)};
 draw()})();
</script>
</body></html>

<?php
require __DIR__ . '/lib.php';
$me = need(); $B = brand(); $role = $me['role']; $admin = $role === 'admin';
$canSave = $admin || ($role === 'viewer' && !empty($me['up']) && $me['dept'] === '');
$d = rj('data.json', ['meta' => null, 'rows' => []]);
if ($role === 'customer') $d = ['meta' => null, 'rows' => []];
elseif (!$admin && $me['dept'] !== '') $d['rows'] = array_values(array_filter($d['rows'], fn($r) => $r[2] === $me['dept']));
$asg = (array)rj('assign.json', []);
if ($role === 'customer') $asg = [];
elseif (!$admin) { $ids = []; foreach ($d['rows'] as $r) $ids[(string)$r[0]] = 1; $asg = array_intersect_key($asg, $ids); }
$v = function ($f) { return @filemtime(__DIR__ . '/assets/' . $f) ?: 1; };
$ini = strtoupper(substr($B['name'], 0, 1));
$sub = sub_state($me) + ['now' => time()];
?><!DOCTYPE html>
<html data-theme="dark" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($B['name']) ?> - Attendance</title>
<?php if ($B['logo']): ?><link rel="icon" href="<?= h($B['logo']) ?>"><?php endif; ?>
<style>
<?= brand_css($B) ?>

:root{--bg:#EEF1F6;--card:#fff;--soft:#F5F7FA;--ink:#142033;--mut:#5B6878;--line:#DCE2EA;--m:#C98A00;--mt:#8A5D00;--e:#3F4FB0;--o:#8190A3;--bad:#B3261E;--good:#12805C;--warn:#B26A00;--onE:#fff;--sh:0 1px 2px rgba(20,32,51,.05),0 10px 28px rgba(20,32,51,.06);color-scheme:light}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#0D1420;--card:#152031;--soft:#1A2639;--ink:#E8EDF4;--mut:#93A0B1;--line:#26334A;--m:#F0B429;--mt:#F0B429;--e:#8C9BFF;--o:#8190A3;--bad:#FF8A80;--good:#4FD1A1;--warn:#F0B429;--onE:#0D1420;--sh:0 1px 2px rgba(0,0,0,.3),0 10px 28px rgba(0,0,0,.25);color-scheme:dark}}
:root[data-theme="dark"]{--bg:#0D1420;--card:#152031;--soft:#1A2639;--ink:#E8EDF4;--mut:#93A0B1;--line:#26334A;--m:#F0B429;--mt:#F0B429;--e:#8C9BFF;--o:#8190A3;--bad:#FF8A80;--good:#4FD1A1;--warn:#F0B429;--onE:#0D1420;--sh:0 1px 2px rgba(0,0,0,.3),0 10px 28px rgba(0,0,0,.25);color-scheme:dark}
*{box-sizing:border-box}
html{scroll-padding-top:12px}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-variant-numeric:tabular-nums;padding-bottom:env(safe-area-inset-bottom,0)}
[hidden]{display:none!important}
button,input,select{font:inherit;color:var(--ink)}
button:focus-visible,select:focus-visible,input:focus-visible,summary:focus-visible,[tabindex]:focus-visible{outline:2px solid var(--ink);outline-offset:2px}

/* header */
.top{background:var(--acc);background:linear-gradient(135deg,var(--acc),color-mix(in srgb,var(--acc) 62%,#000));color:var(--onacc);position:relative;overflow:hidden}
.top:after{content:"";pointer-events:none;position:absolute;width:420px;height:420px;border-radius:50%;background:var(--onacc);opacity:.06;right:-120px;top:-220px}
.top .in{position:relative;max-width:1120px;margin:auto;padding:max(14px,env(safe-area-inset-top,0px)) 16px 14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.tile{width:48px;height:48px;border-radius:13px;background:#fff;display:grid;place-items:center;overflow:hidden;flex:none;box-shadow:0 4px 14px rgba(0,0,0,.15)}
.tile img{max-width:40px;max-height:40px;display:block}.tile b{font-size:22px;color:var(--acc)}
.brand{flex:1;min-width:170px}.brand h1{margin:0;font-size:19px;letter-spacing:-.01em;line-height:1.2}
.brand .sub{font-size:12.5px;opacity:.88;margin-top:2px}
.tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.hb{background:transparent;color:var(--onacc);border:1px solid color-mix(in srgb,var(--onacc) 45%,transparent);border-radius:10px;padding:8px 12px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;gap:6px;align-items:center}
.hb:hover{background:color-mix(in srgb,var(--onacc) 14%,transparent)}
.hb.fill{background:var(--onacc);color:var(--acc);border-color:var(--onacc);font-weight:600}
.who{font-size:13px;text-align:right;line-height:1.25;opacity:.95}.who small{display:block;opacity:.8}

main{max-width:1120px;margin:auto;padding:14px 16px 56px}
h2{font-size:16px;margin:26px 0 10px;letter-spacing:-.005em}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:var(--sh)}
.sub2{color:var(--mut);font-size:13px}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* buttons / inputs */
.btn,select,input[type=search],input[type=time],input[type=number],input[type=date]{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:8px 11px}
.btn{cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn.pri{background:var(--acc);color:var(--onacc);border-color:transparent;font-weight:600}
.btn:hover{border-color:var(--mut)}.btn.pri:hover{filter:brightness(1.07)}
.seg{display:flex;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--card)}
.seg button{background:none;border:0;color:var(--mut);padding:8px 13px;cursor:pointer}
.seg button[aria-pressed=true]{background:var(--ink);color:var(--bg);font-weight:600}
.chips button{font-size:13px;color:var(--mut);background:none;border:1px solid var(--line);border-radius:99px;padding:5px 11px;cursor:pointer}
.chips button[aria-pressed=true]{border-color:var(--ink);color:var(--ink);font-weight:600;background:var(--soft)}
.flt{display:grid;gap:10px}
.flt label{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--mut)}

/* banner / empty */
.banner{display:flex;gap:10px;align-items:center;flex-wrap:wrap;border:1px solid color-mix(in srgb,var(--acc) 40%,var(--line));background:color-mix(in srgb,var(--acc) 8%,var(--card));border-radius:14px;padding:11px 14px;margin:0 0 12px;font-size:14px}
.banner .grow{flex:1;min-width:200px}
.drop{margin-top:6px;text-align:center;border:2px dashed var(--line);border-radius:20px;background:var(--card);padding:clamp(28px,7vw,64px) 20px;transition:.15s}
.drop.on{border-color:var(--acc);background:color-mix(in srgb,var(--acc) 8%,var(--card))}
.drop .ic{width:64px;height:64px;border-radius:18px;background:color-mix(in srgb,var(--acc) 14%,var(--card));display:grid;place-items:center;margin:0 auto 14px}
.drop h2{margin:0 0 6px;font-size:22px}.drop p{margin:0 auto 18px;max-width:46ch;color:var(--mut)}

/* KPIs */
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:12px 0}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:var(--sh);display:flex;gap:12px;align-items:center;min-height:84px}
.kpi b{display:block;font-size:26px;line-height:1.1;letter-spacing:-.02em}
.kpi span{font-size:12.5px;color:var(--mut)}
.kpi.bad b{color:var(--bad)}.kpi.good b{color:var(--good)}
.ring{width:58px;height:58px;flex:none}
.ring circle{fill:none;stroke-width:7}.ring .bg{stroke:var(--line)}.ring text{font:700 14px ui-sans-serif,system-ui;fill:var(--ink)}

/* ruler + shift cards */
.ruler svg{display:block;width:100%;height:auto}
svg text{font:10px ui-sans-serif,system-ui,sans-serif;fill:var(--mut)}
.shifts{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:10px;margin-top:10px}
.sc{cursor:pointer;border-top:5px solid var(--o);position:relative}
.sc.m{border-top-color:var(--m)}.sc.e{border-top-color:var(--e)}
.sc.on{outline:2px solid var(--ink)}
.sc h3{margin:0 0 10px;font-size:15px}.sc h3 small{font-weight:400;color:var(--mut);margin-left:6px}
.st{display:grid;grid-template-columns:repeat(3,1fr);gap:10px 6px}
.st b{display:block;font-size:19px}.st span{font-size:11.5px;color:var(--mut)}
.bad{color:var(--bad)}.good{color:var(--good)}

/* chart + attention */
.two{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:12px}
.leg{display:flex;gap:14px;flex-wrap:wrap;font-size:12.5px;color:var(--mut);margin-top:8px}
.leg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
.att h3{margin:0 0 6px;font-size:13px;color:var(--mut);font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.att button{all:unset;box-sizing:border-box;width:100%;display:flex;justify-content:space-between;gap:8px;padding:7px 4px;border-bottom:1px solid var(--line);cursor:pointer;font-size:14px}
.att button:last-of-type{border:0}.att button:hover{background:var(--soft)}
.att button:focus-visible{outline:2px solid var(--ink)}
.att .none{font-size:13px;color:var(--mut);padding:6px 4px}
.att+.att{margin-top:12px}

/* employees */
.emp{background:var(--card);border:1px solid var(--line);border-radius:14px;margin:7px 0;overflow:hidden}
.emp>button{all:unset;display:block;width:100%;box-sizing:border-box;padding:11px 14px;cursor:pointer}
.emp>button:hover{background:var(--soft)}
.emp>button:focus-visible{outline:2px solid var(--ink);outline-offset:-2px}
.er{display:grid;grid-template-columns:minmax(0,1.7fr) repeat(6,minmax(52px,.5fr));gap:8px;align-items:center}
.nm{font-weight:600;min-width:0}.nm small{display:block;font-weight:400;color:var(--mut);font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nm .rate{height:5px;border-radius:9px;background:var(--line);margin-top:6px;overflow:hidden;max-width:240px}.nm .rate i{display:block;height:100%;background:var(--good)}
.mc b{display:block;font-size:16px}.mc small{font-size:11px;color:var(--mut)}
.pill{display:inline-block;font-size:11px;padding:1px 8px;border-radius:99px;border:1px solid var(--line);color:var(--mut);white-space:nowrap;margin-left:6px;vertical-align:1px}
.pill.m{border-color:var(--m);color:var(--mt)}.pill.e{border-color:var(--e);color:var(--e)}
.pill.bad{border-color:var(--bad);color:var(--bad)}.pill.good{border-color:var(--good);color:var(--good)}
.dl{border-top:1px solid var(--line);padding:10px 14px 12px;font-size:13.5px;background:var(--soft)}
.act{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.strip{margin:2px 0 10px}.strip i{display:inline-block;width:15px;height:15px;border-radius:4px;margin:0 3px 3px 0;background:var(--line)}
.strip i.p{background:var(--good)}.strip i.l{background:var(--warn)}.strip i.a{background:var(--bad)}.strip i.x{background:var(--mut);opacity:.6}
.dl .ln{display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px dashed var(--line);flex-wrap:wrap}
.dl .ln:last-child{border:0}

.note{font-size:12.5px;color:var(--mut);margin-top:16px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.grid2 label{font-size:13px;color:var(--mut);display:grid;gap:3px}
#toast{position:fixed;left:50%;bottom:calc(20px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);background:var(--ink);color:var(--bg);padding:10px 16px;border-radius:12px;font-size:14px;box-shadow:0 8px 30px rgba(0,0,0,.25);z-index:9;max-width:90vw}


/* subscription countdown */
.tpill{display:flex;gap:9px;align-items:center;border:1px solid color-mix(in srgb,var(--onacc) 40%,transparent);background:color-mix(in srgb,var(--onacc) 14%,transparent);border-radius:14px;padding:5px 13px 5px 7px;color:var(--onacc);transition:background .3s,color .3s}
.tpill svg{width:34px;height:34px;flex:none}.tpill circle{fill:none;stroke-width:3.5}.tpill .bgc{stroke:color-mix(in srgb,currentColor 25%,transparent)}.tpill .fgc{stroke:currentColor;stroke-linecap:round;transition:stroke-dashoffset .9s linear}
.tpill small{display:block;font-size:11px;opacity:.85;line-height:1.1}.tpill b{font:600 15px/1.2 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.tpill.warn{background:#F0B429;color:#1b1300;border-color:#F0B429}
.tpill.crit{background:#B3261E;color:#fff;border-color:#FF8A80;animation:tp 1.2s ease-in-out infinite}
@keyframes tp{50%{box-shadow:0 0 0 6px rgba(255,138,128,.35)}}

/* overlays: AI analysis and subscription ended */
.ov{position:fixed;inset:0;z-index:60;display:grid;place-items:center;padding:16px;background:rgba(5,9,18,.74);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);animation:fade .25s both}
@keyframes fade{from{opacity:0}}
.botc{width:min(480px,100%);border-radius:26px;padding:22px;color:#E8EFFC;background:linear-gradient(160deg,#111B33,#0A1020);border:1px solid rgba(124,244,255,.3);box-shadow:0 30px 90px rgba(0,0,0,.6),0 0 70px color-mix(in srgb,var(--acc) 28%,transparent);position:relative;overflow:hidden}
.botc:before{content:"";position:absolute;left:0;right:0;top:0;height:2px;background:linear-gradient(90deg,transparent,#7CF4FF,transparent)}
.bt{display:flex;gap:16px;align-items:center}
.bot{width:116px;flex:none;animation:bob 3s ease-in-out infinite}
@keyframes bob{50%{transform:translateY(-6px)}}
.bot svg{width:100%;display:block;overflow:visible}
.bot .ant{animation:antp 1s ease-in-out infinite;transform-box:fill-box;transform-origin:center}
@keyframes antp{50%{transform:scale(1.6);opacity:.6}}
.bot .eyes{animation:look 3.2s ease-in-out infinite}
.bot .eyes ellipse{animation:blink2 4s infinite;transform-box:fill-box;transform-origin:center}
@keyframes look{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}
@keyframes blink2{0%,92%,100%{transform:scaleY(1)}95%{transform:scaleY(.1)}}
.bot .hap,.bot .smile{display:none}.happy .bot .eyes,.happy .bot .flat{display:none}.happy .bot .hap,.happy .bot .smile{display:block}
.bad .bot .eyes ellipse{fill:#FF8A80}
.doc{flex:1;position:relative;height:122px;border-radius:14px;border:1px solid rgba(124,244,255,.25);background:rgba(255,255,255,.04);overflow:hidden;padding:12px 12px;display:grid;align-content:start;gap:8px}
.doc i{display:block;height:7px;border-radius:4px;background:rgba(255,255,255,.14)}
.doc i:nth-child(2n){width:78%}.doc i:nth-child(3n){width:55%}.doc i:nth-child(5n){width:88%}
.doc .beam{position:absolute;left:0;right:0;top:0;height:34px;background:linear-gradient(transparent,rgba(124,244,255,.42),transparent);border-bottom:1px solid rgba(124,244,255,.8);animation:beam 1.5s ease-in-out infinite alternate}
@keyframes beam{from{transform:translateY(-34px)}to{transform:translateY(122px)}}
.happy .doc .beam,.bad .doc .beam{animation:none;opacity:0}
.botc h3{margin:18px 0 10px;font-size:19px;letter-spacing:-.01em}
.botc h3 .dots span{animation:dt 1.2s infinite;opacity:.2}.botc h3 .dots span:nth-child(2){animation-delay:.2s}.botc h3 .dots span:nth-child(3){animation-delay:.4s}
@keyframes dt{40%{opacity:1}}
.happy h3 .dots,.bad h3 .dots{display:none}
.steps{list-style:none;margin:0;padding:0;display:grid;gap:8px;min-height:60px}
.steps li{display:flex;gap:10px;align-items:flex-start;font-size:14px;color:#9FB0CC;animation:stepin .35s both}
@keyframes stepin{from{opacity:0;transform:translateX(-10px)}}
.steps .sp{flex:none;width:16px;height:16px;margin-top:2px;border-radius:50%;border:2px solid rgba(124,244,255,.8);border-right-color:transparent;animation:spin2 .8s linear infinite}
@keyframes spin2{to{transform:rotate(360deg)}}
.steps li.ok{color:#E8EFFC}.steps li.ok .sp{animation:none;border-color:#3DDC97;background:#3DDC97 center/10px no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 6.2l2.4 2.4 4.6-5' fill='none' stroke='%2304120c' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")}
.steps li.bad{color:#FFB4AB}.steps li.bad .sp{animation:none;border-color:#FF8A80;background:#FF8A80}
.pbar{height:6px;border-radius:9px;background:rgba(255,255,255,.1);margin-top:14px;overflow:hidden}
.pbar i{display:block;height:100%;width:0;border-radius:9px;background:linear-gradient(90deg,var(--acc),#7CF4FF);transition:width .6s cubic-bezier(.2,.8,.2,1);box-shadow:0 0 14px #7CF4FF}
.botc .row2{display:flex;justify-content:flex-end;margin-top:12px}
.botc button{font:inherit;font-size:13px;color:#B7C4DD;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:6px 12px;cursor:pointer}
.endc{width:min(420px,100%);text-align:center;border-radius:24px;padding:28px;color:#E8EFFC;background:linear-gradient(160deg,#111B33,#0A1020);border:1px solid rgba(255,170,90,.4);box-shadow:0 30px 90px rgba(0,0,0,.6)}
.endc h3{margin:12px 0 6px;font-size:22px}.endc p{margin:0 0 16px;color:#9FB0CC}.endc a{display:inline-block;text-decoration:none;font-weight:600;border-radius:12px;padding:11px 20px;background:var(--acc);color:var(--onacc)}

/* animated reveal after a file is analysed */
.rv .flt{animation:up .5s both}
.rv .kpi{animation:up .65s cubic-bezier(.2,.8,.2,1) both,glow 1.5s ease-out both}
.rv .kpi:nth-child(1){animation-delay:.05s}.rv .kpi:nth-child(2){animation-delay:.12s}.rv .kpi:nth-child(3){animation-delay:.19s}.rv .kpi:nth-child(4){animation-delay:.26s}.rv .kpi:nth-child(5){animation-delay:.33s}.rv .kpi:nth-child(6){animation-delay:.4s}
.rv .ruler{animation:up .6s .4s both}
.rv .sc{animation:up .6s both}.rv .sc:nth-child(1){animation-delay:.5s}.rv .sc:nth-child(2){animation-delay:.6s}.rv .sc:nth-child(3){animation-delay:.7s}
.rv #chart rect[data-b]{transform-box:fill-box;transform-origin:bottom;animation:grow .7s cubic-bezier(.2,.8,.2,1) both;animation-delay:calc(.75s + var(--i)*24ms)}
.rv #att{animation:up .6s .9s both}
.rv .emp{animation:up .5s both;animation-delay:calc(1s + var(--i,0)*45ms)}
.rv .rgd{animation:ringdraw 1.3s .15s cubic-bezier(.2,.8,.2,1) both}
@keyframes up{from{opacity:0;transform:translateY(18px) scale(.98)}}
@keyframes grow{from{transform:scaleY(0)}}
@keyframes ringdraw{from{stroke-dashoffset:var(--C)}}
@keyframes glow{0%{box-shadow:0 0 0 0 color-mix(in srgb,var(--acc) 55%,transparent)}100%{box-shadow:var(--sh)}}

@keyframes hot{0%,100%{background-position:0% 50%;transform:translateY(0);box-shadow:0 4px 0 #4A0F1A,0 8px 20px rgba(214,70,60,.22),inset 0 1px 0 rgba(255,255,255,.18)}50%{background-position:100% 50%;transform:translateY(-2px);box-shadow:0 6px 0 #4A0F1A,0 12px 28px rgba(232,110,70,.42),inset 0 1px 0 rgba(255,255,255,.28)}}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(232,110,70,.6)}100%{box-shadow:0 0 0 9px rgba(232,110,70,0)}}
.kpi.bad,.sc b.bad,.att b.bad{background:linear-gradient(120deg,#8E2438,#C2412D 50%,#D9772B,#C2412D 80%,#8E2438);background-size:250% 250%;color:#fff;border:0;animation:hot 2.2s ease-in-out infinite}
.kpi.bad{border-radius:16px}.kpi.bad b,.kpi.bad span{color:#fff;text-shadow:0 1px 2px rgba(40,0,10,.5)}.kpi.bad span{opacity:.9}
.att b.bad{display:inline-block;padding:3px 12px;border-radius:999px;font-size:14px!important;text-shadow:0 1px 2px rgba(40,0,10,.5)}
.sc b.bad{display:block;width:fit-content;min-width:34px;text-align:center;padding:2px 12px;border-radius:999px;font-size:16px!important;margin-bottom:3px;text-shadow:0 1px 2px rgba(40,0,10,.5)}
.setbar input,.setbar select{width:100%;box-sizing:border-box;min-width:0}
.att.al h3{color:#F0907A;display:flex;align-items:center;gap:8px}
.att.al h3:before{content:"";width:9px;height:9px;border-radius:50%;background:#E8654A;animation:ping 1.1s ease-out infinite}
.att button{padding:9px 4px}
.kpis{perspective:900px}
.setbar{margin-top:12px;border-radius:16px;background:var(--card);border:1px solid var(--line);box-shadow:var(--sh),0 3px 0 rgba(0,0,0,.35)}
.setbar>summary{cursor:pointer;font-weight:600;padding:12px 16px;list-style:none;display:flex;align-items:center;gap:10px}
.setbar>summary::-webkit-details-marker{display:none}
.setbar>summary:before{content:"\2699";font-size:18px;color:var(--m)}
.setbar>summary:after{content:"Edit";margin-left:auto;font-size:12px;font-weight:600;padding:4px 12px;border-radius:999px;background:var(--soft);border:1px solid var(--line);color:var(--mut)}
.setbar[open]>summary:after{content:"Close"}
.setbar .in{padding:0 16px 14px}
.top{position:fixed;left:0;right:0;top:0;z-index:45;transition:transform .38s cubic-bezier(.2,.8,.2,1),box-shadow .3s}
#topsp{transition:height .38s cubic-bezier(.2,.8,.2,1)}
.top.hid{transform:translateY(-101%)}.top.pk{box-shadow:0 18px 40px rgba(0,0,0,.5)}
#topgrab{position:fixed;top:0;left:50%;transform:translate(-50%,-100%);z-index:44;border:0;cursor:pointer;font:inherit;font-size:12px;font-weight:600;letter-spacing:.03em;color:#fff;padding:4px 16px 5px;border-radius:0 0 14px 14px;background:linear-gradient(135deg,var(--acc),color-mix(in srgb,var(--acc) 60%,#000));box-shadow:0 4px 14px rgba(0,0,0,.45);opacity:0;pointer-events:none;transition:transform .3s,opacity .3s}
body.hidtop #topgrab{transform:translate(-50%,0);opacity:.95;pointer-events:auto;animation:nudge 2.4s ease-in-out 1s 3}
#topgrab:hover{opacity:1}
@keyframes nudge{0%,100%{padding-bottom:5px}50%{padding-bottom:10px}}
@media(prefers-reduced-motion:reduce){.top,#topsp,#topgrab{transition:none!important;animation:none!important}}
.kpi{position:relative}
#app:not(.rv) .kpi:not(.bad),#app:not(.rv) .sc{transition:transform .18s ease-out,box-shadow .25s,border-color .25s;will-change:transform}
#app:not(.rv) .kpi.tl,#app:not(.rv) .sc.tl{transform:perspective(700px) rotateX(var(--rx,0deg)) rotateY(var(--ry,0deg)) translateY(-5px) scale(1.025);box-shadow:0 18px 36px rgba(0,0,0,.45),0 0 0 1px rgba(124,170,255,.35),0 0 28px rgba(80,120,255,.2);z-index:2}
.kpi.tl:after,.sc.tl:after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;background:radial-gradient(260px circle at var(--gx,50%) var(--gy,50%),rgba(255,255,255,.16),transparent 60%)}

/* ---- v12: now card, alerts, drawer, idle, toast ---- */
.nowc{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-top:12px}
.nowc .nt{display:flex;align-items:center;gap:12px}.nowc .nt i{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;font-style:normal;font-size:18px;background:linear-gradient(135deg,#22D3EE,#4F5BFF);color:#fff;box-shadow:0 0 22px rgba(34,211,238,.4)}
.nowc .nt b{display:block;font-size:15px}.nowc .nt small{color:var(--mut);font-size:12.5px}
.nowc .av2{display:flex;margin-left:auto}.nowc .av2 .av{width:34px;height:34px;font-size:13px;margin-left:-8px;border:2px solid #0b1224}.nowc .av2 .mo{width:34px;height:34px;margin-left:-8px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;background:#26334A;border:2px solid #0b1224}
.nowc .live{display:inline-flex;gap:6px;align-items:center;font-size:12px;font-weight:700;color:#58E2B0}.nowc .live:before{content:"";width:8px;height:8px;border-radius:50%;background:#4FD1A1;animation:ping 1.6s ease-out infinite}
#rail .bell{position:relative;background:none;border:0;font:inherit;width:100%;color:#aab8d6}#rail .bell b{position:absolute;left:30px;top:3px;min-width:18px;height:18px;padding:0 5px;border-radius:99px;background:linear-gradient(120deg,#E8483A,#F08A2B);color:#fff;font-size:11px;display:grid;place-items:center;animation:hot 2.2s ease-in-out infinite}
#rail:hover~#notif{left:236px}
#notif{transition:left .28s;position:fixed;z-index:60;left:84px;bottom:90px;width:min(340px,calc(100vw - 100px));max-height:70vh;overflow:auto;border-radius:18px;background:linear-gradient(160deg,rgba(24,36,66,.97),rgba(9,14,30,.98));border:1px solid rgba(255,255,255,.14);box-shadow:0 24px 60px rgba(0,0,0,.6);animation:pop .3s cubic-bezier(.2,.9,.3,1.2) both}
#notif .nh{display:flex;justify-content:space-between;align-items:center;padding:14px 16px 8px;color:#fff}#notif .nh button{background:none;border:0;color:#9fb0cc;font-size:22px;cursor:pointer}
#notl .al{display:flex;gap:11px;padding:11px 16px;border-top:1px solid rgba(255,255,255,.07);font-size:13.5px;color:#C5D1E6;cursor:pointer;width:100%;text-align:left;background:none;border-left:0;border-right:0;border-bottom:0;font-family:inherit}#notl .al:hover{background:rgba(255,255,255,.05)}
#notl .al i{flex:none;width:30px;height:30px;border-radius:9px;display:grid;place-items:center;font-style:normal;font-size:14px}#notl .al.r i{background:rgba(255,100,80,.2)}#notl .al.o i{background:rgba(240,180,41,.2)}#notl .al.b i{background:rgba(79,91,255,.25)}
#notl .al b{display:block;color:#fff}#notl .none{padding:18px 16px 22px;color:#8ea0c4;font-size:13.5px}
#dscrim{position:fixed;inset:0;z-index:70;background:rgba(2,5,12,.6);backdrop-filter:blur(3px);animation:wf .25s both}
#drawer{position:fixed;z-index:71;top:0;right:0;bottom:0;width:min(480px,100vw);overflow:auto;background:linear-gradient(180deg,#0f1a33,#080d1c);border-left:1px solid rgba(255,255,255,.12);box-shadow:-30px 0 80px rgba(0,0,0,.6);animation:slin .38s cubic-bezier(.2,.85,.25,1) both}
@keyframes slin{from{transform:translateX(100%)}}@keyframes wf{from{opacity:0}}
#drawer .dh{display:flex;gap:14px;align-items:center;padding:22px 22px 14px;position:relative}#drawer .dh .av{width:56px;height:56px;font-size:22px}#drawer .dh h3{margin:0;font-size:20px;color:#fff}#drawer .dh small{color:#9db0d3}
#drawer .dx{position:absolute;right:14px;top:14px;width:34px;height:34px;border-radius:50%;border:0;background:rgba(255,255,255,.08);color:#fff;font-size:20px;cursor:pointer}#drawer .dx:hover{background:rgba(255,255,255,.16)}
#drawer .sec{padding:6px 22px 14px}#drawer h4{margin:6px 0 8px;font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:#8ea0c4}
.dsc{display:flex;align-items:center;gap:16px;padding:14px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09)}
.dsc .big{position:relative;width:84px;height:84px;flex:none}.dsc .big svg{width:84px;height:84px;transform:rotate(-90deg)}.dsc .big circle{fill:none;stroke-width:7}.dsc .big .t{stroke:rgba(255,255,255,.1)}.dsc .big .v{stroke-linecap:round;stroke-dasharray:var(--C);stroke-dashoffset:var(--C);animation:rg2 1.1s .2s cubic-bezier(.2,.8,.2,1) forwards}@keyframes rg2{to{stroke-dashoffset:var(--o)}}
.dsc .big b{position:absolute;inset:0;display:grid;place-items:center;font-size:19px;color:#fff}
.dsc .g3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px 14px;flex:1}.dsc .g3 div b{display:block;font-size:17px;color:#fff}.dsc .g3 div small{font-size:11px;color:#8ea0c4}.dsc .g3 .bad{color:#FF9C8F!important}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}.cal .wd{font-size:10.5px;text-align:center;color:#7d90b8}.cal .mo{grid-column:1/-1;font-size:12.5px;font-weight:700;color:#cfe0ff;margin:8px 0 2px}
.cal .c{aspect-ratio:1;border-radius:8px;display:grid;place-items:center;font-size:11.5px;font-weight:700;color:#fff;background:rgba(255,255,255,.05);opacity:0;animation:hpop .4s calc(var(--i)*14ms) forwards}
.cal .c.p{background:rgba(79,209,161,.55)}.cal .c.l{background:rgba(240,180,41,.65);color:#241a00}.cal .c.a{background:rgba(255,100,80,.7)}.cal .c.x{opacity:.45;animation:none;opacity:.5}.cal .c.e{visibility:hidden;animation:none}
.cal .c:hover{transform:scale(1.18);box-shadow:0 6px 16px rgba(0,0,0,.5)}
.dleg{display:flex;gap:12px;flex-wrap:wrap;font-size:11.5px;color:#9db0d3;margin-top:8px}.dleg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
.dtl .ln{display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.1);flex-wrap:wrap;font-size:13px;color:#c5d1e6}
.dact{display:flex;gap:8px;flex-wrap:wrap;padding:6px 22px 20px}
#idlev .idc{width:min(380px,100%);text-align:center;padding:28px;border-radius:24px;background:linear-gradient(160deg,#131E38,#0A1020);border:1px solid rgba(240,180,41,.5);box-shadow:0 30px 80px rgba(0,0,0,.6),0 0 50px rgba(240,180,41,.2);animation:pop .4s cubic-bezier(.2,.9,.3,1.2) both;color:#E8EFFC}
#idlev svg{width:64px;height:64px;fill:none;stroke:#F0B429;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 0 12px rgba(240,180,41,.6))}
#idlev h3{margin:10px 0 4px;font-size:22px}#idlev p{margin:0 0 14px;color:#9FB0CC}#idlev p b{color:#FFC36B;font-size:20px}.idb{height:6px;border-radius:99px;background:rgba(255,255,255,.1);overflow:hidden;margin-bottom:16px}#idBar{height:100%;background:linear-gradient(90deg,#F0B429,#E8483A);width:100%;transition:width 1s linear}
.idr{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
#toast .ta{margin-left:14px;font:inherit;font-weight:700;border:0;background:none;color:#7CF4FF;cursor:pointer;text-decoration:underline}
@media(prefers-reduced-motion:reduce){#drawer,#dscrim,#notif,#idlev .idc,.cal .c,.dsc .big .v{animation:none!important}.cal .c{opacity:1}.dsc .big .v{stroke-dashoffset:var(--o)}}
@media(max-width:760px){#notif{left:10px;bottom:70px}}

/* ---- filter panel: opens from the Filters button, hides itself 5 s after the last touch ---- */
.fbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0 10px}
#fbtn{display:inline-flex;align-items:center;gap:9px;font:inherit;font-weight:700;cursor:pointer;padding:10px 16px;border-radius:14px;color:#fff;border:1px solid rgba(124,244,255,.4);background:linear-gradient(120deg,rgba(34,211,238,.28),rgba(79,91,255,.35));box-shadow:0 4px 0 rgba(27,47,143,.8),0 10px 24px rgba(34,211,238,.18);transition:transform .12s,box-shadow .12s}
#fbtn:hover{transform:translateY(-1px)}#fbtn:active{transform:translateY(3px);box-shadow:0 1px 0 rgba(27,47,143,.8)}
#fbtn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}#fbtn b{font-size:12px;transition:transform .3s}#fbtn[aria-expanded=true] b{transform:rotate(180deg)}
.fsum{display:flex;gap:6px;flex-wrap:wrap}.fsum span{font-size:12.5px;padding:5px 11px;border-radius:99px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#C5D1E6}.fsum span.on{border-color:rgba(124,244,255,.5);color:#fff;background:rgba(34,211,238,.14)}
.flt{position:relative;overflow:hidden;max-height:700px;transition:max-height .45s cubic-bezier(.2,.8,.2,1),opacity .3s,margin .3s,padding .3s,visibility 0s}
.flt.fh{max-height:0;opacity:0;padding-top:0;padding-bottom:0;margin-top:0;margin-bottom:0;border-width:0;visibility:hidden;pointer-events:none;transition:max-height .4s cubic-bezier(.4,0,.2,1),opacity .25s,margin .3s,padding .3s,visibility 0s .4s}
#ftm{position:absolute;left:0;bottom:0;height:3px;width:100%;background:linear-gradient(90deg,#22D3EE,#4F5BFF,#A24BFF);transform-origin:left;transform:scaleX(1);pointer-events:none}
@media(prefers-reduced-motion:reduce){.flt,.flt.fh,#fbtn{transition:none!important}}
@media(prefers-reduced-motion:reduce){.kpi.bad,.sc b.bad,.att b.bad,.att.al h3:before{animation:none!important}}
.wel{width:min(460px,100%);text-align:left;border-radius:26px;padding:26px;color:#E8EFFC;background:linear-gradient(160deg,#131E38,#0A1020);border:1px solid rgba(124,244,255,.38);box-shadow:0 30px 90px rgba(0,0,0,.6),0 0 70px rgba(74,107,255,.28);animation:pop .45s cubic-bezier(.2,.9,.3,1.2) both}
@keyframes pop{from{opacity:0;transform:translateY(18px) scale(.94)}to{opacity:1;transform:none}}
.wel .tag{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:999px;background:linear-gradient(120deg,#22D3EE,#4A6BFF);color:#06101F}
.wel h3{margin:12px 0 4px;font-size:24px;letter-spacing:-.01em}.wel>p{margin:0 0 14px;color:#9FB0CC;font-size:14px}
.wel ul{list-style:none;margin:0 0 18px;padding:0;display:grid;gap:8px}
.wel li{display:flex;gap:12px;align-items:flex-start;padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);font-size:13.5px;color:#C5D1E6;opacity:0;animation:pop .45s ease forwards}
.wel li b{display:block;color:#fff;font-size:14px;margin-bottom:1px}.wel li i{flex:none;width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-style:normal;font-weight:700;font-size:13px;color:#fff;background:linear-gradient(135deg,#22D3EE,#4A6BFF)}
.wel button{width:100%;font:inherit;font-weight:700;border:0;cursor:pointer;border-radius:14px;padding:13px;color:#fff;background:linear-gradient(120deg,#22D3EE,#4A6BFF 70%,#7C5CFF);box-shadow:0 5px 0 #1B2F8F,0 12px 26px rgba(74,107,255,.4);transition:transform .1s,box-shadow .1s}
.wel button:active{transform:translateY(4px);box-shadow:0 1px 0 #1B2F8F}
@media(prefers-reduced-motion:reduce){.wel,.wel li{animation:none!important;opacity:1}}
@media(prefers-reduced-motion:reduce){.bot,.bot *,.doc .beam,.botc h3 .dots span,.tpill.crit{animation:none!important}.rv *{animation:none!important}}

@media(max-width:860px){.two{grid-template-columns:1fr}}
@media(max-width:640px){
 .er{grid-template-columns:repeat(3,1fr);row-gap:10px}.er .nmw{grid-column:1/-1}
 .tools{width:100%}.who{display:none}
}
@media print{
 body{background:#fff}.top{position:static!important;transform:none!important}#topsp,#topgrab{display:none!important}.top .tools,.noprint,.flt,.chips,.act,#toast,.seg{display:none!important}
 .top{background:var(--acc)!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .card,.kpi,.emp{box-shadow:none;break-inside:avoid}
}
/* ===== Aurora Glass skin ===== */
body{background:#060A14;overflow-x:hidden;padding-left:72px}
body:before,body:after{content:"";position:fixed;z-index:-1;border-radius:50%;filter:blur(90px);opacity:.55;pointer-events:none}
body:before{width:620px;height:620px;left:-120px;top:-140px;background:radial-gradient(circle,#4F5BFF,transparent 65%);animation:drift1 26s ease-in-out infinite alternate}
body:after{width:700px;height:700px;right:-160px;top:20vh;background:radial-gradient(circle,#18C8E8,transparent 65%);animation:drift2 32s ease-in-out infinite alternate}
#aur3{position:fixed;z-index:-1;width:560px;height:560px;left:35vw;bottom:-220px;border-radius:50%;filter:blur(100px);opacity:.42;background:radial-gradient(circle,#A24BFF,transparent 65%);animation:drift3 38s ease-in-out infinite alternate;pointer-events:none}
@keyframes drift1{to{transform:translate(260px,180px) scale(1.15)}}@keyframes drift2{to{transform:translate(-300px,-120px) scale(.9)}}@keyframes drift3{to{transform:translate(-340px,-120px) scale(1.2)}}
.top,#topsp,#topgrab{display:none!important}
.card,.kpi:not(.bad),.sc,.emp,.setbar,.att,.drop{background:linear-gradient(160deg,rgba(255,255,255,.085),rgba(255,255,255,.035))!important;backdrop-filter:blur(16px) saturate(1.35);-webkit-backdrop-filter:blur(16px) saturate(1.35);border:1px solid rgba(255,255,255,.1)!important;box-shadow:0 10px 34px rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.1)!important}
.sc{border-top-width:3px!important}
h2{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#9fb4dc;display:flex;align-items:center;gap:10px;margin:26px 0 10px;scroll-margin-top:16px}
h2:after{content:"";flex:1;height:1px;background:linear-gradient(90deg,rgba(150,180,255,.35),transparent)}
.kpi b,.sc>div b{font-weight:700}
.kpi:not(.bad) b{background:linear-gradient(180deg,#fff,#b9c8ee);-webkit-background-clip:text;background-clip:text;color:transparent}
/* rail */
#rail{position:fixed;z-index:50;left:0;top:0;bottom:0;width:72px;padding:14px 10px;display:flex;flex-direction:column;gap:4px;background:linear-gradient(180deg,rgba(14,22,42,.82),rgba(8,12,26,.88));backdrop-filter:blur(18px);border-right:1px solid rgba(255,255,255,.08);transition:width .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s;overflow:hidden}
#rail:hover,#rail:focus-within{width:224px;box-shadow:18px 0 50px rgba(0,0,0,.5)}
#rail a,#rail .rbtn{display:flex;align-items:center;gap:14px;height:44px;padding:0 12px;border-radius:12px;color:#aab8d6;text-decoration:none;cursor:pointer;white-space:nowrap;transition:background .2s,color .2s,transform .15s}
#rail a:hover,#rail .rbtn:hover{background:linear-gradient(120deg,rgba(79,91,255,.35),rgba(24,200,232,.18));color:#fff;transform:translateX(3px)}
#rail a.on{background:linear-gradient(120deg,rgba(79,91,255,.5),rgba(24,200,232,.25));color:#fff;box-shadow:inset 3px 0 0 #22D3EE}
#rail svg{flex:none;width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
#rail .rl{opacity:0;transition:opacity .2s}#rail:hover .rl,#rail:focus-within .rl{opacity:1;transition-delay:.08s}
#rail .rb{display:flex;align-items:center;gap:12px;padding:2px 3px 14px;color:#fff;font-weight:700;white-space:nowrap}
#rail .rt{flex:none;width:42px;height:42px;border-radius:13px;background:#fff;display:grid;place-items:center;color:#2342d6;overflow:hidden;box-shadow:0 0 22px rgba(79,91,255,.55)}#rail .rt img{width:100%;height:100%;object-fit:contain}
#rail .rg{flex:1}.rbtn{background:linear-gradient(120deg,rgba(34,211,238,.18),rgba(79,91,255,.2));border:1px solid rgba(124,244,255,.25)}
#rail .ru{display:flex;align-items:center;gap:12px;padding:10px 4px 0;border-top:1px solid rgba(255,255,255,.08);margin-top:6px;color:#fff;font-size:13px;font-weight:600;white-space:nowrap}
#rail .ru i{flex:none;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-style:normal;background:linear-gradient(135deg,#22D3EE,#7C5CFF);color:#06101F;font-weight:800}
#rail .ru small{display:block;color:#8ea0c4;font-weight:500;font-size:11px}
main{max-width:1180px}
.drop h2{display:block;text-transform:none;letter-spacing:0;font-size:22px;color:var(--ink);margin:12px 0 6px}.drop h2:after{display:none}
/* employee avatars + status */
.av{flex:none;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:15px;color:#fff;background:linear-gradient(135deg,hsl(var(--h) 80% 55%),hsl(calc(var(--h) + 40) 75% 42%));box-shadow:0 0 0 2px rgba(255,255,255,.12),0 6px 14px hsl(var(--h) 80% 40%/.4)}
.nmw{display:flex;gap:12px;align-items:center;min-width:0}.nmw .nm{min-width:0}
.stb{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;margin-left:6px;vertical-align:1px}
.stb.ok{color:#58E2B0;background:rgba(79,209,161,.14);box-shadow:inset 0 0 0 1px rgba(79,209,161,.35)}
.stb.lt{color:#FFC36B;background:rgba(240,180,41,.13);box-shadow:inset 0 0 0 1px rgba(240,180,41,.35)}
.stb.ab{color:#FF9C8F;background:rgba(255,100,80,.14);box-shadow:inset 0 0 0 1px rgba(255,100,80,.4)}
.emp{transition:transform .2s,box-shadow .25s,border-color .25s}.emp:hover{transform:translateY(-3px) translateX(2px);border-color:rgba(124,244,255,.4)!important;box-shadow:0 16px 36px rgba(0,0,0,.45),0 0 26px rgba(34,211,238,.15)!important}
#list.ani .emp{animation:up .5s cubic-bezier(.2,.8,.2,1) both;animation-delay:calc(var(--i,0)*45ms)}
/* heatmap */
.heatc{overflow-x:auto}#heat{display:flex;gap:6px;align-items:flex-start;min-width:max-content}
.hd{display:grid;grid-template-rows:repeat(7,30px);gap:5px;font-size:10.5px;color:#7d90b8;padding-top:20px}.hd span{display:flex;align-items:center}
.hw{display:grid;grid-template-rows:16px repeat(7,30px);gap:5px}.hw small{font-size:10.5px;color:#7d90b8;text-align:center;line-height:16px;white-space:nowrap}
.hc{width:36px;height:30px;border-radius:8px;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:rgba(255,255,255,.9);cursor:default;background:color-mix(in srgb,var(--good) calc(var(--r)*100%),color-mix(in srgb,var(--warn) 55%,var(--bad)));opacity:0;animation:hpop .45s cubic-bezier(.2,.9,.3,1.3) forwards;animation-delay:calc(var(--i)*22ms);transition:transform .15s,box-shadow .2s}
.hc:hover{transform:scale(1.28);z-index:3;box-shadow:0 8px 22px rgba(0,0,0,.55),0 0 18px color-mix(in srgb,var(--good) calc(var(--r)*100%),var(--bad))}
.hc.x{background:rgba(255,255,255,.05);opacity:1;animation:none}.hc.e{visibility:hidden;animation:none}
@keyframes hpop{from{opacity:0;transform:scale(.4)}to{opacity:1}}
.heatleg{margin-top:12px;align-items:center}.heatleg i{width:26px;height:10px;border-radius:5px}
#rail .tpill{margin:0 0 6px;padding:5px 6px;border-radius:14px;white-space:nowrap;color:#E8EFFC;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12)}#rail .tpill.warn{color:#1b1300}#rail .tpill.crit{color:#fff}
@media(max-width:760px){#rail .tpill{flex:none;margin:0}body{padding-left:0;padding-bottom:70px}#rail{top:auto;bottom:0;width:100%;height:60px;flex-direction:row;padding:6px 8px;border-right:0;border-top:1px solid rgba(255,255,255,.1)}#rail:hover{width:100%}#rail .rb,#rail .ru,#rail .rg,#rail .rl{display:none}#rail a,#rail .rbtn{flex:1;justify-content:center;padding:0}}
@media(prefers-reduced-motion:reduce){body:before,body:after,#aur3,.hc,#list.ani .emp{animation:none!important}.hc{opacity:1}#rail,#rail *{transition:none!important}}
</style>
</head>
<body>
<header class="top"><div class="in">
 <div class="tile"><?php if ($B['logo']): ?><img src="<?= h($B['logo']) ?>" alt="<?= h($B['name']) ?> logo"><?php else: ?><b><?= h($ini) ?></b><?php endif; ?></div>
 <div class="brand"><h1><?= h($B['name']) ?></h1><div class="sub" id="sub">Attendance dashboard</div></div>
 <div class="who"><?= h($me['name']) ?><small><?= $admin ? 'Administrator' : ($role === 'customer' ? 'Customer' : 'Viewer') ?><?= $me['dept'] !== '' && $role === 'viewer' ? ' | ' . h($me['dept']) : '' ?></small></div>
 <div class="tools">
  <button class="hb" id="tophide" type="button" title="Hide this bar">Hide &#9652;</button>
  <label class="hb fill" for="f" tabindex="0" id="openlbl">Open attendance file</label><input type="file" id="f" accept=".xlsx,.xls,.csv" hidden>
  <?php if ($admin): ?><a class="hb" href="admin.php">Admin</a><?php endif; ?>
  <a class="hb" href="logout.php">Log out</a>
 </div>
</div></header>
<div id="topsp" aria-hidden="true"></div><button id="topgrab" type="button" aria-label="Show the top menu">Show menu &#9662;</button>

<div id="aur3"></div>
<nav id="rail" aria-label="Main menu">
 <div class="rb"><div class="rt"><?php if ($B['logo']): ?><img src="<?= h($B['logo']) ?>" alt=""><?php else: ?><b><?= h($ini) ?></b><?php endif; ?></div><span class="rl"><?= h($B['name']) ?></span></div>
 <a href="#s-shifts"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span class="rl">Shifts</span></a>
 <a href="#s-trend"><svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M21 19H3"/></svg><span class="rl">Trends</span></a>
 <a href="#s-heat"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span class="rl">Heatmap</span></a>
 <a href="#s-rep"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/></svg><span class="rl">Reports</span></a>
 <a href="#s-emp"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-4 3.2-6 6.5-6s5.9 2 6.5 6M16 5a3.5 3.5 0 010 6.5M18.5 14.5c1.7.8 2.7 2.7 3 5.5"/></svg><span class="rl">Employees</span></a>
 <span class="rg"></span>
 <button type="button" class="rbtn bell" id="bell" aria-haspopup="true" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M6 17V11a6 6 0 0112 0v6l2 2H4zM10 21a2 2 0 004 0"/></svg><span class="rl">Alerts</span><b id="bellN" hidden>0</b></button>
  <label for="f" class="rbtn" tabindex="0"><svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg><span class="rl">Open file</span></label>
 <?php if ($admin): ?><a href="admin.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 00-2-1.2L14.2 3h-4l-.4 2.7a7 7 0 00-2 1.2l-2.3-1-2 3.4 2 1.5a7 7 0 000 2.4l-2 1.5 2 3.4 2.3-1a7 7 0 002 1.2l.4 2.7h4l.4-2.7a7 7 0 002-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/></svg><span class="rl">Admin</span></a><?php endif; ?>
 <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/></svg><span class="rl">Log out</span></a>
 <?php if ($role !== 'admin'): ?><div class="tpill" id="tpill" hidden title="Subscription"><svg viewBox="0 0 34 34" aria-hidden="true"><circle class="bgc" cx="17" cy="17" r="14"/><circle class="fgc" id="tring" cx="17" cy="17" r="14" transform="rotate(-90 17 17)" stroke-dasharray="87.96" stroke-dashoffset="0"/></svg><div class="rl"><small>Subscription ends in</small><b id="tcd">--</b></div></div><?php endif; ?>
 <div class="ru"><i><?= h(mb_substr($me['name'],0,1) ?: '?') ?></i><span class="rl"><?= h($me['name']) ?><small><?= $admin ? 'Administrator' : ($role === 'customer' ? 'Customer' : 'Viewer') ?></small><?= !empty($me['prev']) ? '<small>Last sign-in ' . h(date('j M, H:i', strtotime($me['prev']))) . '</small>' : '' ?></span></div>
</nav>
<main>
 <div class="banner" id="banner" hidden>
  <div class="grow" id="bantxt"></div>
  <?php if ($canSave): ?><label class="sub2" style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="upm" checked> Keep older records</label><button class="btn pri" id="upsave" type="button">Save for everyone</button><?php endif; ?>
  <button class="btn" id="upback" type="button"><?= $role === 'customer' ? 'Close file' : 'Back to saved data' ?></button>
 </div>

 <section class="drop" id="empty" hidden>
  <div class="ic"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--acc)"><path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></div>
  <h2 id="emptyh">Upload your attendance file</h2>
  <p id="emptyp"><?= $role === 'customer' ? 'Drop your First &amp; Last attendance export here (Excel or CSV). Your file is processed in your browser and is not stored on our server.' : 'No saved data yet. Drop an attendance export here (Excel or CSV) to view it.' ?></p>
  <label class="btn pri" for="f" tabindex="0">Choose a file</label>
  <div class="sub2" id="emptyerr" style="margin-top:12px;color:var(--bad)"></div>
 </section>

 <div id="app" hidden>
  <div class="fbar noprint"><button type="button" id="fbtn" aria-expanded="false" aria-controls="fpanel"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg><span>Filters</span><b>&#9662;</b></button><div id="fsum" class="fsum" aria-live="polite"></div></div>
  <div class="card flt fh noprint" id="fpanel"><i id="ftm" aria-hidden="true"></i>
   <div class="row"><div class="seg" id="segShift"></div><select id="dept" aria-label="Department"></select>
    <label>From <input type="date" id="dfrom"></label><label>To <input type="date" id="dto"></label>
    <input type="search" id="q" placeholder="Search name or ID" style="flex:1;min-width:160px"></div>
   <div class="row chips" id="range"></div>
  </div>

  <h2 id="s-shifts" style="margin-top:18px">Shifts</h2>
  <div class="card ruler"><svg id="ruler" viewBox="0 0 720 100" role="img" aria-label="Shift timings on a 24 hour ruler"></svg></div>
  <div class="shifts" id="cards"></div>
  <div class="card nowc" id="now" hidden></div>
  <details class="setbar noprint"><summary>Shift settings<?= $role === 'customer' ? ' (saved in this browser)' : ' (this page only)' ?></summary>
   <div class="in"><div class="grid2" style="margin-top:4px">
    <label>Morning starts<input type="time" id="ms"></label><label>Morning ends<input type="time" id="me"></label>
    <label>Evening starts<input type="time" id="es"></label><label>Evening ends (next day)<input type="time" id="ee"></label>
    <label>Grace for late / early (min)<input type="number" id="gr" min="0"></label>
    <label>Overtime after (min)<input type="number" id="ot" min="0"></label>
    <label>Match punch within (min)<input type="number" id="tl" min="30"></label>
    <label>Weekly off day<select id="wo"><option value="-1">None</option><option value="0">Sunday</option><option value="5">Friday</option><option value="6">Saturday</option></select></label>
   </div>
   <div class="row" style="margin-top:10px"><button class="btn" id="rst" type="button">Reset to default</button><?php if($role !== 'viewer'): ?><button class="btn" id="clrasg" type="button">Clear all fixed shifts</button><?php endif; ?></div>
  </div></details>

  <div class="kpis" style="margin-top:18px" id="kpis"></div>

  <h2 id="s-trend">Trend and attention</h2>
  <div class="two">
   <div class="card"><div id="chart"></div>
    <div class="leg"><span><i style="background:var(--m)"></i>Morning</span><span><i style="background:var(--e)"></i>Evening</span><span><i style="background:var(--o)"></i>Other</span><span><i style="background:var(--bad);opacity:.55"></i>Absent</span><span class="noprint">Tap a bar to open that day</span></div></div>
   <div class="card" id="att"></div>
  </div>

  <h2 id="s-heat">Attendance heatmap</h2>
  <div class="card heatc"><div id="heat"></div><div class="leg heatleg"><span>Lower</span><i style="background:var(--bad)"></i><i style="background:var(--warn)"></i><i style="background:var(--good)"></i><span>Higher attendance</span></div></div>

  <h2 id="s-rep">Reports</h2>
  <div class="card row noprint">
   <button class="btn pri" id="bpdf" type="button">Download summary PDF</button>
   <button class="btn" id="bxls" type="button">Download Excel</button>
   <button class="btn" id="bprint" type="button">Print</button>
   <span class="sub2">Reports follow the filters above. Open an employee below for a personal report.</span>
  </div>

  <h2 id="s-emp">Employees</h2>
  <div class="row chips noprint" id="sorts"></div>
  <div class="card row noprint" id="bulk" hidden style="margin-top:8px"><span class="sub2" id="bulkt"></span><button class="btn" data-bulk="m" type="button">Set to Morning</button><button class="btn" data-bulk="e" type="button">Set to Evening</button><button class="btn" data-bulk="" type="button">Auto-detect</button></div>
  <div id="list" style="margin-top:8px"></div>

  <p class="note">Hours come from each day's first and last punch only. An evening shift is matched across midnight (in at about 3 PM, out after midnight). A punch far from both shift starts, such as a 6 AM arrival, is listed under Other. If auto-detect gets someone wrong, open the employee and choose Morning or Evening under Shift (or use the buttons above the list to set everyone shown). A shift with only one punch counts as Missing, and a working day with no punch at all counts as Absent (from the employee's first punch in the file; the weekly off day and an unfinished last day are skipped).</p>
 </div>
</main>
<aside id="notif" hidden aria-label="Alerts"><div class="nh"><b>Alerts</b><button type="button" id="notx" aria-label="Close">&times;</button></div><div id="notl"></div></aside>
<div id="dscrim" hidden></div>
<aside id="drawer" hidden role="dialog" aria-modal="true" aria-label="Employee details"><div id="dbody"></div></aside>
<div id="idlev" class="ov" hidden role="alertdialog" aria-modal="true" aria-labelledby="idT"><div class="idc"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="23"/><path d="M26 14v13l8 5"/></svg><h3 id="idT">Are you still there?</h3><p>For your security you will be signed out in <b id="idN">60</b> seconds.</p><div class="idb"><div id="idBar"></div></div><div class="idr"><button type="button" class="btn pri" id="idStay">Stay signed in</button><a class="btn" href="logout.php">Sign out now</a></div></div></div>
<div id="toast" role="status" hidden></div>
<div id="bot" class="ov" hidden role="dialog" aria-modal="true" aria-labelledby="botT"><div class="botc">
 <div class="bt">
  <div class="bot" aria-hidden="true"><svg viewBox="0 0 140 140"><line x1="70" y1="14" x2="70" y2="27" stroke="#7CF4FF" stroke-width="3" stroke-linecap="round"/><circle class="ant" cx="70" cy="10" r="5" fill="#7CF4FF"/>
   <rect x="11" y="52" width="11" height="26" rx="5.5" fill="#2A3A66"/><rect x="118" y="52" width="11" height="26" rx="5.5" fill="#2A3A66"/>
   <rect x="22" y="27" width="96" height="78" rx="26" fill="#18223A" stroke="#4A6BFF" stroke-width="2"/><rect x="34" y="42" width="72" height="44" rx="17" fill="#080D1A"/>
   <g class="eyes"><ellipse cx="54" cy="64" rx="8" ry="10" fill="#7CF4FF"/><ellipse cx="86" cy="64" rx="8" ry="10" fill="#7CF4FF"/></g>
   <path class="hap" d="M45 69q9-14 18 0M77 69q9-14 18 0" stroke="#7CF4FF" stroke-width="4.5" fill="none" stroke-linecap="round"/>
   <path class="flat" d="M60 96h20" stroke="#4A6BFF" stroke-width="3.5" stroke-linecap="round"/><path class="smile" d="M57 94q13 12 26 0" stroke="#4A6BFF" stroke-width="3.5" fill="none" stroke-linecap="round"/>
   <rect x="40" y="110" width="60" height="22" rx="11" fill="#18223A" stroke="#4A6BFF" stroke-width="2"/><circle cx="70" cy="121" r="4" fill="#3DDC97"/></svg></div>
  <div class="doc" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><div class="beam"></div></div>
 </div>
 <h3 id="botT">Analysing your file</h3>
 <ul class="steps" id="botS" aria-live="polite"></ul>
 <div class="pbar"><i id="botB"></i></div>
 <div class="row2"><button id="botSkip" type="button">Skip animation</button><button id="botX" type="button" hidden>Close</button></div>
</div></div>
<div id="welov" class="ov" hidden role="dialog" aria-modal="true" aria-labelledby="welT"><div class="wel">
 <span class="tag">Updated</span>
 <h3 id="welT">Welcome, <?= h($me['name']) ?></h3>
 <p>We have made a few improvements to your dashboard. Here is what is new:</p>
 <ul><?php foreach (welcome_items() as $i => $it): ?><li style="animation-delay:<?= .15 + $i * .12 ?>s"><i><?= $i + 1 ?></i><div><b><?= h($it[0]) ?></b><?= h($it[1]) ?></div></li><?php endforeach; ?></ul>
 <button type="button" id="welok">Got it, let's go</button></div></div>
<div id="endov" class="ov" hidden role="alertdialog" aria-modal="true" aria-labelledby="endT"><div class="endc">
 <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#FFB265" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12M6 21h12M7 3c0 5 4 6 5 9-1 3-5 4-5 9M17 3c0 5-4 6-5 9 1 3 5 4 5 9"/></svg>
 <h3 id="endT">Your demo subscription has ended</h3><p>Access is now locked. Contact your administrator to continue.</p><a href="expired.php">Continue</a></div></div>


<script src="assets/vendor/xlsx.full.min.js?v=1"></script>
<script src="assets/vendor/jspdf.umd.min.js?v=1"></script>
<script src="assets/vendor/jspdf.plugin.autotable.min.js?v=1"></script>
<script src="assets/parse.js?v=<?= $v('parse.js') ?>"></script>
<script>
const BR=<?= jx(['name' => $B['name'], 'tagline' => $B['tagline'], 'accent' => $B['accent'], 'on' => $B['on'], 'logo' => $B['logo']]) ?>;
const USER=<?= jx(['name' => $me['name'], 'user' => $me['username'], 'role' => $role, 'save' => $canSave, 'welcome' => !empty($me['wel'])]) ?>;
const IDLE=<?= IDLE ?>;
const SUB=<?= jx($sub) ?>,CSRF=<?= jx(csrf()) ?>,CFG=<?= jx(settings()) ?>,ASG0=<?= jx((object)$asg) ?>;
let RAW=<?= jx($d['rows']) ?>,SRC=<?= jx($d['meta']['file'] ?? '') ?>;
</script>
<script src="assets/app.js?v=<?= $v('app.js') ?>"></script>
</body>
</html>

<?php
const D = __DIR__ . '/data/';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) ini_set('session.cookie_secure', 1);
header('X-Frame-Options: SAMEORIGIN'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; worker-src 'self' blob:; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
if (!empty($_SERVER['HTTPS'])) header('Strict-Transport-Security: max-age=15552000');
session_name('shiftdash');
session_start();
function rj($f, $d) { $p = D . $f; return is_file($p) ? (json_decode(file_get_contents($p), true) ?? $d) : $d; }
function wj($f, $v) { if (!is_dir(D)) mkdir(D, 0755, true); return file_put_contents(D . $f, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false; }
function cfg() { return rj('config.json', null); }
function users() {
  $u = rj('users.json', null);
  if ($u === null && ($c = cfg()) && !empty($c['admin'])) {
    $u = [['username'=>'admin','name'=>'Admin','role'=>'admin','dept'=>'','hash'=>$c['admin'],'last'=>null]];
    wj('users.json', $u);
  }
  return $u;
}
function me() { $n = $_SESSION['u'] ?? null; if (!$n) return null; foreach (users() ?: [] as $u) if ($u['username'] === $n) return $u + ['dept'=>'','up'=>false,'exp'=>null,'dur'=>null,'startOn'=>null,'wel'=>false]; return null; }
/* Demo timer. exp = unix time the access ends. dur = length in seconds. startOn = 'login' means the clock starts at the user's next login. Admins never expire. */
function sub_state($u) {
  if (($u['role'] ?? '') === 'admin') return ['s' => 'none'];
  $exp = $u['exp'] ?? null; $dur = $u['dur'] ?? null; $now = time();
  if ($exp === null) return ($dur && ($u['startOn'] ?? '') === 'login') ? ['s' => 'pending', 'dur' => (int)$dur] : ['s' => 'none'];
  $exp = (int)$exp;
  return ['s' => $exp > $now ? 'active' : 'expired', 'exp' => $exp, 'dur' => $dur ? (int)$dur : null, 'left' => max(0, $exp - $now)];
}
/* ---- sign-in activity log, online list, idle timeout ---- */
const IDLE = 300; /* seconds of inactivity before sign-out (5 minutes) */
function client_ip() { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
function ua_info($ua) {
  $b = 'Browser'; foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $k => $v) if (strpos($ua, $k) !== false) { $b = $v; break; }
  $o = 'Unknown'; foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Macintosh' => 'Mac', 'Linux' => 'Linux'] as $k => $v) if (strpos($ua, $k) !== false) { $o = $v; break; }
  return $b . ' | ' . $o;
}
function geo($ip) {
  if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return ['cc' => '', 'c' => 'Local network', 'ci' => ''];
  $c = rj('geo.json', []); if (isset($c[$ip]) && $c[$ip]['t'] > time() - 30 * 86400) return $c[$ip];
  $r = ['cc' => '', 'c' => '', 'ci' => '', 't' => time()];
  $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
  $j = @json_decode((string)@file_get_contents('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code,city', false, $ctx), true);
  if (is_array($j) && !empty($j['success'])) $r = ['cc' => (string)($j['country_code'] ?? ''), 'c' => (string)($j['country'] ?? ''), 'ci' => (string)($j['city'] ?? ''), 't' => time()];
  if ($r['c'] !== '') { $c[$ip] = $r; if (count($c) > 500) $c = array_slice($c, -400, null, true); wj('geo.json', $c); }
  return $r;
}
function log_event($ev, $u, $note = '') {
  $ip = client_ip(); $g = geo($ip); $L = rj('logins.json', []); $new = 0;
  if ($ev === 'login' && $g['c'] !== '' && $g['c'] !== 'Local network') {
    $has = false; $same = false;
    foreach ($L as $x) if ($x['u'] === $u && $x['ev'] === 'login') { $has = true; if (($x['c'] ?? '') === $g['c']) { $same = true; break; } }
    $new = ($has && !$same) ? 1 : 0;
  }
  array_unshift($L, ['t' => time(), 'u' => (string)$u, 'ev' => $ev, 'ip' => $ip, 'cc' => $g['cc'], 'c' => $g['c'], 'ci' => $g['ci'], 'd' => ua_info($_SERVER['HTTP_USER_AGENT'] ?? ''), 'n' => $note, 'new' => $new]);
  $cut = time() - 90 * 86400; $L = array_values(array_filter($L, function ($x) use ($cut) { return $x['t'] >= $cut; }));
  wj('logins.json', array_slice($L, 0, 2000));
}
function touch_online($m) {
  if (time() - ($_SESSION['ot'] ?? 0) < 60) return;
  $_SESSION['ot'] = time(); $o = rj('online.json', []); $now = time();
  foreach ($o as $k => $v) if ($v['t'] < $now - 3600) unset($o[$k]);
  $g = geo(client_ip()); $o[$m['username']] = ['t' => $now, 'ip' => client_ip(), 'c' => $g['c'], 'ci' => $g['ci'], 'cc' => $g['cc'], 'd' => ua_info($_SERVER['HTTP_USER_AGENT'] ?? '')];
  wj('online.json', $o);
}
function idle_out() { $_SESSION = []; return; }
function role() { $m = me(); return $m ? $m['role'] : null; }
function need($r = null) {
  $m = me();
  if ($m && isset($_SESSION['la']) && time() - $_SESSION['la'] > IDLE + 20) { log_event('timeout', $m['username']); $_SESSION = []; session_destroy(); header('Location: login.php?idle=1'); exit; }
  if (!$m || ($r && $m['role'] !== $r)) { header('Location: login.php'); exit; }
  $_SESSION['la'] = time(); touch_online($m);
  if (sub_state($m)['s'] === 'expired') { header('Location: expired.php'); exit; }
  if (!defined('TFA_PAGE') && tfa_due($m)) { header('Location: tfa.php?must=1'); exit; }
  return $m;
}
function csrf() { return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(16))); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function jx($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }
function settings() { return rj('settings.json', ['m'=>['s'=>540,'e'=>1080],'e'=>['s'=>900,'e'=>1560],'n'=>['s'=>1200,'e'=>1920,'on'=>0],'grace'=>10,'ot'=>30,'tol'=>120,'off'=>0]); }
function brand() {
  $b = (array)rj('brand.json', []) + ['name'=>'Attendance Portal','tagline'=>'Shift attendance, simplified','accent'=>'#1F4FD8','contact'=>''];
  if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$b['accent'])) $b['accent'] = '#1F4FD8';
  $f = __DIR__ . '/assets/logo.png';
  $b['logo'] = is_file($f) ? 'assets/logo.png?v=' . filemtime($f) : null;
  $x = hexdec(substr($b['accent'], 1, 2)) * .299 + hexdec(substr($b['accent'], 3, 2)) * .587 + hexdec(substr($b['accent'], 5, 2)) * .114;
  $b['on'] = $x > 170 ? '#142033' : '#FFFFFF';
  return $b;
}
function brand_css($b) { return ':root{--acc:' . $b['accent'] . ';--onacc:' . $b['on'] . '}'; }


/* ---- two-step login (authenticator app, TOTP) ---- */
/* policy.json: on = master switch, must = mandatory, from = unix time when it becomes mandatory (first deploy: one week from now). */
function tfa_policy() {
  $p = rj('policy.json', null);
  if (!is_array($p) || !isset($p['from'])) { $p = ['on' => 1, 'must' => 1, 'from' => time() + 7 * 86400]; wj('policy.json', $p); }
  return $p + ['on' => 1, 'must' => 1];
}
function tfa_active() { return !empty(tfa_policy()['on']); }
function tfa_enrolled($u) { return !empty($u['tfa']['on']) && !empty($u['tfa']['s']); }
function tfa_due($u) { $p = tfa_policy(); return ($u['role'] ?? '') !== 'admin' && !empty($p['on']) && !empty($p['must']) && time() >= (int)$p['from'] && empty($u['tfa_x']) && !tfa_enrolled($u); }
function tfa_soon($u) { $p = tfa_policy(); return !empty($p['on']) && !empty($p['must']) && time() < (int)$p['from'] && empty($u['tfa_x']) && !tfa_enrolled($u); }
function b32enc($s) { $A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b = ''; foreach (str_split($s) as $c) $b .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($b, 5) as $q) $o .= $A[bindec(str_pad($q, 5, '0', STR_PAD_RIGHT))]; return $o; }
function b32dec($s) { $A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b = ''; foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $b .= str_pad(decbin(strpos($A, $c)), 5, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($b, 8) as $q) if (strlen($q) === 8) $o .= chr(bindec($q)); return $o; }
function totp_code($secret, $counter) {
  $h = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), b32dec($secret), true); $o = ord($h[19]) & 15;
  $v = ((ord($h[$o]) & 127) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
  return str_pad((string)($v % 1000000), 6, '0', STR_PAD_LEFT);
}
function tfa_new_secret() { return b32enc(random_bytes(20)); }
function tfa_recovery() { $p = []; $h = []; for ($i = 0; $i < 8; $i++) { $c = bin2hex(random_bytes(4)); $p[] = substr($c, 0, 4) . '-' . substr($c, 4); $h[] = hash('sha256', $c); } return [$p, $h]; }
/* Checks a 6-digit code (one step either side, each code usable once) or a recovery code. Updates $t (user['tfa']) by reference; the caller saves it. */
function tfa_check(&$t, $code) {
  $c = strtolower(preg_replace('/[\s-]/', '', (string)$code));
  if (preg_match('/^\d{6}$/', $c)) {
    $now = (int)floor(time() / 30);
    for ($d = -1; $d <= 1; $d++) { $k = $now + $d; if ($k > (int)($t['lc'] ?? 0) && hash_equals(totp_code($t['s'], $k), $c)) { $t['lc'] = $k; return true; } }
    return false;
  }
  if (preg_match('/^[0-9a-f]{8}$/', $c)) {
    $hh = hash('sha256', $c);
    foreach ($t['rc'] ?? [] as $i => $x) if (hash_equals($x, $hh)) { unset($t['rc'][$i]); $t['rc'] = array_values($t['rc']); return true; }
  }
  return false;
}
/* Small reminder shown on the dashboard and admin pages until two-step login is set up. */
function tfa_banner($m) {
  $late = ($m['role'] ?? '') === 'admin' && ($p = tfa_policy()) && !empty($p['on']) && !empty($p['must']) && time() >= (int)$p['from'] && !tfa_enrolled($m);
  if (!tfa_soon($m) && !$late) return '';
  $d = max(1, (int)ceil(((int)tfa_policy()['from'] - time()) / 86400));
  $txt = $late ? 'Your admin account does not have two-step login yet. Please set it up now.' : 'Two-step login becomes <b>mandatory in ' . $d . ' day' . ($d > 1 ? 's' : '') . '</b>. Set it up now in 2 minutes.';
  return '<div id="tfab" style="position:fixed;left:50%;bottom:max(14px,env(safe-area-inset-bottom,0px));transform:translateX(-50%);z-index:70;max-width:calc(100vw - 24px);display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:center;background:rgba(14,21,48,.95);color:#e8ecf8;border:1px solid rgba(91,140,255,.5);border-radius:14px;padding:10px 14px;font:14px/1.4 system-ui,sans-serif;box-shadow:0 14px 40px rgba(0,0,0,.5)"><span>' . $txt . '</span><a href="tfa.php" style="color:#fff;text-decoration:none;font-weight:600;background:linear-gradient(135deg,#5b8cff,#8a5bff);border-radius:10px;padding:7px 12px">Set up</a><button type="button" onclick="this.parentNode.remove()" aria-label="Close" style="background:none;border:0;color:#8f9bb8;font-size:20px;cursor:pointer;line-height:1">&times;</button></div>';
}
/* One-time welcome / what's new popup. Admin ticks 'Show welcome message' per user (admin panel); it shows once, then un-ticks itself. */
const WELCOME_V=8;
function welcome_items() { return [
  ['Employee side panel', 'Click any employee to open a panel with their attendance ring, a colourful month calendar and a day-by-day timeline.'],
  ['Alerts and live shift countdown', 'The bell in the side menu warns about long absences, late arrivals and missing punches. A card shows when the next shift starts.'],
  ['Filters on demand', 'Press the Filters button to open the filter panel. It tucks itself away again after 5 seconds.'],
  ['Safer sign-in', 'For your security you are signed out after 5 minutes of inactivity, with a warning before it happens. Sign-ins are logged.'],
  ['Quick search', 'Press Ctrl+K (or the search button in the side menu) to jump to any employee or section. Top on-time staff and department rates now sit under the summary cards.'],
  ['Two-step login', 'Protect your account with a 6 digit code from an authenticator app. Open Two-step login from the reminder bar to set it up in about 2 minutes.'],
  ['Undo for shifts', 'Changed a shift by mistake? Press Undo in the message that appears.'],
]; }

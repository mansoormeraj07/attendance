<?php
const D = __DIR__ . '/data/';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) ini_set('session.cookie_secure', 1);
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
  return $m;
}
function csrf() { return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(16))); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function jx($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }
function settings() { return rj('settings.json', ['m'=>['s'=>540,'e'=>1080],'e'=>['s'=>900,'e'=>1560],'grace'=>10,'ot'=>30,'tol'=>120,'off'=>0]); }
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

/* One-time welcome / what's new popup. Admin ticks 'Show welcome message' per user (admin panel); it shows once, then un-ticks itself. */
const WELCOME_V = 6;
function welcome_items() { return [
  ['Employee side panel', 'Click any employee to open a panel with their attendance ring, a colourful month calendar and a day-by-day timeline.'],
  ['Alerts and live shift countdown', 'The bell in the side menu warns about long absences, late arrivals and missing punches. A card shows when the next shift starts.'],
  ['Filters on demand', 'Press the Filters button to open the filter panel. It tucks itself away again after 5 seconds.'],
  ['Safer sign-in', 'For your security you are signed out after 5 minutes of inactivity, with a warning before it happens. Sign-ins are logged.'],
  ['Undo for shifts', 'Changed a shift by mistake? Press Undo in the message that appears.'],
]; }

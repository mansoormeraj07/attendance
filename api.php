<?php
require __DIR__ . '/lib.php';
header('Content-Type: application/json');
$m = me();
if (!$m) { http_response_code(403); echo '{"ok":0,"msg":"Please sign in again."}'; exit; }
if (sub_state($m)['s'] === 'expired') { http_response_code(403); echo '{"ok":0,"expired":1,"msg":"Your demo subscription has ended."}'; exit; }
if (tfa_due($m)) { http_response_code(403); echo '{"ok":0,"msg":"Set up two-step login first (tfa.php)."}'; exit; }
$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals(csrf(), (string)($in['csrf'] ?? ''))) { http_response_code(403); echo '{"ok":0,"msg":"Session expired. Reload the page."}'; exit; }
function cut($s, $n) { $s = (string)$s; return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
function out($ok, $msg, $x = []) { echo json_encode(['ok' => $ok, 'msg' => $msg] + $x); exit; }
$a = $in['a'] ?? '';
if (isset($_SESSION['la']) && time() - $_SESSION['la'] > IDLE + 20) { http_response_code(403); echo '{"ok":0,"idle":1,"msg":"You were signed out after 5 minutes of inactivity."}'; exit; }
if ($a !== 'sub') $_SESSION['la'] = time();
if ($a === 'ping') out(1, 'ok', ['idle' => IDLE]);
$isAdmin = $m['role'] === 'admin';
if ($a === 'sub') { $st = sub_state($m); out(1, 'ok', ['sub' => $st, 'now' => time()]); }
if ($a === 'seen') { $us = users() ?: []; foreach ($us as &$x) if ($x['username'] === $m['username']) { $x['wel'] = false; $x['seen'] = WELCOME_V; } unset($x); wj('users.json', $us); out(1, 'ok'); }
if ($a !== 'data' && !$isAdmin) out(0, 'Admins only.');
if ($a === 'data' && !($isAdmin || ($m['role'] === 'viewer' && !empty($m['up']) && $m['dept'] === ''))) out(0, 'You do not have permission to save files.');

if ($a === 'data') {
  $rows = [];
  foreach (($in['rows'] ?? []) as $r) {
    if (!is_array($r) || count($r) < 6) continue;
    $rows[] = [is_numeric($r[0]) ? (int)$r[0] : cut(trim((string)$r[0]), 30), cut(trim((string)$r[1]), 80), cut(trim((string)$r[2]), 40), substr((string)$r[3], 0, 10), substr((string)$r[4], 0, 8), substr((string)$r[5], 0, 8)];
  }
  if (!$rows) out(0, 'No valid rows found in that file.');
  if (!empty($in['merge'])) {
    $mm = [];
    foreach (rj('data.json', ['rows' => []])['rows'] as $r) $mm[$r[0] . '|' . $r[3]] = $r;
    foreach ($rows as $r) $mm[$r[0] . '|' . $r[3]] = $r;
    $rows = array_values($mm);
  }
  $dates = array_column($rows, 3);
  $meta = ['file' => cut($in['file'] ?? 'Upload', 100), 'at' => date('Y-m-d H:i'), 'count' => count($rows), 'from' => min($dates), 'to' => max($dates)];
  if (!wj('data.json', ['meta' => $meta, 'rows' => $rows])) out(0, 'Could not write the data folder. Set its permission to 755.');
  out(1, 'Saved ' . count($rows) . ' records (' . $meta['from'] . ' to ' . $meta['to'] . ').');
}

if ($a === 'settings') {
  $s = $in['s'] ?? [];
  $t = fn($v, $lo, $hi) => max($lo, min($hi, (int)$v));
  $v = ['m' => ['s' => $t($s['m']['s'] ?? 540, 0, 1439), 'e' => $t($s['m']['e'] ?? 1080, 1, 2879)],
        'e' => ['s' => $t($s['e']['s'] ?? 900, 0, 1439), 'e' => $t($s['e']['e'] ?? 1560, 1, 2879)],
        'n' => ['s' => $t($s['n']['s'] ?? 1200, 0, 1439), 'e' => $t($s['n']['e'] ?? 1920, 1, 2879), 'on' => empty($s['n']['on']) ? 0 : 1],
        'grace' => $t($s['grace'] ?? 10, 0, 240), 'ot' => $t($s['ot'] ?? 30, 0, 600), 'tol' => $t($s['tol'] ?? 120, 30, 600), 'off' => $t($s['off'] ?? 0, -1, 6)];
  out(wj('settings.json', $v) ? 1 : 0, 'Shift settings saved.');
}

if ($a === 'assign') {
  if (!empty($in['clear'])) out(wj('assign.json', new stdClass) ? 1 : 0, 'All fixed shifts cleared.');
  $map = (array)rj('assign.json', []); $n = 0;
  foreach ((array)($in['set'] ?? []) as $id => $v) {
    if (++$n > 3000) break;
    $id = cut($id, 30);
    if ($v === 'm' || $v === 'e' || $v === 'n') $map[$id] = $v; else unset($map[$id]);
  }
  out(wj('assign.json', (object)$map) ? 1 : 0, 'Shift saved.');
}

if ($a === 'brand') {
  $name = cut(trim((string)($in['name'] ?? '')), 60);
  if ($name === '') out(0, 'Company name cannot be empty.');
  $acc = (string)($in['accent'] ?? '');
  if (!preg_match('/^#[0-9a-fA-F]{6}$/', $acc)) out(0, 'Pick a valid colour.');
  out(wj('brand.json', ['name' => $name, 'tagline' => cut(trim((string)($in['tagline'] ?? '')), 100), 'accent' => strtoupper($acc), 'contact' => cut(trim((string)($in['contact'] ?? '')), 160)]) ? 1 : 0, 'Branding saved.');
}

if ($a === 'logo') {
  $img = (string)($in['img'] ?? '');
  if (strpos($img, 'data:image/png;base64,') !== 0) out(0, 'Please choose an image file.');
  $bin = base64_decode(substr($img, 22), true);
  if ($bin === false || strlen($bin) > 900000) out(0, 'The image is too large. Use a smaller logo.');
  $i = @getimagesizefromstring($bin);
  if (!$i || $i[2] !== IMAGETYPE_PNG || $i[0] > 1200 || $i[1] > 1200) out(0, 'That image could not be used.');
  if (function_exists('imagecreatefromstring') && ($g = @imagecreatefromstring($bin))) {
    imagealphablending($g, false); imagesavealpha($g, true);
    ob_start(); imagepng($g); $bin = ob_get_clean();
  }
  $dir = __DIR__ . '/assets';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (@file_put_contents($dir . '/logo.png', $bin, LOCK_EX) === false) out(0, 'Could not write the assets folder. Set its permission to 755.');
  out(1, 'Logo saved.', ['logo' => brand()['logo']]);
}

if ($a === 'logo_del') {
  $f = __DIR__ . '/assets/logo.png';
  if (is_file($f)) @unlink($f);
  out(1, 'Logo removed.');
}

if ($a === 'tfa_policy') {
  $p = tfa_policy(); $p['on'] = !empty($in['on']) ? 1 : 0; $p['must'] = !empty($in['must']) ? 1 : 0;
  $d = (string)($in['from'] ?? '');
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($ts = strtotime($d . ' 00:00:00')) !== false) $p['from'] = $ts;
  out(wj('policy.json', $p) ? 1 : 0, $p['on'] ? 'Two-step login settings saved.' : 'Two-step login is switched off for everyone.');
}

function secs($v, $unit) {
  $v = (float)$v; $u = ['m' => 60, 'h' => 3600, 'd' => 86400][$unit] ?? 0;
  $n = (int)round($v * $u);
  return ($u && $n >= 60 && $n <= 86400 * 3650) ? $n : 0;
}
function sub_row($x) { return ['username' => $x['username'], 'sub' => sub_state($x)]; }

if (strpos($a, 'u_') === 0) {
  $us = users() ?: [];
  $find = function ($n) use (&$us) { foreach ($us as $i => $x) if ($x['username'] === $n) return $i; return -1; };
  $un = strtolower(trim((string)($in['username'] ?? '')));
  $pw = (string)($in['password'] ?? '');
  if ($a === 'u_welall') { foreach ($us as &$x) $x['wel'] = !empty($in['on']); unset($x); if (!wj('users.json', array_values($us))) out(0, 'Could not save.'); out(1, !empty($in['on']) ? 'Welcome message will show once to every user on their next login.' : 'Welcome message cleared for all users.'); }
  $role = in_array($in['role'] ?? '', ['admin', 'viewer', 'customer'], true) ? $in['role'] : 'viewer';
  $dept = $role === 'viewer' ? cut($in['dept'] ?? '', 40) : '';
  $up = $role === 'viewer' && $dept === '' && !empty($in['up']);
  if ($a === 'u_add') {
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) out(0, 'Username: 3 to 30 letters, numbers, dot, dash or underscore.');
    if ($find($un) >= 0) out(0, 'That username already exists.');
    if (strlen($pw) < 10) out(0, 'Password needs at least 10 characters.');
    $nm = cut(trim((string)($in['name'] ?? '')), 60);
    $nu = ['username'=>$un,'name'=>$nm !== '' ? $nm : $un,'role'=>$role,'dept'=>$dept,'up'=>$up,'hash'=>password_hash($pw, PASSWORD_DEFAULT),'last'=>null,'exp'=>null,'dur'=>null,'startOn'=>null,'wel'=>!empty($in['wel'])];
    $tm = $in['timer'] ?? null;
    if ($role !== 'admin' && is_array($tm) && !empty($tm['on'])) {
      $d = secs($tm['value'] ?? 0, $tm['unit'] ?? '');
      if (!$d) out(0, 'Enter a valid demo length (at least 1 minute).');
      $nu['dur'] = $d;
      if (($tm['start'] ?? 'now') === 'login') $nu['startOn'] = 'login'; else $nu['exp'] = time() + $d;
    }
    $us[] = $nu;
  } else {
    $i = $find($un); if ($i < 0) out(0, 'User not found.');
    if ($a === 'u_pw') { if (strlen($pw) < 10) out(0, 'Password needs at least 10 characters.'); $us[$i]['hash'] = password_hash($pw, PASSWORD_DEFAULT); }
    elseif ($a === 'u_del') { unset($us[$i]); }
    elseif ($a === 'u_tfa_reset') { unset($us[$i]['tfa']); log_event('tfa_reset', $us[$i]['username']); }
    elseif ($a === 'u_tfa_x') { if (!empty($in['on'])) $us[$i]['tfa_x'] = 1; else unset($us[$i]['tfa_x']); }
    elseif ($a === 'u_set') { $us[$i]['role'] = $role; $us[$i]['dept'] = $dept; $us[$i]['up'] = $up; $us[$i]['wel'] = !empty($in['wel']); if ($role === 'admin') { $us[$i]['exp'] = $us[$i]['dur'] = $us[$i]['startOn'] = null; } }
    elseif ($a === 'u_timer') {
      if ($us[$i]['role'] === 'admin') out(0, 'Admins never expire, so they cannot have a timer.');
      $op = (string)($in['op'] ?? ''); $x = &$us[$i]; $now = time();
      if ($op === 'clear') { $x['exp'] = $x['dur'] = $x['startOn'] = null; }
      elseif ($op === 'expire') { $x['exp'] = $now - 1; $x['startOn'] = null; if (empty($x['dur'])) $x['dur'] = 3600; }
      elseif ($op === 'set' || $op === 'add') {
        $d = secs($in['value'] ?? 0, $in['unit'] ?? '');
        if (!$d) out(0, 'Enter a valid time (at least 1 minute).');
        if ($op === 'set') {
          $x['dur'] = $d;
          if (($in['start'] ?? 'now') === 'login') { $x['exp'] = null; $x['startOn'] = 'login'; } else { $x['exp'] = $now + $d; $x['startOn'] = null; }
        } else {
          if (($x['exp'] ?? null) === null && ($x['startOn'] ?? '') === 'login' && !empty($x['dur'])) { $x['dur'] += $d; }
          else {
            /* extending a running timer adds to its length; restarting an ended or unlimited user begins a fresh period */
            $live = ($x['exp'] ?? null) !== null && (int)$x['exp'] > $now;
            $x['exp'] = ($live ? (int)$x['exp'] : $now) + $d;
            $x['dur'] = $live ? max((int)($x['dur'] ?? 0), 0) + $d : $d;
            $x['startOn'] = null;
          }
        }
      } else out(0, 'Unknown timer action.');
      unset($x);
      if (!wj('users.json', array_values($us))) out(0, 'Could not save.');
      out(1, 'Timer updated.', ['row' => sub_row($us[$i]), 'now' => time()]);
    }
  }
  $us = array_values($us);
  if (!array_filter($us, fn($x) => $x['role'] === 'admin')) out(0, 'At least one admin is needed.');
  out(wj('users.json', $us) ? 1 : 0, 'Saved.');
}
out(0, 'Unknown action.');

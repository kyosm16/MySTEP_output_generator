<?php
session_start();
require_once __DIR__ . '/draft.php';

// Initialize Guest Trial System (Limit: 3 trials)
if (!isset($_SESSION['guest_trials'])) {
    $_SESSION['guest_trials'] = 3;
}

// Simple JSON File Storage for Auth Users
$usersFile = __DIR__ . '/users.json';
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}

$authMessage = '';
$authMessageType = 'red';

// Malay month names (index 1-12)
$bulanMalay = [1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];

// Handle Authentication POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $users = json_decode(file_get_contents($usersFile), true) ?: [];

    if ($_POST['action'] === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username !== '' && $password !== '') {
            if (isset($users[$username])) {
                $authMessage = 'Username sudah wujud!';
            } else {
                $users[$username] = password_hash($password, PASSWORD_DEFAULT);
                file_put_contents($usersFile, json_encode($users));
                $_SESSION['user'] = $username;
                $authMessage = 'Akaun berjaya didaftarkan dan dilog masuk!';
                $authMessageType = 'green';
            }
        } else {
            $authMessage = 'Sila isi ruangan username dan password.';
        }
    } elseif ($_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            $_SESSION['user'] = $username;
            $authMessage = 'Berjaya log masuk!';
            $authMessageType = 'green';
        } else {
            $authMessage = 'Username atau password salah!';
        }
    } elseif ($_POST['action'] === 'logout') {
        unset($_SESSION['user']);
        $authMessage = 'Anda telah log keluar.';
        $authMessageType = 'blue';
    }
}

// Handle Form Output Generation
$generatedData = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $isLoggedIn = isset($_SESSION['user']);

    // Guest Trial Check
    if (!$isLoggedIn) {
        if ($_SESSION['guest_trials'] <= 0) {
            $errorMsg = 'Had percubaan tetamu (3 kali) telah tamat! Sila daftar akaun percuma untuk meneruskan.';
        } else {
            $_SESSION['guest_trials']--;
        }
    }

    if (!$errorMsg) {
        // Selected month & year -> last day of that month
        $bulanNo = (int)($_POST['bulan_nama'] ?? date('n'));
        if ($bulanNo < 1 || $bulanNo > 12) $bulanNo = (int)date('n');
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');
        $hariAkhir = (int)date('t', mktime(0, 0, 0, $bulanNo, 1, $tahun));

        $generatedData = [
            'nama' => strtoupper(trim($_POST['nama'] ?? '')),
            'kp' => trim($_POST['kp'] ?? ''),
            'unit' => trim($_POST['unit'] ?? ''),
            'bulan' => strtoupper($bulanMalay[$bulanNo] . ' ' . $tahun),
            'tarikh_akhir' => $hariAkhir . ' ' . $bulanMalay[$bulanNo] . ' ' . $tahun,
            'upah_kasar' => floatval($_POST['upah_kasar'] ?? 0),
            'tugasan' => []
        ];

        if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
            foreach ($_POST['tasks'] as $task) {
                // Process Tugasan Category/Title
                $tugasanJenis = $task['jenis'] ?? '';
                if ($tugasanJenis === 'Lain-lain') {
                    $tugasanTajuk = trim($task['jenis_custom'] ?? 'Tugasan Lain-lain');
                } else {
                    $tugasanTajuk = $tugasanJenis;
                }
                $tugasanDeskripsi = trim($task['deskripsi'] ?? '');

                // Process Sasaran (Items OR Free Text)
                $sasaranMode = $task['sasaran_mode'] ?? 'items';
                $sasaranList = [];
                $sasaranText = '';
                $totalSasaranKuantiti = 0;
                $sasaranJenisUnit = 'unit';

                if ($sasaranMode === 'text') {
                    $sasaranText = trim($task['sasaran_text'] ?? '');
                    $totalSasaranKuantiti = floatval($task['sasaran_manual_qty'] ?? 0);
                    $sasaranJenisUnit = trim($task['sasaran_manual_unit'] ?? 'unit');
                } else {
                    if (isset($task['sasaran']) && is_array($task['sasaran'])) {
                        foreach ($task['sasaran'] as $s) {
                            $desc = trim($s['desc'] ?? '');
                            $val = floatval($s['val'] ?? 0);
                            $unit = trim($s['unit'] ?? 'unit');
                            if ($val > 0 || $desc !== '') {
                                $sasaranList[] = ['desc' => $desc, 'val' => $val, 'unit' => $unit];
                                $totalSasaranKuantiti += $val;
                                if (!empty($unit)) $sasaranJenisUnit = $unit;
                            }
                        }
                    }
                }

                // Process Pencapaian (Items OR Free Text)
                $pencapaianMode = $task['pencapaian_mode'] ?? 'items';
                $pencapaianList = [];
                $pencapaianText = '';
                $totalPencapaianKuantiti = 0;
                $pencapaianJenisUnit = 'unit';

                if ($pencapaianMode === 'text') {
                    $pencapaianText = trim($task['pencapaian_text'] ?? '');
                    $totalPencapaianKuantiti = floatval($task['pencapaian_manual_qty'] ?? 0);
                    $pencapaianJenisUnit = trim($task['pencapaian_manual_unit'] ?? 'unit');
                } else {
                    if (isset($task['pencapaian']) && is_array($task['pencapaian'])) {
                        foreach ($task['pencapaian'] as $p) {
                            $desc = trim($p['desc'] ?? '');
                            $val = floatval($p['val'] ?? 0);
                            $unit = trim($p['unit'] ?? 'unit');
                            if ($val > 0 || $desc !== '') {
                                $pencapaianList[] = ['desc' => $desc, 'val' => $val, 'unit' => $unit];
                                $totalPencapaianKuantiti += $val;
                                if (!empty($unit)) $pencapaianJenisUnit = $unit;
                            }
                        }
                    }
                }

                // Calculate Percent & Fee
                $peratusAsal = $totalSasaranKuantiti > 0 ? ($totalPencapaianKuantiti / $totalSasaranKuantiti) * 100 : 0;
                $peratusKiraRaw = min($peratusAsal, 100);
                $peratusKira = round($peratusKiraRaw, 2);
                $upahLayak = ($peratusKira / 100) * $generatedData['upah_kasar'];

                $generatedData['tugasan'][] = [
                    'tajuk' => $tugasanTajuk,
                    'deskripsi' => $tugasanDeskripsi,
                    'sasaran_mode' => $sasaranMode,
                    'sasaran_list' => $sasaranList,
                    'sasaran_text' => $sasaranText,
                    'total_sasaran' => $totalSasaranKuantiti,
                    'sasaran_unit' => $sasaranJenisUnit,
                    'pencapaian_mode' => $pencapaianMode,
                    'pencapaian_list' => $pencapaianList,
                    'pencapaian_text' => $pencapaianText,
                    'total_pencapaian' => $totalPencapaianKuantiti,
                    'pencapaian_unit' => $pencapaianJenisUnit,
                    'peratus_asal' => round($peratusAsal, 2),
                    'peratus_kira' => $peratusKira,
                    'peratus_exact' => abs($peratusKiraRaw - $peratusKira) < 0.0000001,
                    'upah_layak' => $upahLayak,
                    'justifikasi' => trim($task['justifikasi'] ?? ''),
                    'nota' => trim($task['nota'] ?? '')
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySTEP Output Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Caladea:wght@400;700&family=Source+Serif+4:wght@400;600;700&display=swap" rel="stylesheet">
    <script>try{var t=localStorage.getItem("mystep-theme");if(t)document.documentElement.setAttribute("data-theme",t)}catch(e){}</script>
    <style>
        /* ================= Themes ================= */
        :root, [data-theme="sage"] {
            --canvas: #eef1ea; --surface: #fcfdfa; --soft: #f3f6ef; --field: #f6f8f2;
            --ink: #1e2b25; --ink-soft: #56665d; --ink-faint: #80908a; --ph: #94a39b;
            --brand: #3f6b57; --brand-deep: #2b4a3c; --brand-ink: #2f5444; --brand-tint: #dfe9dc;
            --accent: #d9a05b;
            --line: #dbe3d6; --line-strong: #bfcbba;
            --nav-bg: #25382f; --nav-ink: #eef3ec; --nav-soft: #aebfb4;
            --ok: #2f7d4f; --ok-tint: #e1f1e6; --ok-line: #bfe0cb; --ok-ink: #1b5a37;
            --bad: #b3402f; --bad-tint: #fbe9e5; --bad-line: #f0c9c1; --bad-ink: #86281b;
            --info-tint: #e3eff6; --info-line: #bdd7e6; --info-ink: #1d4d6b;
            --shadow-rgb: 38 60 48;
            --glow1: rgba(217,160,91,.24); --glow2: rgba(63,107,87,.18);
            color-scheme: light;
        }
        [data-theme="blush"] {
            --canvas: #f8eef0; --surface: #fffbfb; --soft: #fbf3f4; --field: #fbf4f5;
            --ink: #2e1b29; --ink-soft: #6b5062; --ink-faint: #9a8191; --ph: #b49aa8;
            --brand: #94385f; --brand-deep: #6b2344; --brand-ink: #7a2c4e; --brand-tint: #f5dfe6;
            --accent: #d4915f;
            --line: #eedade; --line-strong: #dcbfc8;
            --nav-bg: #4a2138; --nav-ink: #fbeef2; --nav-soft: #d9b4c4;
            --shadow-rgb: 90 40 65;
            --glow1: rgba(212,145,95,.24); --glow2: rgba(148,56,95,.15);
        }
        [data-theme="midnight"] {
            --canvas: #0f131c; --surface: #181e2b; --soft: #1d2433; --field: #121823;
            --ink: #e7ebf3; --ink-soft: #a2adc2; --ink-faint: #7b879d; --ph: #5f6b82;
            --brand: #4d6dd1; --brand-deep: #3b57b0; --brand-ink: #9db4f5; --brand-tint: #222d4a;
            --accent: #e0b25f;
            --line: #2a3347; --line-strong: #3a4660;
            --nav-bg: #1b2232; --nav-ink: #eef1f8; --nav-soft: #9aa6bd;
            --ok: #5cc690; --ok-tint: #123024; --ok-line: #1f5a3d; --ok-ink: #8be0b2;
            --bad: #f08a7a; --bad-tint: #3a1b1a; --bad-line: #6b2e2a; --bad-ink: #ffb4a8;
            --info-tint: #14283a; --info-line: #25506f; --info-ink: #a9d3f2;
            --shadow-rgb: 0 0 0;
            --glow1: rgba(224,178,95,.10); --glow2: rgba(88,120,220,.20);
            color-scheme: dark;
        }

        :root {
            --r-lg: 20px; --r-md: 14px; --r-sm: 10px;
            --shadow: 0 1px 2px rgb(var(--shadow-rgb) / .06), 0 18px 40px -20px rgb(var(--shadow-rgb) / .22);
            --font-ui: "DM Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --font-head: "Fraunces", Georgia, "Times New Roman", serif;
            --font-doc: Cambria, "Caladea", "Source Serif 4", Georgia, "Times New Roman", serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; background: var(--canvas); }
        body {
            margin: 0; font-family: var(--font-ui); font-size: 15px; line-height: 1.55;
            color: var(--ink); background: var(--canvas); padding-bottom: 72px;
            transition: background .25s, color .25s;
        }
        body::before {
            content: ""; position: fixed; inset: 0; z-index: -1; pointer-events: none;
            background:
                radial-gradient(820px 460px at 88% -8%, var(--glow1), transparent 62%),
                radial-gradient(720px 520px at -6% 8%, var(--glow2), transparent 58%);
        }
        h1, h2, h3, p { margin: 0; }
        button, input, select, textarea { font: inherit; color: inherit; }
        .hidden { display: none !important; }
        :focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }

        /* ---------- Floating nav ---------- */
        .topbar { padding: 18px 24px 0; }
        .topbar-inner {
            max-width: 1180px; margin: 0 auto; background: var(--nav-bg); color: var(--nav-ink);
            border-radius: 22px; padding: 10px 12px 10px 16px;
            display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
            box-shadow: var(--shadow);
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-mark {
            width: 40px; height: 40px; border-radius: 13px; background: var(--accent); color: var(--nav-bg);
            display: grid; place-items: center; font-family: var(--font-head); font-weight: 600; font-size: 17px;
        }
        .brand-title { font-family: var(--font-head); font-size: 18px; font-weight: 500; line-height: 1.2; letter-spacing: -.01em; }
        .brand-sub { font-size: 12.5px; color: var(--nav-soft); }
        .userbar { display: flex; align-items: center; gap: 10px; font-size: 14px; flex-wrap: wrap; }
        .userbar .who { color: var(--nav-soft); }
        .userbar .who b { color: var(--nav-ink); font-weight: 600; }
        .pill {
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
            border-radius: 999px; padding: 5px 13px; font-size: 13px; color: var(--nav-soft);
        }
        .pill b { color: var(--nav-ink); }

        .swatches { display: flex; gap: 7px; padding: 0 6px; align-items: center; }
        .sw {
            width: 22px; height: 22px; border-radius: 50%; border: 0; cursor: pointer; padding: 0;
            box-shadow: inset 0 0 0 1.5px rgba(255,255,255,.35); transition: transform .15s;
        }
        .sw:hover { transform: scale(1.12); }
        .sw[aria-pressed="true"] { box-shadow: 0 0 0 2px var(--nav-bg), 0 0 0 4px var(--nav-ink); }
        .sw-sage { background: linear-gradient(135deg, #3f6b57 50%, #d9a05b 50%); }
        .sw-blush { background: linear-gradient(135deg, #94385f 50%, #f1c9d4 50%); }
        .sw-midnight { background: linear-gradient(135deg, #4d6dd1 50%, #0f131c 50%); }

        /* ---------- Buttons ---------- */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: 1px solid transparent; border-radius: 999px; padding: 10px 20px;
            font-size: 14px; font-weight: 600; line-height: 1.2; cursor: pointer; white-space: nowrap;
            transition: background .15s, border-color .15s, transform .15s, box-shadow .15s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--brand); color: #fff; box-shadow: 0 6px 16px -8px var(--brand); }
        .btn-primary:hover { background: var(--brand-deep); }
        .btn-light { background: var(--nav-ink); color: var(--nav-bg); }
        .btn-light:hover { background: #fff; }
        .btn-ghost-light { background: transparent; color: var(--nav-ink); border-color: rgba(255,255,255,.3); }
        .btn-ghost-light:hover { background: rgba(255,255,255,.1); }
        .btn-outline { background: transparent; color: var(--ink); border-color: var(--line-strong); }
        .btn-outline:hover { background: var(--soft); }
        .btn-danger-text { background: transparent; color: var(--bad); padding: 6px 12px; font-weight: 500; }
        .btn-danger-text:hover { background: var(--bad-tint); }
        .btn-block { width: 100%; padding: 14px 18px; font-size: 15px; }
        .btn-sm { padding: 7px 14px; font-size: 13px; }

        /* ---------- Page ---------- */
        .page { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .intro { padding: 60px 0 40px; }
        .intro h1 {
            font-family: var(--font-head); font-weight: 500; font-size: 46px; line-height: 1.08;
            letter-spacing: -.025em; max-width: 20ch; color: var(--ink);
        }
        .intro p { margin-top: 16px; font-size: 16.5px; color: var(--ink-soft); max-width: 58ch; }

        .alert { border-radius: var(--r-md); padding: 13px 18px; font-size: 14px; border: 1px solid; margin-bottom: 22px; font-weight: 500; }
        .alert-red   { background: var(--bad-tint);  border-color: var(--bad-line);  color: var(--bad-ink); }
        .alert-green { background: var(--ok-tint);   border-color: var(--ok-line);   color: var(--ok-ink); }
        .alert-blue  { background: var(--info-tint); border-color: var(--info-line); color: var(--info-ink); }

        .layout { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 28px; align-items: start; }

        .card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); box-shadow: var(--shadow); }
        .card + .card { margin-top: 20px; }
        .card-head { padding: 24px 28px 0; }
        .card-head h2 { font-family: var(--font-head); font-size: 22px; font-weight: 500; letter-spacing: -.01em; }
        .card-head p { color: var(--ink-soft); font-size: 14px; margin-top: 3px; }
        .card-body { padding: 20px 28px 28px; }

        /* ---------- Fields ---------- */
        .grid-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px 20px; }
        .span-2 { grid-column: span 2; }
        .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 7px; color: var(--ink); }
        .field .hint { font-weight: 400; color: var(--ink-faint); }
        .input, .select, .textarea {
            width: 100%; background: var(--field); border: 1.5px solid var(--line); border-radius: 12px;
            padding: 10px 14px; font-size: 14.5px; transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .input::placeholder, .textarea::placeholder { color: var(--ph); }
        .input:hover, .select:hover, .textarea:hover { border-color: var(--line-strong); }
        .input:focus, .select:focus, .textarea:focus {
            outline: none; border-color: var(--brand); background: var(--surface);
            box-shadow: 0 0 0 4px rgb(var(--shadow-rgb) / .10), 0 0 0 1.5px var(--brand);
        }
        .select { appearance: none; padding-right: 38px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%23808f89' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .textarea { resize: vertical; }
        .input-prefix { position: relative; }
        .input-prefix span { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ink-faint); font-size: 14px; font-weight: 600; pointer-events: none; }
        .input-prefix .input { padding-left: 46px; font-variant-numeric: tabular-nums; }

        /* ---------- Tasks ---------- */
        .tasks-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin: 40px 4px 16px; }
        .tasks-head h2 { font-family: var(--font-head); font-size: 24px; font-weight: 500; letter-spacing: -.01em; }
        .tasks-head p { color: var(--ink-soft); font-size: 14px; }

        .task-row { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); box-shadow: var(--shadow); }
        .task-row + .task-row { margin-top: 18px; }
        .task-top {
            display: flex; align-items: center; justify-content: space-between; padding: 12px 14px 12px 24px;
            background: var(--soft); border-bottom: 1px solid var(--line); border-radius: var(--r-lg) var(--r-lg) 0 0;
        }
        .task-bil { font-family: var(--font-head); font-weight: 500; font-size: 18px; color: var(--brand-ink); }
        .task-body { padding: 24px; display: grid; gap: 20px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .custom-box { background: var(--brand-tint); border-radius: var(--r-md); padding: 14px 16px; }

        .panel { border: 1px solid var(--line); border-radius: var(--r-md); background: var(--surface); display: flex; flex-direction: column; overflow: hidden; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; border-bottom: 1px solid var(--line); }
        .panel-title { font-size: 14px; font-weight: 600; }
        .seg { display: inline-flex; background: var(--soft); border: 1px solid var(--line); border-radius: 999px; padding: 2px; }
        .seg button { border: 0; background: transparent; color: var(--ink-soft); padding: 4px 12px; font-size: 12.5px; font-weight: 600; border-radius: 999px; cursor: pointer; transition: background .15s, color .15s; }
        .seg button.active { background: var(--brand); color: #fff; }
        .panel-body { padding: 14px; flex: 1; }
        .panel-foot { display: flex; justify-content: space-between; align-items: baseline; padding: 11px 14px; border-top: 1px solid var(--line); background: var(--soft); font-size: 13px; color: var(--ink-soft); }
        .panel-foot b { font-size: 16px; color: var(--ink); font-variant-numeric: tabular-nums; }

        .item-row { display: grid; grid-template-columns: 1fr 84px 68px 30px; gap: 6px; align-items: center; }
        .item-row + .item-row { margin-top: 7px; }
        .item-row .input { padding: 8px 10px; font-size: 13.5px; border-radius: 10px; }
        .icon-btn { border: 0; background: transparent; color: var(--ink-faint); cursor: pointer; width: 30px; height: 30px; border-radius: 50%; font-size: 14px; line-height: 1; }
        .icon-btn:hover { background: var(--bad-tint); color: var(--bad); }
        .link-btn { background: none; border: 0; padding: 10px 0 0; color: var(--brand-ink); font-size: 13px; font-weight: 600; cursor: pointer; }
        .link-btn:hover { text-decoration: underline; text-underline-offset: 3px; }
        .qty-row { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px; color: var(--ink-soft); }
        .qty-row .input { width: 90px; padding: 8px 10px; font-size: 13.5px; }
        .qty-row .input.unit { width: 74px; }

        .result-strip { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center; background: var(--brand-tint); border-radius: var(--r-md); padding: 16px 20px; }
        .result-label { font-size: 12.5px; color: var(--ink-soft); }
        .result-value { font-family: var(--font-head); font-size: 28px; font-weight: 500; line-height: 1.15; font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
        .result-value.money { color: var(--ok); }
        .result-value small { font-family: var(--font-ui); font-size: 12px; font-weight: 500; color: var(--ink-soft); margin-left: 8px; letter-spacing: 0; }
        .bar { height: 8px; border-radius: 99px; background: rgb(var(--shadow-rgb) / .12); margin-top: 10px; overflow: hidden; }
        .bar > i { display: block; height: 100%; width: 0; background: var(--brand); border-radius: 99px; transition: width .3s ease; }

        /* ---------- Summary ---------- */
        .aside { position: sticky; top: 20px; }
        .aside .card-body { padding-top: 16px; }
        .sum-list { list-style: none; margin: 0; padding: 0; font-size: 13.5px; }
        .sum-list li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px dashed var(--line-strong); }
        .sum-list li span:first-child { color: var(--ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sum-list li span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }
        .sum-total { display: flex; justify-content: space-between; align-items: baseline; padding: 16px 0 2px; gap: 8px; }
        .sum-total span:first-child { font-weight: 600; font-size: 14px; }
        .sum-total span:last-child { font-family: var(--font-head); font-size: 30px; font-weight: 500; color: var(--brand-ink); font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
        .aside-note { margin-top: 14px; font-size: 12.5px; color: var(--ink-faint); }

        /* ---------- Generated document ---------- */
        .doc-wrap { margin-top: 56px; }
        .doc-toolbar { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; margin: 0 4px 16px; flex-wrap: wrap; }
        .doc-toolbar h2 { font-family: var(--font-head); font-size: 26px; font-weight: 500; letter-spacing: -.01em; }
        .doc-toolbar p { font-size: 14px; color: var(--ink-soft); }
        .paper {
            background: #fff; border: 1px solid #d5d9d6; border-radius: 6px;
            box-shadow: 0 2px 4px rgb(var(--shadow-rgb) / .08), 0 30px 60px -24px rgb(var(--shadow-rgb) / .35);
            padding: 48px 52px; font-family: var(--font-doc); color: #111; font-size: 14px; line-height: 1.5;
        }
        .paper .meta { display: grid; grid-template-columns: 130px 1fr; row-gap: 3px; margin-bottom: 20px; }
        .paper .meta dt { font-weight: 600; }
        .paper table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .paper th, .paper td { border: 1px solid #555; padding: 8px 10px; vertical-align: top; }
        .paper thead th.c, .paper td.c { text-align: center; }
        .paper td { font-size: 13px; }
        .paper .t-title { font-weight: 700; }
        .paper .t-desc { font-size: 12px; color: #333; margin-top: 4px; white-space: pre-line; }
        .paper table.mini { width: 100%; margin: 0; min-width: 0; table-layout: auto; }
        .paper table.mini td { border: 1px solid #555; padding: 4px 8px; font-size: 12.5px; vertical-align: top; }
        .paper table.mini td.q { text-align: right; white-space: nowrap; width: 1%; font-weight: 600; }
        .paper table.mini tr.sum td { font-weight: 700; background: #f2f4f5; }
        .paper .free { font-size: 12.5px; white-space: pre-line; margin-bottom: 8px; }
        .paper tfoot td { background: #f5f6f7; font-weight: 700; }

        .paper .doc-title { text-align: center; font-weight: 700; font-size: 16px; line-height: 1.5; margin-bottom: 26px; }
        .paper .meta dd { margin: 0; font-weight: 700; }
        .paper thead th {
            background: #f2f2f2; color: #111; text-align: center; vertical-align: middle;
            font-family: var(--font-doc); font-weight: 700; font-size: 12.5px; line-height: 1.35; padding: 12px 8px;
        }
        .paper td.upah { font-size: 12.5px; }
        .paper td.upah p { margin: 0; }
        .paper .u-h { font-weight: 700; text-decoration: underline; margin-bottom: 8px !important; }
        .paper .u-b { font-weight: 700; margin-top: 6px !important; }
        .paper .gapTop { margin-top: 14px !important; }
        .paper .u-j { margin-top: 14px !important; }
        .paper .fx { display: grid; grid-template-columns: max-content max-content; column-gap: 8px; align-items: center; width: max-content; margin: 8px 0 0; }
        .paper .fx.wide { grid-template-columns: 1fr auto; width: 100%; }
        .paper .fx .n { grid-column: 1; grid-row: 1; }
        .paper .fx .l { grid-column: 1; grid-row: 2; border-top: 1px solid #111; height: 0; }
        .paper .fx .m { grid-column: 2; grid-row: 2; line-height: 1.2; }
        .paper .fx .d { grid-column: 1; grid-row: 3; }
        .paper .fx.gapTop { margin-top: 18px; }
        .paper .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px; font-size: 12.5px; }
        .paper .sign p { margin: 0; }
        .paper .sign .lbl { margin-top: 14px; }
        .paper .sign .space { height: 34px; }
        .paper .sign .dots { letter-spacing: 0; line-height: 1.2; }
        .paper .sign .nm { font-weight: 700; }

        .paper .skop { margin: 0 0 8px; font-size: 12.5px; white-space: pre-line; }
        .paper .nota { margin: 10px 0 0; font-size: 12.5px; font-weight: 700; white-space: pre-line; }
        .month-row { display: grid; grid-template-columns: 1fr 104px; gap: 8px; }

        /* ---------- Modals ---------- */
        .modal { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgb(15 20 18 / .55); backdrop-filter: blur(4px); }
        .modal form { background: var(--surface); color: var(--ink); border: 1px solid var(--line); border-radius: 24px; width: 100%; max-width: 410px; box-shadow: 0 30px 70px rgba(0,0,0,.35); }
        .modal-head { padding: 26px 28px 0; }
        .modal-head h3 { font-family: var(--font-head); font-size: 24px; font-weight: 500; letter-spacing: -.01em; }
        .modal-head p { font-size: 14px; color: var(--ink-soft); margin-top: 4px; }
        .modal-body { padding: 20px 28px; display: grid; gap: 16px; }
        .modal-foot { padding: 4px 28px 26px; display: flex; justify-content: flex-end; gap: 8px; }

        /* ---------- Responsive ---------- */
        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
            .aside { position: static; }
            .intro h1 { font-size: 38px; }
        }
        @media (max-width: 720px) {
            .page { padding-left: 16px; padding-right: 16px; }
            .topbar { padding: 12px 12px 0; }
            .topbar-inner { border-radius: 20px; padding: 12px 14px; }
            .intro { padding: 36px 0 28px; }
            .intro h1 { font-size: 32px; }
            .grid-details, .grid-2, .result-strip { grid-template-columns: 1fr; }
            .span-2 { grid-column: auto; }
            .card-head, .card-body { padding-left: 20px; padding-right: 20px; }
            .tasks-head { flex-direction: column; align-items: flex-start; }
            .paper { padding: 24px 16px; overflow-x: auto; }
            .paper table { min-width: 760px; }
            }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

        /* ---------- Print ---------- */
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            html, body { background: #fff !important; padding: 0; }
            body::before { display: none; }
            .no-print, .topbar, .intro, .layout, .modal { display: none !important; }
            .page { max-width: none; padding: 0; }
            .doc-wrap { margin: 0; }
            .paper { box-shadow: none; border: 0; padding: 0; border-radius: 0; }
            .paper tr { break-inside: avoid; }
        }
    </style>
    <?php draft_styles(); ?>
</head>
<body>

    <!-- Top bar -->
    <header class="topbar no-print">
        <div class="topbar-inner">
            <div class="brand">
                <button type="button" class="menu-btn" id="menuBtn" onclick="toggleDrawer()" aria-label="Buka menu" aria-expanded="false" aria-controls="drawer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="brand-mark" aria-hidden="true">M</div>
                <div>
                    <div class="brand-title">MySTEP Output Generator</div>
                    <div class="brand-sub">Borang penilaian output dan pengiraan upah automatik</div>
                </div>
            </div>
            <div class="userbar">
                <div class="swatches" role="group" aria-label="Pilih tema">
                    <button type="button" class="sw sw-sage" data-theme-btn="sage" aria-label="Tema Sage" title="Sage" onclick="setTheme('sage')"></button>
                    <button type="button" class="sw sw-blush" data-theme-btn="blush" aria-label="Tema Blush" title="Blush" onclick="setTheme('blush')"></button>
                    <button type="button" class="sw sw-midnight" data-theme-btn="midnight" aria-label="Tema Midnight" title="Midnight" onclick="setTheme('midnight')"></button>
                </div>
                <?php if (isset($_SESSION['user'])): ?>
                    <span class="who">Pengguna: <b><?= htmlspecialchars($_SESSION['user']) ?></b></span>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-ghost-light btn-sm">Log keluar</button>
                    </form>
                <?php else: ?>
                    <span class="pill">Percubaan tetamu: <b><?= (int)$_SESSION['guest_trials'] ?></b> / 3</span>
                    <button type="button" onclick="toggleModal('loginModal')" class="btn btn-ghost-light btn-sm">Log masuk</button>
                    <button type="button" onclick="toggleModal('registerModal')" class="btn btn-light btn-sm">Daftar percuma</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="page">

        <section class="intro no-print">
            <h1>Borang output bulanan, siap dalam beberapa minit.</h1>
            <p>Isi maklumat pegawai, tambah tugasan beserta sasaran dan pencapaian. Peratusan dan upah layak dikira secara automatik, kemudian cetak atau simpan sebagai PDF.</p>
        </section>

        <!-- Flash Messages -->
        <?php if ($authMessage): ?>
            <div class="alert alert-<?= htmlspecialchars($authMessageType) ?> no-print" role="status">
                <?= htmlspecialchars($authMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-red no-print" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Generator Input Form -->
        <form method="POST" id="mainForm" class="no-print">
            <input type="hidden" name="action" value="generate">

            <div class="layout">
                <div>
                    <!-- Personal Details -->
                    <section class="card">
                        <div class="card-head">
                            <h2>Maklumat pegawai</h2>
                            <p>Maklumat ini dipaparkan pada pengepala borang.</p>
                        </div>
                        <div class="card-body">
                            <div class="grid-details">
                                <div class="field span-2">
                                    <label for="nama">Nama penuh</label>
                                    <input type="text" id="nama" name="nama" required class="input" placeholder="cth: DEBORA GRACE" autocomplete="name">
                                </div>
                                <div class="field">
                                    <label for="kp">No. kad pengenalan</label>
                                    <input type="text" id="kp" name="kp" required class="input" placeholder="000000-00-0000" inputmode="numeric">
                                </div>
                                <div class="field">
                                    <label for="bulan_nama">Bulan penilaian</label>
                                    <div class="month-row">
                                        <select id="bulan_nama" name="bulan_nama" class="select" required>
                                            <?php foreach ($bulanMalay as $no => $nm): ?>
                                                <option value="<?= $no ?>" <?= $no === (int)date('n') ? 'selected' : '' ?>><?= $nm ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="number" id="tahun" name="tahun" class="input" min="2000" max="2100" value="<?= date('Y') ?>" required aria-label="Tahun">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="unit">Bahagian / unit</label>
                                    <input type="text" id="unit" name="unit" required class="input" placeholder="cth: IT / Aplikasi">
                                </div>
                                <div class="field">
                                    <label for="upahKasar">Upah kasar</label>
                                    <div class="input-prefix">
                                        <span>RM</span>
                                        <input type="number" step="0.01" name="upah_kasar" id="upahKasar" value="2100.00" required class="input" onchange="calculateAllTasks()" oninput="calculateAllTasks()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Dynamic Tasks -->
                    <div class="tasks-head">
                        <div>
                            <h2>Tugasan dan output</h2>
                            <p>Satu kad bagi setiap tugasan yang dinilai.</p>
                        </div>
                        <button type="button" onclick="addTaskRow()" class="btn btn-primary">+ Tambah tugasan</button>
                    </div>

                    <div id="tasksWrapper">
                        <!-- Dynamic Tasks Rendered Here -->
                    </div>
                </div>

                <!-- Summary -->
                <aside class="aside">
                    <section class="card">
                        <div class="card-head">
                            <h2>Ringkasan upah</h2>
                            <p>Dikemas kini semasa anda mengisi borang.</p>
                        </div>
                        <div class="card-body">
                            <ul class="sum-list" id="summaryList"></ul>
                            <div class="sum-total">
                                <span>Jumlah layak dibayar</span>
                                <span id="summaryTotal">RM 0.00</span>
                            </div>
                            <div class="field" style="margin-top:18px">
                                <label for="draftName">Nama draf <span class="hint">(pilihan)</span></label>
                                <input type="text" id="draftName" class="input" placeholder="Auto: bulan dan nama pegawai">
                            </div>
                            <button type="button" class="btn btn-outline btn-block" style="margin-top:12px" onclick="saveDraft()">Simpan draf</button>
                            <p class="save-status" id="saveStatus">Belum disimpan</p>
                            <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px">Jana borang penilaian</button>
                            <p class="aside-note">Peratusan pencapaian dihadkan kepada 100% bagi setiap tugasan.</p>
                        </div>
                    </section>
                </aside>
            </div>
        </form>

        <!-- Printable Document Output -->
        <?php if ($generatedData): ?>
            <?php $jumlahUpah = array_sum(array_column($generatedData['tugasan'], 'upah_layak')); ?>
            <section class="doc-wrap" id="hasil">
                <div class="doc-toolbar no-print">
                    <div>
                        <h2>Borang siap dijana</h2>
                        <p>Semak maklumat di bawah, kemudian cetak atau simpan sebagai PDF.</p>
                    </div>
                    <button type="button" onclick="window.print()" class="btn btn-primary">Cetak / simpan PDF</button>
                </div>

                <div class="paper" id="printableArea">
                    <div class="doc-title">
                        BORANG PENILAIAN PENCAPAIAN OUTPUT<br>
                        <u>BULAN <?= htmlspecialchars($generatedData['bulan']) ?></u>
                    </div>

                    <dl class="meta">
                        <dt>Nama</dt><dd>: <?= htmlspecialchars($generatedData['nama']) ?></dd>
                        <dt>No KP</dt><dd>: <?= htmlspecialchars($generatedData['kp']) ?></dd>
                        <dt>Bahagian/Unit</dt><dd>: <?= htmlspecialchars($generatedData['unit']) ?></dd>
                    </dl>

                    <table>
                        <thead>
                            <tr>
                                <th style="width:5%">BIL.</th>
                                <th style="width:22%">TUGASAN</th>
                                <th style="width:24%">SASARAN OUTPUT<br>(KUANTITI ATAU MASA ATAU KUALITI)</th>
                                <th style="width:24%">PENCAPAIAN</th>
                                <th style="width:25%">UPAH</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($generatedData['tugasan'] as $idx => $t): ?>
                                <tr>
                                    <td class="c"><b><?= $idx + 1 ?>.</b></td>
                                    <td>
                                        <div class="t-title"><?= htmlspecialchars($t['tajuk']) ?></div>
                                    </td>

                                    <!-- Sasaran Display -->
                                    <td>
                                        <?php if ($t['deskripsi'] !== ''): ?>
                                            <p class="skop"><?= htmlspecialchars($t['deskripsi']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($t['sasaran_mode'] === 'text' && $t['sasaran_text'] !== ''): ?>
                                            <p class="free"><?= htmlspecialchars($t['sasaran_text']) ?></p>
                                        <?php endif; ?>
                                        <table class="mini">
                                            <?php if ($t['sasaran_mode'] !== 'text'): ?>
                                                <?php foreach ($t['sasaran_list'] as $sItem): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($sItem['desc']) ?></td>
                                                        <td class="q"><?= $sItem['val'] ?> <?= htmlspecialchars($sItem['unit']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <tr class="sum">
                                                <td>Jumlah Sasaran</td>
                                                <td class="q"><?= $t['total_sasaran'] ?> <?= htmlspecialchars($t['sasaran_unit']) ?></td>
                                            </tr>
                                        </table>
                                    </td>

                                    <!-- Pencapaian Display -->
                                    <td>
                                        <p class="skop">Pencapaian sehingga <?= htmlspecialchars($generatedData['tarikh_akhir']) ?>:</p>
                                        <?php if ($t['pencapaian_mode'] === 'text' && $t['pencapaian_text'] !== ''): ?>
                                            <p class="free"><?= htmlspecialchars($t['pencapaian_text']) ?></p>
                                        <?php endif; ?>
                                        <table class="mini">
                                            <?php if ($t['pencapaian_mode'] !== 'text'): ?>
                                                <?php foreach ($t['pencapaian_list'] as $pItem): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($pItem['desc']) ?></td>
                                                        <td class="q"><?= $pItem['val'] ?> <?= htmlspecialchars($pItem['unit']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <tr class="sum">
                                                <td>Jumlah Pencapaian</td>
                                                <td class="q"><?= $t['total_pencapaian'] ?> <?= htmlspecialchars($t['pencapaian_unit']) ?></td>
                                            </tr>
                                        </table>
                                        <?php if ($t['nota'] !== ''): ?>
                                            <p class="nota"><?= htmlspecialchars($t['nota']) ?></p>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Upah Calculation -->
                                    <td class="upah">
                                        <?php
                                            $pctTxt = (abs($t['peratus_kira'] - round($t['peratus_kira'])) < 0.005)
                                                ? number_format($t['peratus_kira'], 0)
                                                : number_format($t['peratus_kira'], 2);
                                            $sign = $t['peratus_exact'] ? '=' : '≈';
                                            $just = $t['justifikasi'];
                                            if ($just !== '' && $just[0] !== '-') $just = '- ' . $just;
                                        ?>
                                        <p class="u-h">Peratus Pencapaian Output:</p>

                                        <div class="fx wide">
                                            <span class="n">Pencapaian</span>
                                            <span class="l"></span><span class="m">× 100%</span>
                                            <span class="d">Sasaran</span>
                                        </div>

                                        <div class="fx gapTop">
                                            <span class="n"><?= $t['total_pencapaian'] ?> <?= htmlspecialchars($t['pencapaian_unit']) ?></span>
                                            <span class="l"></span><span class="m">× 100%</span>
                                            <span class="d"><?= $t['total_sasaran'] ?> <?= htmlspecialchars($t['sasaran_unit']) ?></span>
                                        </div>

                                        <p class="u-b"><?= $sign ?> <?= $pctTxt ?>% (Had Maksimum 100%)</p>

                                        <p class="u-h gapTop">Upah layak dibayar :</p>
                                        <p class="u-b">= <?= $pctTxt ?>% × Upah Kasar<br>= <?= $pctTxt ?>% × RM <?= number_format($generatedData['upah_kasar'], 2) ?><br>= RM <?= number_format($t['upah_layak'], 2) ?></p>

                                        <?php if ($just !== ''): ?>
                                            <p class="u-j gapTop"><b>Justifikasi:</b><br><?= htmlspecialchars($just) ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (count($generatedData['tugasan']) > 1): ?>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align:right">JUMLAH UPAH LAYAK DIBAYAR</td>
                                <td>RM <?= number_format($jumlahUpah, 2) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>

                    <div class="sign">
                        <div>
                            <p>Saya mengesahkan bahawa segala maklumat yang dikemukakan adalah benar.</p>
                            <p class="lbl">Disediakan oleh :</p>
                            <div class="space"></div>
                            <p class="dots">..........................................................</p>
                            <p class="nm">(<?= htmlspecialchars($generatedData['nama']) ?>)</p>
                        </div>
                        <div>
                            <p>Saya mengesahkan bahawa Personel ini telah mencapai / tidak mencapai sasaran output yang ditetapkan.</p>
                            <p class="lbl">Disahkan oleh :</p>
                            <div class="space"></div>
                            <p class="dots">..........................................................</p>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <?php draft_markup(); ?>

    <!-- Modals -->
    <div id="loginModal" class="modal hidden no-print" onclick="if(event.target===this)toggleModal('loginModal')">
        <form method="POST" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
            <input type="hidden" name="action" value="login">
            <div class="modal-head">
                <h3 id="loginTitle">Log masuk</h3>
                <p>Masukkan username dan password akaun anda.</p>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="loginUser">Username</label>
                    <input type="text" id="loginUser" name="username" required class="input" autocomplete="username">
                </div>
                <div class="field">
                    <label for="loginPass">Password</label>
                    <input type="password" id="loginPass" name="password" required class="input" autocomplete="current-password">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="toggleModal('loginModal')" class="btn btn-outline">Batal</button>
                <button type="submit" class="btn btn-primary">Log masuk</button>
            </div>
        </form>
    </div>

    <div id="registerModal" class="modal hidden no-print" onclick="if(event.target===this)toggleModal('registerModal')">
        <form method="POST" role="dialog" aria-modal="true" aria-labelledby="regTitle">
            <input type="hidden" name="action" value="register">
            <div class="modal-head">
                <h3 id="regTitle">Daftar akaun percuma</h3>
                <p>Akaun membolehkan anda menjana borang tanpa had percubaan.</p>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="regUser">Username</label>
                    <input type="text" id="regUser" name="username" required class="input" autocomplete="username">
                </div>
                <div class="field">
                    <label for="regPass">Password</label>
                    <input type="password" id="regPass" name="password" required class="input" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" onclick="toggleModal('registerModal')" class="btn btn-outline">Batal</button>
                <button type="submit" class="btn btn-primary">Daftar sekarang</button>
            </div>
        </form>
    </div>

    <!-- Frontend Script -->
    <script>
        let taskCounter = 0;

        const tugasanOptions = [
            "Pengurusan fail",
            "Pengurusan dokumen",
            "Penyambut tetamu",
            "Pertanyaan awam",
            "Urusan kaunter",
            "Penyediaan mesyuarat",
            "Logistik bekalan",
            "Kemas kini rekod kesihatan",
            "Pemantauan Rangkaian",
            "Akaun Pengguna & Emel",
            "Pembangunan/Pengaturcaraan Ringan",
            "Lain-lain"
        ];

        const THEME_KEY = 'mystep-theme';

        function setTheme(name) {
            document.documentElement.setAttribute('data-theme', name);
            try { localStorage.setItem(THEME_KEY, name); } catch (e) {}
            syncSwatches();
        }

        function syncSwatches() {
            const current = document.documentElement.getAttribute('data-theme') || 'sage';
            document.querySelectorAll('[data-theme-btn]').forEach(b => {
                b.setAttribute('aria-pressed', b.dataset.themeBtn === current ? 'true' : 'false');
            });
        }

        function toggleModal(id) {
            document.getElementById(id).classList.toggle('hidden');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ['loginModal', 'registerModal'].forEach(id => {
                    const m = document.getElementById(id);
                    if (m && !m.classList.contains('hidden')) m.classList.add('hidden');
                });
            }
        });

        function handleTugasanChange(taskId) {
            const select = document.getElementById(`jenis-select-${taskId}`);
            const textareaDiv = document.getElementById(`custom-tugasan-div-${taskId}`);
            textareaDiv.classList.toggle('hidden', select.value !== 'Lain-lain');
            updateSummary();
        }

        function toggleOutputMode(taskId, type, mode, btn) {
            const itemsDiv = document.getElementById(`${type}-items-div-${taskId}`);
            const textDiv = document.getElementById(`${type}-text-div-${taskId}`);
            const modeInput = document.getElementById(`${type}-mode-input-${taskId}`);

            modeInput.value = mode;
            itemsDiv.classList.toggle('hidden', mode === 'text');
            textDiv.classList.toggle('hidden', mode !== 'text');

            if (btn) {
                btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }
            calculateTask(taskId);
        }

        function addTaskRow() {
            taskCounter++;
            const wrapper = document.getElementById('tasksWrapper');
            const row = document.createElement('div');
            row.className = 'task-row';
            row.id = `task-row-${taskCounter}`;

            const optionsHtml = tugasanOptions.map(opt => `<option value="${opt}">${opt}</option>`).join('');

            row.innerHTML = `
                <div class="task-top">
                    <span class="task-bil">Tugasan 1</span>
                    <button type="button" onclick="removeTaskRow(${taskCounter})" class="btn btn-danger-text btn-sm">Padam tugasan</button>
                </div>

                <div class="task-body">
                    <div class="grid-2">
                        <div class="field">
                            <label for="jenis-select-${taskCounter}">Kategori tugasan</label>
                            <select name="tasks[${taskCounter}][jenis]" id="jenis-select-${taskCounter}" onchange="handleTugasanChange(${taskCounter})" class="select">
                                ${optionsHtml}
                            </select>
                        </div>
                        <div class="field">
                            <label>Skop tambahan <span class="hint">(pilihan, dipaparkan di bawah Sasaran Output sebelum jadual)</span></label>
                            <textarea name="tasks[${taskCounter}][deskripsi]" class="textarea" rows="2" placeholder="cth: Membuat pendaftaran, pertukaran atau pertambahan fasiliti, dan pengemaskinian kata laluan akaun pengguna MPIS berdasarkan jadual harian/mingguan:"></textarea>
                        </div>
                    </div>

                    <div id="custom-tugasan-div-${taskCounter}" class="custom-box hidden">
                        <div class="field">
                            <label>Nyatakan skop tugasan lain-lain</label>
                            <textarea name="tasks[${taskCounter}][jenis_custom]" class="textarea" rows="2" placeholder="Masukkan butiran tugasan spesifik anda"></textarea>
                        </div>
                    </div>

                    <div class="grid-2">
                        <!-- Sasaran -->
                        <div class="panel">
                            <div class="panel-head">
                                <span class="panel-title">Sasaran output</span>
                                <div class="seg">
                                    <button type="button" class="active" onclick="toggleOutputMode(${taskCounter}, 'sasaran', 'items', this)">Jadual</button>
                                    <button type="button" onclick="toggleOutputMode(${taskCounter}, 'sasaran', 'text', this)">Teks</button>
                                </div>
                            </div>
                            <div class="panel-body">
                                <input type="hidden" name="tasks[${taskCounter}][sasaran_mode]" id="sasaran-mode-input-${taskCounter}" value="items">

                                <div id="sasaran-items-div-${taskCounter}">
                                    <div id="sasaran-container-${taskCounter}"></div>
                                    <button type="button" class="link-btn" onclick="addSasaranItem(${taskCounter})">+ Tambah item</button>
                                </div>

                                <div id="sasaran-text-div-${taskCounter}" class="hidden">
                                    <textarea name="tasks[${taskCounter}][sasaran_text]" class="textarea" rows="3" placeholder="Tulis butiran sasaran di sini"></textarea>
                                    <div class="qty-row">
                                        <span>Jumlah kuantiti</span>
                                        <input type="number" step="1" name="tasks[${taskCounter}][sasaran_manual_qty]" class="input sasaran-manual-qty" placeholder="0" oninput="calculateTask(${taskCounter})">
                                        <input type="text" name="tasks[${taskCounter}][sasaran_manual_unit]" class="input unit" value="unit" placeholder="unit">
                                    </div>
                                </div>
                            </div>
                            <div class="panel-foot">
                                <span>Jumlah sasaran</span>
                                <b id="total-sasaran-txt-${taskCounter}">0</b>
                            </div>
                        </div>

                        <!-- Pencapaian -->
                        <div class="panel">
                            <div class="panel-head">
                                <span class="panel-title">Pencapaian output</span>
                                <div class="seg">
                                    <button type="button" class="active" onclick="toggleOutputMode(${taskCounter}, 'pencapaian', 'items', this)">Jadual</button>
                                    <button type="button" onclick="toggleOutputMode(${taskCounter}, 'pencapaian', 'text', this)">Teks</button>
                                </div>
                            </div>
                            <div class="panel-body">
                                <input type="hidden" name="tasks[${taskCounter}][pencapaian_mode]" id="pencapaian-mode-input-${taskCounter}" value="items">

                                <div id="pencapaian-items-div-${taskCounter}">
                                    <div id="pencapaian-container-${taskCounter}"></div>
                                    <button type="button" class="link-btn" onclick="addPencapaianItem(${taskCounter})">+ Tambah item</button>
                                </div>

                                <div id="pencapaian-text-div-${taskCounter}" class="hidden">
                                    <textarea name="tasks[${taskCounter}][pencapaian_text]" class="textarea" rows="3" placeholder="Tulis butiran pencapaian di sini"></textarea>
                                    <div class="qty-row">
                                        <span>Jumlah kuantiti</span>
                                        <input type="number" step="1" name="tasks[${taskCounter}][pencapaian_manual_qty]" class="input pencapaian-manual-qty" placeholder="0" oninput="calculateTask(${taskCounter})">
                                        <input type="text" name="tasks[${taskCounter}][pencapaian_manual_unit]" class="input unit" value="unit" placeholder="unit">
                                    </div>
                                </div>
                            </div>
                            <div class="panel-foot">
                                <span>Jumlah pencapaian</span>
                                <b id="total-pencapaian-txt-${taskCounter}">0</b>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Nota bawah jadual pencapaian <span class="hint">(pilihan, cth: lampiran)</span></label>
                        <textarea name="tasks[${taskCounter}][nota]" class="textarea" rows="2" placeholder="cth: Lampiran 1: Senarai Rekod Pendaftaran, pertukaran fasiliti, dan pengemaskinian kata laluan akaun pengguna MPIS pada bulan September."></textarea>
                    </div>

                    <div class="result-strip">
                        <div>
                            <div class="result-label">Peratus pencapaian output</div>
                            <div class="result-value"><span id="percent-val-${taskCounter}">0%</span></div>
                            <div class="bar"><i id="bar-${taskCounter}"></i></div>
                        </div>
                        <div>
                            <div class="result-label">Upah layak dibayar</div>
                            <div class="result-value money" id="fee-val-${taskCounter}">RM 0.00</div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Justifikasi <span class="hint">(pilihan, jika sasaran tidak dicapai)</span></label>
                        <input type="text" name="tasks[${taskCounter}][justifikasi]" class="input" placeholder="Nyatakan sebab sasaran tidak dapat disiapkan">
                    </div>
                </div>
            `;
            wrapper.appendChild(row);

            addSasaranItem(taskCounter);
            addPencapaianItem(taskCounter);
            reindexBil();
            updateSummary();
        }

        function removeTaskRow(id) {
            const elem = document.getElementById(`task-row-${id}`);
            if (elem) elem.remove();
            reindexBil();
            updateSummary();
        }

        function reindexBil() {
            document.querySelectorAll('.task-row').forEach((row, index) => {
                row.querySelector('.task-bil').innerText = `Tugasan ${index + 1}`;
            });
        }

        let sasaranCounter = 0;
        function addSasaranItem(taskId) {
            sasaranCounter++;
            const container = document.getElementById(`sasaran-container-${taskId}`);
            const item = document.createElement('div');
            item.className = 'item-row sasaran-item';
            item.id = `sasaran-item-${sasaranCounter}`;
            item.innerHTML = `
                <input type="text" name="tasks[${taskId}][sasaran][${sasaranCounter}][desc]" class="input" placeholder="Keterangan (cth: 3-7 Ogos)">
                <input type="number" step="1" name="tasks[${taskId}][sasaran][${sasaranCounter}][val]" class="input sasaran-val" placeholder="Kuantiti" oninput="calculateTask(${taskId})">
                <input type="text" name="tasks[${taskId}][sasaran][${sasaranCounter}][unit]" class="input" value="set" aria-label="Unit">
                <button type="button" class="icon-btn" aria-label="Buang item" onclick="removeSasaranItem('${item.id}', ${taskId})">✕</button>
            `;
            container.appendChild(item);
        }

        function removeSasaranItem(itemId, taskId) {
            const elem = document.getElementById(itemId);
            if (elem) elem.remove();
            calculateTask(taskId);
        }

        let pencapaianCounter = 0;
        function addPencapaianItem(taskId) {
            pencapaianCounter++;
            const container = document.getElementById(`pencapaian-container-${taskId}`);
            const item = document.createElement('div');
            item.className = 'item-row pencapaian-item';
            item.id = `pencapaian-item-${pencapaianCounter}`;
            item.innerHTML = `
                <input type="text" name="tasks[${taskId}][pencapaian][${pencapaianCounter}][desc]" class="input" placeholder="Keterangan (cth: 3-7 Ogos)">
                <input type="number" step="1" name="tasks[${taskId}][pencapaian][${pencapaianCounter}][val]" class="input pencapaian-val" placeholder="Kuantiti" oninput="calculateTask(${taskId})">
                <input type="text" name="tasks[${taskId}][pencapaian][${pencapaianCounter}][unit]" class="input" value="set" aria-label="Unit">
                <button type="button" class="icon-btn" aria-label="Buang item" onclick="removePencapaianItem('${item.id}', ${taskId})">✕</button>
            `;
            container.appendChild(item);
        }

        function removePencapaianItem(itemId, taskId) {
            const elem = document.getElementById(itemId);
            if (elem) elem.remove();
            calculateTask(taskId);
        }

        function calculateTask(taskId) {
            const row = document.getElementById(`task-row-${taskId}`);
            if (!row) return;
            const sasaranMode = document.getElementById(`sasaran-mode-input-${taskId}`).value;
            const pencapaianMode = document.getElementById(`pencapaian-mode-input-${taskId}`).value;

            let totalSasaran = 0;
            if (sasaranMode === 'text') {
                totalSasaran = parseFloat(row.querySelector('.sasaran-manual-qty').value) || 0;
            } else {
                row.querySelectorAll('.sasaran-val').forEach(input => {
                    totalSasaran += parseFloat(input.value) || 0;
                });
            }

            let totalPencapaian = 0;
            if (pencapaianMode === 'text') {
                totalPencapaian = parseFloat(row.querySelector('.pencapaian-manual-qty').value) || 0;
            } else {
                row.querySelectorAll('.pencapaian-val').forEach(input => {
                    totalPencapaian += parseFloat(input.value) || 0;
                });
            }

            document.getElementById(`total-sasaran-txt-${taskId}`).innerText = totalSasaran;
            document.getElementById(`total-pencapaian-txt-${taskId}`).innerText = totalPencapaian;

            const upahKasar = parseFloat(document.getElementById('upahKasar').value) || 0;

            const percentage = totalSasaran > 0 ? (totalPencapaian / totalSasaran) * 100 : 0;
            const rawPercentage = Math.round(percentage);
            const cappedPercentage = Math.min(percentage, 100);
            const pctRounded = Math.round(cappedPercentage * 100) / 100;
            const fee = (pctRounded / 100) * upahKasar;

            document.getElementById(`percent-val-${taskId}`).innerHTML =
                `${Math.round(percentage * 100) / 100}%` + (percentage > 100 ? '<small>Had maksimum 100%</small>' : '');
            document.getElementById(`fee-val-${taskId}`).innerText = `RM ${fee.toFixed(2)}`;
            document.getElementById(`bar-${taskId}`).style.width = `${cappedPercentage}%`;

            row.dataset.fee = fee;
            updateSummary();
        }

        function calculateAllTasks() {
            document.querySelectorAll('.task-row').forEach(row => {
                calculateTask(row.id.replace('task-row-', ''));
            });
        }

        function updateSummary() {
            const list = document.getElementById('summaryList');
            const totalEl = document.getElementById('summaryTotal');
            if (!list || !totalEl) return;

            let total = 0;
            let html = '';
            document.querySelectorAll('.task-row').forEach((row, i) => {
                const id = row.id.replace('task-row-', '');
                const sel = document.getElementById(`jenis-select-${id}`);
                const fee = parseFloat(row.dataset.fee) || 0;
                total += fee;
                const label = sel ? sel.value : '';
                html += `<li><span>${i + 1}. ${label.replace(/</g, '&lt;')}</span><span>RM ${fee.toFixed(2)}</span></li>`;
            });
            list.innerHTML = html || '<li><span>Belum ada tugasan</span><span>-</span></li>';
            totalEl.innerText = `RM ${total.toFixed(2)}`;
        }

        window.onload = function () {
            syncSwatches();
            initDrafts();
            <?php if ($generatedData): ?>
            const hasil = document.getElementById('hasil');
            if (hasil) hasil.scrollIntoView({ behavior: 'smooth', block: 'start' });
            <?php endif; ?>
        };
    </script>
    <?php draft_script(); ?>
</body>
</html>
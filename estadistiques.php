<?php
/**
 * ARS Advocats i Assessors — Panell d'Estadístiques Anònimes (RGPD 100%)
 * - POST: Registra comptadors anònims (sense IPs, sense cookies, sense textos).
 * - GET (?k=SECRET): Mostra el panell corporatiu privat (noindex, nofollow).
 */

header('X-Robots-Tag: noindex, nofollow, noarchive', true);

define('ARS_STATS_KEY', 'ars2026-8f4c9a2e');
define('ARS_DATA_DIR', __DIR__ . '/stats-data');
define('ARS_DATA_FILE', ARS_DATA_DIR . '/metrics.json');

$ALLOWED_EVENTS = [
    'visit_google',
    'visit_ai',
    'ai_chatgpt',
    'ai_perplexity',
    'ai_gemini',
    'ai_claude',
    'ai_other',
    'visit_social',
    'visit_direct',
    'chat_open',
    'chat_message',
    'chat_to_wa',
    'chat_to_form',
    'web_wa',
    'web_form_direct',
    'web_form_from_chat',
];

function ars_load_data($file) {
    if (!file_exists($file)) {
        return ['totals' => [], 'months' => [], 'days' => []];
    }
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        return ['totals' => [], 'months' => [], 'days' => []];
    }
    $data['totals'] = isset($data['totals']) && is_array($data['totals']) ? $data['totals'] : [];
    $data['months'] = isset($data['months']) && is_array($data['months']) ? $data['months'] : [];
    $data['days']   = isset($data['days'])   && is_array($data['days'])   ? $data['days']   : [];
    return $data;
}

// =========================================================================
// 1. POST: REGISTRE ANÒNIM D'ESDEVENIMENTS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar origen (només arsadvocats.com o localhost)
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $source  = $origin ?: $referer;
    $host    = $source ? (parse_url($source, PHP_URL_HOST) ?: '') : '';
    $validHost = in_array($host, ['www.arsadvocats.com', 'arsadvocats.com', 'localhost', '127.0.0.1'], true);

    if (!$validHost) {
        http_response_code(403);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);
    $events = [];

    if (is_array($payload)) {
        if (isset($payload['events']) && is_array($payload['events'])) {
            $events = $payload['events'];
        } elseif (isset($payload['event']) && is_string($payload['event'])) {
            $events = [$payload['event']];
        }
    } elseif (isset($_POST['event']) && is_string($_POST['event'])) {
        $events = [$_POST['event']];
    }

    // Filtrar només esdeveniments permesos (màxim 4 per petició)
    $validEvents = [];
    foreach (array_slice($events, 0, 4) as $ev) {
        if (is_string($ev) && in_array($ev, $ALLOWED_EVENTS, true)) {
            $validEvents[] = $ev;
        }
    }

    if (empty($validEvents)) {
        http_response_code(400);
        exit;
    }

    if (!is_dir(ARS_DATA_DIR)) {
        @mkdir(ARS_DATA_DIR, 0750, true);
    }

    $fp = @fopen(ARS_DATA_FILE, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        rewind($fp);
        $content = stream_get_contents($fp);
        $data = json_decode($content ?: '', true);
        if (!is_array($data)) {
            $data = ['totals' => [], 'months' => [], 'days' => []];
        }
        $data['totals'] = isset($data['totals']) && is_array($data['totals']) ? $data['totals'] : [];
        $data['months'] = isset($data['months']) && is_array($data['months']) ? $data['months'] : [];
        $data['days']   = isset($data['days'])   && is_array($data['days'])   ? $data['days']   : [];

        $monthKey = date('Y-m');
        $dayKey   = date('Y-m-d');

        if (!isset($data['months'][$monthKey]) || !is_array($data['months'][$monthKey])) {
            $data['months'][$monthKey] = [];
        }
        if (!isset($data['days'][$dayKey]) || !is_array($data['days'][$dayKey])) {
            $data['days'][$dayKey] = [];
        }

        foreach ($validEvents as $ev) {
            $data['totals'][$ev]            = (int)($data['totals'][$ev] ?? 0) + 1;
            $data['months'][$monthKey][$ev] = (int)($data['months'][$monthKey][$ev] ?? 0) + 1;
            $data['days'][$dayKey][$ev]     = (int)($data['days'][$dayKey][$ev] ?? 0) + 1;
        }

        // Retenir només els últims 60 dies a 'days' per mantenir l'arxiu lleuger
        if (count($data['days']) > 60) {
            ksort($data['days']);
            $data['days'] = array_slice($data['days'], -60, null, true);
        }

        $data['updated_at'] = date('c');

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    http_response_code(204);
    exit;
}

// =========================================================================
// 2. GET: PANELL CORPORATIU PRIVAT (PROTEGIT AMB CLAU)
// =========================================================================
$key = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
if (!hash_equals(ARS_STATS_KEY, $key)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$data = ars_load_data(ARS_DATA_FILE);
$currentMonth = date('Y-m');
$availableMonths = array_keys($data['months']);
if (!in_array($currentMonth, $availableMonths, true)) {
    $availableMonths[] = $currentMonth;
}
rsort($availableMonths);

$selectedPeriod = isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : $currentMonth;
if ($selectedPeriod !== 'all' && !in_array($selectedPeriod, $availableMonths, true)) {
    $selectedPeriod = $currentMonth;
}

$m = ($selectedPeriod === 'all')
    ? $data['totals']
    : ($data['months'][$selectedPeriod] ?? []);

$val = function ($k) use ($m) {
    return (int)($m[$k] ?? 0);
};

// Mètriques agregades
$vGoogle = $val('visit_google');
$vAi     = $val('visit_ai');
$vSocial = $val('visit_social');
$vDirect = $val('visit_direct');
$vTotal  = $vGoogle + $vAi + $vSocial + $vDirect;

$aiChatgpt    = $val('ai_chatgpt');
$aiPerplexity = $val('ai_perplexity');
$aiGemini     = $val('ai_gemini');
$aiClaude     = $val('ai_claude');
$aiOther      = $val('ai_other');

$chatOpen   = $val('chat_open');
$chatMsg    = $val('chat_message');
$chatToWa   = $val('chat_to_wa');
$chatToForm = $val('chat_to_form');

$webWa           = $val('web_wa');
$webFormDirect   = $val('web_form_direct');
$webFormFromChat = $val('web_form_from_chat');

$totalWa   = $webWa + $chatToWa;
$totalForm = $webFormDirect + $webFormFromChat;
$totalLeads = $totalWa + $totalForm;

$pct = function ($part, $whole) {
    if ($whole <= 0) return 0;
    return round(($part / $whole) * 100);
};

$chatActions = $chatToWa + $chatToForm;
$chatConvRate = $chatOpen > 0 ? round(($chatActions / $chatOpen) * 100, 1) : 0;

// Últims 14 dies per al gràfic diari
$last14 = [];
$maxDayVal = 1;
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dm = $data['days'][$d] ?? [];
    $dVisits = (int)($dm['visit_google'] ?? 0) + (int)($dm['visit_ai'] ?? 0) + (int)($dm['visit_social'] ?? 0) + (int)($dm['visit_direct'] ?? 0);
    $dChat   = (int)($dm['chat_message'] ?? 0);
    $dLeads  = (int)($dm['chat_to_wa'] ?? 0) + (int)($dm['web_wa'] ?? 0) + (int)($dm['web_form_direct'] ?? 0) + (int)($dm['web_form_from_chat'] ?? 0);
    $maxDayVal = max($maxDayVal, $dVisits, $dChat, $dLeads);
    $last14[] = [
        'label'  => date('d/m', strtotime($d)),
        'visits' => $dVisits,
        'chat'   => $dChat,
        'leads'  => $dLeads,
    ];
}

$monthNames = [
    '01' => 'Gen', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
    '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
    '09' => 'Set', '10' => 'Oct', '11' => 'Nov', '12' => 'Des',
];
$formatMonth = function ($ym) use ($monthNames) {
    $parts = explode('-', $ym);
    if (count($parts) !== 2) return $ym;
    return ($monthNames[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
};

$updatedLabel = !empty($data['updated_at'])
    ? date('d/m/Y · H:i', strtotime($data['updated_at']))
    : 'Sense activitat registrada encara';

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isPrettyUrl = (strpos($reqPath, '/panell-ars') === 0);
$buildUrl = function ($period) use ($isPrettyUrl, $key) {
    if ($isPrettyUrl) {
        return '/panell-ars?m=' . urlencode($period);
    }
    return '?k=' . urlencode($key) . '&m=' . urlencode($period);
};
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>Estadístiques Web & Assistent | ARS Advocats i Assessors</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #fbf7f6;
      --surface: #ffffff;
      --surface-soft: #f5eeed;
      --accent: #c10c13;
      --accent-dark: #7a070c;
      --accent-deep: #540307;
      --accent-soft: #dda09f;
      --text-main: #1c1314;
      --text-body: #3c3233;
      --text-muted: #6e6061;
      --border: rgba(40, 20, 20, 0.09);
      --shadow: 0 10px 30px rgba(40, 10, 15, 0.06);
      --serif: "Cormorant Garamond", Georgia, serif;
      --sans: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--sans);
      background: var(--bg);
      color: var(--text-main);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
      padding-bottom: 48px;
    }

    /* Top Brand Bar */
    .topbar {
      background: linear-gradient(135deg, #1c1314 0%, #540307 100%);
      color: #ffffff;
      padding: 20px 20px 24px;
      border-bottom: 3px solid var(--accent);
    }

    .wrap {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 18px;
    }

    .topbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-mark {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: #ffffff;
      padding: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      flex-shrink: 0;
    }

    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }

    .brand h1 {
      font-family: var(--serif);
      font-size: clamp(1.35rem, 3vw, 1.8rem);
      font-weight: 700;
      line-height: 1.1;
      letter-spacing: 0.01em;
    }

    .brand-sub {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.75);
      margin-top: 3px;
    }

    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 7px 13px;
      border-radius: 9999px;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.18);
      font-size: 0.75rem;
      color: rgba(255,255,255,0.9);
      white-space: nowrap;
    }

    .meta-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #4ade80;
    }

    /* Period Filter Bar */
    .period-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin: 22px 0 20px;
    }

    .period-pills {
      display: flex;
      align-items: center;
      gap: 8px;
      overflow-x: auto;
      padding-bottom: 4px;
      -webkit-overflow-scrolling: touch;
      max-width: 100%;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      padding: 7px 14px;
      border-radius: 9999px;
      font-size: 0.8rem;
      font-weight: 600;
      text-decoration: none;
      color: var(--text-body);
      background: var(--surface);
      border: 1px solid var(--border);
      white-space: nowrap;
      transition: all 0.2s ease;
    }

    .pill:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    .pill.active {
      background: var(--accent);
      color: #ffffff;
      border-color: var(--accent);
      box-shadow: 0 4px 12px rgba(193, 12, 19, 0.22);
    }

    .refresh-link {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--accent-dark);
      text-decoration: none;
      padding: 6px 10px;
      border-radius: 8px;
      background: var(--surface-soft);
    }

    /* KPI Grid */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 20px;
    }

    .kpi-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-top: 3px solid var(--accent-soft);
      border-radius: 12px;
      padding: 18px 18px 16px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .kpi-card--primary {
      border-top-color: var(--accent);
    }

    .kpi-label {
      font-size: 0.74rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
    }

    .kpi-value {
      font-size: clamp(1.85rem, 3.5vw, 2.35rem);
      font-weight: 700;
      color: var(--text-main);
      font-variant-numeric: tabular-nums;
      line-height: 1.1;
    }

    .kpi-sub {
      font-size: 0.76rem;
      color: var(--text-muted);
      margin-top: 2px;
    }

    .kpi-sub strong {
      color: var(--accent-dark);
      font-weight: 600;
    }

    /* Two-column dashboard layout */
    .panels-grid {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 16px;
      margin-bottom: 16px;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      box-shadow: var(--shadow);
    }

    .panel-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 16px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 12px;
    }

    .panel-title {
      font-family: var(--serif);
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--text-main);
    }

    .panel-badge {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--accent-dark);
      background: var(--surface-soft);
      padding: 3px 9px;
      border-radius: 9999px;
      white-space: nowrap;
    }

    /* Progress Bars */
    .metric-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .metric-row-top {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 8px;
      font-size: 0.84rem;
      margin-bottom: 6px;
    }

    .metric-name {
      font-weight: 600;
      color: var(--text-main);
    }

    .metric-num {
      font-variant-numeric: tabular-nums;
      font-weight: 700;
      color: var(--text-main);
      white-space: nowrap;
    }

    .metric-num span {
      font-weight: 500;
      color: var(--text-muted);
      font-size: 0.76rem;
      margin-left: 4px;
    }

    .bar-track {
      height: 9px;
      background: var(--surface-soft);
      border-radius: 9999px;
      overflow: hidden;
    }

    .bar-fill {
      height: 100%;
      border-radius: 9999px;
      background: linear-gradient(90deg, var(--accent) 0%, var(--accent-dark) 100%);
      transition: width 0.5s ease;
    }

    .bar-fill--ai {
      background: linear-gradient(90deg, #1c1314 0%, #7a070c 100%);
    }

    .bar-fill--soft {
      background: #c78b8a;
    }

    .bar-fill--muted {
      background: #b5a6a7;
    }

    /* AI Engine Chips */
    .ai-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }

    .ai-chip {
      font-size: 0.71rem;
      font-weight: 600;
      padding: 3px 9px;
      border-radius: 9999px;
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--text-body);
      font-variant-numeric: tabular-nums;
    }

    /* Split Comparison Box */
    .split-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px dashed var(--border);
    }

    .split-box {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px;
    }

    .split-box-title {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .split-box-val {
      font-size: 1.3rem;
      font-weight: 700;
      margin: 4px 0 2px;
      font-variant-numeric: tabular-nums;
    }

    .split-box-detail {
      font-size: 0.74rem;
      color: var(--text-muted);
    }

    /* 14-day Chart */
    .chart-scroll {
      overflow-x: auto;
      padding-bottom: 4px;
      -webkit-overflow-scrolling: touch;
    }

    .chart-bars {
      display: grid;
      grid-template-columns: repeat(14, minmax(36px, 1fr));
      gap: 8px;
      align-items: end;
      height: 150px;
      padding-top: 16px;
    }

    .day-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      height: 100%;
      justify-content: flex-end;
      gap: 6px;
    }

    .day-stack {
      width: 100%;
      height: 112px;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 3px;
    }

    .vbar {
      width: 9px;
      min-height: 3px;
      border-radius: 4px 4px 1px 1px;
    }

    .vbar--visits { background: #d9b8b7; }
    .vbar--chat   { background: var(--accent); }
    .vbar--leads  { background: #1c1314; }

    .day-label {
      font-size: 0.68rem;
      color: var(--text-muted);
      font-variant-numeric: tabular-nums;
    }

    .chart-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      margin-top: 12px;
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .legend-dot {
      width: 9px;
      height: 9px;
      border-radius: 3px;
    }

    .footer-note {
      margin-top: 22px;
      text-align: center;
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    /* Responsive Mobile */
    @media (max-width: 860px) {
      .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .panels-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .topbar { padding: 16px 0 18px; }
      .wrap { padding: 0 14px; }
      .kpi-grid { gap: 10px; }
      .kpi-card { padding: 14px; }
      .panel { padding: 16px; }
      .split-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <div class="brand">
        <div class="brand-mark">
          <img src="/assets/images/apple-touch-icon.png" alt="ARS Advocats">
        </div>
        <div>
          <h1>ARS Advocats · Estadístiques</h1>
          <div class="brand-sub">Rendiment web, Assistent IA i captació de consultes</div>
        </div>
      </div>
      <div class="meta-pill">
        <span class="meta-dot"></span>
        <span>Actualitzat: <?= htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
  </header>

  <main class="wrap">
    <div class="period-bar">
      <nav class="period-pills" aria-label="Selector de període">
        <a href="<?= htmlspecialchars($buildUrl('all'), ENT_QUOTES, 'UTF-8') ?>" class="pill <?= $selectedPeriod === 'all' ? 'active' : '' ?>">Històric total</a>
        <?php foreach ($availableMonths as $ym): ?>
          <a href="<?= htmlspecialchars($buildUrl($ym), ENT_QUOTES, 'UTF-8') ?>" class="pill <?= $selectedPeriod === $ym ? 'active' : '' ?>">
            <?= htmlspecialchars($formatMonth($ym), ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <a href="<?= htmlspecialchars($buildUrl($selectedPeriod), ENT_QUOTES, 'UTF-8') ?>" class="refresh-link">↻ Actualitzar</a>
    </div>

    <!-- 4 Hero KPIs -->
    <section class="kpi-grid">
      <article class="kpi-card kpi-card--primary">
        <div class="kpi-label">Visites web</div>
        <div class="kpi-value"><?= number_format($vTotal, 0, ',', '.') ?></div>
        <div class="kpi-sub">Google: <strong><?= $vGoogle ?></strong> · IA: <strong><?= $vAi ?></strong></div>
      </article>

      <article class="kpi-card kpi-card--primary">
        <div class="kpi-label">Consultes al Chatbot</div>
        <div class="kpi-value"><?= number_format($chatMsg, 0, ',', '.') ?></div>
        <div class="kpi-sub">Obertures del xat: <strong><?= $chatOpen ?></strong></div>
      </article>

      <article class="kpi-card">
        <div class="kpi-label">Clics a WhatsApp</div>
        <div class="kpi-value"><?= number_format($totalWa, 0, ',', '.') ?></div>
        <div class="kpi-sub">Via Chatbot: <strong><?= $chatToWa ?></strong> · Web directe: <strong><?= $webWa ?></strong></div>
      </article>

      <article class="kpi-card">
        <div class="kpi-label">Formularis enviats</div>
        <div class="kpi-value"><?= number_format($totalForm, 0, ',', '.') ?></div>
        <div class="kpi-sub">Via Chatbot: <strong><?= $webFormFromChat ?></strong> · Directes: <strong><?= $webFormDirect ?></strong></div>
      </article>
    </section>

    <!-- Main Breakdown Panels -->
    <section class="panels-grid">
      <!-- Left: Chatbot Funnel & Conversions -->
      <article class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Rendiment de l'Assistent IA</h2>
          <span class="panel-badge">Conversió xat: <?= $chatConvRate ?>%</span>
        </div>

        <div class="metric-list">
          <div>
            <div class="metric-row-top">
              <span class="metric-name">1. Obertures de la finestra del xat</span>
              <span class="metric-num"><?= $chatOpen ?></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $chatOpen > 0 ? 100 : 0 ?>%"></div></div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">2. Consultes enviades a l'assistent</span>
              <span class="metric-num"><?= $chatMsg ?></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $chatOpen > 0 ? min(100, $pct($chatMsg, max($chatOpen, $chatMsg))) : ($chatMsg > 0 ? 100 : 0) ?>%"></div></div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">3. Pas a WhatsApp des del xat</span>
              <span class="metric-num"><?= $chatToWa ?> <span>(<?= $pct($chatToWa, $chatOpen) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill bar-fill--ai" style="width: <?= min(100, $pct($chatToWa, max(1, $chatOpen))) ?>%"></div></div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">4. Pas al Formulari des del xat</span>
              <span class="metric-num"><?= $chatToForm ?> <span>(<?= $pct($chatToForm, $chatOpen) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill bar-fill--soft" style="width: <?= min(100, $pct($chatToForm, max(1, $chatOpen))) ?>%"></div></div>
          </div>
        </div>

        <div class="split-grid">
          <div class="split-box">
            <div class="split-box-title">Total contactes generats</div>
            <div class="split-box-val"><?= $totalLeads ?></div>
            <div class="split-box-detail"><?= $totalWa ?> WhatsApp · <?= $totalForm ?> Formularis</div>
          </div>
          <div class="split-box">
            <div class="split-box-title">Origen dels contactes</div>
            <div class="split-box-val"><?= $pct($chatToWa + $webFormFromChat, $totalLeads) ?>% xat</div>
            <div class="split-box-detail"><?= $chatToWa + $webFormFromChat ?> via Chatbot · <?= $webWa + $webFormDirect ?> web directe</div>
          </div>
        </div>
      </article>

      <!-- Right: Traffic Sources (Google vs AI Search) -->
      <article class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Origen de les visites</h2>
          <span class="panel-badge">1 vegada per sessió</span>
        </div>

        <div class="metric-list">
          <div>
            <div class="metric-row-top">
              <span class="metric-name">Buscadors clàssics (Google, Bing...)</span>
              <span class="metric-num"><?= $vGoogle ?> <span>(<?= $pct($vGoogle, $vTotal) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $pct($vGoogle, $vTotal) ?>%"></div></div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">Buscadors i Assistents IA</span>
              <span class="metric-num"><?= $vAi ?> <span>(<?= $pct($vAi, $vTotal) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill bar-fill--ai" style="width: <?= $pct($vAi, $vTotal) ?>%"></div></div>
            <div class="ai-chips">
              <span class="ai-chip">ChatGPT: <?= $aiChatgpt ?></span>
              <span class="ai-chip">Perplexity: <?= $aiPerplexity ?></span>
              <span class="ai-chip">Gemini: <?= $aiGemini ?></span>
              <span class="ai-chip">Claude: <?= $aiClaude ?></span>
              <?php if ($aiOther > 0): ?><span class="ai-chip">Altres IA: <?= $aiOther ?></span><?php endif; ?>
            </div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">Xarxes socials i directoris</span>
              <span class="metric-num"><?= $vSocial ?> <span>(<?= $pct($vSocial, $vTotal) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill bar-fill--soft" style="width: <?= $pct($vSocial, $vTotal) ?>%"></div></div>
          </div>

          <div>
            <div class="metric-row-top">
              <span class="metric-name">Directe / Apps / Altres</span>
              <span class="metric-num"><?= $vDirect ?> <span>(<?= $pct($vDirect, $vTotal) ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill bar-fill--muted" style="width: <?= $pct($vDirect, $vTotal) ?>%"></div></div>
          </div>
        </div>
      </article>
    </section>

    <!-- 14-Day Activity Chart -->
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-title">Activitat dels últims 14 dies</h2>
        <span class="panel-badge">Evolució diària</span>
      </div>
      <div class="chart-scroll">
        <div class="chart-bars">
          <?php foreach ($last14 as $day): ?>
            <?php
              $hV = $day['visits'] > 0 ? max(8, round(($day['visits'] / $maxDayVal) * 100)) : 3;
              $hC = $day['chat']   > 0 ? max(8, round(($day['chat']   / $maxDayVal) * 100)) : 3;
              $hL = $day['leads']  > 0 ? max(8, round(($day['leads']  / $maxDayVal) * 100)) : 3;
            ?>
            <div class="day-col" title="<?= $day['label'] ?> — Visites: <?= $day['visits'] ?> · Xat: <?= $day['chat'] ?> · Contactes: <?= $day['leads'] ?>">
              <div class="day-stack">
                <div class="vbar vbar--visits" style="height: <?= $hV ?>%"></div>
                <div class="vbar vbar--chat"   style="height: <?= $hC ?>%"></div>
                <div class="vbar vbar--leads"  style="height: <?= $hL ?>%"></div>
              </div>
              <span class="day-label"><?= $day['label'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="chart-legend">
        <span class="legend-item"><span class="legend-dot" style="background:#d9b8b7"></span> Visites</span>
        <span class="legend-item"><span class="legend-dot" style="background:#c10c13"></span> Consultes Chatbot</span>
        <span class="legend-item"><span class="legend-dot" style="background:#1c1314"></span> Contactes (WhatsApp + Formulari)</span>
      </div>
    </section>

    <p class="footer-note">
      ARS Advocats i Assessors Granollers, S.C.P. · Panell privat no indexat · Dades 100% anònimes sense cookies ni textos de converses.
    </p>
  </main>
</body>
</html>

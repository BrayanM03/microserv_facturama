<?php
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Strict'
]);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$autenticado = !empty($_SESSION['autenticado']);
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <meta name="theme-color" content="#f4f6f3">
    <title>Facturama | Consola Multiemisor</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #19221f;
            --muted: #72807a;
            --line: #e3e9e5;
            --paper: #ffffff;
            --canvas: #f4f6f3;
            --green: #147a58;
            --green-dark: #0f5c43;
            --green-soft: #e6f3ec;
            --lime: #c9ed70;
            --red: #b8413e;
            --red-soft: #fff0ed;
            --amber: #94621b;
            --amber-soft: #fff4dd;
            --mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            --sans: "Avenir Next", Avenir, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: var(--canvas); font-family: var(--sans); font-size: 14px; }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        .shell { min-height: 100vh; display: grid; grid-template-columns: 236px minmax(0, 1fr); }
        .sidebar { background: #17231e; color: #e9f0eb; display: flex; flex-direction: column; min-height: 100vh; padding: 24px 15px 18px; }
        .brand { display: flex; align-items: center; gap: 11px; padding: 0 9px 30px; }
        .brand-mark { width: 35px; height: 35px; border-radius: 11px; display: grid; place-items: center; color: #14201a; background: var(--lime); font-weight: 850; font-size: 17px; }
        .brand-name { font-size: 14px; font-weight: 750; letter-spacing: .2px; }
        .brand-sub { color: #9ba9a1; font-size: 10px; margin-top: 3px; letter-spacing: 1.1px; text-transform: uppercase; }
        .nav-caption { color: #809087; padding: 0 11px; margin: 8px 0 8px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .nav { display: grid; gap: 5px; }
        .nav button { border: 0; color: #b9c6be; background: transparent; text-align: left; padding: 11px 12px; border-radius: 7px; display: flex; gap: 12px; align-items: center; font-weight: 600; }
        .nav button:hover { background: #22332b; color: white; }
        .nav button.active { background: #2d4539; color: white; }
        .nav-icon { width: 18px; text-align: center; font-size: 16px; color: #a9d16c; }
        .side-bottom { margin-top: auto; border-top: 1px solid #304139; padding: 17px 7px 0; }
        .connection-label { display: flex; align-items: center; gap: 8px; color: #c1cdc5; font-size: 12px; }
        .connection-dot { width: 7px; height: 7px; background: #c9ed70; border-radius: 50%; }
        .connection-copy { color: #83928a; font-size: 11px; margin: 8px 0 16px 15px; }
        .side-bottom button { border: 1px solid #43554b; background: transparent; border-radius: 6px; color: #cbd5ce; padding: 8px 10px; font-size: 12px; width: 100%; }
        .main { min-width: 0; }
        .topbar { min-height: 68px; padding: 0 34px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.75); }
        .crumb { color: var(--muted); font-size: 12px; }
        .top-actions { display: flex; align-items: center; gap: 12px; }
        .account { border-left: 1px solid var(--line); padding-left: 13px; color: #53615b; font-size: 12px; }
        .content { max-width: 1500px; padding: 31px 34px 48px; margin: 0 auto; }
        .page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 25px; }
        h1 { font-size: 25px; line-height: 1.2; margin: 0; letter-spacing: 0; font-weight: 720; }
        .subtitle { color: var(--muted); margin: 7px 0 0; font-size: 13px; }
        .heading-right { display: flex; gap: 9px; align-items: center; }
        .btn { border: 1px solid #d7dfda; background: white; color: var(--ink); border-radius: 6px; min-height: 37px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 13px; font-weight: 650; font-size: 12px; }
        .btn:hover { border-color: #9aaba1; background: #fbfcfb; }
        .btn-primary { color: #fff; border-color: var(--green); background: var(--green); }
        .btn-primary:hover { color: #fff; background: var(--green-dark); border-color: var(--green-dark); }
        .btn-danger { color: var(--red); border-color: #f0d1cc; background: #fff; }
        .btn-icon { width: 36px; min-width: 36px; padding: 0; font-size: 16px; }
        .btn:disabled { opacity: .48; cursor: not-allowed; }
        .metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border: 1px solid var(--line); border-radius: 7px; background: var(--paper); margin-bottom: 20px; }
        .metric { padding: 17px 20px; min-width: 0; }
        .metric + .metric { border-left: 1px solid var(--line); }
        .metric-label { color: var(--muted); font-size: 11px; }
        .metric-value { font-size: 22px; font-weight: 720; margin-top: 7px; }
        .metric-note { color: #8a9690; font-size: 10px; margin-top: 3px; }
        .filter-panel { background: var(--paper); border: 1px solid var(--line); border-radius: 7px; padding: 15px; margin-bottom: 14px; }
        .filters { display: grid; grid-template-columns: 1fr .9fr 1.15fr .7fr .8fr .85fr .85fr auto; gap: 9px; align-items: end; }
        .assignment-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .field { min-width: 0; display: grid; gap: 6px; }
        .field label { color: #64716b; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .55px; }
        .field input, .field select { height: 36px; border: 1px solid #dce4df; border-radius: 5px; background: white; padding: 0 10px; color: var(--ink); min-width: 0; outline: none; }
        .field input:focus, .field select:focus { border-color: #57977c; box-shadow: 0 0 0 3px rgba(20,122,88,.1); }
        input::placeholder { color: #a0aaa5; }
        .filters-actions { display: flex; gap: 7px; }
        .table-wrap { overflow: auto; background: var(--paper); border: 1px solid var(--line); border-radius: 7px; }
        table { border-collapse: collapse; width: 100%; min-width: 810px; }
        th { background: #fafcfb; color: #76827c; font-size: 10px; text-align: left; text-transform: uppercase; letter-spacing: .65px; padding: 12px 14px; font-weight: 700; border-bottom: 1px solid var(--line); }
        td { padding: 13px 14px; border-bottom: 1px solid #edf0ee; vertical-align: middle; font-size: 12px; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #fbfdfb; }
        .folio { font-family: var(--mono); color: #33463c; font-size: 11px; }
        .client-name { max-width: 235px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 650; }
        .subcell { color: #87928c; font-size: 10px; margin-top: 4px; }
        .amount { text-align: right; white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }
        .status { display: inline-flex; align-items: center; gap: 6px; border-radius: 4px; padding: 5px 8px; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .status::before { content: ""; display: block; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .status-active { color: var(--green); background: var(--green-soft); }
        .status-canceled { color: var(--red); background: var(--red-soft); }
        .status-pending { color: var(--amber); background: var(--amber-soft); }
        .row-actions { display: flex; gap: 4px; justify-content: flex-end; }
        .link-button { border: 0; border-radius: 4px; padding: 6px; color: #496359; background: transparent; font-family: var(--mono); font-size: 10px; font-weight: 700; }
        .link-button:hover { background: var(--green-soft); color: var(--green-dark); }
        .pagination { display: flex; align-items: center; justify-content: space-between; padding: 13px 2px 0; color: var(--muted); font-size: 11px; }
        .page-controls { display: flex; gap: 6px; }
        .empty { padding: 48px 15px; text-align: center; color: var(--muted); }
        .empty strong { display: block; color: var(--ink); margin-bottom: 5px; font-size: 13px; }
        .view { display: none; }
        .view.active { display: block; animation: reveal .22s ease-out; }
        @keyframes reveal { from { opacity: .5; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .issuer-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
        .issuer-toolbar strong { font-size: 13px; }
        .issuer-table { min-width: 700px; }
        .rfc { font-family: var(--mono); font-size: 12px; font-weight: 700; }
        .issuer-card-list { display: grid; gap: 10px; }
        .issuer-mobile { display: none; border: 1px solid var(--line); background: white; border-radius: 7px; padding: 14px; }
        .issuer-mobile .mobile-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
        .toast { position: fixed; right: 22px; bottom: 20px; z-index: 20; max-width: min(420px, calc(100vw - 32px)); padding: 12px 15px; border-radius: 6px; background: #20332a; color: white; box-shadow: 0 8px 25px #14251c33; font-size: 12px; opacity: 0; pointer-events: none; transform: translateY(8px); transition: .2s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.error { background: #8f312e; }
        .loading { color: var(--muted); text-align: center; padding: 38px 15px; }
        .overlay { position: fixed; inset: 0; z-index: 30; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(16,28,22,.5); }
        .overlay.open { display: flex; }
        .dialog { width: min(580px, 100%); max-height: min(88vh, 820px); overflow: auto; background: white; border-radius: 8px; box-shadow: 0 22px 70px #06160f40; }
        .dialog-head { padding: 18px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; }
        .dialog-head h2 { margin: 0; font-size: 17px; }
        .dialog-head p { color: var(--muted); margin: 5px 0 0; font-size: 11px; }
        .dialog-body { padding: 20px; }
        .dialog-foot { padding: 14px 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-grid .wide { grid-column: 1 / -1; }
        .file-input { width: 100%; border: 1px dashed #cbd7d0 !important; height: auto !important; padding: 9px !important; background: #fbfcfb !important; }
        .help { color: var(--muted); font-size: 10px; line-height: 1.5; margin: 1px 0 0; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
        .detail-item { padding: 11px 0; border-bottom: 1px solid #edf0ee; min-width: 0; }
        .detail-key { text-transform: uppercase; color: var(--muted); font-size: 9px; letter-spacing: .65px; }
        .detail-value { font-size: 12px; font-weight: 620; margin-top: 5px; overflow-wrap: anywhere; }
        pre { white-space: pre-wrap; overflow-wrap: anywhere; font-family: var(--mono); font-size: 10px; background: #f6f8f6; padding: 12px; border-radius: 5px; max-height: 260px; overflow: auto; }
        .login-screen { min-height: 100vh; display: grid; place-items: center; padding: 20px; background: linear-gradient(135deg, #e9f0eb, #f8faf8 55%, #e3eee5); }
        .login-box { width: min(390px, 100%); background: white; border: 1px solid var(--line); border-radius: 8px; padding: 28px; box-shadow: 0 18px 55px #26413412; }
        .login-box .brand { padding: 0 0 25px; }
        .login-box h1 { font-size: 21px; }
        .login-box .subtitle { line-height: 1.5; margin-bottom: 22px; }
        .login-box .field { margin-bottom: 13px; }
        .login-box .field input { height: 42px; }
        .login-box .btn { width: 100%; height: 41px; margin-top: 5px; }
        .login-error { min-height: 18px; color: var(--red); font-size: 11px; margin-top: 10px; }
        @media (max-width: 1120px) {
            .filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .assignment-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters-actions { grid-column: 1 / -1; }
        }
        @media (max-width: 760px) {
            .shell { display: block; }
            .sidebar { min-height: auto; padding: 12px 14px 0; }
            .brand { padding: 0 5px 12px; }
            .nav-caption, .side-bottom { display: none; }
            .nav { display: flex; overflow-x: auto; gap: 4px; margin: 0 -2px; padding-bottom: 10px; }
            .nav button { white-space: nowrap; padding: 9px 11px; font-size: 11px; }
            .nav-icon { width: auto; }
            .topbar { min-height: 49px; padding: 0 17px; }
            .account { display: none; }
            .content { padding: 22px 15px 35px; }
            .page-heading { align-items: flex-start; flex-direction: column; margin-bottom: 18px; }
            h1 { font-size: 22px; }
            .metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .metric { padding: 13px 11px; }
            .metric-value { font-size: 19px; }
            .metric-note { font-size: 9px; }
            .filters { grid-template-columns: 1fr 1fr; }
            .filters .field:nth-child(3), .filters .field:nth-child(4) { grid-column: span 1; }
            .filters-actions .btn { flex: 1; }
            .issuer-table-wrap { display: none; }
            .issuer-mobile { display: block; }
            .issuer-mobile-list { display: grid; gap: 8px; padding: 12px; }
            .issuer-toolbar { padding: 12px; }
            .issuer-toolbar .btn { padding: 0 10px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .wide { grid-column: auto; }
            .detail-grid { grid-template-columns: 1fr; }
            .dialog-body { padding: 16px; }
        }
        @media (max-width: 420px) {
            .metrics { grid-template-columns: 1fr; }
            .metric { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
            .metric + .metric { border-left: 0; border-top: 1px solid var(--line); }
            .metric-value { margin: 0; }
            .metric-note { display: none; }
            .filters { grid-template-columns: 1fr; }
            .filters .field { grid-column: auto !important; }
            .heading-right { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
<?php if (!$autenticado): ?>
    <main class="login-screen">
        <form class="login-box" id="loginForm">
            <div class="brand"><div class="brand-mark">F</div><div><div class="brand-name">Facturama</div><div class="brand-sub">Consola Multiemisor</div></div></div>
            <h1>Acceso a tu cuenta</h1>
            <p class="subtitle">Ingresa la contraseña privada configurada para esta consola.</p>
            <div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div>
            <button class="btn btn-primary" type="submit">Entrar a la consola <span aria-hidden="true">→</span></button>
            <div class="login-error" id="loginError" role="alert"></div>
        </form>
    </main>
<?php else: ?>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand"><div class="brand-mark">F</div><div><div class="brand-name">Facturama</div><div class="brand-sub">Consola Multiemisor</div></div></div>
            <div class="nav-caption">Administración</div>
            <nav class="nav" aria-label="Navegación principal">
                <button class="active" data-view="invoices"><span class="nav-icon">▤</span>Facturas</button>
                <button data-view="issuers"><span class="nav-icon">◈</span>Emisores y CSD</button>
                <button data-view="assignments"><span class="nav-icon">↔</span>Asignaciones</button>
            </nav>
            <div class="side-bottom"><div class="connection-label"><span class="connection-dot"></span>Consola protegida</div><div class="connection-copy">Credenciales resguardadas en servidor</div><button id="logoutButton">Cerrar sesión</button></div>
        </aside>
        <main class="main">
            <header class="topbar"><div class="crumb">Administración <span aria-hidden="true">/</span> <strong id="crumbCurrent">Facturas</strong></div><div class="top-actions"><span class="account">API Multiemisor · Facturama</span><button class="btn btn-icon" id="refreshButton" title="Actualizar vista" aria-label="Actualizar vista">↻</button></div></header>
            <div class="content">
                <section class="view active" id="view-invoices">
                    <div class="page-heading"><div><h1>Facturas emitidas</h1><p class="subtitle">Consulta, descarga y administra los CFDI de todos tus emisores.</p></div><div class="heading-right"><button class="btn" id="invoiceRefresh">↻ <span>Actualizar</span></button></div></div>
                    <div class="metrics">
                        <div class="metric"><div><div class="metric-label">Resultados en esta página</div><div class="metric-note">10 CFDI por página en Facturama</div></div><div class="metric-value" id="metricTotal">—</div></div>
                        <div class="metric"><div><div class="metric-label">Vigentes en esta página</div><div class="metric-note">Según el filtro actual</div></div><div class="metric-value" id="metricActive">—</div></div>
                        <div class="metric"><div><div class="metric-label">Importe visible</div><div class="metric-note">Suma de los resultados mostrados</div></div><div class="metric-value" id="metricAmount">—</div></div>
                    </div>
                    <form class="filter-panel" id="filterForm">
                        <div class="filters">
                            <div class="field"><label for="filterIssuer">RFC emisor</label><input id="filterIssuer" name="rfcIssuer" placeholder="RFC o parte del RFC" maxlength="13"></div>
                            <div class="field"><label for="filterReceiver">RFC receptor</label><input id="filterReceiver" name="rfc" placeholder="RFC receptor" maxlength="13"></div>
                            <div class="field"><label for="filterClient">Razón social</label><input id="filterClient" name="taxEntityName" placeholder="Nombre del receptor"></div>
                            <div class="field"><label for="filterFolio">Folio</label><input id="filterFolio" name="folio" placeholder="Folio exacto"></div>
                            <div class="field"><label for="filterStatus">Estado</label><select id="filterStatus" name="status"><option value="all">Todos</option><option value="active">Vigentes</option><option value="canceled">Cancelados</option></select></div>
                            <div class="field"><label for="filterDateStart">Desde</label><input id="filterDateStart" name="dateStart" type="date"></div>
                            <div class="field"><label for="filterDateEnd">Hasta</label><input id="filterDateEnd" name="dateEnd" type="date"></div>
                            <div class="filters-actions"><button class="btn btn-primary" type="submit">Buscar</button><button class="btn" id="clearFilters" type="button">Limpiar</button></div>
                        </div>
                    </form>
                    <div class="table-wrap"><table><thead><tr><th>Folio / UUID</th><th>Receptor</th><th>Emisor</th><th>Fecha</th><th>Estado</th><th style="text-align:right">Total</th><th style="text-align:right">Archivos</th></tr></thead><tbody id="invoiceRows"><tr><td colspan="7" class="loading">Consultando Facturama…</td></tr></tbody></table></div>
                    <div class="pagination"><span id="pageLabel">Página 1</span><div class="page-controls"><button class="btn btn-icon" id="prevPage" title="Página anterior" aria-label="Página anterior">←</button><button class="btn btn-icon" id="nextPage" title="Página siguiente" aria-label="Página siguiente">→</button></div></div>
                </section>
                <section class="view" id="view-issuers">
                    <div class="page-heading"><div><h1>Emisores y CSD</h1><p class="subtitle">Revisa los certificados asociados a los RFC que pueden emitir CFDI.</p></div><div class="heading-right"><button class="btn btn-primary" id="addIssuer">＋ Registrar CSD</button></div></div>
                    <div class="table-wrap">
                        <div class="issuer-toolbar"><strong id="issuerCount">Emisores registrados</strong><button class="btn" id="issuerRefresh">↻ Actualizar</button></div>
                        <div class="issuer-table-wrap"><table class="issuer-table"><thead><tr><th>RFC emisor</th><th>Vigencia del CSD</th><th>Fecha de carga</th><th style="text-align:right">Acciones</th></tr></thead><tbody id="issuerRows"><tr><td colspan="4" class="loading">Consultando emisores…</td></tr></tbody></table></div>
                        <div class="issuer-mobile-list" id="issuerMobile"></div>
                    </div>
                </section>
                <section class="view" id="view-assignments">
                    <div class="page-heading"><div><h1>Asignaciones de emisores</h1><p class="subtitle">Relaciona cada cliente de Pikero o Colefy con los RFC que puede usar para facturar.</p></div></div>
                    <form class="filter-panel" id="assignmentForm">
                        <div class="filters assignment-fields">
                            <div class="field"><label for="assignmentSystem">Sistema</label><select id="assignmentSystem" name="system" required><option value="">Selecciona sistema</option></select></div>
                            <div class="field"><label for="assignmentClientId">ID del cliente</label><input id="assignmentClientId" name="clientId" required maxlength="100" placeholder="ID estable en Pikero"></div>
                            <div class="field"><label for="assignmentClientName">Nombre del cliente</label><input id="assignmentClientName" name="clientName" required maxlength="160" placeholder="Nombre visible"></div>
                            <div class="field"><label for="assignmentRfc">RFC emisor (CSD cargado)</label><select id="assignmentRfc" name="issuerRfc" required><option value="">Primero carga los emisores</option></select></div>
                            <div class="field"><label for="assignmentIssuerName">Razón social fiscal</label><input id="assignmentIssuerName" name="issuerName" required maxlength="160" placeholder="Nombre registrado ante el SAT"></div>
                            <div class="field"><label for="assignmentRegime">Régimen fiscal</label><input id="assignmentRegime" name="fiscalRegime" required pattern="[0-9]{3}" maxlength="3" placeholder="Ej. 601"></div>
                            <div class="filters-actions"><button class="btn btn-primary" type="submit">Guardar asignación</button><button class="btn" id="clearAssignment" type="reset">Limpiar</button></div>
                        </div>
                    </form>
                    <div class="table-wrap"><table><thead><tr><th>Sistema</th><th>Cliente en Pikero</th><th>Emisor autorizado</th><th>Régimen</th><th style="text-align:right">Acciones</th></tr></thead><tbody id="assignmentRows"><tr><td colspan="5" class="loading">Cargando asignaciones…</td></tr></tbody></table></div>
                </section>
            </div>
        </main>
    </div>
    <div class="overlay" id="dialogOverlay" role="presentation"><section class="dialog" role="dialog" aria-modal="true" aria-labelledby="dialogTitle"><div class="dialog-head"><div><h2 id="dialogTitle"></h2><p id="dialogSubtitle"></p></div><button class="btn btn-icon" id="closeDialog" aria-label="Cerrar">×</button></div><div class="dialog-body" id="dialogBody"></div><div class="dialog-foot" id="dialogFoot"></div></section></div>
    <div class="toast" id="toast" role="status"></div>
<?php endif; ?>
<script>
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
let csrfToken = csrfMeta?.content || '';
const initialAuthenticated = <?= $autenticado ? 'true' : 'false' ?>;
let currentPage = 0;
let invoiceRows = [];
let activeFilters = {};
let toastTimer;

async function api(action, values = {}, options = {}) {
    const headers = { 'X-CSRF-Token': csrfToken };
    let body;
    if (options.formData) {
        body = options.formData;
    } else {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify({ action, ...values });
    }
    const response = await fetch('panel-api.php', { method: 'POST', headers, body, credentials: 'same-origin' });
    const contentType = response.headers.get('content-type') || '';
    if (options.download && response.ok) return response.blob();
    const result = contentType.includes('application/json') ? await response.json() : {};
    if (!response.ok) throw new Error(result.error || `Error ${response.status}`);
    return result;
}
function showToast(message, error = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('error', error);
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3400);
}
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}
function money(value, currency = 'MXN') {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '—';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: /^[A-Z]{3}$/.test(currency) ? currency : 'MXN', maximumFractionDigits: 2 }).format(amount);
}
function dateLabel(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? escapeHtml(value) : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(date);
}
function getField(object, keys, fallback = '') {
    for (const key of keys) if (object?.[key] !== undefined && object[key] !== null && object[key] !== '') return object[key];
    return fallback;
}
function invoiceList(result) {
    if (Array.isArray(result)) return result;
    for (const key of ['Data', 'data', 'Items', 'items', 'Results', 'results', 'Cfdis', 'cfdis']) {
        if (Array.isArray(result?.[key])) return result[key];
    }
    return [];
}
function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) { button.dataset.original = button.innerHTML; button.disabled = true; button.textContent = label || 'Procesando…'; }
    else { button.disabled = false; button.innerHTML = button.dataset.original || button.innerHTML; }
}
async function loadInvoices() {
    const body = document.getElementById('invoiceRows');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="7" class="loading">Consultando Facturama…</td></tr>';
    try {
        const result = await api('invoices', { ...activeFilters, page: currentPage });
        invoiceRows = invoiceList(result.data);
        renderInvoices(invoiceRows);
    } catch (error) {
        body.innerHTML = `<tr><td colspan="7" class="empty"><strong>No se pudieron cargar las facturas</strong>${escapeHtml(error.message)}</td></tr>`;
        ['metricTotal', 'metricActive', 'metricAmount'].forEach(id => document.getElementById(id).textContent = '—');
    }
}
function renderInvoices(rows) {
    const body = document.getElementById('invoiceRows');
    const active = rows.filter(row => String(getField(row, ['Status', 'status'], '')).toLowerCase() === 'active').length;
    const total = rows.reduce((sum, row) => sum + (Number(getField(row, ['Total', 'total', 'Amount'], 0)) || 0), 0);
    document.getElementById('metricTotal').textContent = rows.length;
    document.getElementById('metricActive').textContent = active;
    document.getElementById('metricAmount').textContent = money(total);
    document.getElementById('pageLabel').textContent = `Página ${currentPage + 1} · ${rows.length} resultados`;
    document.getElementById('prevPage').disabled = currentPage === 0;
    document.getElementById('nextPage').disabled = rows.length < 10;
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="empty"><strong>No hay CFDI para mostrar</strong>Prueba con otros filtros o cambia el estado.</td></tr>';
        return;
    }
    body.innerHTML = rows.map((row, index) => {
        const id = getField(row, ['Id', 'id']);
        const uuid = getField(row, ['Uuid', 'UUID', 'uuid'], '');
        const folio = [getField(row, ['Serie', 'serie']), getField(row, ['Folio', 'folio'])].filter(Boolean).join(' ') || getField(row, ['Id', 'id'], 'Sin folio');
        const receiver = row.Receiver || row.receiver || {};
        const issuer = row.Issuer || row.issuer || {};
        const client = getField(receiver, ['Name', 'TaxName', 'name'], getField(row, ['TaxEntityName', 'Name', 'ReceiverName'], 'Receptor sin nombre'));
        const issuerName = getField(issuer, ['Rfc', 'RFC', 'rfc'], getField(row, ['RfcIssuer', 'IssuerRfc'], '—'));
        const status = String(getField(row, ['Status', 'status'], 'active')).toLowerCase();
        const statusText = status.includes('cancel') ? 'Cancelada' : status.includes('pend') ? 'Pendiente' : status === 'active' ? 'Vigente' : status;
        const statusClass = status.includes('cancel') ? 'status-canceled' : status.includes('pend') ? 'status-pending' : 'status-active';
        const currency = getField(row, ['Currency', 'currency'], 'MXN').slice(0, 3);
        const cancelAction = status === 'active' ? `<button class="link-button" title="Solicitar cancelación" data-cancel="${index}">ANULAR</button>` : '';
        return `<tr>
            <td><div class="folio">${escapeHtml(folio)}</div><div class="subcell" title="${escapeHtml(uuid)}">${escapeHtml(uuid || id || '')}</div></td>
            <td><div class="client-name" title="${escapeHtml(client)}">${escapeHtml(client)}</div><div class="subcell">${escapeHtml(getField(receiver, ['Rfc', 'RFC', 'rfc'], getField(row, ['Rfc', 'ReceiverRfc'], '')))}</div></td>
            <td><span class="folio">${escapeHtml(issuerName)}</span></td>
            <td>${dateLabel(getField(row, ['Date', 'date', 'IssuedDate']))}</td>
            <td><span class="status ${statusClass}">${escapeHtml(statusText)}</span></td>
            <td class="amount">${money(getField(row, ['Total', 'total', 'Amount'], 0), currency)}</td>
            <td><div class="row-actions"><button class="link-button" title="Ver detalle" data-detail="${index}">VER</button><button class="link-button" title="Descargar XML" data-download="xml" data-index="${index}">XML</button><button class="link-button" title="Descargar PDF" data-download="pdf" data-index="${index}">PDF</button>${cancelAction}</div></td>
        </tr>`;
    }).join('');
}
async function loadIssuers() {
    const rows = document.getElementById('issuerRows');
    if (!rows) return;
    rows.innerHTML = '<tr><td colspan="4" class="loading">Consultando emisores…</td></tr>';
    document.getElementById('issuerMobile').innerHTML = '';
    try {
        const result = await api('issuers');
        const issuers = Array.isArray(result.data) ? result.data : [];
        window.currentIssuers = issuers;
        populateAssignmentCsds(issuers);
        document.getElementById('issuerCount').textContent = `${issuers.length} ${issuers.length === 1 ? 'emisor registrado' : 'emisores registrados'}`;
        if (!issuers.length) {
            rows.innerHTML = '<tr><td colspan="4" class="empty"><strong>Aún no hay emisores</strong>Registra el CSD de un RFC para habilitar la emisión.</td></tr>';
            document.getElementById('issuerMobile').innerHTML = '<div class="empty"><strong>Aún no hay emisores</strong>Registra el CSD de un RFC para habilitar la emisión.</div>';
            return;
        }
        rows.innerHTML = issuers.map((issuer, index) => `<tr><td><span class="rfc">${escapeHtml(issuer.Rfc || issuer.RFC || '—')}</span></td><td>${dateLabel(issuer.CsdExpirationDate || issuer.ExpirationDate)}</td><td>${dateLabel(issuer.UploadDate)}</td><td><div class="row-actions"><button class="link-button" data-update-issuer="${index}">ACTUALIZAR CSD</button><button class="link-button" data-delete-issuer="${index}" title="Eliminar CSD">ELIMINAR</button></div></td></tr>`).join('');
        document.getElementById('issuerMobile').innerHTML = issuers.map((issuer, index) => `<article class="issuer-mobile"><div class="mobile-head"><div><div class="rfc">${escapeHtml(issuer.Rfc || issuer.RFC || '—')}</div><div class="subcell">Vence ${dateLabel(issuer.CsdExpirationDate || issuer.ExpirationDate)}</div><div class="subcell">Cargado ${dateLabel(issuer.UploadDate)}</div></div><button class="btn btn-icon" title="Acciones del emisor" data-issuer-menu="${index}">⋯</button></div></article>`).join('');
    } catch (error) {
        rows.innerHTML = `<tr><td colspan="4" class="empty"><strong>No se pudieron cargar los emisores</strong>${escapeHtml(error.message)}</td></tr>`;
    }
}
function populateAssignmentCsds(issuers = window.currentIssuers || []) {
    const select = document.getElementById('assignmentRfc');
    if (!select) return;
    const selected = select.value;
    select.innerHTML = '<option value="">Selecciona RFC con CSD cargado</option>' + issuers.map(issuer => {
        const rfc = issuer.Rfc || issuer.RFC || '';
        return `<option value="${escapeHtml(rfc)}">${escapeHtml(rfc)}</option>`;
    }).join('');
    if (selected && issuers.some(issuer => (issuer.Rfc || issuer.RFC) === selected)) select.value = selected;
}
async function loadAssignments() {
    const rows = document.getElementById('assignmentRows');
    if (!rows) return;
    rows.innerHTML = '<tr><td colspan="5" class="loading">Cargando asignaciones…</td></tr>';
    try {
        const result = await api('assignments');
        const systems = Array.isArray(result.systems) ? result.systems : [];
        const systemSelect = document.getElementById('assignmentSystem');
        systemSelect.innerHTML = '<option value="">Selecciona sistema</option>' + systems.map(system => `<option value="${escapeHtml(system)}">${escapeHtml(system)}</option>`).join('');
        const assignments = Array.isArray(result.data) ? result.data : [];
        if (!assignments.length) {
            rows.innerHTML = '<tr><td colspan="5" class="empty"><strong>No hay asignaciones todavía</strong>Vincula un cliente de Pikero con su RFC emisor para habilitar la emisión.</td></tr>';
            return;
        }
        rows.innerHTML = assignments.map(assignment => `<tr><td>${escapeHtml(assignment.system_name)}</td><td><strong>${escapeHtml(assignment.client_name)}</strong><div class="subcell">ID: ${escapeHtml(assignment.client_id)}</div></td><td><span class="rfc">${escapeHtml(assignment.issuer_rfc)}</span><div class="subcell">${escapeHtml(assignment.issuer_name)}</div></td><td>${escapeHtml(assignment.fiscal_regime)}</td><td><div class="row-actions"><button class="link-button" data-delete-assignment="${Number(assignment.id)}">QUITAR</button></div></td></tr>`).join('');
    } catch (error) {
        rows.innerHTML = `<tr><td colspan="5" class="empty"><strong>No se pudieron cargar las asignaciones</strong>${escapeHtml(error.message)}</td></tr>`;
    }
}
function openDialog(title, subtitle, content, actions = []) {
    document.getElementById('dialogTitle').textContent = title;
    document.getElementById('dialogSubtitle').textContent = subtitle || '';
    document.getElementById('dialogBody').innerHTML = content;
    document.getElementById('dialogFoot').innerHTML = actions.map(action => `<button class="btn ${action.primary ? 'btn-primary' : ''} ${action.danger ? 'btn-danger' : ''}" id="${action.id}">${action.label}</button>`).join('');
    document.getElementById('dialogOverlay').classList.add('open');
}
function closeDialog() { document.getElementById('dialogOverlay')?.classList.remove('open'); }
async function showDetail(index) {
    const row = invoiceRows[index];
    const id = getField(row, ['Id', 'id']);
    if (!id) return showToast('La respuesta no incluye identificador del CFDI.', true);
    openDialog('Detalle del CFDI', 'Consultando información completa…', '<div class="loading">Cargando detalle…</div>');
    try {
        const result = await api('detail', { id });
        const data = result.data || {};
        const receiver = data.Receiver || {};
        const issuer = data.Issuer || {};
        const items = [
            ['UUID', getField(data, ['Uuid', 'UUID'], data.TaxStamp?.Uuid)], ['Estado', getField(data, ['Status', 'status'])],
            ['Emisor', `${getField(issuer, ['TaxName', 'Name'], '')} · ${getField(issuer, ['Rfc', 'RFC'], '')}`],
            ['Receptor', `${getField(receiver, ['Name', 'TaxName'], '')} · ${getField(receiver, ['Rfc', 'RFC'], '')}`],
            ['Fecha de emisión', getField(data, ['Date', 'date'])], ['Forma de pago', getField(data, ['PaymentForm', 'PaymentTerms'])],
            ['Método de pago', getField(data, ['PaymentMethod'])], ['Total', money(getField(data, ['Total'], 0), getField(data, ['Currency'], 'MXN').slice(0, 3))]
        ];
        document.getElementById('dialogSubtitle').textContent = `Folio ${getField(data, ['Serie'], '')} ${getField(data, ['Folio'], '')}`;
        document.getElementById('dialogBody').innerHTML = `<div class="detail-grid">${items.map(([key, value]) => `<div class="detail-item"><div class="detail-key">${escapeHtml(key)}</div><div class="detail-value">${escapeHtml(value || '—')}</div></div>`).join('')}</div><details style="margin-top:16px"><summary style="cursor:pointer;font-size:11px;font-weight:650">Ver respuesta completa de Facturama</summary><pre>${escapeHtml(JSON.stringify(data, null, 2))}</pre></details>`;
        document.getElementById('dialogFoot').innerHTML = `<button class="btn" data-dialog-download="xml" data-id="${escapeHtml(id)}">Descargar XML</button><button class="btn" data-dialog-download="pdf" data-id="${escapeHtml(id)}">Descargar PDF</button><button class="btn" id="detailClose">Cerrar</button>`;
        document.getElementById('detailClose').addEventListener('click', closeDialog);
    } catch (error) {
        document.getElementById('dialogBody').innerHTML = `<div class="empty"><strong>No se pudo cargar el detalle</strong>${escapeHtml(error.message)}</div>`;
    }
}
function showIssuerForm(issuer = null, update = false) {
    const rfc = issuer?.Rfc || issuer?.RFC || '';
    openDialog(update ? 'Actualizar CSD' : 'Registrar emisor', update ? `Renueva los archivos del certificado para ${rfc}.` : 'Carga los certificados de sello digital del emisor.', `<form id="issuerForm" class="form-grid" enctype="multipart/form-data"><div class="field wide"><label for="issuerRfc">RFC del emisor</label><input id="issuerRfc" name="rfc" value="${escapeHtml(rfc)}" ${update ? 'readonly' : ''} required maxlength="13" placeholder="RFC asociado al CSD"></div><div class="field"><label for="issuerCer">Certificado .cer</label><input class="file-input" id="issuerCer" name="certificate" type="file" accept=".cer" required></div><div class="field"><label for="issuerKey">Llave privada .key</label><input class="file-input" id="issuerKey" name="privateKey" type="file" accept=".key" required></div><div class="field wide"><label for="issuerPassword">Contraseña de la llave</label><input id="issuerPassword" name="privateKeyPassword" type="password" autocomplete="new-password" required></div><p class="help wide">Los archivos se envían al servidor únicamente para registrarlos en Facturama. No se guardan en esta aplicación y el panel no vuelve a mostrar la contraseña.</p></form>`, [{ id: 'cancelIssuer', label: 'Cancelar' }, { id: 'saveIssuer', label: update ? 'Actualizar CSD' : 'Registrar emisor', primary: true }]);
    document.getElementById('cancelIssuer').addEventListener('click', closeDialog);
    document.getElementById('saveIssuer').addEventListener('click', async event => {
        const form = document.getElementById('issuerForm');
        if (!form.reportValidity()) return;
        const button = event.currentTarget;
        const formData = new FormData(form);
        formData.append('action', 'saveIssuer');
        formData.append('update', update ? '1' : '0');
        setBusy(button, true, 'Enviando…');
        try {
            const result = await api('saveIssuer', {}, { formData });
            closeDialog(); showToast(result.message || 'CSD guardado.'); loadIssuers();
        } catch (error) { setBusy(button, false); showToast(error.message, true); }
    });
}
function showCancelForm(index) {
    const row = invoiceRows[index];
    const id = getField(row, ['Id', 'id']);
    const uuid = getField(row, ['Uuid', 'UUID', 'uuid'], '');
    openDialog('Solicitar cancelación', `CFDI ${getField(row, ['Serie'], '')} ${getField(row, ['Folio'], '')} · ${uuid}`, `<div class="field"><label for="cancelMotive">Motivo de cancelación</label><select id="cancelMotive"><option value="02">02 · Errores sin relación</option><option value="01">01 · Errores con relación (sustitución)</option><option value="03">03 · No se llevó a cabo la operación</option><option value="04">04 · Operación nominativa en factura global</option></select></div><div class="field" id="replacementField" style="display:none;margin-top:14px"><label for="replacementUuid">UUID del CFDI sustituto</label><input id="replacementUuid" placeholder="UUID del comprobante que sustituye"></div><p class="help" style="margin-top:14px">La solicitud puede quedar pendiente de aceptación del receptor. Confirma el motivo fiscal antes de continuar.</p>`, [{ id: 'cancelDialog', label: 'Volver' }, { id: 'confirmCancel', label: 'Solicitar cancelación', danger: true }]);
    const motive = document.getElementById('cancelMotive');
    motive.addEventListener('change', () => { document.getElementById('replacementField').style.display = motive.value === '01' ? 'grid' : 'none'; });
    document.getElementById('cancelDialog').addEventListener('click', closeDialog);
    document.getElementById('confirmCancel').addEventListener('click', async event => {
        const button = event.currentTarget;
        setBusy(button, true, 'Solicitando…');
        try {
            await api('cancel', { id, motive: motive.value, replacementUuid: document.getElementById('replacementUuid').value });
            closeDialog(); showToast('Solicitud de cancelación enviada a Facturama.'); loadInvoices();
        } catch (error) { setBusy(button, false); showToast(error.message, true); }
    });
}
async function downloadFile(id, format) {
    try {
        const blob = await api('download', { id, format }, { download: true });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `cfdi-${String(id).replace(/[^A-Za-z0-9_-]/g, '')}.${format}`;
        document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(link.href);
    } catch (error) { showToast(error.message, true); }
}

if (!initialAuthenticated) {
    document.getElementById('loginForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button');
        setBusy(button, true, 'Verificando…');
        try { const result = await api('login', { password: document.getElementById('password').value }); csrfToken = result.csrf; location.reload(); }
        catch (error) { document.getElementById('loginError').textContent = error.message; setBusy(button, false); }
    });
} else {
    loadInvoices();
    document.querySelectorAll('.nav button[data-view]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('.nav button').forEach(item => item.classList.toggle('active', item === button));
        document.querySelectorAll('.view').forEach(view => view.classList.toggle('active', view.id === `view-${button.dataset.view}`));
        const labels = { invoices: 'Facturas', issuers: 'Emisores y CSD', assignments: 'Asignaciones' };
        document.getElementById('crumbCurrent').textContent = labels[button.dataset.view];
        if (button.dataset.view === 'issuers') loadIssuers();
        if (button.dataset.view === 'assignments') { loadIssuers(); loadAssignments(); }
    }));
    document.getElementById('filterForm').addEventListener('submit', event => {
        event.preventDefault(); activeFilters = Object.fromEntries(new FormData(event.currentTarget)); currentPage = 0; loadInvoices();
    });
    document.getElementById('clearFilters').addEventListener('click', () => { document.getElementById('filterForm').reset(); activeFilters = {}; currentPage = 0; loadInvoices(); });
    document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 0) { currentPage--; loadInvoices(); } });
    document.getElementById('nextPage').addEventListener('click', () => { if (invoiceRows.length === 10) { currentPage++; loadInvoices(); } });
    document.getElementById('invoiceRefresh').addEventListener('click', loadInvoices);
    document.getElementById('refreshButton').addEventListener('click', () => {
        if (document.getElementById('view-issuers').classList.contains('active')) loadIssuers();
        else if (document.getElementById('view-assignments').classList.contains('active')) { loadIssuers(); loadAssignments(); }
        else loadInvoices();
    });
    document.getElementById('issuerRefresh').addEventListener('click', loadIssuers);
    document.getElementById('addIssuer').addEventListener('click', () => showIssuerForm());
    document.getElementById('assignmentRfc').addEventListener('change', event => {
        const issuer = (window.currentIssuers || []).find(item => (item.Rfc || item.RFC) === event.target.value);
        document.getElementById('assignmentIssuerName').value = issuer?.Name || issuer?.TaxName || '';
    });
    document.getElementById('assignmentForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const values = Object.fromEntries(new FormData(form));
        const button = form.querySelector('[type="submit"]');
        setBusy(button, true, 'Guardando…');
        try {
            const result = await api('saveAssignment', values);
            showToast(result.message || 'Asignación guardada.');
            form.reset();
            loadAssignments();
        } catch (error) { showToast(error.message, true); }
        finally { setBusy(button, false); }
    });
    document.getElementById('assignmentRows').addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-assignment]');
        if (!button || !confirm('¿Quitar este emisor del cliente? El cliente dejará de poder facturar con ese RFC.')) return;
        try {
            await api('deleteAssignment', { id: Number(button.dataset.deleteAssignment) });
            showToast('Asignación eliminada.');
            loadAssignments();
        } catch (error) { showToast(error.message, true); }
    });
    document.getElementById('logoutButton').addEventListener('click', async () => { try { await api('logout'); location.reload(); } catch (error) { showToast(error.message, true); } });
    document.getElementById('closeDialog').addEventListener('click', closeDialog);
    document.getElementById('dialogOverlay').addEventListener('click', event => { if (event.target.id === 'dialogOverlay') closeDialog(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeDialog(); });
    document.getElementById('invoiceRows').addEventListener('click', event => {
        const detail = event.target.closest('[data-detail]');
        const download = event.target.closest('[data-download]');
        const cancel = event.target.closest('[data-cancel]');
        if (detail) showDetail(Number(detail.dataset.detail));
        if (download) downloadFile(getField(invoiceRows[Number(download.dataset.index)], ['Id', 'id']), download.dataset.download);
        if (cancel) showCancelForm(Number(cancel.dataset.cancel));
    });
    document.getElementById('dialogFoot').addEventListener('click', event => {
        const button = event.target.closest('[data-dialog-download]');
        if (button) downloadFile(button.dataset.id, button.dataset.dialogDownload);
    });
    document.getElementById('issuerRows').addEventListener('click', event => {
        const update = event.target.closest('[data-update-issuer]');
        const remove = event.target.closest('[data-delete-issuer]');
        if (update) showIssuerForm(window.currentIssuers[Number(update.dataset.updateIssuer)], true);
        if (remove) deleteIssuer(window.currentIssuers[Number(remove.dataset.deleteIssuer)]);
    });
    document.getElementById('issuerMobile').addEventListener('click', event => {
        const button = event.target.closest('[data-issuer-menu]');
        if (!button) return;
        const issuer = window.currentIssuers[Number(button.dataset.issuerMenu)];
        openDialog('Acciones del emisor', issuer.Rfc || issuer.RFC, '<p class="help">Elige qué deseas hacer con el CSD de este emisor.</p>', [{ id: 'mobileUpdate', label: 'Actualizar CSD', primary: true }, { id: 'mobileDelete', label: 'Eliminar CSD', danger: true }]);
        document.getElementById('mobileUpdate').addEventListener('click', () => { closeDialog(); showIssuerForm(issuer, true); });
        document.getElementById('mobileDelete').addEventListener('click', () => { closeDialog(); deleteIssuer(issuer); });
    });
}
async function deleteIssuer(issuer) {
    const rfc = issuer?.Rfc || issuer?.RFC || '';
    if (!confirm(`¿Eliminar el CSD asociado a ${rfc}? Esto impedirá emitir nuevos CFDI con este RFC.`)) return;
    try { await api('deleteIssuer', { rfc }); showToast('CSD eliminado.'); loadIssuers(); }
    catch (error) { showToast(error.message, true); }
}
</script>
</body>
</html>

<?php
//pagina visibile solo agli admin che consente di vedere tutte le prenotazioni e i contatti degli utenti
session_start();
require_once __DIR__ . '/includes/config.php';

// Accesso riservato agli amministratori
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php?next=clienti.php');
    exit;
}

$conn = pg_connect($connection_string);
if (!$conn) die('Errore DB.');
// Funzione di utilità: verifica se una colonna esiste in una tabella
function column_exists($conn, $table, $column) {
    $res = pg_query_params($conn, "SELECT 1 FROM information_schema.columns WHERE table_name=$1 AND column_name=$2", array($table, $column));
    return ($res && pg_num_rows($res) > 0);
}

// Rileva colonne opzionali (possono mancare se la migrazione DB non è stata eseguita)
$has_archived = column_exists($conn, 'cancellazioni', 'archived');
$has_seen = column_exists($conn, 'cancellazioni', 'seen_by_admin');

// salva tutte le prenotazioni con i dati di contatto dell'utente
$now = (new DateTime('now'))->format('Y-m-d');
$sql = "SELECT a.id, a.id_servizio, a.data_appuntamento, a.ora_appuntamento, a.barber, a.note, u.id AS user_id, u.nome, u.cognome, u.email, u.telefono
    FROM appuntamenti a
    JOIN utenti u ON a.id_utente = u.id
    WHERE a.data_appuntamento >= $1
    ORDER BY a.data_appuntamento, a.ora_appuntamento";
$res = pg_prepare($conn, 'sel_bookings', $sql);
$res = pg_execute($conn, 'sel_bookings', array($now));
// mappa i servizi
$serviceMap = [
    1 => 'Taglio e Shampoo - €16',
    2 => 'Taglio, Shampoo e Barba - €20'
];

$bookings = [];
if ($res) {
    while ($r = pg_fetch_assoc($res)) $bookings[] = $r;
}

// Se richiesto l'export, invia CSV
if (isset($_GET['export']) && $_GET['export'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prenotazioni.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','data_appuntamento','ora_appuntamento','barber','servizio','cliente_nome','cliente_cognome','email','telefono','note']);
    foreach ($bookings as $b) {
        $svc = $serviceMap[intval($b['id_servizio'])] ?? ('Servizio #' . intval($b['id_servizio']));
        // formatta la data in dd/mm/YYYY
        $dt = DateTime::createFromFormat('Y-m-d', $b['data_appuntamento']);
        $dateLabel = $dt ? $dt->format('d/m/Y') : $b['data_appuntamento'];
        fputcsv($out, [$b['id'],$dateLabel,substr($b['ora_appuntamento'],0,5),$b['barber'],$svc,$b['nome'],$b['cognome'],$b['email'],$b['telefono'],$b['note']]);
    }
    fclose($out);
    pg_close($conn);
    exit;
}
// salva le cancellazioni recenti (non cancellate e non viste nelle ultime 24 ore)
$cancellations = [];
$where = [];
if ($has_archived) $where[] = "(c.archived IS NOT TRUE)";
if ($has_seen) $where[] = "(c.seen_by_admin IS NOT TRUE)";
$where[] = "(c.cancelled_at >= now() - interval '24 hours')";
$whereSql = implode(' AND ', $where);
$r2_sql = "SELECT c.id, c.booking_id, c.id_utente, c.cancelled_by, c.reason, c.data_appuntamento, c.ora_appuntamento, c.barber, c.note, c.cancelled_at,
    COALESCE(uc.nome, uo.nome) AS canc_nome,
    COALESCE(uc.cognome, uo.cognome) AS canc_cognome,
    COALESCE(uc.telefono, uo.telefono) AS canc_telefono
    FROM cancellazioni c
    LEFT JOIN utenti uc ON c.cancelled_by = uc.id
    LEFT JOIN utenti uo ON c.id_utente = uo.id
    WHERE $whereSql
    ORDER BY c.cancelled_at DESC LIMIT 200";
$r2 = @pg_query($conn, $r2_sql);
if ($r2) {
    while ($rc = pg_fetch_assoc($r2)) $cancellations[] = $rc;
}
// NOTE: rimosso il supporto alla cancellazione diretta delle prenotazioni via POST
// per evitare che utenti con ruoli diversi possano rimuovere appuntamenti.
// Se in futuro sarà necessario ripristinare la funzionalità, aggiungere
// un endpoint amministrativo dedicato con conferma e audit.

// Endpoint: restituisce l'id dell'ultima cancellazione 
if (isset($_GET['last_cancel_check'])) {
    $resLatest = pg_query($conn, 'SELECT id FROM cancellazioni ORDER BY cancelled_at DESC LIMIT 1');
    $latest = null;
    if ($resLatest && pg_num_rows($resLatest) > 0) $latest = intval(pg_fetch_result($resLatest,0,'id'));
    header('Content-Type: application/json');
    echo json_encode(['latest_cancel_id' => $latest]);
    pg_close($conn);
    exit;
}
// Endpoint: restituisce le cancellazioni recenti (per polling refresh, non cancellate e non viste nelle ultime 24 ore)
if (isset($_GET['get_cancellations'])) {
    $out = [];
    $where = [];
    if ($has_archived) $where[] = "(c.archived IS NOT TRUE)";
    if ($has_seen) $where[] = "(c.seen_by_admin IS NOT TRUE)";
    $where[] = "(c.cancelled_at >= now() - interval '24 hours')";
    $whereSql = implode(' AND ', $where);
    $q_sql = "SELECT c.id, c.booking_id, c.data_appuntamento, c.ora_appuntamento, c.barber, c.reason, c.cancelled_at,
        COALESCE(uc.nome, uo.nome) AS canc_nome, COALESCE(uc.cognome, uo.cognome) AS canc_cognome, COALESCE(uc.telefono, uo.telefono) AS canc_telefono
        FROM cancellazioni c
        LEFT JOIN utenti uc ON c.cancelled_by = uc.id
        LEFT JOIN utenti uo ON c.id_utente = uo.id
        WHERE $whereSql
        ORDER BY c.cancelled_at DESC LIMIT 200";
    $q = @pg_query($conn, $q_sql);
    if ($q) {
        while ($c = pg_fetch_assoc($q)) {
            $out[] = $c;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['cancellations' => $out]);
    pg_close($conn);
    exit;
}

// Endpoint: elimina definitivamente le cancellazioni più vecchie di 24 ore (Refresh persistente)
if (isset($_GET['refresh_persist'])) {
    $d = @pg_query($conn, "DELETE FROM cancellazioni WHERE cancelled_at < now() - interval '24 hours'");
    header('Content-Type: application/json');
    echo json_encode(['ok' => (bool)$d, 'deleted' => ($d ? pg_affected_rows($d) : 0)]);
    pg_close($conn);
    exit;
}

// Endpoint: elimina definitivamente TUTTE le cancellazioni (Prendi nota)
if (isset($_GET['prendi_nota'])) {
    $d2 = @pg_query($conn, "DELETE FROM cancellazioni");
    header('Content-Type: application/json');
    echo json_encode(['ok' => (bool)$d2, 'deleted' => ($d2 ? pg_affected_rows($d2) : 0)]);
    pg_close($conn);
    exit;
}

// se richiesto verifica se evidenziare una cancellazione specifica
$show_cancel = null;
$show_cancel_user = null;
if (!empty($_GET['show_cancel_id'])) {
    $scid = intval($_GET['show_cancel_id']);
    $q = pg_query_params($conn, 'SELECT * FROM cancellazioni WHERE id=$1', array($scid));
    if ($q && pg_num_rows($q) > 0) {
        $show_cancel = pg_fetch_assoc($q);
        if (!empty($show_cancel['id_utente'])) {
            $ru = pg_query_params($conn, 'SELECT id, nome, cognome, email, telefono FROM utenti WHERE id=$1', array(intval($show_cancel['id_utente'])));
            if ($ru && pg_num_rows($ru) > 0) $show_cancel_user = pg_fetch_assoc($ru);
        }
    }
}

pg_close($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Clienti - Report prenotazioni</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main class="card-main" style="padding:120px 40px 40px 40px; margin:40px auto; max-width:1200px; background:transparent; color:inherit; border-radius:12px;">
    <h1 style="color:#d4af37;margin:0 0 8px 0;padding-top:4px;font-size:40px;line-height:1.05;font-weight:800;letter-spacing:0.4px;">Report prenotazioni</h1>
    <p style="color:#e7d6a3; margin: 8px 0 20px 0; font-size:15px;">Elenco completo delle prenotazioni attuali. Puoi esportare le prenotazioni in un file di testo.</p>
        <?php if (!empty($_GET['msg'])): ?><div style="color:#9ee39e; margin-bottom:12px;"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div style="color:#ff8b8b; margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display:flex;gap:12px;align-items:center;margin:18px 0 22px 0;">
            <a href="clienti.php?export=1" style="background:linear-gradient(135deg,#d4af37,#f7c948);color:#111;margin:0;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700;">Esporta CSV</a>
        </div>

    <div class="table-container" style="margin-bottom:28px;">
    <table class="booking-table">
            <thead>
                <tr style="text-align:left; background:#111; color:#d4af37;">
                            <th>Data</th>
                            <th>Ora</th>
                            <th>Servizio</th>
                            <th>Barbiere</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bookings)===0): ?>
                    <tr><td colspan="8" style="padding:16px;text-align:center;color:#ccc;">Nessuna prenotazione trovata.</td></tr>
                <?php else: foreach($bookings as $b): ?>
                            <tr>
                                <td>
                                    <?php $dt = DateTime::createFromFormat('Y-m-d', $b['data_appuntamento']); echo htmlspecialchars($dt ? $dt->format('d/m/Y') : $b['data_appuntamento']); ?>
                                </td>
                                <td><?= substr(htmlspecialchars($b['ora_appuntamento']),0,5) ?></td>
                                <td><?= htmlspecialchars($serviceMap[intval($b['id_servizio'])] ?? ('Servizio #' . intval($b['id_servizio']))) ?></td>
                                <td class="td-muted"><?= htmlspecialchars($b['barber']) ?></td>
                                <td><?= htmlspecialchars($b['nome'].' '.$b['cognome']) ?></td>
                                <td><?= htmlspecialchars($b['email']) ?></td>
                                <td><?= htmlspecialchars($b['telefono']) ?></td>
                                <td><?= htmlspecialchars($b['note']) ?></td>
                            </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </main>
    <script>
        // intervallo di polling per verificare nuove cancellazioni
        (function(){
            const pollInterval = 8000; // ms
            let latest = null;
            // leggi l'id dell'ultima cancellazione dal DOM ispezionando la prima riga se esiste
            const firstCancelCell = document.querySelector('main h2') ? null : null;
            // salva le prenotazioni iniziali dal server
            async function checkLatest() {
                try {
                    const r = await fetch(window.location.pathname + '?last_cancel_check=1', {cache: 'no-store'});
                    if (!r.ok) return;
                    const j = await r.json();
                    if (latest === null) {
                        latest = j.latest_cancel_id;
                        return;
                    }
                    if (j.latest_cancel_id && latest && j.latest_cancel_id !== latest) {
                        // a ogni cancellazione ricarica la pagina
                        window.location.reload();
                    }
                } catch (e) {
                    // ignora errori
                }
            }
            setInterval(checkLatest, pollInterval);
        })();
    </script>
    <main class="card-sub" style="padding:28px 24px;">
        <div style="margin-bottom:12px;">
            <h2 style="margin:0;font-size:20px;color:#d4af37;">Ultime cancellazioni</h2>
            <p style="margin:6px 0 0 0;color:#bfb6a0;font-size:13px;">Elenco degli appunatmenti cancellati dagli utenti</p>
        </div>
            <?php if (count($cancellations)===0): ?>
                <div style="color:#666;padding:12px 0;">Nessuna cancellazione registrata.</div>
            <?php else: ?>
            <div class="table-container">
            <table class="booking-table" style="min-width:800px; margin-top:10px;">
                <thead>
                        <tr style="text-align:left; background:#111;color:#d4af37;">
                            <th>Numero</th>
                            <th>Data</th>
                            <th>Ora</th>
                            <th>Barbiere</th>
                            <th>Cancellato da</th>
                            <th>Motivo</th>
                            <th>Cancellata il</th>
                        </tr>
                </thead>
                <tbody id="cancellations-tbody">
                    <?php foreach($cancellations as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['canc_telefono'] ?? '') ?></td>
                            <td>
                                <?php $dtc = DateTime::createFromFormat('Y-m-d', $c['data_appuntamento']); echo htmlspecialchars($dtc ? $dtc->format('d/m/Y') : $c['data_appuntamento']); ?>
                            </td>
                            <td><?= substr(htmlspecialchars($c['ora_appuntamento']),0,5) ?></td>
                            <td class="td-muted"><?= htmlspecialchars($c['barber']) ?></td>
                            <td><?= htmlspecialchars(trim(($c['canc_nome'] ?? '') . ' ' . ($c['canc_cognome'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars($c['reason']) ?></td>
                            <td><?php $ca = new DateTime($c['cancelled_at']); echo htmlspecialchars($ca ? $ca->format('d/m/Y H:i') : $c['cancelled_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:12px;">
            <button id="btn-refresh-cancellations" style="background:linear-gradient(135deg,#d4af37,#f7c948);color:#111;padding:8px 14px;border-radius:8px;border:none;font-weight:700;cursor:pointer;">Refresh</button>
            <button id="btn-prendi-nota" style="background:#1e1e1e;color:#d4af37;border:2px solid #d4af37;padding:8px 14px;border-radius:8px;font-weight:700;cursor:pointer;">Prendi nota</button>
        </div>

        <div id="cancellations-msg" style="margin-top:10px;color:#9ee39e;"></div>
        <script>
            (function(){
                const refreshBtn = document.getElementById('btn-refresh-cancellations');
                const prendiBtn = document.getElementById('btn-prendi-nota');
                const tbody = document.getElementById('cancellations-tbody');
                const msg = document.getElementById('cancellations-msg');

                async function fetchCancellations() {
                    try {
                        const r = await fetch(window.location.pathname + '?get_cancellations=1', {cache: 'no-store'});
                        if (!r.ok) return;
                        const j = await r.json();
                        const list = j.cancellations || [];
                        let html = '';
                        for (const c of list) {
                            // formatta la data in dd/mm/YYYY
                            let d = c.data_appuntamento || '';
                            if (d && d.indexOf('-')>=0) {
                                const parts = d.split('-');
                                d = parts[2] + '/' + parts[1] + '/' + parts[0];
                            }
                            const cancelledAt = c.cancelled_at ? new Date(c.cancelled_at).toLocaleString('it-IT') : c.cancelled_at;
                            html += '<tr>';
                            html += '<td>' + (c.canc_telefono||'') + '</td>';
                            html += '<td>' + d + '</td>';
                            html += '<td>' + (c.ora_appuntamento||'') + '</td>';
                            html += '<td class="td-muted">' + (c.barber||'') + '</td>';
                            html += '<td>' + (c.canc_nome||'') + '</td>';
                            html += '<td>' + (c.reason||'') + '</td>';
                            html += '<td>' + (cancelledAt||'') + '</td>';
                            html += '</tr>';
                        }
                        if (tbody) tbody.innerHTML = html || '<tr><td colspan="7" style="padding:8px;color:#666;">Nessuna cancellazione registrata.</td></tr>';
                    } catch (e) {
                        console.error(e);
                    }
                }

                if (refreshBtn) refreshBtn.addEventListener('click', async function(){
                    try {
                        if (!confirm('Confermi l\'eliminazione PERMANENTE delle cancellazioni più vecchie di 24 ore?')) return;
                        // elimina definitivamente le cancellazioni più vecchie di 24 ore sul server
                        const r = await fetch(window.location.pathname + '?refresh_persist=1');
                        if (r.ok) {
                            const j = await r.json();
                            await fetchCancellations();
                            if (msg) msg.textContent = (j.deleted ? ('Eliminate: ' + j.deleted) : 'Eliminate');
                            setTimeout(()=>{ if(msg) msg.textContent=''; },3000);
                        }
                    } catch (e) { console.error(e); }
                });
                if (prendiBtn) prendiBtn.addEventListener('click', async function(){
                    try {
                        if (!confirm('Confermi l\'eliminazione PERMANENTE di TUTTE le cancellazioni? Questa azione non è reversibile.')) return;
                        // elimina tutte le prenotazioni cancellate definitivamente
                        const r = await fetch(window.location.pathname + '?prendi_nota=1');
                        if (r.ok) {
                            const j = await r.json();
                            await fetchCancellations();
                            if (msg) msg.textContent = (j.deleted ? ('Eliminate: ' + j.deleted) : 'Eliminate');
                            setTimeout(()=>{ if(msg) msg.textContent=''; },3000);
                        }
                    } catch (e) { console.error(e); }
                });
            })();
        </script>
        
            <?php if ($show_cancel): ?>
                <div style="margin-top:18px;padding:14px;border:1px solid #333;background:#111;color:#fff;border-radius:8px;">
                    <h3 style="color:#d4af37;">Dettaglio cancellazione #<?= htmlspecialchars($show_cancel['id']) ?></h3>
                    <p><strong>Booking ID:</strong> <?= htmlspecialchars($show_cancel['booking_id']) ?></p>
                    <p><strong>Data:</strong> <?= htmlspecialchars(($dts = DateTime::createFromFormat('Y-m-d', $show_cancel['data_appuntamento'])) ? $dts->format('d/m/Y') : $show_cancel['data_appuntamento']) ?> <strong>Ora:</strong> <?= htmlspecialchars(substr($show_cancel['ora_appuntamento'],0,5)) ?></p>
                    <p><strong>Barbiere:</strong> <?= htmlspecialchars($show_cancel['barber']) ?></p>
                    <p><strong>Motivo:</strong> <?= htmlspecialchars($show_cancel['reason']) ?></p>
                    <?php if ($show_cancel_user): ?>
                        <h4>Dati utente associato</h4>
                        <p><strong>Nome:</strong> <?= htmlspecialchars($show_cancel_user['nome'] . ' ' . $show_cancel_user['cognome']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($show_cancel_user['email']) ?></p>
                        <p><strong>Telefono:</strong> <?= htmlspecialchars($show_cancel_user['telefono']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

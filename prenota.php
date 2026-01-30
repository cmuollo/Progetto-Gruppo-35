<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the page is opened with ?data=...&orario=..., capture them for prefill
$prefill_date = isset($_GET['data']) ? trim($_GET['data']) : '';
$prefill_orario = isset($_GET['orario']) ? trim($_GET['orario']) : '';

// Redirect non-loggati alla pagina di login/registrazione, preserving prefill params
if (!isset($_SESSION["user_id"])) {
    // build next with optional query string
    $next = 'prenota.php';
    $qs = [];
    if ($prefill_date !== '') $qs[] = 'data=' . rawurlencode($prefill_date);
    if ($prefill_orario !== '') $qs[] = 'orario=' . rawurlencode($prefill_orario);
    if (count($qs) > 0) $next .= '?' . implode('&', $qs);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

// If frontend requests availability for a specific date, return JSON
if (isset($_GET['availability_date'])) {
    require_once __DIR__ . "/includes/config.php";
    $conn = pg_connect($connection_string);
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection error']);
        exit;
    }
    $date = $_GET['availability_date'];
    $res = pg_query_params($conn, "SELECT ora_appuntamento, barber FROM appuntamenti WHERE data_appuntamento=$1", array($date));
    $map = [];
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $ora = substr($r['ora_appuntamento'],0,8);
            if (!isset($map[$ora])) $map[$ora] = [];
            $map[$ora][] = $r['barber'];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($map);
    exit;
}

// Check whether current user already has a booking for this date/orario or same day
if (isset($_GET['check_user']) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . "/includes/config.php";
    $conn = pg_connect($connection_string);
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection error']);
        exit;
    }
    $date = $_GET['date'] ?? '';
    $orario = $_GET['orario'] ?? '';
    $userId = intval($_SESSION['user_id']);
    $res1 = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2 AND ora_appuntamento=$3', array($userId, $date, $orario));
    $sameSlot = ($res1 ? intval(pg_fetch_result($res1,0,'c')) : 0) > 0;
    $res2 = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2', array($userId, $date));
    $sameDay = ($res2 ? intval(pg_fetch_result($res2,0,'c')) : 0) > 0;
    header('Content-Type: application/json');
    echo json_encode(['sameSlot' => $sameSlot, 'sameDay' => $sameDay]);
    pg_close($conn);
    exit;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prenota Appuntamento - La Bottega del Barbiere</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Upcoming bookings card style (uniform with site) */
        .upcoming-bookings .booking-item { display:flex; justify-content:space-between; align-items:center; gap:12px; border:1px solid #eee; padding:10px 12px; border-radius:8px; background:#fff; }
        .upcoming-bookings .booking-item .booking-meta { color:#333; font-weight:600; }
        .upcoming-bookings .booking-item .booking-note { color:#666; font-size:0.9rem; }
        /* cancel button: red with trash icon */
        .btn-cancel { background:#b00020; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:700; display:inline-flex; align-items:center; gap:8px; }
        .btn-cancel i { font-size:0.95em; }
        .btn-cancel:hover { filter:brightness(.95); }
        .upcoming-bookings h3 { margin-bottom:8px; }

        /* Modal styles for cancellation confirmation */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center; z-index:10000; }
        .modal { background:#fff; border-radius:10px; padding:18px; max-width:520px; width:92%; box-shadow:0 12px 30px rgba(0,0,0,0.35); }
        .modal h3 { margin-top:0; color:#1e1e1e; }
        .modal textarea { width:100%; min-height:100px; border:1px solid #ddd; padding:8px; border-radius:6px; resize:vertical; }
        .modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
        .modal .btn-cancel-confirm { background:#b00020; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:700; }
        .modal .btn-close { background:#e7e7e7; color:#222; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; }
    </style>
</head>
<body>   
    
    <?php
    include __DIR__ . "/includes/header.php";
    // If the logged-in user is an admin, redirect to the clienti (admin report) page
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: clienti.php');
        exit;
    }
    // Elaborazione POST per creare prenotazione
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Cancellation request (AJAX or form)
        if (isset($_POST['cancel_booking']) && !empty($_POST['booking_id'])) {
            include __DIR__ . "/includes/config.php";
            $conn = pg_connect($connection_string);
            if (!$conn) {
                $error = "Errore di connessione al database.";
            } else {
                $booking_id = intval($_POST['booking_id']);
                $reason = trim($_POST['reason'] ?? '');
                // Verify ownership or admin
                $resOwner = pg_query_params($conn, 'SELECT id_utente, data_appuntamento, ora_appuntamento, barber, note FROM appuntamenti WHERE id=$1', array($booking_id));
                if ($resOwner && pg_num_rows($resOwner) > 0) {
                    $rowB = pg_fetch_assoc($resOwner);
                    $owner = intval($rowB['id_utente']);
                    $isAdmin = false;
                    if (isset($_SESSION['user_id'])) {
                        $r = pg_query_params($conn, 'SELECT ruolo FROM utenti WHERE id=$1', array(intval($_SESSION['user_id'])));
                        if ($r && pg_num_rows($r)>0) $isAdmin = (pg_fetch_result($r,0,'ruolo') === 'admin');
                    }
                    if ($owner === intval($_SESSION['user_id']) || $isAdmin) {
                        // Log cancellation into cancellazioni table and retrieve the inserted id
                        $insSql = 'INSERT INTO cancellazioni (booking_id, id_utente, cancelled_by, reason, data_appuntamento, ora_appuntamento, barber, note) VALUES ($1,$2,$3,$4,$5,$6,$7,$8) RETURNING id';
                        $cancelRes = pg_prepare($conn, 'ins_cancel', $insSql);
                        $cancelExec = pg_execute($conn, 'ins_cancel', array($booking_id, $owner, intval($_SESSION['user_id']), $reason, $rowB['data_appuntamento'], $rowB['ora_appuntamento'], $rowB['barber'], $rowB['note']));
                        $cancel_id = null;
                        if ($cancelExec && pg_num_rows($cancelExec) > 0) {
                            $cancel_id = intval(pg_fetch_result($cancelExec, 0, 'id'));
                        }
                        // Then delete the booking
                        $del = pg_query_params($conn, 'DELETE FROM appuntamenti WHERE id=$1', array($booking_id));
                            if ($del) {
                                pg_close($conn);
                                // If we have a cancellation record id, redirect admin/users: admin -> clienti.php, owner -> prenota.php
                                if ($cancel_id) {
                                    if ($isAdmin) {
                                        header('Location: clienti.php?show_cancel_id=' . urlencode($cancel_id) . '&msg=' . urlencode('Prenotazione annullata'));
                                    } else {
                                        header('Location: prenota.php?msg=' . urlencode('Prenotazione annullata') . '&cancel_id=' . urlencode($cancel_id));
                                    }
                                } else {
                                    if ($isAdmin) header('Location: clienti.php?msg=' . urlencode('Prenotazione annullata'));
                                    else header('Location: prenota.php?msg=' . urlencode('Prenotazione annullata'));
                                }
                                exit;
                            } else {
                            $error = 'Errore durante l\'annullamento.';
                        }
                    } else {
                        $error = 'Non sei autorizzato ad annullare questa prenotazione.';
                    }
                } else {
                    $error = 'Prenotazione non trovata.';
                }
            }
        }
        // Verifica login
        if (!isset($_SESSION["user_id"])) {
            $error = "Devi essere loggato per prenotare.";
        } else {
            include __DIR__ . "/includes/config.php";
            $conn = pg_connect($connection_string);
            if (!$conn) {
                $error = "Errore di connessione al database.";
            } else {
                // Parametri prenotazione
                $user_id = intval($_SESSION["user_id"]);
                $servizio = $_POST["servizio"] ?? null;
                $barber = $_POST["barbiere"] ?? null; // optional: Simone or Massimo
                $data = $_POST["data"] ?? null;
                $orario = $_POST["orario"] ?? null; // expected HH:MM:SS
                $note = trim($_POST["note"] ?? '');

                // Validazioni
                if (!$data || !$orario || !$servizio) {
                    $error = "Compila tutti i campi obbligatori.";
                } else {
                    // Controllo formato date e orario separatamente
                    $dtCheck = DateTime::createFromFormat('Y-m-d', $data);
                    $timeCheck = DateTime::createFromFormat('H:i:s', $orario);
                    if (!$dtCheck || $dtCheck->format('Y-m-d') !== $data || !$timeCheck || $timeCheck->format('H:i:s') !== $orario) {
                        $error = "Data o orario non validi.";
                    } else {
                        // Combine into a single DateTime and compare with now
                        $slotDate = DateTime::createFromFormat('Y-m-d H:i:s', $data . ' ' . $orario);
                        $now = new DateTime('now');
                        if (!$slotDate || $slotDate < $now) {
                            $error = "Non è possibile prenotare nel passato.";
                        } else {
                            // Controllo giorno Tue-Sat (N: 1=Mon .. 7=Sun)
                            $dow = intval($dtCheck->format('N'));
                            if ($dow < 2 || $dow > 6) {
                                $error = "È possibile prenotare solo dal martedì al sabato.";
                            } else {
                                // Controllo fascia valida (genero lista orari consentiti)
                                $validSlots = [];
                                $t = new DateTime('08:30');
                                $end = new DateTime('19:45');
                                while ($t <= $end) {
                                    $validSlots[] = $t->format('H:i:s');
                                    $t->modify('+45 minutes');
                                }
                                if (!in_array($orario, $validSlots, true)) {
                                    $error = "Orario non valido.";
                                } else {
                                    // Verifico disponibilità
                                    if ($barber) {
                                        $check = pg_query_params($conn, "SELECT COUNT(*) AS c FROM appuntamenti WHERE data_appuntamento=$1 AND ora_appuntamento=$2 AND barber=$3", array($data, $orario, $barber));
                                        $cnt = intval(pg_fetch_result($check, 0, 'c'));
                                        if ($cnt > 0) {
                                            $error = "Il barbiere selezionato non è disponibile in quella fascia.";
                                        }
                                    } else {
                                        // Se non specificato, cerco un barber libero
                                        $checkBoth = pg_query_params($conn, "SELECT barber FROM appuntamenti WHERE data_appuntamento=$1 AND ora_appuntamento=$2", array($data, $orario));
                                        $occupied = [];
                                        while ($r = pg_fetch_assoc($checkBoth)) {
                                            $occupied[] = $r['barber'];
                                        }
                                        $allBarbers = ['Simone','Massimo'];
                                        $free = array_values(array_diff($allBarbers, $occupied));
                                        if (count($free) === 0) {
                                            $error = "La fascia è completamente prenotata.";
                                        } else {
                                            $barber = $free[0];
                                        }
                                    }

                                    // Se non ci sono errori, controlli lato server addizionali e inserisco
                                    if (!isset($error)) {
                                        // Server-side guard: prevent same user booking same slot or more than one booking per day
                                        $resUserSlot = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2 AND ora_appuntamento=$3', array($user_id, $data, $orario));
                                        $userHasSlot = ($resUserSlot ? intval(pg_fetch_result($resUserSlot,0,'c')) : 0) > 0;
                                        if ($userHasSlot) {
                                            $error = 'Hai già una prenotazione nella stessa fascia oraria.';
                                        }
                                        $resUserDay = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2', array($user_id, $data));
                                        $userHasDay = ($resUserDay ? intval(pg_fetch_result($resUserDay,0,'c')) : 0) > 0;
                                        if ($userHasDay) {
                                            $error = 'Hai già una prenotazione per questo giorno.';
                                        }

                                        if (!isset($error)) {
                                            $servizio_id = intval($servizio);
                                            $insert = pg_query_params($conn,"INSERT INTO appuntamenti (id_utente, id_servizio, data_appuntamento, ora_appuntamento, barber, note) VALUES ($1,$2,$3,$4,$5,$6)", array($user_id, $servizio_id, $data, $orario, $barber, $note));
                                            if ($insert) {
                                                // Redirect alla stessa pagina prenota.php con messaggio -> il client ricaricherà la vista
                                                pg_close($conn);
                                                $dlabel = (DateTime::createFromFormat('Y-m-d', $data) ?: null);
                                                $dateLabel = $dlabel ? $dlabel->format('d/m/Y') : $data;
                                                header('Location: prenota.php?msg=' . urlencode('Prenotazione confermata per ' . $dateLabel . ' ' . substr($orario,0,5) . ' - Barbiere: ' . $barber));
                                                exit;
                                            } else {
                                                // Detect common unique-constraint / duplicate errors and show a friendly message
                                                $db_err = pg_last_error($conn);
                                                if ($db_err && (stripos($db_err, 'unique_user_per_day') !== false || stripos($db_err, 'violates unique constraint') !== false || stripos($db_err, 'duplicate key') !== false)) {
                                                    $error = 'Una sola prenotazione è consentita per utente per giorno. Controlla le tue prenotazioni o annulla quella esistente.';
                                                } else {
                                                    $error = "Errore durante il salvataggio della prenotazione.";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    ?>

    <!--MAIN-->
    <main class="booking-section">
        <div class="booking-container">
            <h1 class="section-title">Prenota il tuo taglio</h1>
            <p class="booking-subtitle">Compila il modulo per fissare un appuntamento.</p>
            <!-- Refresh moved to bottom of the booking container to avoid overlap with header -->

            <form action="#" method="POST" class="booking-form">
                <?php if (!empty($error)): ?>
                    <div class="form-error" style="color:#b00020; margin-bottom:10px;"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['msg'])): ?>
                        <div class="form-success" style="color:green; margin-bottom:10px;"><?= htmlspecialchars($_GET['msg']) ?></div>
                    <?php endif; ?>

                <!-- Elenco prenotazioni future dell'utente con possibilità di annullare -->
                <?php
                $userUpcoming = [];
                if (isset($_SESSION['user_id'])) {
                    include __DIR__ . "/includes/config.php";
                    $c = pg_connect($connection_string);
                    if ($c) {
                        $nowDate = (new DateTime('now'))->format('Y-m-d');
                        $rq = pg_query_params($c, 'SELECT id, data_appuntamento, ora_appuntamento, barber, note FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento>=$2 ORDER BY data_appuntamento, ora_appuntamento', array(intval($_SESSION['user_id']), $nowDate));
                        if ($rq) {
                            while ($row = pg_fetch_assoc($rq)) {
                                $userUpcoming[] = $row;
                            }
                        }
                        pg_close($c);
                    }
                }
                if (count($userUpcoming) > 0): ?>
                    <div class="upcoming-bookings" style="margin-bottom:15px;">
                        <h3>I tuoi prossimi appuntamenti</h3>
                        <ul style="list-style:none; padding-left:0;">
                            <?php foreach ($userUpcoming as $b): ?>
                                <li style="margin-bottom:8px;">
                                    <?php $ud = DateTime::createFromFormat('Y-m-d', $b['data_appuntamento']); ?>
                                    <div class="booking-item">
                                        <div>
                                            <div class="booking-meta">
                                                <strong><?= htmlspecialchars($ud ? $ud->format('d/m/Y') : $b['data_appuntamento']) ?> <?= substr(htmlspecialchars($b['ora_appuntamento']),0,5) ?></strong>
                                                &nbsp;•&nbsp; <span>Barbiere: <?= htmlspecialchars($b['barber']) ?></span>
                                            </div>
                                            <?php if (!empty($b['note'])): ?>
                                                <div class="booking-note" style="margin-top:6px;">Note: <?= htmlspecialchars($b['note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn-cancel" data-booking-id="<?= intval($b['id']) ?>"><i class="fas fa-trash"></i> Annulla</button>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!--Servizio-->
                <div class="form-group">
                    <label for="servizio"><i class="fas fa-cut"></i> Servizio</label>
                    <select id="servizio" name="servizio" required>
                        <option value="" disabled selected>-- Seleziona un servizio --</option>
                        <option value="1">Taglio e Shampoo - €16</option>
                        <option value="2">Taglio, Shampoo e Barba - €20</option>
                    </select>
                </div>

                <!--Barbiere-->
                <div class="form-group">
                    <label for="barbiere"><i class="fas fa-user-tie"></i> Barbiere </label>
                    <select id="barbiere" name="barbiere">
                        <option value="" selected>-- Assegna automaticamente (consigliato) --</option>
                        <option value="Simone">Simone</option>
                        <option value="Massimo">Massimo</option>
                    </select>
                </div>

                <!--Data-->
                <div class="form-row">
                    <div class="form-group">
                        <label for="data"><i class="far fa-calendar-alt"></i> Data</label>
                        <input type="date" id="data" name="data" required value="<?php echo htmlspecialchars($prefill_date); ?>">
                    </div>
                    <!--Ora-->
                    <div class="form-group">
                        <label for="orario"><i class="far fa-clock"></i> Ora</label>
                        <select id="orario" name="orario" required>
                            <option value="" disabled selected>-- Seleziona un orario --</option>
                            <?php
                                $t = new DateTime('08:30');
                                $end = new DateTime('19:45');
                                while ($t <= $end) {
                                    $labelStart = $t->format('G.i');
                                    $tEnd = clone $t; $tEnd->modify('+45 minutes');
                                    $labelEnd = $tEnd->format('G.i');
                                    $value = $t->format('H:i:s');
                                    $sel = ($prefill_orario === $value) ? ' selected' : '';
                                    echo "<option value=\"$value\"$sel>$labelStart - $labelEnd</option>\n";
                                    $t->modify('+45 minutes');
                                }
                            ?>
                        </select>
                        <small style="color: #888;">Aperti dalle 8.30 alle 20:30</small>
                    </div>
                </div>

                <!--Note aggiuntive-->
                <div class="form-group">
                    <label for="note"><i class="fas fa-comment"></i> Note aggiuntive (facoltativo)</label>
                    <textarea id="note" name="note" rows="3" placeholder="Hai richieste particolari? Scrivile qui..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Conferma Prenotazione</button>
            </form>
            <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                <button id="refreshBtn" type="button" style="background:linear-gradient(135deg,#d4af37,#f7c948);color:#1e1e1e;padding:10px 14px;border-radius:8px;border:none;cursor:pointer;font-weight:700;">Refresh disponibilità</button>
            </div>
        </div>
    </main>

    <script>
        // When user picks a date, fetch availability and disable fully occupied slots
        (function(){
            const dateInput = document.getElementById('data');
            const orarioSelect = document.getElementById('orario');
            if (!dateInput || !orarioSelect) return;

            function resetOptions() {
                for (const opt of orarioSelect.options) {
                    opt.disabled = false;
                    // remove marker text if present
                    if (opt.dataset.baseLabel) opt.text = opt.dataset.baseLabel;
                }
            }

            // store base labels
            for (const opt of orarioSelect.options) {
                if (!opt.dataset.baseLabel) opt.dataset.baseLabel = opt.text;
            }

            dateInput.addEventListener('change', async function(){
                resetOptions();
                const d = this.value;
                if (!d) return;
                try {
                    const resp = await fetch(window.location.pathname + '?availability_date=' + encodeURIComponent(d));
                    if (!resp.ok) return;
                    const map = await resp.json();
                    // map keys are times like HH:MM:SS -> array of barbers
                    for (const opt of orarioSelect.options) {
                        const val = opt.value; // HH:MM:SS
                        if (!val) continue;
                        const arr = map[val];
                        if (Array.isArray(arr) && arr.length >= 2) {
                            opt.disabled = true;
                            opt.text = (opt.dataset.baseLabel || opt.text) + ' (Occupato)';
                        } else if (Array.isArray(arr) && arr.length === 1) {
                            // leave enabled but show which barber is occupied
                            opt.text = (opt.dataset.baseLabel || opt.text) + ' (Parziale: ' + arr[0] + ')';
                        }
                    }
                } catch (e) {
                    console.error('Errore availability fetch', e);
                }
            });

            // If the page was opened with prefill parameters, trigger change to update availability
            <?php if ($prefill_date !== ''): ?>
            (async function(){
                // set the date input value (already set server-side) and dispatch change so JS updates options
                try { dateInput.dispatchEvent(new Event('change')); } catch(e) { /* ignore */ }
            })();
            <?php endif; ?>
        })();
    </script>

    <script>
    // Client-side: prevent duplicate bookings and handle cancellation
    (function(){
        const form = document.querySelector('form.booking-form');
        const dateInput = document.getElementById('data');
        const orarioSelect = document.getElementById('orario');

        if (form && dateInput && orarioSelect) {
            form.addEventListener('submit', async function(e){
                // Basic required check first
                if (!form.checkValidity()) return; // let browser show messages

                const d = dateInput.value;
                const o = orarioSelect.value;
                if (!d || !o) return; // form-level validation will handle

                try {
                    // First check if user already has booking same slot/day
                    const resp = await fetch(window.location.pathname + '?check_user=1&date=' + encodeURIComponent(d) + '&orario=' + encodeURIComponent(o), { credentials: 'same-origin' });
                    if (!resp.ok) return; // if endpoint fails, let server handle
                    const json = await resp.json();
                    if (json.sameSlot) {
                        e.preventDefault();
                        alert('Hai già una prenotazione nella stessa fascia oraria.');
                        return;
                    }
                    if (json.sameDay) {
                        e.preventDefault();
                        alert('Hai già una prenotazione per questo giorno: è consentita una sola prenotazione al giorno.');
                        return;
                    }

                    // Then fetch current availability for the selected date to avoid race conditions
                    const availResp = await fetch(window.location.pathname + '?availability_date=' + encodeURIComponent(d), { credentials: 'same-origin' });
                    if (availResp.ok) {
                        const map = await availResp.json();
                        const arr = map[o] || [];
                        // If two or more barbers are occupied then slot is full
                        if (Array.isArray(arr) && arr.length >= 2) {
                            e.preventDefault();
                            alert('La fascia è ora completamente occupata. Aggiorna e scegli un altro orario.');
                            // trigger UI refresh
                            try { dateInput.dispatchEvent(new Event('change')); } catch(e) {}
                            return;
                        }
                        // If user selected a barber and that barber is in the occupied list -> not available
                        const selectedBarber = (document.getElementById('barbiere') && document.getElementById('barbiere').value) || '';
                        if (selectedBarber && Array.isArray(arr) && arr.includes(selectedBarber)) {
                            e.preventDefault();
                            alert('Il barbiere selezionato non è più disponibile in questa fascia. Aggiorna e scegli un altro barbiere oppure lascia che venga assegnato automaticamente.');
                            try { dateInput.dispatchEvent(new Event('change')); } catch(e) {}
                            return;
                        }
                    }

                } catch (err) {
                    console.error('Errore check_user', err);
                }
            });
        }

        // Refresh button handler: update availability and reload upcoming bookings
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn && dateInput) {
            refreshBtn.addEventListener('click', function(){
                // if a date is selected, trigger change to refresh options; otherwise reload page
                if (dateInput.value) {
                    try { dateInput.dispatchEvent(new Event('change')); } catch(e) {}
                    // also reload the page to refresh upcoming bookings from server
                    setTimeout(function(){ window.location.reload(); }, 300);
                } else {
                    window.location.reload();
                }
            });
        }

        // Cancellation: open modal to confirm and collect reason
        (function(){
            const cancelModal = document.createElement('div');
            cancelModal.className = 'modal-overlay';
            cancelModal.id = 'cancelModal';
            cancelModal.innerHTML = `
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
                    <h3 id="cancelModalTitle">Sei sicuro di voler annullare la prenotazione?</h3>
                    <p>Ci dispiace. Se vuoi, scrivici il motivo (opzionale):</p>
                    <textarea id="cancelReason" placeholder="Motivo (opzionale)"></textarea>
                    <div class="modal-actions">
                        <button type="button" class="btn-close" id="cancelModalClose">Annulla</button>
                        <button type="button" class="btn-cancel-confirm" id="cancelModalConfirm">Conferma cancellazione</button>
                    </div>
                </div>`;
            document.body.appendChild(cancelModal);

            let cancelTargetId = null;

            document.addEventListener('click', function(ev){
                const btn = ev.target.closest && ev.target.closest('.btn-cancel');
                if (!btn) return;
                const id = btn.getAttribute('data-booking-id');
                if (!id) return;
                cancelTargetId = id;
                document.getElementById('cancelReason').value = '';
                cancelModal.style.display = 'flex';
                setTimeout(()=> document.getElementById('cancelReason').focus(), 120);
            });

            // close handlers
            cancelModal.addEventListener('click', function(e){
                if (e.target === cancelModal) { cancelModal.style.display = 'none'; cancelTargetId = null; }
            });
            document.addEventListener('click', function(e){
                if (e.target && e.target.id === 'cancelModalClose') { cancelModal.style.display = 'none'; cancelTargetId = null; }
            });

            document.addEventListener('click', function(e){
                if (e.target && e.target.id === 'cancelModalConfirm') {
                    // submit hidden form with reason
                    const reason = document.getElementById('cancelReason').value || '';
                    const f = document.createElement('form'); f.method = 'POST'; f.style.display = 'none';
                    const i1 = document.createElement('input'); i1.name = 'cancel_booking'; i1.value = '1';
                    const i2 = document.createElement('input'); i2.name = 'booking_id'; i2.value = cancelTargetId || '';
                    const i3 = document.createElement('input'); i3.name = 'reason'; i3.value = reason;
                    f.appendChild(i1); f.appendChild(i2); f.appendChild(i3);
                    document.body.appendChild(f);
                    f.submit();
                }
            });
        })();
    })();
    </script>

    <?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>
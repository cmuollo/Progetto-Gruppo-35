<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$prefill_date = isset($_GET['data']) ? trim($_GET['data']) : '';
$prefill_orario = isset($_GET['orario']) ? trim($_GET['orario']) : '';

if (!isset($_SESSION["user_id"])) {
    $next = 'prenota.php';
    $qs = [];
    if ($prefill_date !== '')
        $qs[] = 'data=' . rawurlencode($prefill_date);
    if ($prefill_orario !== '')
        $qs[] = 'orario=' . rawurlencode($prefill_orario);
    if (count($qs) > 0)
        $next .= '?' . implode('&', $qs);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

if (isset($_GET['availability_date'])) {
    require_once __DIR__ . "/includes/logindb.php";
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
            $ora = substr($r['ora_appuntamento'], 0, 8);
            if (!isset($map[$ora]))
                $map[$ora] = [];
            $map[$ora][] = $r['barber'];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($map);
    exit;
}

if (isset($_GET['check_user']) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . "/includes/logindb.php";
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
    $sameSlot = ($res1 ? intval(pg_fetch_result($res1, 0, 'c')) : 0) > 0;
    $res2 = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2', array($userId, $date));
    $sameDay = ($res2 ? intval(pg_fetch_result($res2, 0, 'c')) : 0) > 0;
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
</head>

<body>

    <?php
    include __DIR__ . "/includes/header.php";
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: clienti.php');
        exit;
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST['cancel_booking']) && !empty($_POST['booking_id'])) {
            include __DIR__ . "/includes/logindb.php";
            $conn = pg_connect($connection_string);
            if (!$conn) {
                $error = "Errore di connessione al database.";
            } else {
                $booking_id = intval($_POST['booking_id']);
                $reason = trim($_POST['reason'] ?? '');
                $resOwner = pg_query_params($conn, 'SELECT id_utente, data_appuntamento, ora_appuntamento, barber, note FROM appuntamenti WHERE id=$1', array($booking_id));
                if ($resOwner && pg_num_rows($resOwner) > 0) {
                    $rowB = pg_fetch_assoc($resOwner);
                    $owner = intval($rowB['id_utente']);
                    if ($owner === intval($_SESSION['user_id'])) {
                        $insSql = 'INSERT INTO cancellazioni (booking_id, id_utente, cancelled_by, reason, data_appuntamento, ora_appuntamento, barber, note) VALUES ($1,$2,$3,$4,$5,$6,$7,$8) RETURNING id';
                        $cancelRes = pg_prepare($conn, 'ins_cancel', $insSql);
                        $cancelExec = pg_execute($conn, 'ins_cancel', array($booking_id, $owner, intval($_SESSION['user_id']), $reason, $rowB['data_appuntamento'], $rowB['ora_appuntamento'], $rowB['barber'], $rowB['note']));
                        $cancel_id = null;
                        if ($cancelExec && pg_num_rows($cancelExec) > 0) {
                            $cancel_id = intval(pg_fetch_result($cancelExec, 0, 'id'));
                        }
                        $del = pg_query_params($conn, 'DELETE FROM appuntamenti WHERE id=$1', array($booking_id));
                        if ($del) {
                            pg_close($conn);
                            if ($cancel_id) {
                                header('Location: prenota.php?msg=' . urlencode('Prenotazione annullata') . '&cancel_id=' . urlencode($cancel_id));
                            } else {
                                header('Location: prenota.php?msg=' . urlencode('Prenotazione annullata'));
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
        if (!isset($_SESSION["user_id"])) {
            $error = "Devi essere loggato per prenotare.";
        } else {
            include __DIR__ . "/includes/logindb.php";
            $conn = pg_connect($connection_string);
            if (!$conn) {
                $error = "Errore di connessione al database.";
            } else {
                $user_id = intval($_SESSION["user_id"]);
                $servizio = $_POST["servizio"] ?? null;
                $barber = $_POST["barbiere"] ?? null;
                $data = $_POST["data"] ?? null;
                $orario = $_POST["orario"] ?? null;
                $note = trim($_POST["note"] ?? '');

                if (!$data || !$orario || !$servizio) {
                    $error = "Compila tutti i campi obbligatori.";
                } else {
                    $dtCheck = DateTime::createFromFormat('Y-m-d', $data);
                    $timeCheck = DateTime::createFromFormat('H:i:s', $orario);
                    if (!$dtCheck || $dtCheck->format('Y-m-d') !== $data || !$timeCheck || $timeCheck->format('H:i:s') !== $orario) {
                        $error = "Data o orario non validi.";
                    } else {
                        $slotDate = DateTime::createFromFormat('Y-m-d H:i:s', $data . ' ' . $orario);
                        $now = new DateTime('now');
                        if (!$slotDate || $slotDate < $now) {
                            $error = "Non è possibile prenotare nel passato.";
                        } else {
                            $dow = intval($dtCheck->format('N'));
                            if ($dow < 2 || $dow > 6) {
                                $error = "È possibile prenotare solo dal martedì al sabato.";
                            } else {
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
                                    if ($barber) {
                                        $check = pg_query_params($conn, "SELECT COUNT(*) AS c FROM appuntamenti WHERE data_appuntamento=$1 AND ora_appuntamento=$2 AND barber=$3", array($data, $orario, $barber));
                                        $cnt = intval(pg_fetch_result($check, 0, 'c'));
                                        if ($cnt > 0) {
                                            $error = "Il barbiere selezionato non è disponibile in quella fascia.";
                                        }
                                    } else {
                                        $checkBoth = pg_query_params($conn, "SELECT barber FROM appuntamenti WHERE data_appuntamento=$1 AND ora_appuntamento=$2", array($data, $orario));
                                        $occupied = [];
                                        while ($r = pg_fetch_assoc($checkBoth)) {
                                            $occupied[] = $r['barber'];
                                        }
                                        $allBarbers = ['Simone', 'Massimo'];
                                        $free = array_values(array_diff($allBarbers, $occupied));
                                        if (count($free) === 0) {
                                            $error = "La fascia è completamente prenotata.";
                                        } else {
                                            $barber = $free[0];
                                        }
                                    }

                                    if (!isset($error)) {
                                        $resUserSlot = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2 AND ora_appuntamento=$3', array($user_id, $data, $orario));
                                        $userHasSlot = ($resUserSlot ? intval(pg_fetch_result($resUserSlot, 0, 'c')) : 0) > 0;
                                        if ($userHasSlot) {
                                            $error = 'Hai già una prenotazione nella stessa fascia oraria.';
                                        }
                                        $resUserDay = pg_query_params($conn, 'SELECT COUNT(*) AS c FROM appuntamenti WHERE id_utente=$1 AND data_appuntamento=$2', array($user_id, $data));
                                        $userHasDay = ($resUserDay ? intval(pg_fetch_result($resUserDay, 0, 'c')) : 0) > 0;
                                        if ($userHasDay) {
                                            $error = 'Hai già una prenotazione per questo giorno.';
                                        }

                                        if (!isset($error)) {
                                            $servizio_id = intval($servizio);
                                            $insert = pg_query_params($conn, "INSERT INTO appuntamenti (id_utente, id_servizio, data_appuntamento, ora_appuntamento, barber, note) VALUES ($1,$2,$3,$4,$5,$6)", array($user_id, $servizio_id, $data, $orario, $barber, $note));
                                            if ($insert) {
                                                pg_close($conn);
                                                $dlabel = (DateTime::createFromFormat('Y-m-d', $data) ?: null);
                                                $dateLabel = $dlabel ? $dlabel->format('d/m/Y') : $data;
                                                header('Location: prenota.php?msg=' . urlencode('Prenotazione confermata per ' . $dateLabel . ' ' . substr($orario, 0, 5) . ' - Barbiere: ' . $barber));
                                                exit;
                                            } else {
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

    <main class="booking-section">
        <div class="booking-container">
            <h1 class="section-title">Prenota il tuo taglio</h1>
            <p class="booking-subtitle">Compila il modulo per fissare un appuntamento.</p>

            <form action="#" method="POST" class="booking-form">
                <input type="hidden" id="prefill-date" value="<?= htmlspecialchars($prefill_date) ?>">
                <?php if (!empty($error)): ?>
                    <div class="form-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['msg'])): ?>
                    <div class="form-success"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>

                <?php
                $userUpcoming = [];
                if (isset($_SESSION['user_id'])) {
                    include __DIR__ . "/includes/logindb.php";
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
                    <div class="upcoming-bookings">
                        <h3>I tuoi prossimi appuntamenti</h3>
                        <ul class="upcoming-bookings__list">
                            <?php foreach ($userUpcoming as $b): ?>
                                <li class="upcoming-bookings__item">
                                    <?php $ud = DateTime::createFromFormat('Y-m-d', $b['data_appuntamento']); ?>
                                    <div class="booking-item">
                                        <div>
                                            <div class="booking-meta">
                                                <strong><?= htmlspecialchars($ud ? $ud->format('d/m/Y') : $b['data_appuntamento']) ?>
                                                    <?= substr(htmlspecialchars($b['ora_appuntamento']), 0, 5) ?></strong>
                                                &nbsp;•&nbsp; <span>Barbiere: <?= htmlspecialchars($b['barber']) ?></span>
                                            </div>
                                            <?php if (!empty($b['note'])): ?>
                                                <div class="booking-note booking-note--spaced">Note:
                                                    <?= htmlspecialchars($b['note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn-cancel"
                                                data-booking-id="<?= intval($b['id']) ?>"><i class="fas fa-trash"></i>
                                                Annulla</button>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="servizio"><i class="fas fa-cut"></i> Servizio</label>
                    <select id="servizio" name="servizio" required>
                        <option value="" disabled selected>-- Seleziona un servizio --</option>
                        <option value="1">Taglio e Shampoo - €16</option>
                        <option value="2">Taglio, Shampoo e Barba - €20</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="barbiere"><i class="fas fa-user-tie"></i> Barbiere </label>
                    <select id="barbiere" name="barbiere">
                        <option value="" selected>-- Assegna automaticamente (consigliato) --</option>
                        <option value="Simone">Simone</option>
                        <option value="Massimo">Massimo</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="data"><i class="far fa-calendar-alt"></i> Data</label>
                        <input type="date" id="data" name="data" required
                            value="<?php echo htmlspecialchars($prefill_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="orario"><i class="far fa-clock"></i> Ora</label>
                        <select id="orario" name="orario" required>
                            <option value="" disabled selected>-- Seleziona un orario --</option>
                            <?php
                            $t = new DateTime('08:30');
                            $end = new DateTime('19:45');
                            while ($t <= $end) {
                                $labelStart = $t->format('G.i');
                                $tEnd = clone $t;
                                $tEnd->modify('+45 minutes');
                                $labelEnd = $tEnd->format('G.i');
                                $value = $t->format('H:i:s');
                                $sel = ($prefill_orario === $value) ? ' selected' : '';
                                echo "<option value=\"$value\"$sel>$labelStart - $labelEnd</option>\n";
                                $t->modify('+45 minutes');
                            }
                            ?>
                        </select>
                        <small class="booking-hint">Aperti dalle 8.30 alle 20:30</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="note"><i class="fas fa-comment"></i> Note aggiuntive (facoltativo)</label>
                    <textarea id="note" name="note" rows="3"
                        placeholder="Hai richieste particolari? Scrivile qui..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Conferma Prenotazione</button>
            </form>
            <div class="booking-actions">
                <button id="refreshBtn" type="button" class="btn-refresh">Refresh disponibilità</button>
            </div>
        </div>
    </main>

    <script src="js/prenota.js" defer></script>

    <?php include __DIR__ . "/includes/footer.php"; ?>

</body>

</html>
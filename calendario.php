<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - La Bottega del Barbiere</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php
    include __DIR__ . "/includes/header.php";
    include __DIR__ . "/includes/logindb.php";

    $conn = pg_connect($connection_string);
    if (!$conn) {
        $dbError = "Impossibile connettersi al database.";
    }

    $currentIsBarber = isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin";

    // costruiamo l'elenco dei giorni utili (solo giorni feriali: martedì-sabato) per le prossime due settimane
    $days = [];
    $start = new DateTimeImmutable('today');
    $endLimit = $start->modify('+14 days');
    $d = $start;
    while ($d <= $endLimit) {
        $dow = intval($d->format('N')); // 1=Mon ... 7=Sun
        if ($dow >= 2 && $dow <= 6) { // Tue..Sat
            $days[] = $d->format('Y-m-d');
        }
        $d = $d->modify('+1 day');
    }

    // genera i time slot per gli appuntamenti (08:30 .. 19:45)
    $timeSlots = [];
    $t = new DateTime('08:30');
    $end = new DateTime('19:45');
    while ($t <= $end) {
        $timeSlots[] = $t->format('H:i:s');
        $t->modify('+45 minutes');
    }

    $bookings = [];
    if ($conn) {
        // inseriremo gli appuntamenti esistenti in questa struttura: $bookings[date][time][barber] = record
        if (count($days) === 0) {
            $startDate = $start->format('Y-m-d');
            $endDate = $endLimit->format('Y-m-d');
        } else {
            $startDate = $days[0];
            $endDate = $days[count($days) - 1];
        }
        $sql = "SELECT a.*, u.nome, u.cognome, u.email, u.telefono FROM appuntamenti a LEFT JOIN utenti u ON a.id_utente=u.id WHERE a.data_appuntamento BETWEEN $1 AND $2";
        $res = pg_query_params($conn, $sql, array($startDate, $endDate));
        if ($res) {
            while ($r = pg_fetch_assoc($res)) {
                $d = $r['data_appuntamento'];
                $ora = substr($r['ora_appuntamento'], 0, 8);
                $barb = $r['barber'];
                $bookings[$d][$ora][$barb] = $r; // store whole record incl user fields
            }
        }
    }
    ?>

    <main class="calendar-section">
        <div class="calendar-header">
            <h1 class="section-title">Calendario Appuntamenti</h1>
        </div>
        <?php if (!empty($_GET['msg'])): ?>
            <div class="form-success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if (!empty($dbError)): ?>
            <div class="form-error"><?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table id="calendarTable" class="booking-table calendar-grid">
                <thead>
                    <tr>
                        <th class="time">Orario</th>
                        <?php foreach ($days as $day):
                            $dateObj = new DateTime($day);
                            $dow = intval($dateObj->format('N'));
                            // abbreviated day names to fit table: LUN, MAR, MER, GIO, VEN, SAB, DOM
                            $dayNames = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN', 6 => 'SAB', 7 => 'DOM'];
                            $dayName = $dayNames[$dow];
                            $dateLabel = $dateObj->format('d/m');
                            ?>
                            <th class="day-header">
                                <div class="day-name"><?php echo htmlspecialchars($dayName); ?></div>
                                <div class="day-date"><?php echo htmlspecialchars($dateLabel); ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeSlots as $slot):
                        $labelStart = (new DateTime($slot))->format('G.i');
                        $tEnd = new DateTime($slot);
                        $tEnd->modify('+45 minutes');
                        $labelEnd = $tEnd->format('G.i');
                        ?>
                        <tr>
                            <th class="time"><?= htmlspecialchars($labelStart . ' - ' . $labelEnd) ?></th>
                            <?php foreach ($days as $day):
                                // determina le prenotazioni per questo giorno/orario
                                $cellBookings = $bookings[$day][$slot] ?? array();
                                $count = is_array($cellBookings) ? count($cellBookings) : 0;
                                // link di prenotazione con data e orario (ora:minuti:secondi)
                                $link = 'prenota.php?data=' . urlencode($day) . '&orario=' . urlencode($slot);
                                ?>
                                <td>
                                    <?php
                                    if ($count === 0) {
                                        // libero: solo un quadrato verde
                                        echo '<a href="' . $link . '"><div class="barber-cell free" title="Libero" aria-label="Libero"></div></a>';
                                    } elseif ($count === 1) {
                                        // parziale: singola prenotazione,mostra nome barbiere (giallo)
                                        $vals = array_values($cellBookings);
                                        $rec = $vals[0];
                                        $bname = htmlspecialchars($rec['barber'] ?? 'Barbiere');
                                        $title = $bname;
                                        if ($currentIsBarber) {
                                            $cust = trim(($rec['nome'] ?? '') . ' ' . ($rec['cognome'] ?? '')) ?: 'Utente';
                                            $contact = trim(($rec['email'] ?? '') . ' ' . ($rec['telefono'] ?? ''));
                                            $title .= " - " . $cust . " (" . $contact . ")";
                                        }
                                        // mostra quadrato giallo con nome barbiere; il title contiene il nome del barbiere per i dettagli
                                        echo '<a href="' . $link . '"><div class="barber-cell partial" title="' . htmlspecialchars($title) . '" aria-label="' . htmlspecialchars($bname) . '"></div></a>';
                                    } else {
                                        // due o più prenotazioni: completamente occupato (quadrato rosso)
                                        echo '<a href="' . $link . '"><div class="barber-cell full" title="Occupato" aria-label="Occupato"></div></a>';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="legend">
                <div class="item"><span class="swatch swatch--free"></span> Libero</div>
                <div class="item"><span class="swatch swatch--partial"></span> Parzialmente occupato (nome del barbiere
                    occupato)</div>
                <div class="item"><span class="swatch swatch--full"></span> Occupato</div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>

</body>

</html>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - La Bottega del Barbiere</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .calendar-section { padding: 120px 20px 20px 20px; }
    .calendar-grid { width: 100%; border-collapse: collapse; }
    .calendar-grid th, .calendar-grid td { border: 1px solid #444; padding: 12px; vertical-align: middle; }
    .calendar-grid th.time { width: 140px; background:#111; color:#d4af37; }
    .calendar-grid th.day-header, .calendar-grid td { min-width: 140px; }
    .slot { display: flex; gap:6px; }
    /* compact square indicator for availability */
    .barber-cell { width:36px; height:36px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; margin:6px auto; font-size:0.85rem; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    /* partial (yellow) as compact square like others; tooltip contains barber name */
    .barber-cell.partial { width:36px; height:36px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; margin:6px auto; }
    /* Semaforo colors: green = libero, yellow = parzialmente occupato, red = totalmente occupato */
    .free { background:#27ae60; color:#ffffff; }
    .partial { background:#f1c40f; color:#1e1e1e; }
    .full { background:#e74c3c; color:#ffffff; }
    .closed-day { background:#efefef; color:#666; text-align:center; }
    .day-header { font-weight:600; font-size:0.8rem; text-align:center; }
    .day-header .day-name { display:block; font-weight:700; font-size:0.9rem; }
    .day-header .day-date { display:block; font-size:0.8rem; color:#d0cfcf; }
    .legend { margin-top:10px; display:flex; gap:18px; align-items:center; }
    .legend .item { display:flex; gap:8px; align-items:center; font-weight:600; color:#333; }
    .legend .swatch { width:14px; height:14px; border-radius:50%; display:inline-block; }
    </style>
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

// Build days: from today up to +14 days, but include only Tue-Sat (exclude Mon & Sun)
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
    // safe start/end 
    if (count($days) === 0) {
        $startDate = $start->format('Y-m-d');
        $endDate = $endLimit->format('Y-m-d');
    } else {
        $startDate = $days[0];
        $endDate = $days[count($days)-1];
    }
    $sql = "SELECT a.*, u.nome, u.cognome, u.email, u.telefono FROM appuntamenti a LEFT JOIN utenti u ON a.id_utente=u.id WHERE a.data_appuntamento BETWEEN $1 AND $2";
    $res = pg_query_params($conn, $sql, array($startDate, $endDate));
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $d = $r['data_appuntamento'];
            $ora = substr($r['ora_appuntamento'],0,8);
            $barb = $r['barber'];
            $bookings[$d][$ora][$barb] = $r; // store whole record incl user fields
        }
    }
}
?>

    <main class="calendar-section">
        <div style="display:flex;align-items:center;gap:12px;">
            <h1 class="section-title">Calendario Appuntamenti</h1>
        </div>
        <?php if (!empty($_GET['msg'])): ?>
            <div class="form-success" style="color:green; margin-bottom:10px;"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if (!empty($dbError)): ?>
            <div class="form-error" style="color:#b00020; margin-bottom:10px;"><?= htmlspecialchars($dbError) ?></div>
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
                                            $dayNames = [1=>'LUN',2=>'MAR',3=>'MER',4=>'GIO',5=>'VEN',6=>'SAB',7=>'DOM'];
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
                        // human label
                        $labelStart = (new DateTime($slot))->format('G.i');
                        $tEnd = new DateTime($slot); $tEnd->modify('+45 minutes');
                        $labelEnd = $tEnd->format('G.i');
                    ?>
                        <tr>
                            <th class="time"><?= htmlspecialchars($labelStart . ' - ' . $labelEnd) ?></th>
                            <?php foreach ($days as $day):
                                    // Determine bookings for this day/slot
                                    $cellBookings = $bookings[$day][$slot] ?? array();
                                    $count = is_array($cellBookings) ? count($cellBookings) : 0;
                                    // build prenota link with date and orario (HH:MM:SS)
                                    $link = 'prenota.php?data=' . urlencode($day) . '&orario=' . urlencode($slot);
                                ?>
                                    <td>
                                        <?php
                                            if ($count === 0) {
                                                // free: just a green square (no text)
                                                echo '<a href="' . $link . '"><div class="barber-cell free" title="Libero" aria-label="Libero"></div></a>';
                                            } elseif ($count === 1) {
                                                // partial: single booking -> show barber name (yellow)
                                                $vals = array_values($cellBookings);
                                                $rec = $vals[0];
                                                $bname = htmlspecialchars($rec['barber'] ?? 'Barbiere');
                                                $title = $bname;
                                                if ($currentIsBarber) {
                                                    $cust = trim(($rec['nome'] ?? '') . ' ' . ($rec['cognome'] ?? '')) ?: 'Utente';
                                                    $contact = trim(($rec['email'] ?? '') . ' ' . ($rec['telefono'] ?? ''));
                                                    $title .= " - " . $cust . " (" . $contact . ")";
                                                }
                                                // show compact yellow square; title contains barber name for details
                                                echo '<a href="' . $link . '"><div class="barber-cell partial" title="' . htmlspecialchars($title) . '" aria-label="' . htmlspecialchars($bname) . '"></div></a>';
                                            } else {
                                                // two or more bookings: fully occupied (red square)
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
                <div class="item"><span class="swatch" style="background:#27ae60; padding: 20px; margin-left: 20px;"></span> Libero</div>
                <div class="item"><span class="swatch" style="background:#f1c40f; padding: 20px; margin-left: 20px;"></span> Parzialmente occupato (nome del barbiere occupato)</div>
                <div class="item"><span class="swatch" style="background:#e74c3c; padding: 20px; margin-left: 20px;"></span> Occupato</div>
            </div>
    </div>
    </main>
<?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>
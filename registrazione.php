<?php
// Importo la stringa di connessione al DB ($connection_string)
require_once __DIR__ . "/includes/logindb.php";

// Variabile per messaggi di errore da mostrare nella pagina
$errore = "";
$showLoginLink = false;

// Flag che diventa true se la registrazione va a buon fine
$ok = false;

// Entro qui solo se l'utente ha inviato il form (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Leggo i campi dal form. trim() elimina spazi all'inizio/fine.
    // Se un campo non esiste, uso "" (operatore ??)
    $nome = trim($_POST["nome"] ?? "");
    $cognome = trim($_POST["cognome"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $pwd = $_POST["password"] ?? "";     // password: NON faccio trim (può contenere spazi)
    $pwd2 = $_POST["password2"] ?? "";    // conferma password
    $sec_q = $_POST["security_question"] ?? ""; // codice domanda (pet/school/team/movie)
    $sec_a = trim($_POST["security_answer"] ?? ""); // risposta domanda

    // Validazione lato server: tutti i campi devono essere compilati
    if ($nome === "" || $cognome === "" || $telefono === "" || $email === "" || $pwd === "" || $pwd2 === "" || $sec_q === "" || $sec_a === "") {
        $errore = "Compila tutti i campi.";

        // Controllo formato email con filtro PHP
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Email non valida.";

        // Controllo che le password coincidano
    } elseif ($pwd !== $pwd2) {
        $errore = "Le password non coincidono.";

        // Se la validazione base è ok, passo al DB
    } else {

        // Connessione a PostgreSQL usando la stringa importata
        $conn = pg_connect($connection_string);

        // Se la connessione fallisce
        if (!$conn) {
            $errore = "Connessione al database fallita.";
        } else {

            // Creo hash sicuro della password (NON salvo mai la password in chiaro)
            $pwd_hash = password_hash($pwd, PASSWORD_DEFAULT);

            // Creo hash sicuro della risposta segreta (anche questa non va salvata in chiaro)
            $sec_a_hash = password_hash($sec_a, PASSWORD_DEFAULT);

            // Query di inserimento con parametri ($1..$7) per evitare SQL injection
            $sql = "INSERT INTO utenti (nome,cognome,telefono,email,password_hash,security_question,security_answer_hash)
                    VALUES ($1,$2,$3,$4,$5,$6,$7)";

            // Controllo preventivo: esiste già un account con questa email o telefono?
            $exists = pg_query_params(
                $conn,
                'SELECT 1 FROM utenti WHERE email=$1 OR telefono=$2 LIMIT 1',
                array($email, $telefono)
            );
            if ($exists && pg_num_rows($exists) > 0) {
                $errore = "Email o telefono già esistenti.";
                $showLoginLink = true;
            } else {
                // Preparo la query con un nome "ins_user" (prepared statement)
                pg_prepare($conn, "ins_user", $sql);

                // Eseguo la query passando i valori nell'ordine dei parametri
                // Usiamo @ per evitare che un warning di pg_execute mostri messaggi non gestiti
                $res = @pg_execute($conn, "ins_user", [
                    $nome,
                    $cognome,
                    $telefono,
                    $email,
                    $pwd_hash,
                    $sec_q,
                    $sec_a_hash
                ]);

                // Se l'INSERT fallisce
                if (!$res) {
                    // Leggo l'errore del DB
                    $db_err = pg_last_error($conn);

                    // Se nell'errore compare "duplicate" assumo email già registrata
                    if ($db_err && stripos($db_err, "duplicate") !== false) {
                        $errore = "Email o telefono già esistenti.";
                        $showLoginLink = true;
                    } else {
                        $errore = "Errore database.";
                    }

                    // Se l'INSERT va a buon fine
                } else {
                    // Redirect automatico alla pagina di login e precompilo l'email
                    pg_close($conn);
                    header('Location: login.php?email=' . rawurlencode($email) . '&registered=1');
                    exit;
                }
            }
        }
    }
}

// Valori "sticky" per ripopolare il form in caso di errore
$sticky = [
    'nome' => htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'),
    'cognome' => htmlspecialchars($_POST['cognome'] ?? '', ENT_QUOTES, 'UTF-8'),
    'telefono' => htmlspecialchars($_POST['telefono'] ?? '', ENT_QUOTES, 'UTF-8'),
    'email' => htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    'security_question' => $_POST['security_question'] ?? '',
    'security_answer' => htmlspecialchars($_POST['security_answer'] ?? '', ENT_QUOTES, 'UTF-8'),
];

// Carico email/telefono esistenti per controllo client-side
$existingUsers = [];
$connList = pg_connect($connection_string);
if ($connList) {
    $resList = pg_query($connList, 'SELECT email, telefono FROM utenti');
    if ($resList) {
        while ($row = pg_fetch_assoc($resList)) {
            $existingUsers[] = [
                'email' => strtolower(trim($row['email'] ?? '')),
                'telefono' => preg_replace('/\D+/', '', $row['telefono'] ?? '')
            ];
        }
    }
    pg_close($connList);
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Bottega del Barbiere - Registrazione</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include_once __DIR__ . "/includes/header.php"; ?>
    <main class="auth-page-reg">
        <div class="auth-card">
            <h1>Crea Account</h1>
            <p class="auth-subtitle">Unisciti alla Bottega per prenotare velocemente</p>

            <?php if ($errore !== ""): ?>
                <p class="auth-message auth-message--error">
                    <?= htmlspecialchars($errore) ?>
                    <?php if (!empty($showLoginLink)): ?>
                        &nbsp;<a class="gold-link" href="login.php">Accedi</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <p id="duplicate-error" class="auth-message auth-message--error auth-message--hidden">
                Email o numero di telefono già esistenti. Se hai un account <a class="gold-link"
                    href="login.php">Accedi</a>
            </p>

            <!-- Se registrazione ok mostro messaggio + link login -->
            <?php if ($ok): ?>
                <p class="auth-message auth-message--info">
                    Registrazione completata. Ora puoi <a class="gold-link" href="login.php">accedere</a>.
                </p>
            <?php endif; ?>

            <!-- Form di registrazione (POST -> stessa pagina) -->
            <form id="registerForm" action="registrazione.php" method="POST" class="auth-form">

                <!-- Riga con Nome + Cognome -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" placeholder="Mario" required
                            value="<?= $sticky['nome'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="cognome">Cognome</label>
                        <input type="text" id="cognome" name="cognome" placeholder="Rossi" required
                            value="<?= $sticky['cognome'] ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <input type="tel" id="telefono" name="telefono" placeholder="333 1234567" required
                        value="<?= $sticky['telefono'] ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="latuamail@esempio.com" required
                        value="<?= $sticky['email'] ?>">
                </div>

                <div class="form-group has-eye">
                    <label for="password">Password</label>
                    <div class="input-with-toggle">
                        <input type="password" id="password" name="password" required class="input-with-icon">
                        <i class="fas fa-eye toggle-password" id="togglePassword" role="button" tabindex="0"
                            aria-label="Mostra o nascondi password"></i>
                    </div>

                    <div class="strength-container">
                        <div id="strength-bar" class="strength-bar"></div>
                    </div>
                    <small id="strength-text" class="strength-text"></small>
                </div>

                <!-- Ripeti Password (con icona per mostrare/nascondere) -->
                <div class="form-group has-eye">
                    <label for="password2">Ripeti Password</label>
                    <div class="input-with-toggle">
                        <input type="password" id="password2" name="password2" required class="input-with-icon">
                        <i class="fas fa-eye toggle-password" id="togglePassword2" role="button" tabindex="0"
                            aria-label="Mostra o nascondi password di conferma"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_question">Domanda di sicurezza</label>
                    <select id="security_question" name="security_question" required>
                        <option value="" disabled <?= $sticky['security_question'] === '' ? 'selected' : '' ?>>-- Seleziona
                            una domanda --</option>
                        <option value="pet" <?= $sticky['security_question'] === 'pet' ? 'selected' : '' ?>>Qual è il nome
                            del tuo primo animale domestico?</option>
                        <option value="school" <?= $sticky['security_question'] === 'school' ? 'selected' : '' ?>>Qual è
                            stata la tua scuola elementare?</option>
                        <option value="team" <?= $sticky['security_question'] === 'team' ? 'selected' : '' ?>>Qual è la tua
                            squadra del cuore?</option>
                        <option value="movie" <?= $sticky['security_question'] === 'movie' ? 'selected' : '' ?>>Qual è il
                            tuo film preferito?</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="security_answer">Risposta</label>
                    <input type="text" id="security_answer" name="security_answer" placeholder="Scrivi la risposta"
                        required value="<?= $sticky['security_answer'] ?>">
                </div>

                <button type="submit" class="btn-submit">Registrati</button>

                <div class="auth-links">
                    <span>Hai già un account? <a href="login.php" class="gold-link">Accedi</a></span>
                </div>
            </form>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // animazione di entrata della card
            // Dato che qui non c'è il footer, attiviamo manualmente l'animazione
            setTimeout(function () {
                var card = document.querySelector('.auth-card');
                if (card) card.classList.add('active');
            }, 100); // Ritardo minimo per garantire l'effetto entrata

            // logica per mostrare/nascondere la password
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');

            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.classList.toggle('fa-eye-slash');
                    this.style.color = type === 'text' ? '#d4af37' : '#888';
                });
                // keyboard accessibility
                togglePassword.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault(); this.click();
                    }
                });
            }

            // determina la robustezza della password
            const bar = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');

            // toggle for repeated password
            const togglePassword2 = document.querySelector('#togglePassword2');
            const password2 = document.querySelector('#password2');
            if (togglePassword2 && password2) {
                togglePassword2.addEventListener('click', function () {
                    const type = password2.getAttribute('type') === 'password' ? 'text' : 'password';
                    password2.setAttribute('type', type);
                    this.classList.toggle('fa-eye-slash');
                    this.style.color = type === 'text' ? '#d4af37' : '#888';
                });
                togglePassword2.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
                });
            }

            if (password && bar && text) {
                password.addEventListener('input', function () {
                    const val = password.value;
                    let strength = 0;
                    if (val.length >= 8) strength++;
                    if (val.match(/[0-9]/)) strength++;
                    if (val.match(/[A-Z]/)) strength++;
                    if (val.match(/[@$%&!#?]/)) strength++;

                    let width = '0%';
                    let color = 'red';
                    let label = '';

                    switch (strength) {
                        case 0: width = '0%'; label = ''; break;
                        case 1: width = '25%'; color = '#e74c3c'; label = 'Debole'; break;
                        case 2: width = '50%'; color = '#f1c40f'; label = 'Media'; break;
                        case 3: width = '75%'; color = '#3498db'; label = 'Buona'; break;
                        case 4: width = '100%'; color = '#2ecc71'; label = 'Ottima'; break;
                    }

                    // animate bar
                    bar.style.width = width;
                    bar.style.backgroundColor = color;

                    // fade-in text for better UX
                    try {
                        text.style.transition = 'opacity .28s ease, color .28s ease';
                        text.style.opacity = '0';
                        setTimeout(function () {
                            text.innerText = label;
                            text.style.color = color;
                            text.style.opacity = '1';
                        }, 30);
                    } catch (e) {
                        text.innerText = label;
                        text.style.color = color;
                    }
                });
            }

            // Validate telefono exactly 10 digits on submit
            const registerForm = document.getElementById('registerForm');
            const telInput = document.getElementById('telefono');
            const telError = document.getElementById('telefono-error');
            const emailInput = document.getElementById('email');
            const dupError = document.getElementById('duplicate-error');
            const existingUsers = <?php echo json_encode($existingUsers); ?>;
            if (telInput) {
                telInput.addEventListener('input', function () {
                    const digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 10) {
                        this.style.borderColor = '#b00020';
                        if (telError) telError.style.display = 'block';
                    } else {
                        this.style.borderColor = '';
                        if (telError) telError.style.display = 'none';
                    }
                });
            }
            if (registerForm) {
                registerForm.addEventListener('submit', function (e) {
                    if (dupError) dupError.style.display = 'none';
                    if (emailInput) emailInput.style.borderColor = '';
                    if (telInput) telInput.style.borderColor = '';

                    if (telInput) {
                        const digits = (telInput.value || '').replace(/\D/g, '');
                        if (digits.length !== 10) {
                            e.preventDefault();
                            if (telError) telError.style.display = 'block';
                            telInput.style.borderColor = '#b00020';
                            telInput.focus();
                            return false;
                        }
                    }

                    if (emailInput && telInput) {
                        const emailVal = (emailInput.value || '').trim().toLowerCase();
                        const telDigits = (telInput.value || '').replace(/\D/g, '');
                        const dup = (existingUsers || []).some(function (u) {
                            return (u.email && u.email === emailVal) || (u.telefono && u.telefono === telDigits);
                        });
                        if (dup) {
                            e.preventDefault();
                            if (dupError) dupError.style.display = 'block';
                            if (emailInput) emailInput.style.borderColor = '#b00020';
                            if (telInput) telInput.style.borderColor = '#b00020';
                            if (emailInput) emailInput.focus();
                            return false;
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>
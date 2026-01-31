<?php
// Importo la connessione al DB
require_once __DIR__ . "/includes/logindb.php";

// step indica quale “schermata” mostrare:
// 1 = inserisci email
// 2 = domanda di sicurezza
// 3 = nuova password
// 4 = conferma successo
$step = 1;

// stringa errore da mostrare
$errore = "";

// flag successo (qui viene settato a true nello step 4)
$ok = false;

// variabili di appoggio
$email = "";
$questionCode = "";  // codice domanda (pet/school/team/movie)
$questionText = "";  // testo leggibile della domanda

// Funzione che traduce il codice in testo.
// ATTENZIONE: usa match -> richiede PHP 8+
function questionText($code) {
    return match($code) {
        "pet" => "Qual è il nome del tuo primo animale domestico?",
        "school" => "Qual è stata la tua scuola elementare?",
        "team" => "Qual è la tua squadra del cuore?",
        "movie" => "Qual è il tuo film preferito?",
        default => "Domanda di sicurezza"
    };
}

// variabile connessione (qui non è indispensabile, ma non dà fastidio)
$conn = null;

// Se l’utente ha inviato uno dei form (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Leggo sempre l'email dal POST (in tutti gli step la mantieni con input hidden)
    $email = trim($_POST["email"] ?? "");

    // Controllo base dell'email
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Inserisci un'email valida.";
        $step = 1;

    } else {
        // Connessione DB
        $conn = pg_connect($connection_string);

        if (!$conn) {
            $errore = "Connessione al database fallita.";
            $step = 1;

        } else {

            // Prendo dal DB: codice domanda e hash risposta, cercando per email
            $sql = "SELECT security_question, security_answer_hash FROM utenti WHERE email = $1";
            pg_prepare($conn, "sel_sec", $sql);
            $res = pg_execute($conn, "sel_sec", [$email]);

            // Riga utente (o null se non esiste)
            $row = $res ? pg_fetch_assoc($res) : null;

            // Se non esiste l'utente
            if (!$row) {
                $errore = "Email non trovata.";
                $step = 1;

            } else {
                // Se l'utente esiste, preparo la domanda
                $questionCode = $row["security_question"];
                $questionText = questionText($questionCode);

                // Se nel POST non c'è "answer", significa che siamo solo dopo l’email:
                // quindi passo 2: mostra domanda e input risposta
                if (!isset($_POST["answer"])) {
                    $step = 2;

                } else {
                    // Se answer esiste, significa che l’utente ha provato a rispondere
                    $answer = trim($_POST["answer"] ?? "");

                    if ($answer === "") {
                        $errore = "Inserisci la risposta.";
                        $step = 2;

                    } else {

                        // Verifico la risposta confrontando con l’hash salvato.
                        // Supportiamo sia hash creati con password_hash() sia hash creati con pgcrypto (crypt).
                        $storedAns = $row["security_answer_hash"];
                        $ansOk = false;
                        if ($storedAns && password_verify($answer, $storedAns)) {
                            $ansOk = true;
                        } elseif ($storedAns && @crypt($answer, $storedAns) === $storedAns) {
                            $ansOk = true;
                        }

                        if (!$ansOk) {
                            $errore = "Risposta errata.";
                            $step = 2;

                        } else {
                            // Risposta corretta -> ora posso far scegliere la nuova password

                            // Se non c'è new_password nel POST => devo mostrare form step 3
                            if (!isset($_POST["new_password"])) {
                                $step = 3;

                            } else {
                                // Se c'è new_password, sto tentando l'aggiornamento
                                $np1 = $_POST["new_password"] ?? "";
                                $np2 = $_POST["new_password2"] ?? "";

                                // Controlli sulle nuove password
                                if ($np1 === "" || $np2 === "") {
                                    $errore = "Inserisci la nuova password.";
                                    $step = 3;

                                } elseif ($np1 !== $np2) {
                                    $errore = "Le password non coincidono.";
                                    $step = 3;

                                } else {
                                    // Creo l'hash della nuova password
                                    $new_hash = password_hash($np1, PASSWORD_DEFAULT);

                                    // Update della password per l’utente con quella email
                                    $upd = "UPDATE utenti SET password_hash = $1 WHERE email = $2";
                                    pg_prepare($conn, "upd_pwd", $upd);
                                    $u = pg_execute($conn, "upd_pwd", [$new_hash, $email]);

                                    // Se update fallisce
                                    if (!$u) {
                                        $errore = "Errore durante l'aggiornamento della password.";
                                        $step = 3;

                                    } else {
                                        // Update OK: successo
                                        $ok = true;
                                        $step = 4;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Chiudo connessione
            pg_close($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password dimenticata - La Bottega del Barbiere</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include __DIR__ . "/includes/header.php"; ?>

<main class="auth-page-log">
    <div class="auth-card login-box">
        <h1>Password dimenticata</h1>
        <p class="auth-subtitle">Recupera l’accesso con la domanda di sicurezza</p>

        <?php if ($errore !== ""): ?>
            <p class="auth-message auth-message--error"><?= htmlspecialchars($errore) ?></p>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form id="f1" action="password_dimenticata.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="latuamail@esempio.com" required>
                </div>
                <button type="submit" class="btn-submit">Continua</button>
            </form>

        <?php elseif ($step === 2): ?>
            <form id="f2" action="password_dimenticata.php" method="POST" class="auth-form">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div class="form-group">
                    <label>Domanda di sicurezza</label>
                    <div class="security-question-box">
                        <?= htmlspecialchars($questionText) ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="answer">Risposta</label>
                    <input type="text" id="answer" name="answer" placeholder="Scrivi la risposta" required>
                </div>

                <button type="submit" class="btn-submit">Verifica</button>
            </form>

        <?php elseif ($step === 3): ?>
            <form id="f3" action="password_dimenticata.php" method="POST" class="auth-form">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <input type="hidden" name="answer" value="<?= htmlspecialchars($_POST["answer"] ?? "") ?>">

                <div class="form-group">
                    <label for="new_password">Nuova Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Nuova password" required>
                </div>

                <div class="form-group">
                    <label for="new_password2">Ripeti Password</label>
                    <input type="password" id="new_password2" name="new_password2" placeholder="Ripeti la password" required>
                </div>

                <button type="submit" class="btn-submit">Aggiorna Password</button>
            </form>

        <?php else: ?>
            <p class="auth-message auth-message--success">Password aggiornata correttamente.</p>
            <a href="login.php" class="btn-submit btn-submit-link">Vai al Login</a>
        <?php endif; ?>

        <div class="auth-links auth-links--compact">
            <span>Torna al <a href="login.php" class="gold-link">Login</a></span>
        </div>

    </div>
</main>

<script src="js/password_dimenticata.js" defer></script>

</body>
</html>

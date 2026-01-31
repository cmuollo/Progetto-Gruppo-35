<?php
// Avvio la sessione: mi serve per salvare user_id e user_email
session_start(); // abilita uso di $_SESSION

// Importo la connessione al DB
require_once __DIR__ . "/includes/logindb.php"; // contiene $connection_string

// Messaggio di errore (vuoto = nessun errore)
$errore = "";

// Se l'utente ha inviato il form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Leggo email e password dal POST (trim sull'email)
    $email = trim($_POST["email"] ?? "");
    $pwd   = $_POST["password"] ?? "";

    // Controllo minimo: campi non vuoti
    if ($email === "" || $pwd === "") {
        $errore = "Compila tutti i campi.";
    } else {

        // Connessione al DB
        $conn = pg_connect($connection_string);

        // Se la connessione fallisce
        if (!$conn) {
            $errore = "Connessione al database fallita.";
        } else {

            // Query parametrizzata: cerco l'utente per email (includo ruolo)
            $sql = "SELECT id, email, password_hash, ruolo FROM utenti WHERE email = $1";

            // Preparo la query (evita SQL injection)
            pg_prepare($conn, "sel_user", $sql);

            // Eseguo passando l'email nel parametro $1
            $res = pg_execute($conn, "sel_user", array($email));

            // Se ho un risultato, prendo la riga; altrimenti null
            $row = $res ? pg_fetch_assoc($res) : null;

            // Se utente non esiste oppure password sbagliata -> errore
            $okLogin = false;
            $matchedBy = null; // 'php' or 'crypt'
            if ($row) {
                $storedHash = $row["password_hash"];
                // Compat layer: prima proviamo password_verify (PHP hashes).
                if ($storedHash && password_verify($pwd, $storedHash)) {
                    $okLogin = true;
                    $matchedBy = 'php';
                } elseif ($storedHash && @crypt($pwd, $storedHash) === $storedHash) {
                    // fallback per hash generati con pgcrypto crypt()
                    $okLogin = true;
                    $matchedBy = 'crypt';
                }
            }

            if (!$row || !$okLogin) {
                $errore = "Credenziali non valide.";
            } else {

                // Login OK: salvo nella sessione dati utili
                $_SESSION["user_id"] = $row["id"];
                $_SESSION["user_email"] = $row["email"];
                $_SESSION["user_role"] = $row["ruolo"] ?? 'user';

                // Se l'hash era stato verificato con crypt() (fallback), facciamo il re-hash con password_hash() e
                // aggiorniamo il DB in modo da migrare gli hash verso l'algoritmo PHP corrente.
                if ($matchedBy === 'crypt') {
                    $newHash = password_hash($pwd, PASSWORD_DEFAULT);
                    // update sicuro
                    pg_prepare($conn, 'rehash_user', 'UPDATE utenti SET password_hash = $1 WHERE id = $2');
                    @pg_execute($conn, 'rehash_user', array($newHash, $row['id']));
                }

                // Chiudo connessione
                pg_close($conn);

                // Redirect: rispettiamo il parametro next SOLO se relativo (no open-redirect)
                $next = '';
                if (!empty($_GET['next'])) $next = $_GET['next'];
                if (!empty($_POST['next'])) $next = $_POST['next'];

                $useNext = false;
                if (!empty($next)) {
                    // Allow only relative/simple paths (no schema, no double-slash)
                    if (strpos($next, '//') === false && strpos($next, ':') === false && preg_match('#^[a-zA-Z0-9_\-\/\.\?=&]+$#', $next)) {
                        $useNext = true;
                    }
                }

                if ($useNext) {
                    header('Location: ' . $next);
                } else {
                    header("Location: index.php");
                }
                exit;
            }

            // Se arrivo qui e non ho fatto exit, chiudo connessione
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
    <title>Login - La Bottega del Barbiere</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include __DIR__ . "/includes/header.php"; ?>

<main class="auth-page-log">
    <div class="auth-card login-box">
        <h1>Bentornato</h1>
        <p class="auth-subtitle">Accedi per gestire i tuoi appuntamenti</p>

        <?php if ($errore !== ""): ?>
            <p style="color:#ff6b6b; margin-bottom:15px;"><?= htmlspecialchars($errore) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['profile_updated'])): ?>
            <p style="color:#d4af37; margin-bottom:15px;">Profilo aggiornato con successo. Effettua il login con le nuove credenziali.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['registered'])): ?>
            <p style="color:#d4af37; margin-bottom:15px;">Registrazione completata. Inserisci le tue credenziali per accedere.</p>
        <?php endif; ?>

        <form id="loginForm" action="login.php" method="POST" class="auth-form" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="latuamail@esempio.com" required value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Inserisci la password" required>
            </div>

            <button type="submit" class="btn-submit">Accedi</button>

            <div class="auth-links">
                <a href="password_dimenticata.php">Password dimenticata?</a>
                <span>Non hai un account? <a href="registrazione.php" class="gold-link">Registrati</a></span>
            </div>
        </form>
    </div>
</main>

</body>
</html>

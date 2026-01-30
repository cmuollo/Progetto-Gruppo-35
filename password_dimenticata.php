<?php
// Importo la connessione al DB
require_once __DIR__ . "/includes/config.php";

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
            <p style="color:#ff6b6b; margin-bottom:15px;"><?= htmlspecialchars($errore) ?></p>
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
                    <div style="padding:12px; background:#1a1a1a; border:1px solid #444; border-radius:5px; color:#fff;">
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

                <div class="form-group" style="position: relative;">
                    <label for="new_password">Nuova Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Nuova password" required style="padding-right:40px;">
                    <i class="fas fa-eye" id="togglePassword" role="button" tabindex="0" aria-label="Mostra o nascondi password" style="position: absolute; right: 15px; top: 36px; cursor: pointer; color: #888; z-index:2;"></i>
                    <div class="strength-container" style="margin-top:8px;">
                        <div id="strength-bar" style="height:8px; width:0%; background:#e74c3c; border-radius:4px; transition: width .28s ease, background-color .28s ease;"></div>
                    </div>
                    <small id="strength-text" style="display:block; margin-top:6px; transition: color .28s ease;"></small>
                </div>

                <div class="form-group" style="position: relative;">
                    <label for="new_password2">Ripeti Password</label>
                    <input type="password" id="new_password2" name="new_password2" placeholder="Ripeti la password" required style="padding-right:40px;">
                    <i class="fas fa-eye" id="togglePassword2" role="button" tabindex="0" aria-label="Mostra o nascondi password di conferma" style="position: absolute; right: 15px; top: 36px; cursor: pointer; color: #888; z-index:2;"></i>
                </div>

                <button type="submit" class="btn-submit">Aggiorna Password</button>
            </form>

        <?php else: ?>
            <p style="color:#d4af37; font-weight:bold; margin-bottom:10px;">Password aggiornata correttamente.</p>
            <a href="login.php" class="btn-submit" style="display:inline-block; text-decoration:none; line-height:44px;">Vai al Login</a>
        <?php endif; ?>

        <div class="auth-links" style="margin-top:15px;">
            <span>Torna al <a href="login.php" class="gold-link">Login</a></span>
        </div>

    </div>
</main>

<script>
(function() {
    const f3 = document.getElementById("f3");
    if (f3) {
        const p1 = document.getElementById("new_password");
        const p2 = document.getElementById("new_password2");
        // eye toggles for new password fields
        const toggleP1 = document.getElementById('togglePassword');
        const toggleP2 = document.getElementById('togglePassword2');
        if (toggleP1 && p1) {
            toggleP1.addEventListener('click', function() {
                const type = p1.getAttribute('type') === 'password' ? 'text' : 'password';
                p1.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
                this.style.color = type === 'text' ? '#d4af37' : '#888';
            });
            toggleP1.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); } });
        }
        if (toggleP2 && p2) {
            toggleP2.addEventListener('click', function() {
                const type = p2.getAttribute('type') === 'password' ? 'text' : 'password';
                p2.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
                this.style.color = type === 'text' ? '#d4af37' : '#888';
            });
            toggleP2.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); } });
        }

        // strength indicator (copiato da registrazione.php)
        const bar = document.getElementById('strength-bar');
        const text = document.getElementById('strength-text');
                if (p1 && bar && text) {
            p1.addEventListener('input', function() {
                const val = p1.value;
                let strength = 0;
                if (val.length >= 8) strength++;
                if (val.match(/[0-9]/)) strength++;
                if (val.match(/[A-Z]/)) strength++;
                if (val.match(/[@$%&!#?]/)) strength++;

                let width = '0%';
                let color = 'red';
                let label = '';

                switch(strength) {
                    case 0: width = '0%'; label = ''; break;
                    case 1: width = '25%'; color = '#e74c3c'; label = 'Debole'; break;
                    case 2: width = '50%'; color = '#f1c40f'; label = 'Media'; break;
                    case 3: width = '75%'; color = '#3498db'; label = 'Buona'; break;
                    case 4: width = '100%'; color = '#2ecc71'; label = 'Ottima'; break;
                }

                bar.style.width = width;
                bar.style.backgroundColor = color;

                // fade-in text for better UX
                try {
                    text.style.transition = 'opacity .28s ease, color .28s ease';
                    text.style.opacity = '0';
                    setTimeout(function(){
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

        function check() {
            if (p2.value && p1.value !== p2.value) p2.setCustomValidity("Le password non coincidono.");
            else p2.setCustomValidity("");
        }
        p1.addEventListener("input", check);
        p2.addEventListener("input", check);
        f3.addEventListener("submit", (e) => {
            check();
            if (!f3.checkValidity()) { e.preventDefault(); f3.reportValidity(); }
        });
    }
})();
</script>

</body>
</html>
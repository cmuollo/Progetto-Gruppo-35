<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Solo utenti autenticati
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=profilo.php');
    exit;
}

$conn = pg_connect($connection_string);
if (!$conn) die('Errore DB.');

$userid = intval($_SESSION['user_id']);
$messaggio = '';
$mostra_form = false;
$user_data = array('nome' => '', 'cognome' => '', 'telefono' => '', 'email' => '', 'security_question' => '');

// 1. CARICA DATI UTENTE (sicuro)
$sql = "SELECT nome, cognome, telefono, email, security_question FROM utenti WHERE id = $1";
$res = pg_query_params($conn, $sql, array($userid));
if ($res) $user_data = pg_fetch_assoc($res) ?: $user_data;

// Nota: non persistiamo la verifica della password nella sessione. L'utente può
// verificare la password per mostrare il form nella stessa richiesta; l'aggiornamento
// richiederà nuovamente la password corrente per conferma.

// VERIFICA PASSWORD (usa password_verify contro hash memorizzata)
if (isset($_POST['verifica_password']) && !empty($_POST['password'])) {
    $password = trim($_POST['password']);
    // fetch hashed password from DB
    $res_check = pg_query_params($conn, 'SELECT password_hash FROM utenti WHERE id=$1', array($userid));
    if ($res_check && pg_num_rows($res_check) > 0) {
    $row = pg_fetch_assoc($res_check);
    $stored = $row['password_hash'];
    if ($stored && (password_verify($password, $stored) || (@crypt($password, $stored) === $stored))) {
            // Mostra il form di modifica solo per questa richiesta
            $mostra_form = true;
            $messaggio = '<div class="success-msg">Password corretta! Ora puoi modificare i dati (dovrai inserire nuovamente la password per confermare).</div>';
        } else {
            $messaggio = '<div class="error-msg">Password errata!</div>';
        }
    } else {
        $messaggio = '<div class="error-msg"> Utente non trovato!</div>';
    }
}

// 3. AGGIORNA DATI UTENTE
// Richiede la password corrente nel POST per confermare l'identità prima dell'aggiornamento
if (isset($_POST['aggiorna_dati'])) {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');

    if (empty($current_password)) {
        $messaggio = '<div class="error-msg">Devi inserire la password attuale per confermare la modifica.</div>';
        $mostra_form = true;
    } else {
        // verify password
        $res_pw = pg_query_params($conn, 'SELECT password_hash FROM utenti WHERE id=$1', array($userid));
        if ($res_pw && pg_num_rows($res_pw) > 0) {
            $rpw = pg_fetch_assoc($res_pw);
            $stored = $rpw['password_hash'];
            if (!($stored && (password_verify($current_password, $stored) || (@crypt($current_password, $stored) === $stored)))) {
                $messaggio = '<div class="error-msg">Password non corretta. Impossibile aggiornare i dati.</div>';
                $mostra_form = true;
            } else {
                // Check email uniqueness
                $res_email = pg_query_params($conn, 'SELECT id FROM utenti WHERE email=$1 AND id<>$2', array($email, $userid));

                if ($res_email && pg_num_rows($res_email) == 0) {
                    // Note: security_question is intentionally NOT updated here (not editable from profile)
                    $ok = pg_query_params($conn, 'UPDATE utenti SET nome=$1, cognome=$2, telefono=$3, email=$4 WHERE id=$5', array($nome, $cognome, $telefono, $email, $userid));
                    if ($ok) {
                        // After changing identifying data, invalidate session and force re-login
                        pg_close($conn);
                        // Unset session variables and destroy session
                        if (session_status() !== PHP_SESSION_NONE) {
                            $_SESSION = array();
                            if (ini_get("session.use_cookies")) {
                                $params = session_get_cookie_params();
                                setcookie(session_name(), '', time() - 42000,
                                    $params['path'], $params['domain'], $params['secure'], $params['httponly']
                                );
                            }
                            session_unset();
                            session_destroy();
                        }
                        header('Location: login.php?profile_updated=1');
                        exit;
                    } else {
                        $messaggio = '<div class="error-msg"> Errore DB!</div>';
                        $mostra_form = true;
                    }
                } else {
                    $messaggio = '<div class="error-msg">Email già usata!</div>';
                    $mostra_form = true;
                }
            }
        } else {
            $messaggio = '<div class="error-msg">Utente non trovato!</div>';
            $mostra_form = true;
        }
    }
}
// Gestisce l'azione annulla: ricarica la pagina per mostrare la sezione password
if (isset($_POST['annulla'])) {
    header('Location: profilo.php');
    exit;
}
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente - Modifica i tuoi dati</title>
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* stili per il form */
        .profile-container { max-width: 600px; margin: 120px auto 40px; padding: 30px; background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .success-msg { color: #2e7d32; margin-bottom: 12px; font-weight: 600; }
        .error-msg { color: #b00020; margin-bottom: 12px; font-weight: 600; }
        .profile-title { text-align: center; color: #d4af37; margin-bottom: 30px; font-size: 2.2em; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
    .form-group input, .form-group textarea { width: 100%; padding: 15px; padding-right: 44px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; transition: border-color 0.3s; box-sizing: border-box; }
    /* wrapper che contiene input + icona per centrare l'occhio rispetto all'input */
    .input-with-toggle { position: relative; display: block; }
    /* icona per mostra/nascondi password dentro il field (centrata verticalmente rispetto all'input) */
    .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; z-index: 2; font-size: 1rem; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 10px rgba(212,175,55,0.2); }
        .btn-primary, .btn-secondary { padding: 15px 30px; border: none; border-radius: 25px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; width: 100%; margin: 10px 0; }
        .btn-primary { background: linear-gradient(135deg, #d4af37, #f7c948); color: #1e1e2e; }
        .btn-secondary { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(212,175,55,0.4); }
        .btn-secondary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(255,107,107,0.4); }
        .password-section, .edit-section { display: <?php echo $mostra_form ? 'none' : 'block'; ?>; }
        .edit-section { display: <?php echo $mostra_form ? 'block' : 'none'; ?>; }
        @media (max-width: 768px) { .profile-container { margin: 20px; padding: 20px; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="profile-container">
        <h1 class="profile-title"><i class="fas fa-user-edit"></i> Profilo Utente</h1>
        
        <?php echo $messaggio; ?>
        
        <!-- SEZIONE PASSWORD -->
        <div class="password-section">
            <div style="text-align: center; color: #666; margin-bottom: 30px;">
                <i class="fas fa-lock" style="font-size: 3em; color: #d4af37;"></i>
                <p style="font-size: 1.2em; margin-top: 15px;">Inserisci password per modificare dati</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password"><i class="fas fa-key"></i> Password attuale</label>
                    <div class="input-with-toggle">
                        <input type="password" id="password" name="password" required placeholder="Password..." autocomplete="current-password">
                        <i class="fas fa-eye toggle-password" id="toggleVerifyPassword" role="button" tabindex="0" aria-label="Mostra o nascondi password"></i>
                    </div>
                </div>
                <button type="submit" name="verifica_password" class="btn-primary">
                    <i class="fas fa-eye"></i> Verifica e Modifica
                </button>
            </form>
            <p style="text-align: center; margin-top: 12px;"><a href="password_dimenticata.php">Hai dimenticato la password?</a></p>
        </div>
        
        <!-- SEZIONE MODIFICA DATI -->
        <div class="edit-section">
            <form method="POST">
                <input type="hidden" name="aggiorna_dati" value="1">
                
                <div class="form-group">
                    <label for="nome"><i class="fas fa-user"></i> Nome *</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($user_data['nome'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="cognome"><i class="fas fa-user"></i> Cognome *</label>
                    <input type="text" id="cognome" name="cognome" value="<?php echo htmlspecialchars($user_data['cognome'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono"><i class="fas fa-phone"></i> Telefono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($user_data['telefono'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                </div>
                
                <!-- La domanda di sicurezza non è modificabile -->

                <div class="form-group">
                    <label for="current_password"><i class="fas fa-key"></i> Password attuale *</label>
                    <div class="input-with-toggle">
                        <input type="password" id="current_password" name="current_password" required placeholder="Password..." autocomplete="current-password">
                        <i class="fas fa-eye toggle-password" id="toggleCurrentPassword" role="button" tabindex="0" aria-label="Mostra o nascondi password"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Aggiorna Profilo
                </button>
            </form>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="annulla" class="btn-secondary">
                    <i class="fas fa-times"></i> Annulla
                </button>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
        // opzione annulla (guardia: controlla l'esistenza dei bottoni)
        const annullaBtn = document.querySelector('button[name="annulla"]');
        if (annullaBtn) {
            annullaBtn.addEventListener('click', function(e){
                e.preventDefault();
                const pwd = document.querySelector('.password-section');
                const edit = document.querySelector('.edit-section');
                if (pwd) pwd.style.display = 'block';
                if (edit) edit.style.display = 'none';
            });
        }

        // gestione visibilità password
        (function(){
            const toggleVerify = document.getElementById('toggleVerifyPassword');
            const verifyInput = document.getElementById('password');
            if (toggleVerify && verifyInput) {
                toggleVerify.addEventListener('click', function(){
                    const t = verifyInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    verifyInput.setAttribute('type', t);
                    this.classList.toggle('fa-eye-slash');
                    this.style.color = t === 'text' ? '#d4af37' : '#888';
                });
                toggleVerify.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); } });
            }

            const toggleCurrent = document.getElementById('toggleCurrentPassword');
            const currentInput = document.getElementById('current_password');
            if (toggleCurrent && currentInput) {
                toggleCurrent.addEventListener('click', function(){
                    const t = currentInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    currentInput.setAttribute('type', t);
                    this.classList.toggle('fa-eye-slash');
                    this.style.color = t === 'text' ? '#d4af37' : '#888';
                });
                toggleCurrent.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); } });
            }

            // validazione telefono in tempo reale
            const telInput = document.getElementById('telefono');
            const telError = document.getElementById('telefono-error');
            if (telInput) {
                telInput.addEventListener('input', function(){
                    const digits = (this.value||'').replace(/\D/g,'');
                    if (digits.length !== 10) {
                        this.style.borderColor = '#b00020';
                        if (telError) telError.style.display = 'block';
                    } else {
                        this.style.borderColor = '';
                        if (telError) telError.style.display = 'none';
                    }
                });
            }
        })();
    </script>
</body>
</html>

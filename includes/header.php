<?php
// Avvio sessione solo se non è già attiva (così header è includibile ovunque)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nome file corrente (serve per aggiungere la classe "active" al link giusto)
$current = basename($_SERVER["PHP_SELF"]);

// Ritorna "active" se $page è la pagina corrente
function isActive(string $page, string $current): string {
    return ($page === $current) ? "active" : "";
}

// Flag comodo: utente loggato?
$isLogged = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>

<header class="main-header">

    <!-- Menu principale -->
    <nav class="nav-links">
        <a href="index.php"       class="<?= isActive("index.php", $current) ?>">Home</a>
        <a href="chi-siamo.php"   class="<?= isActive("chi-siamo.php", $current) ?>">Chi Siamo</a>
        <a href="prodotti.php"    class="<?= isActive("prodotti.php", $current) ?>">Prodotti</a>
        <?php
            // If admin, route Prenota link to clienti.php (admin report)
            if ($isLogged) {
                    if ($isAdmin) {
                        $prenotaLink = "clienti.php";
                        $prenotaClass = isActive("clienti.php", $current);
                    } else {
                        $prenotaLink = "prenota.php";
                        $prenotaClass = isActive("prenota.php", $current);
                    }
                } else {
                    // quando non loggato rimando al login ma con parametro next per tornare a prenota.php
                    $prenotaLink = "login.php?next=prenota.php";
                    $prenotaClass = "";
                }
            ?>
            <a href="<?= $prenotaLink ?>" class="<?= $prenotaClass ?>"><?php echo $isAdmin ? 'CLIENTI' : 'Prenota'; ?></a>
        <a href="calendario.php"  class="<?= isActive("calendario.php", $current) ?>">Calendario</a>
        <a href="profilo.php"  class="<?= isActive("profilo.php", $current) ?>">Profilo</a>
    </nav>

    <!-- Area autenticazione (destra) -->
    <div class="auth-section">
        <i class="fas fa-user-circle user-icon"></i>

        <?php if ($isLogged): ?>
            <!-- Se loggato: mostro stato utente + Logout -->
            <!-- Qui uso l’email salvata in sessione (messa in login.php) -->
            <span style="color:#ccc; font-size:0.9rem;">
                <?= htmlspecialchars($_SESSION["user_email"] ?? "Utente") ?>
                <?php if ($isAdmin): ?>
                    <span style="background:#d4af37; color:#111; font-weight:700; padding:2px 6px; margin-left:8px; border-radius:6px; font-size:0.75rem;">ADMIN</span>
                <?php endif; ?>
            </span>

            <span class="separator">|</span>

            <!-- Logout con stessa classe stile dei bottoni -->
            <a href="logout.php" class="auth-btn">Logout</a>

        <?php else: ?>
            <!-- Se NON loggato: mostro Login + Registrazione -->
            <a href="login.php" class="auth-btn <?= isActive("login.php", $current) ?>">Login</a>
            <span class="separator">|</span>
            <a href="registrazione.php" class="auth-btn <?= isActive("registrazione.php", $current) ?>">Registrazione</a>
        <?php endif; ?>
    </div>

</header>

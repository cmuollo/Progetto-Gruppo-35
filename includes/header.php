<?php
// Avvio sessione solo se non è già attiva (così header è includibile ovunque)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nome file corrente (serve per aggiungere la classe "active" al link giusto)
$current = basename($_SERVER["PHP_SELF"]);

// Ritorna "active" se $page è la pagina corrente
function isActive(string $page, string $current): string
{
    return ($page === $current) ? "active" : "";
}

// Flag comodo: utente loggato?
$isLogged = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>

<header class="main-header">

    <nav class="nav-links">
        <a href="index.php" class="<?= isActive("index.php", $current) ?>">Home</a>
        <a href="chi-siamo.php" class="<?= isActive("chi-siamo.php", $current) ?>">Chi Siamo</a>
        <a href="prodotti.php" class="<?= isActive("prodotti.php", $current) ?>">Prodotti</a>
        <?php
    // Se admin, il link Prenota porta al report clienti
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
        <a href="calendario.php" class="<?= isActive("calendario.php", $current) ?>">Calendario</a>
        <a href="profilo.php" class="<?= isActive("profilo.php", $current) ?>">Profilo</a>
    </nav>

    <div class="auth-section">
        <i class="fas fa-user-circle user-icon"></i>

        <?php if ($isLogged): ?>
            <span class="auth-user-email">
                <?= htmlspecialchars($_SESSION["user_email"] ?? "Utente") ?>
                <?php if ($isAdmin): ?>
                    <span class="auth-admin-badge">ADMIN</span>
                <?php endif; ?>
            </span>

            <span class="separator">|</span>

            <a href="logout.php" class="auth-btn">Logout</a>

        <?php else: ?>
            <a href="login.php" class="auth-btn <?= isActive("login.php", $current) ?>">Login</a>
            <span class="separator">|</span>
            <a href="registrazione.php" class="auth-btn <?= isActive("registrazione.php", $current) ?>">Registrazione</a>
        <?php endif; ?>
    </div>

</header>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLogged = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Bottega del Barbiere</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="home-page">

    <?php include __DIR__ . "/includes/header.php"; ?>

    <!--TITOLO CENTRALE-->
    <main class="hero-section">
        <h1 class="main-title">La Bottega del Barbiere</h1>
    </main>

    <?php if (!$isLogged): ?>
        <div id="guest-message" class="auth-banner auth-banner--guest auth-banner--bottom" style="background:#111;color:#ccc;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 40px;border-top:1px solid #333;">
            <div class="banner-content" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <strong>Registrati per sbloccare le funzionalità aggiuntive</strong>
            </div>
            <div class="banner-actions" style="display:flex;gap:12px;align-items:center;">
                <a href="registrazione.php" class="banner-btn" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#ffffff;color:#111111;font-weight:700;text-decoration:none;">Registrati</a>
                <a href="login.php" class="banner-btn banner-btn--ghost" style="display:inline-block;padding:10px 18px;border-radius:6px;background:transparent;color:#d4af37;border:1px solid #d4af37;font-weight:700;text-decoration:none;">Login</a>
            </div>
        </div>
    <?php endif; ?>

    <?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>

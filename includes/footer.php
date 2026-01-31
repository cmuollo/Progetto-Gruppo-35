<footer class="main-footer">        
    <div class="footer-container">
        
        <!-- colonna acquisti -->
        <div class="footer-col">
            <h3>Acquisti</h3>
            <ul>
                <li><a href="prodotti.php">Prodotti</a></li>
                <?php
                    // Se non loggato, manda al login (niente warning: NON faccio session_start qui)
                    $prenotaHref = (isset($_SESSION["user_id"]) ? "prenota.php" : "login.php");
                ?>
                <li><a href="<?= htmlspecialchars($prenotaHref) ?>">Prenota un taglio</a></li>
            </ul>
        </div>

        <!-- colonna su di noi -->
        <div class="footer-col">
            <h3>Su di noi</h3>
            <ul>
                <li><a href="chi-siamo.php">Chi Siamo</a></li>
                <li><a href="calendario.php">Calendario</a></li>
            </ul>
        </div>

        <!-- colonna social -->
        <div class="footer-col">
            <h3>Seguici</h3>
            <div class="social-links">
                <a href="https://www.instagram.com" target="_blank"><i class="fab fa-instagram"></i> Instagram</a>
                <a href="https://www.facebook.com" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
                <a href="https://www.youtube.com" target="_blank"><i class="fab fa-youtube"></i> Youtube</a>
            </div>
        </div>

        <!-- colonna info -->
        <div class="footer-col">
            <h3>Contattaci</h3>
            <div class="contact-info">
                <p><i class="fas fa-map-marker-alt"></i> Via Salerno 24, Pagani (SA)</p>
                <p><i class="far fa-envelope"></i> labottegadelbarbiere@gmail.com</p>
                <p><i class="fas fa-phone"></i> +39 329 355 5469</p>
            </div>
        </div>

    </div>
</footer>

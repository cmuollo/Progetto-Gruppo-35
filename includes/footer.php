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
                <a href="https://www.instagram.com" target="_blank" aria-label="Instagram">
                    <!-- Instagram SVG -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:middle;margin-right:8px;">
                        <path d="M7 2C4.243 2 2 4.243 2 7v10c0 2.757 2.243 5 5 5h10c2.757 0 5-2.243 5-5V7c0-2.757-2.243-5-5-5H7zm10 2a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h10zM12 7.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zm0 2a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM17.8 6.2a.9.9 0 1 1-1.8 0 .9.9 0 0 1 1.8 0z" />
                    </svg>
                    <span>Instagram</span>
                </a>
                <a href="https://www.facebook.com" target="_blank" aria-label="Facebook">
                    <!-- Facebook SVG -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:middle;margin-right:8px;">
                        <path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2.2V12h2.2V9.8c0-2.2 1.3-3.4 3.3-3.4.96 0 1.96.17 1.96.17v2.2h-1.13c-1.12 0-1.47.7-1.47 1.42V12h2.5l-.4 2.9h-2.1v7A10 10 0 0 0 22 12z" />
                    </svg>
                    <span>Facebook</span>
                </a>
                <a href="https://www.youtube.com" target="_blank" aria-label="YouTube">
                    <!-- YouTube SVG -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:middle;margin-right:8px;">
                        <path d="M23.5 6.2a3 3 0 0 0-2.1-2.12C19.7 3.6 12 3.6 12 3.6s-7.7 0-9.4.48A3 3 0 0 0 .5 6.2 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.12C4.3 20.4 12 20.4 12 20.4s7.7 0 9.4-.48a3 3 0 0 0 2.1-2.12A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.8zM9.8 15.5V8.5l6.2 3.5-6.2 3.5z" />
                    </svg>
                    <span>YouTube</span>
                </a>
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

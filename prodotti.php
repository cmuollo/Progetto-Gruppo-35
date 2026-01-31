<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servizi e Prodotti - La Bottega del Barbiere</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <?php include __DIR__ . "/includes/header.php"; ?>

    <!--SEZIONE SERVIZI-->
    <main class="products-section">
        <h1 class="section-title">I Nostri Servizi</h1>

        <!--TAGLIO e SHAMPOO-->
        <section class="product-card product-dark">
            <div class="product-content">
                <div class="product-image">
                    <img src="multimedia/taglio.jpg" alt="Taglio e Shampoo">
                </div>
                <div class="product-details">
                    <div>
                        <h2>Taglio e Shampoo</h2>
                        <p>Il classico taglio eseguito a forbice e macchinetta, rifinito con uno shampoo rinfrescante e styling finale.</p>
                    </div>
                    <span class="product-price">€ 16,00</span>
                </div>
            </div>
        </section>

        <!--TAGLIO SHAMPOO e BARBA-->
        <section class="product-card product-light">
            <div class="product-content">
                <div class="product-details">
                    <div>
                        <h2>Taglio, Shampoo e Barba</h2>
                        <p>Il trattamento completo per il gentleman. Taglio su misura e modellatura della barba con panno caldo e oli essenziali.</p>
                    </div>
                    <span class="product-price">€ 20,00</span>
                </div>
                <div class="product-image">
                    <img src="multimedia/combo.jpg" alt="Taglio e Barba Combo">
                </div>
            </div>
        </section>

        <!--SEZIONE PRODOTTI-->
        <h1 class="section-title">I Nostri Prodotti</h1>

        <!--CERA-->
        <section class="product-card product-dark">
            <div class="product-content">
                <div class="product-image">
                    <img src="multimedia/Cera-Opaca.jpg" alt="Cera per capelli">
                </div>
                <div class="product-details">
                    <div>
                        <h2>Cera Opaca Strong</h2>
                        <p>Una cera a tenuta forte con finitura opaca naturale. Ideale per stili strutturati che durano tutto il giorno senza ungere.</p>
                    </div>
                    <span class="product-price">€ 20,00</span>
                </div>
            </div>
        </section>

        <!--OLIO-->
        <section class="product-card product-light">
            <div class="product-content">
                <div class="product-details">
                    <div>
                        <h2>Olio da Barba</h2>
                        <p>Miscela di oli essenziali naturali per ammorbidire e idratare la barba. Profumazione legnosa e delicata.</p>
                    </div>
                    <span class="product-price">€ 15,00</span>
                </div>
                <div class="product-image">
                    <img src="multimedia/olio.jpg" alt="Olio Barba">
                </div>
            </div>
        </section>

        <!--SHAMPOO-->
        <section class="product-card product-dark">
            <div class="product-content">
                <div class="product-image">
                    <img src="multimedia/Shampoo.jpg" alt="Shampoo">
                </div>
                <div class="product-details">
                    <div>
                        <h2>Shampoo Rinfrescante</h2>
                        <p>Detergente quotidiano alla menta piperita. Stimola la cute e lascia una sensazione di freschezza immediata.</p>
                    </div>
                    <span class="product-price">€ 12,00</span>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>
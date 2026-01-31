<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Siamo - La Bottega del Barbiere</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="multimedia/barbiere.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>

    <?php include __DIR__ . "/includes/header.php"; ?>

<main class="barber-profiles-section">

    <div id="map-container" class="reveal">
        <h2 class="map-title"><i class="fas fa-map-marked-alt"></i> Dove Siamo</h2>
        <div id="map"></div>
    </div>

    <!--Descrizione Shop-->
    <section class="shop-description">
            <div class="description-text">
                <h1 class="main-title">La Bottega del Barbiere</h1>
                <p>
                    Situata nel cuore della pittoresca Campania, "La bottega del barbiere" è il barber shop di riferimento per l'uomo moderno 
                     che non rinuncia al piacere di un'esperienza di cura personale di alta qualità. Facilmente raggiungibile via Salerno 24, a Pagani (SA),
                     questa rinomata barberia offre un'esperienza unica di ritorno al classico barbiere di una volta. Ricercata per la sua notorietà per
                     il taglio uomo e la cura della barba, riceve visitatori da tutta la regione e oltre.
                </p>
                <p>
                    Grazie agli efficienti mezzi di trasporto pubblico disponibili nella zona, e dalla vicinanza con l'autostrada, 
                    il percorso per giungere alla bottega è agevole e veloce. Nell'app Zetabarber è possibile prenotare in un clic il servizio
                     desiderato, senza dover attendere in loco. Un servizio di consulenza è incluso in ogni prenotazione per assicurare il miglior
                      taglio e cura della barba. "La bottega del barbiere" è un punto di riferimento, un franchising di barberie diffuso in tutta Italia.
                       Venite a scoprire il miglior barbiere a Pagani.
                </p>
            </div>
        </section>

        <!--BARBIERI-->
        <h1 class="section-title">Il Nostro Team</h1>

        <!--SIMONE-->
        <section class="barber-profile profile-simone">
            <div class="profile-content">
                
                <div class="profile-image">
                    <img src="multimedia/Barbieri.jpeg" alt="Barbiere Simone">
                </div>

                <div class="profile-text">
                    <h2>Simone</h2>
                    <p>
                        Braccio destro di Massimo e talento emergente, Simone porta in salone l'energia delle nuove tendenze. 
                        Specializzato in tagli moderni, sfumature "razor fade" e styling urbani, è il punto di riferimento per i più giovani 
                        e per chi cerca un look al passo coi tempi.
                    </p>
                    <p>
                        Nonostante la giovane età, la sua mano è ferma e precisa. Sotto la guida esperta di Massimo, 
                        Simone unisce la tecnica tradizionale a una creatività fresca, garantendo uno stile unico e 
                        personalizzato per ogni cliente.
                    </p>
                </div>
            </div>
        </section>

        <!--MASSIMO-->
        <section class="barber-profile profile-massimo">
            <div class="profile-content">
                
                <div class="profile-text">
                    <h2>Massimo</h2>
                    <p>
                        Fondatore e anima della Bottega, Massimo vanta oltre trent'anni di esperienza nell'arte della barberia italiana. 
                        Maestro della "vecchia scuola", è specializzato nel taglio classico a forbice e nel rituale della 
                        rasatura tradizionale con panno caldo.
                    </p>
                    <p>
                        La sua visione ha dato vita a questo luogo, dove professionalità e accoglienza si incontrano. 
                        Per Massimo, ogni cliente è un ospite d'onore e ogni taglio è un'opera di precisione che deve durare nel tempo.
                    </p>
                </div>

                <div class="profile-image">
                    <img src="multimedia/cracco.jpg" alt="Barbiere Massimo">
                </div>

            </div>
        </section>
        <!-- map moved to top of page for better visibility -->
    </main>

    <?php include __DIR__ . "/includes/footer.php"; ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Coordinate Pagani (SA)
    var lat = 40.742556;
    var lon = 14.625962;

    // crea la mappa solo dopo che il DOM è pronto per evitare problemi con contenitori nascosti
    document.addEventListener('DOMContentLoaded', function(){
        var map = L.map('map', {
            scrollWheelZoom: false
        }).setView([lat, lon], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        var marker = L.marker([lat, lon]).addTo(map);
        marker.bindPopup("<b>La Bottega del Barbiere</b><br>Via Salerno 24, Pagani (SA)").openPopup();
    });
    
</script>

</body>
</html>
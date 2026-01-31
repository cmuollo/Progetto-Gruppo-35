// Coordinate Pagani (SA)
var lat = 40.742556;
var lon = 14.625962;

// Crea la mappa solo dopo che il DOM è pronto
document.addEventListener('DOMContentLoaded', function () {
  var map = L.map('map', {
    scrollWheelZoom: false
  }).setView([lat, lon], 16);

  // Aggiunge lo stile della mappa (OpenStreetMap)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  // Inserisce il segnaposto con popup
  var marker = L.marker([lat, lon]).addTo(map);
  marker
    .bindPopup("<b>La Bottega del Barbiere</b><br>Via Salerno 24, Pagani (SA)")
    .openPopup();
});

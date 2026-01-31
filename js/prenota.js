(function () {
  const dateInput = document.getElementById("data");
  const orarioSelect = document.getElementById("orario");
  const prefillDateInput = document.getElementById("prefill-date");

  if (!dateInput || !orarioSelect) return;

  function resetOptions() {
    for (const opt of orarioSelect.options) {
      opt.disabled = false;
      if (opt.dataset.baseLabel) opt.text = opt.dataset.baseLabel;
    }
  }

  // Salva le etichette base per ripristinare i testi originali
  for (const opt of orarioSelect.options) {
    if (!opt.dataset.baseLabel) opt.dataset.baseLabel = opt.text;
  }

  // Aggiorna la disponibilità quando cambia la data
  dateInput.addEventListener("change", async function () {
    resetOptions();
    const d = this.value;
    if (!d) return;
    try {
      const resp = await fetch(
        window.location.pathname +
          "?availability_date=" +
          encodeURIComponent(d),
      );
      if (!resp.ok) return;
      const map = await resp.json();
      for (const opt of orarioSelect.options) {
        const val = opt.value;
        if (!val) continue;
        const arr = map[val];
        if (Array.isArray(arr) && arr.length >= 2) {
          opt.disabled = true;
          opt.text = (opt.dataset.baseLabel || opt.text) + " (Occupato)";
        } else if (Array.isArray(arr) && arr.length === 1) {
          opt.text =
            (opt.dataset.baseLabel || opt.text) + " (Parziale: " + arr[0] + ")";
        }
      }
    } catch (e) {
      console.error("Errore availability fetch", e);
    }
  });

  // Se la pagina è stata aperta con prefill, aggiorna subito la disponibilità
  if (prefillDateInput && prefillDateInput.value) {
    try {
      dateInput.dispatchEvent(new Event("change"));
    } catch (e) {
      // ignore
    }
  }
})();

(function () {
  const form = document.querySelector("form.booking-form");
  const dateInput = document.getElementById("data");
  const orarioSelect = document.getElementById("orario");

  if (form && dateInput && orarioSelect) {
    let bookingSubmitting = false;

    form.addEventListener("submit", async function (e) {
      // Evita invii multipli
      if (bookingSubmitting) return;

      e.preventDefault();

      // Validazione HTML5
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const d = dateInput.value;
      const o = orarioSelect.value;
      if (!d || !o) {
        form.reportValidity();
        return;
      }

      try {
        // Controllo duplicati utente (stesso slot e stesso giorno)
        const resp = await fetch(
          window.location.pathname +
            "?check_user=1&date=" +
            encodeURIComponent(d) +
            "&orario=" +
            encodeURIComponent(o),
          { credentials: "same-origin" },
        );

        if (resp.ok) {
          const json = await resp.json();

          if (json.sameSlot) {
            alert("Hai già una prenotazione nella stessa fascia oraria.");
            return;
          }
          if (json.sameDay) {
            alert(
              "Hai già una prenotazione per questo giorno: è consentita una sola prenotazione al giorno.",
            );
            return;
          }
        }

        // Controllo disponibilità lato server (anti race)
        const availResp = await fetch(
          window.location.pathname +
            "?availability_date=" +
            encodeURIComponent(d),
          { credentials: "same-origin" },
        );

        if (availResp.ok) {
          const map = await availResp.json();
          const arr = map[o] || [];

          if (Array.isArray(arr) && arr.length >= 2) {
            alert(
              "La fascia è ora completamente occupata. Aggiorna e scegli un altro orario.",
            );
            try {
              dateInput.dispatchEvent(new Event("change"));
            } catch (e) {
              // ignore
            }
            return;
          }

          const selectedBarber =
            document.getElementById("barbiere")?.value || "";
          if (
            selectedBarber &&
            Array.isArray(arr) &&
            arr.includes(selectedBarber)
          ) {
            alert(
              "Il barbiere selezionato non è più disponibile in questa fascia. Aggiorna e scegli un altro barbiere oppure lascia che venga assegnato automaticamente.",
            );
            try {
              dateInput.dispatchEvent(new Event("change"));
            } catch (e) {
              // ignore
            }
            return;
          }
        }
      } catch (err) {
        console.error("Errore check_user/availability", err);
      }

      bookingSubmitting = true;
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      form.submit();
    });
  }

  const refreshBtn = document.getElementById("refreshBtn");
  if (refreshBtn && dateInput) {
    refreshBtn.addEventListener("click", function () {
      // Aggiorna disponibilità e ricarica le prenotazioni
      if (dateInput.value) {
        try {
          dateInput.dispatchEvent(new Event("change"));
        } catch (e) {
          // ignore
        }
        setTimeout(function () {
          window.location.reload();
        }, 300);
      } else {
        window.location.reload();
      }
    });
  }

  (function () {
    // Crea modale di annullamento
    const cancelModal = document.createElement("div");
    cancelModal.className = "modal-overlay";
    cancelModal.id = "cancelModal";
    cancelModal.innerHTML = `
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
            <h3 id="cancelModalTitle">Sei sicuro di voler annullare la prenotazione?</h3>
            <p>Ci dispiace. Se vuoi, scrivici il motivo (opzionale):</p>
            <textarea id="cancelReason" placeholder="Motivo (opzionale)"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-close" id="cancelModalClose">Annulla</button>
                <button type="button" class="btn-cancel-confirm" id="cancelModalConfirm">Conferma cancellazione</button>
            </div>
        </div>`;
    document.body.appendChild(cancelModal);

    let cancelTargetId = null;

    // Apre modale quando si clicca su annulla
    document.addEventListener("click", function (ev) {
      const btn = ev.target.closest && ev.target.closest(".btn-cancel");
      if (!btn) return;
      const id = btn.getAttribute("data-booking-id");
      if (!id) return;
      cancelTargetId = id;
      document.getElementById("cancelReason").value = "";
      cancelModal.style.display = "flex";
      setTimeout(() => document.getElementById("cancelReason").focus(), 120);
    });

    // Chiude modale cliccando sullo sfondo
    cancelModal.addEventListener("click", function (e) {
      if (e.target === cancelModal) {
        cancelModal.style.display = "none";
        cancelTargetId = null;
      }
    });

    // Chiusura con il pulsante annulla
    document.addEventListener("click", function (e) {
      if (e.target && e.target.id === "cancelModalClose") {
        cancelModal.style.display = "none";
        cancelTargetId = null;
      }
    });

    // Conferma annullamento e invio form nascosto
    document.addEventListener("click", function (e) {
      if (e.target && e.target.id === "cancelModalConfirm") {
        const reason = document.getElementById("cancelReason").value || "";
        const f = document.createElement("form");
        f.method = "POST";
        f.style.display = "none";
        const i1 = document.createElement("input");
        i1.name = "cancel_booking";
        i1.value = "1";
        const i2 = document.createElement("input");
        i2.name = "booking_id";
        i2.value = cancelTargetId || "";
        const i3 = document.createElement("input");
        i3.name = "reason";
        i3.value = reason;
        f.appendChild(i1);
        f.appendChild(i2);
        f.appendChild(i3);
        document.body.appendChild(f);
        f.submit();
      }
    });
  })();
})();

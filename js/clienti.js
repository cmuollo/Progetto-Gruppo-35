(function () {
    const pollInterval = 8000; // ms
    let latest = null;

    async function checkLatest() {
        try {
            const r = await fetch(window.location.pathname + '?last_cancel_check=1', { cache: 'no-store' });
            if (!r.ok) return;
            const j = await r.json();
            if (latest === null) {
                latest = j.latest_cancel_id;
                return;
            }
            if (j.latest_cancel_id && latest && j.latest_cancel_id !== latest) {
                window.location.reload();
            }
        } catch (e) {
            // ignora errori
        }
    }

    setInterval(checkLatest, pollInterval);
})();

(function () {
    const refreshBtn = document.getElementById('btn-refresh-cancellations');
    const prendiBtn = document.getElementById('btn-prendi-nota');
    const tbody = document.getElementById('cancellations-tbody');
    const msg = document.getElementById('cancellations-msg');

    async function fetchCancellations() {
        try {
            const r = await fetch(window.location.pathname + '?get_cancellations=1', { cache: 'no-store' });
            if (!r.ok) return;
            const j = await r.json();
            const list = j.cancellations || [];
            let html = '';
            for (const c of list) {
                let d = c.data_appuntamento || '';
                if (d && d.indexOf('-') >= 0) {
                    const parts = d.split('-');
                    d = parts[2] + '/' + parts[1] + '/' + parts[0];
                }
                const cancelledAt = c.cancelled_at ? new Date(c.cancelled_at).toLocaleString('it-IT') : c.cancelled_at;
                html += '<tr>';
                html += '<td>' + (c.canc_telefono || '') + '</td>';
                html += '<td>' + d + '</td>';
                html += '<td>' + (c.ora_appuntamento || '') + '</td>';
                html += '<td class="td-muted">' + (c.barber || '') + '</td>';
                html += '<td>' + (c.canc_nome || '') + '</td>';
                html += '<td>' + (c.reason || '') + '</td>';
                html += '<td>' + (cancelledAt || '') + '</td>';
                html += '</tr>';
            }
            if (tbody) {
                tbody.innerHTML = html || '<tr><td colspan="7" class="table-empty-compact">Nessuna cancellazione registrata.</td></tr>';
            }
        } catch (e) {
            console.error(e);
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', async function () {
            try {
                if (!confirm('Confermi l\'eliminazione PERMANENTE delle cancellazioni più vecchie di 24 ore?')) return;
                const r = await fetch(window.location.pathname + '?refresh_persist=1');
                if (r.ok) {
                    const j = await r.json();
                    await fetchCancellations();
                    if (msg) msg.textContent = (j.deleted ? ('Eliminate: ' + j.deleted) : 'Eliminate');
                    setTimeout(() => { if (msg) msg.textContent = ''; }, 3000);
                }
            } catch (e) { console.error(e); }
        });
    }

    if (prendiBtn) {
        prendiBtn.addEventListener('click', async function () {
            try {
                if (!confirm('Confermi l\'eliminazione PERMANENTE di TUTTE le cancellazioni? Questa azione non è reversibile.')) return;
                const r = await fetch(window.location.pathname + '?prendi_nota=1');
                if (r.ok) {
                    const j = await r.json();
                    await fetchCancellations();
                    if (msg) msg.textContent = (j.deleted ? ('Eliminate: ' + j.deleted) : 'Eliminate');
                    setTimeout(() => { if (msg) msg.textContent = ''; }, 3000);
                }
            } catch (e) { console.error(e); }
        });
    }
})();

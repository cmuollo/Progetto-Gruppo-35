(() => {
    const f3 = document.getElementById("f3");
    if (f3) {
        const p1 = document.getElementById("new_password");
        const p2 = document.getElementById("new_password2");
        function check() {
            if (p2.value && p1.value !== p2.value) p2.setCustomValidity("Le password non coincidono.");
            else p2.setCustomValidity("");
        }
        p1.addEventListener("input", check);
        p2.addEventListener("input", check);
        f3.addEventListener("submit", (e) => {
            check();
            if (!f3.checkValidity()) {
                e.preventDefault();
                f3.reportValidity();
            }
        });
    }
})();

// opzione annulla (guardia: controlla l'esistenza dei bottoni)
const annullaBtn = document.querySelector('button[name="annulla"]');
if (annullaBtn) {
  annullaBtn.addEventListener("click", function (e) {
    e.preventDefault();
    const pwd = document.querySelector(".password-section");
    const edit = document.querySelector(".edit-section");
    if (pwd) pwd.style.display = "block";
    if (edit) edit.style.display = "none";
  });
}

// gestione visibilità password
(function () {
  const toggleVerify = document.getElementById("toggleVerifyPassword");
  const verifyInput = document.getElementById("password");
  if (toggleVerify && verifyInput) {
    toggleVerify.addEventListener("click", function () {
      const t =
        verifyInput.getAttribute("type") === "password" ? "text" : "password";
      verifyInput.setAttribute("type", t);
      this.classList.toggle("fa-eye-slash");
      this.style.color = t === "text" ? "#d4af37" : "#888";
    });
    toggleVerify.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        this.click();
      }
    });
  }

  const toggleCurrent = document.getElementById("toggleCurrentPassword");
  const currentInput = document.getElementById("current_password");
  if (toggleCurrent && currentInput) {
    toggleCurrent.addEventListener("click", function () {
      const t =
        currentInput.getAttribute("type") === "password" ? "text" : "password";
      currentInput.setAttribute("type", t);
      this.classList.toggle("fa-eye-slash");
      this.style.color = t === "text" ? "#d4af37" : "#888";
    });
    toggleCurrent.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        this.click();
      }
    });
  }

  // validazione telefono in tempo reale
  const telInput = document.getElementById("telefono");
  const telError = document.getElementById("telefono-error");
  if (telInput) {
    telInput.addEventListener("input", function () {
      const digits = (this.value || "").replace(/\D/g, "");
      if (digits.length !== 10) {
        this.style.borderColor = "#b00020";
        if (telError) telError.style.display = "block";
      } else {
        this.style.borderColor = "";
        if (telError) telError.style.display = "none";
      }
    });
  }
})();

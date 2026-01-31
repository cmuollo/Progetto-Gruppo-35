function initRegistrazione() {
  // animazione di entrata della card
  // Dato che qui non c'è il footer, attiviamo manualmente l'animazione
  setTimeout(function () {
    var card = document.querySelector(".auth-card");
    if (card) card.classList.add("active");
  }, 100); // Ritardo minimo per garantire l'effetto entrata

  // logica per mostrare/nascondere la password
  const togglePassword = document.querySelector("#togglePassword");
  const password = document.querySelector("#password");

  if (togglePassword && password) {
    togglePassword.addEventListener("click", function () {
      const type =
        password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      this.classList.toggle("fa-eye-slash");
      this.style.color = type === "text" ? "#d4af37" : "#888";
    });
    // keyboard accessibility
    togglePassword.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        this.click();
      }
    });
  }

  // determina la robustezza della password
  const bar = document.getElementById("strength-bar");
  const text = document.getElementById("strength-text");

  // toggle for repeated password
  const togglePassword2 = document.querySelector("#togglePassword2");
  const password2 = document.querySelector("#password2");
  if (togglePassword2 && password2) {
    togglePassword2.addEventListener("click", function () {
      const type =
        password2.getAttribute("type") === "password" ? "text" : "password";
      password2.setAttribute("type", type);
      this.classList.toggle("fa-eye-slash");
      this.style.color = type === "text" ? "#d4af37" : "#888";
    });
    togglePassword2.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        this.click();
      }
    });
  }

  if (password && bar && text) {
    password.addEventListener("input", function () {
      const val = password.value;
      let strength = 0;
      if (val.length >= 8) strength++;
      if (val.match(/[0-9]/)) strength++;
      if (val.match(/[A-Z]/)) strength++;
      if (val.match(/[@$%&!#?]/)) strength++;

      let width = "0%";
      let color = "red";
      let label = "";

      switch (strength) {
        case 0:
          width = "0%";
          label = "";
          break;
        case 1:
          width = "25%";
          color = "#e74c3c";
          label = "Debole";
          break;
        case 2:
          width = "50%";
          color = "#f1c40f";
          label = "Media";
          break;
        case 3:
          width = "75%";
          color = "#3498db";
          label = "Buona";
          break;
        case 4:
          width = "100%";
          color = "#2ecc71";
          label = "Ottima";
          break;
      }

      // animate bar
      bar.style.width = width;
      bar.style.backgroundColor = color;

      // fade-in text for better UX
      try {
        text.style.transition = "opacity .28s ease, color .28s ease";
        text.style.opacity = "0";
        setTimeout(function () {
          text.innerText = label;
          text.style.color = color;
          text.style.opacity = "1";
        }, 30);
      } catch (e) {
        text.innerText = label;
        text.style.color = color;
      }
    });
  }

  // Validate telefono exactly 10 digits on submit
  const registerForm = document.getElementById("registerForm");
  const telInput = document.getElementById("telefono");
  const telError = document.getElementById("telefono-error");
  const emailInput = document.getElementById("email");
  const dupError = document.getElementById("duplicate-error");
  const dataInput = document.getElementById("existing-users-data");
  let existingUsers = [];

  if (dataInput && dataInput.value) {
    try {
      existingUsers = JSON.parse(dataInput.value) || [];
    } catch (e) {
      existingUsers = [];
    }
  }

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
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      if (dupError) dupError.style.display = "none";
      if (emailInput) emailInput.style.borderColor = "";
      if (telInput) telInput.style.borderColor = "";

      if (telInput) {
        const digits = (telInput.value || "").replace(/\D/g, "");
        if (digits.length !== 10) {
          e.preventDefault();
          if (telError) telError.style.display = "block";
          telInput.style.borderColor = "#b00020";
          telInput.focus();
          return false;
        }
      }

      if (emailInput && telInput) {
        const emailVal = (emailInput.value || "").trim().toLowerCase();
        const telDigits = (telInput.value || "").replace(/\D/g, "");
        const dup = (existingUsers || []).some(function (u) {
          return (
            (u.email && u.email === emailVal) ||
            (u.telefono && u.telefono === telDigits)
          );
        });
        if (dup) {
          e.preventDefault();
          if (dupError) dupError.style.display = "block";
          if (emailInput) emailInput.style.borderColor = "#b00020";
          if (telInput) telInput.style.borderColor = "#b00020";
          if (emailInput) emailInput.focus();
          return false;
        }
      }
    });
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initRegistrazione);
} else {
  initRegistrazione();
}

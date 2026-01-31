document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('toggleLoginPassword');
  const input = document.getElementById('password');

  if (toggle && input) {
    toggle.addEventListener('click', function () {
      const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
      input.setAttribute('type', type);
      this.classList.toggle('fa-eye-slash');
      this.style.color = type === 'text' ? '#d4af37' : '#888';
    });

    toggle.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  }
});

// Lógica del botón para cambiar entre temas oscuro y claro:
const themeSwitch = document.getElementById('theme-switch');

// Mantener preferencia en localStorage:
if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light-theme');
    themeSwitch.checked = true;
}

themeSwitch.addEventListener('change', () => {
    document.body.classList.toggle('light-theme', themeSwitch.checked);
    localStorage.setItem('theme', themeSwitch.checked ? 'light' : 'dark');
});

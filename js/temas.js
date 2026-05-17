const themeSwitch = document.getElementById('theme-switch');

function applyTheme(isLight){
  document.body.classList.toggle('light-theme', isLight);
  try{ localStorage.setItem('theme', isLight ? 'light' : 'dark'); }catch(e){}
}

// Inicializar: preferencia guardada > prefers-color-scheme > default dark
const saved = (function(){ try{ return localStorage.getItem('theme'); }catch(e){ return null; } })();
const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
const useLight = saved ? saved === 'light' : prefersLight;
applyTheme(useLight);
if(themeSwitch) themeSwitch.checked = useLight;

if(themeSwitch){
  themeSwitch.addEventListener('change', () => applyTheme(themeSwitch.checked));
}

// Exponer método para otros scripts que necesiten leer variables
window.getThemeColor = function(varName){
  return getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
}
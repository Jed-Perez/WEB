'use strict';
(() => {
 const storageKey='resort360-theme';
 const systemTheme=()=>window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';
 let theme='light';
 try {
  const saved=window.localStorage.getItem(storageKey);
  theme=saved==='dark'||saved==='light'?saved:systemTheme();
 } catch(error) {
  console.error('Unable to read the saved theme preference.',error);
  theme=systemTheme();
 }
 const applyTheme=value=>{
  theme=value;
  document.documentElement.dataset.theme=value;
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
   const next=value==='dark'?'light':'dark';
   const label=`Switch to ${next} mode`;
   button.setAttribute('aria-label',label);
   button.setAttribute('aria-pressed',String(value==='dark'));
   button.title=label;
   const icon=button.querySelector('[data-theme-icon]');
   if(icon)icon.className=`bi ${value==='dark'?'bi-sun':'bi-moon-stars'}`;
  });
  window.dispatchEvent(new CustomEvent('resort360:themechange',{detail:{theme:value}}));
 };
 applyTheme(theme);
 document.addEventListener('DOMContentLoaded',()=>{
  applyTheme(theme);
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>button.addEventListener('click',()=>{
   const next=theme==='dark'?'light':'dark';
   applyTheme(next);
   try {
    window.localStorage.setItem(storageKey,next);
   } catch(error) {
    console.error('Unable to save the theme preference.',error);
   }
  }));
 });
 window.addEventListener('storage',event=>{
  if(event.key!==storageKey)return;
  applyTheme(event.newValue==='dark'||event.newValue==='light'?event.newValue:systemTheme());
 });
})();

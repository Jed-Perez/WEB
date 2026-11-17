'use strict';
// Reservations are handled in the signed-in workspace.
try {sessionStorage.removeItem('r360ReservationIntent');} catch {}
document.querySelectorAll('#publicNav a[href^="#"]').forEach(link=>link.addEventListener('click',()=>bootstrap.Collapse.getInstance(document.querySelector('#publicNav'))?.hide()));
document.querySelector('#landingLogout')?.addEventListener('click',async event=>{
 const button=event.currentTarget;button.disabled=true;
 try {await R360.api('logout','POST',{});location.href='home.php';}
 catch(error){const box=document.querySelector('#landingError');box.textContent=error.message;box.classList.remove('d-none');box.scrollIntoView({behavior:'smooth'});}
 finally {button.disabled=false;}
});

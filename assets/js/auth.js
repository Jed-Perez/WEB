'use strict';
const resetFromLink=new URLSearchParams(location.hash.slice(1)).get('reset');
const resetLinkValid=typeof resetFromLink==='string'&&/^[a-f0-9]{64}$/.test(resetFromLink);
let authMode=resetLinkValid||new URLSearchParams(location.search).get('mode')==='reset'?'reset':new URLSearchParams(location.search).get('mode')==='register'?'register':'login';
const authForm=document.querySelector('#authForm');
try {sessionStorage.removeItem('r360ReservationIntent');} catch {}

function authField(name,label,type='text',extra=''){return `<div class="mb-3"><label class="form-label" for="${name}">${label}</label><input class="form-control form-control-lg" id="${name}" name="${name}" type="${type}" required ${extra}></div>`;}
function authPasswordField(label,extra,showToggle){
 return `<div class="mb-3"><label class="form-label" for="password">${label}</label><div class="input-group auth-password-group"><input class="form-control form-control-lg" id="password" name="password" type="password" required ${extra}>${showToggle?'<button class="btn btn-outline-secondary auth-password-toggle" type="button" id="togglePassword" aria-label="Show password" aria-controls="password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>':''}</div></div>`;
}
function renderAuth(){
 document.querySelectorAll('[data-auth]').forEach(b=>b.classList.toggle('active',b.dataset.auth===authMode));
 document.querySelector('#authError').classList.add('d-none');
 const pw=authPasswordField(authMode==='login'?'Password':'New password',`${authMode==='login'?'':'minlength="12"'} maxlength="72" autocomplete="${authMode==='login'?'current-password':'new-password'}"`,authMode==='login');
 document.querySelector('#authFields').innerHTML=(authMode==='register'?authField('name','Full name','text','maxlength="100" autocomplete="name"'):'')+(authMode==='reset'?authField('token','One-use reset code','text','minlength="64" maxlength="64" autocomplete="off"'):authField('email','Email address','email','maxlength="190" autocomplete="email"'))+(authMode==='register'?authField('phone','Contact number','tel','maxlength="40" autocomplete="tel"'):'')+pw;
 document.querySelector('#togglePassword')?.addEventListener('click',event=>{
  const input=document.querySelector('#password'),button=event.currentTarget,visible=input.type==='text';
  input.type=visible?'password':'text';
  button.setAttribute('aria-pressed',String(!visible));
  button.setAttribute('aria-label',visible?'Show password':'Hide password');
  button.innerHTML=`<i class="bi ${visible?'bi-eye':'bi-eye-slash'}" aria-hidden="true"></i>`;
 });
 document.querySelector('#authSubmit').textContent={login:'Sign in →',register:'Create guest account →',reset:'Reset password →'}[authMode];
 document.querySelector('#authNote').textContent=authMode==='reset'?'Contact reception to verify your identity and receive a reset code. Codes expire after 30 minutes.':authMode==='register'?'Use at least 12 characters for your password. Guest accounts can access only their own reservations.':'Secure access for guests and authorized resort staff.';
}
document.querySelectorAll('[data-auth]').forEach(b=>b.addEventListener('click',()=>{authMode=b.dataset.auth;renderAuth();}));
authForm.addEventListener('submit',async event=>{event.preventDefault();const button=document.querySelector('#authSubmit');button.disabled=true;const mode=authMode;try{const fields=Object.fromEntries(new FormData(authForm));await R360.api(mode,'POST',fields);if(mode==='login'){location.href='index.php';return;}R360.toast(mode==='register'?'Account created. Sign in to book your stay.':'Password updated. Sign in with your new password.');authMode='login';renderAuth();if(mode==='register')document.querySelector('#email').value=fields.email;}catch(error){const box=document.querySelector('#authError');box.textContent=error.message;box.classList.remove('d-none');}finally{button.disabled=false;}});
renderAuth();
if(resetLinkValid){document.querySelector('#token').value=resetFromLink;history.replaceState(null,'',location.pathname+location.search);}

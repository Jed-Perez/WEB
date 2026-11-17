'use strict';
const R360 = {
 csrf: document.querySelector('meta[name="csrf-token"]').content,
 esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); },
 async api(route, method='GET', data=null, params={}) {
  const url='api/index.php?'+new URLSearchParams({route,...params});
  let response;
  try {response=await fetch(url,{method,credentials:'same-origin',headers:{'Accept':'application/json',...(method!=='GET'?{'Content-Type':'application/json','X-CSRF-Token':this.csrf}:{})},...(data!==null?{body:JSON.stringify(data)}:{})});}
  catch {throw new Error('Connection interrupted. Check your network and try again.');}
  let result;try{result=await response.json();}catch{throw new Error('The server returned an invalid response. Contact your administrator.');}
  if(!response.ok||!result.success){if(response.status===401&&!['login','register','reset'].includes(route)){location.href='login.php';}const error=new Error(result.message||'Request failed.');error.fields=result.errors||{};throw error;}
  return result.data;
 },
 toast(message){const el=document.querySelector('#appToast');el.querySelector('.toast-body').textContent=message;bootstrap.Toast.getOrCreateInstance(el).show();},
 async confirm(title,text='This change will be recorded in the activity log.'){return (await Swal.fire({title,text,icon:'question',showCancelButton:true,confirmButtonText:'Confirm',confirmButtonColor:'#246650'})).isConfirmed;},
 error(error){Swal.fire({title:'Unable to complete',text:error.message,icon:'error',confirmButtonColor:'#246650'});},
 money(value){return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(Number(value||0));},
 label(value){return String(value??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());},
 badge(value){return `<span class="status-badge status-${this.esc(value)}">${this.esc(this.label(value))}</span>`;}
};

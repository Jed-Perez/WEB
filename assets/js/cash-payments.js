'use strict';

async function newCashSlip(prefill={}) {
 const data=await R360.api('lookups');
 const bookings=data.reservations||[];
 if(!bookings.length)throw new Error('There are no reservations with an outstanding balance.');
 const fields=formField('reservation_id','Reservation','select',prefill.reservation_id||'', [{value:'',label:'Choose a reservation'},...bookings.map(r=>({value:r.id,label:r.name}))])+
  formField('amount','Cash to collect (PHP)','number','',[],true,'min="0.01" step="0.01"')+
  formField('notes','Message for the guest','textarea','',[],false,'maxlength="500"');
 const el=modal('Generate cash payment slip',`<p>The guest will see this slip in <strong>Cash payments</strong> and pay at reception. Generating a slip does not mark the booking as paid.</p><form id="recordForm"><div id="formError" class="alert alert-danger d-none" role="alert"></div><div class="row g-3">${fields}</div><div id="cashQuote" class="report-summary mt-3" role="status"></div></form>`, '<button class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" form="recordForm" class="btn btn-primary">Generate slip</button>');
 const form=el.querySelector('#recordForm'),submit=el.querySelector('[type=submit]');
 const quote=()=>{
  const r=bookings.find(r=>String(r.id)===form.elements.reservation_id.value);
  const balance=r?(Math.round(Number(r.total)*100)-Math.round(Number(r.paid)*100))/100:0;
  form.elements.amount.value=r?balance.toFixed(2):'';form.elements.amount.max=balance;submit.disabled=!r;
  el.querySelector('#cashQuote').textContent=r?`Booking total: ${money(r.total)} · Received: ${money(r.paid)} · Balance: ${money(balance)}`:'Choose a reservation to calculate the cash amount.';
 };
 form.elements.reservation_id.addEventListener('change',quote);quote();
 form.onsubmit=async event=>{
  event.preventDefault();submit.disabled=true;
  try {
   const result=await R360.api('records','POST',Object.fromEntries(new FormData(form)),{module:'cash_requests'});
   R360.toast('Cash payment slip generated.');
   await showCashSlip(result.id);
   await refreshCashList();
  } catch(error){const box=form.querySelector('#formError');box.textContent=error.message;box.classList.remove('d-none');}
  finally{submit.disabled=false;}
 };
}

async function refreshCashList() {
 if(moduleName==='dashboard')await dashboard(requestVersion);
 else if($('#filterForm'))await loadTable();
}

async function showCashSlip(id) {
 const r=await R360.api('records','GET',null,{module:'cash_requests',id});
 const pending=r.status==='pending',paid=r.status==='paid';
 const message=pending?'Bring this reference to reception and pay the amount below in cash. Your balance updates after staff records the cash received.':paid?'Cash received and recorded. You can print your receipt below.':r.status==='voided'?'This receipt has been voided. It is no longer proof of a valid payment.':'This slip was cancelled. Do not use it to make a payment.';
 const manage=can('payments.manage')&&pending;
 const content=`<div class="cash-slip-summary"><div><span class="eyebrow">${paid?'CASH PAYMENT RECEIPT':'CASH PAYMENT SLIP'}</span><h3>${E(r.reference)}</h3>${R360.badge(r.status)}</div><div class="cash-slip-amount"><span>${paid?'Cash received':pending?'Cash to pay':'Slip amount'}</span><strong>${E(money(r.amount))}</strong></div></div><p class="mt-3">${E(message)}</p><dl class="detail-grid"><div><dt>Booking</dt><dd>${E(r.booking)}</dd></div><div><dt>Guest</dt><dd>${E(r.guest)}</dd></div><div><dt>Room / cottage</dt><dd>${E(r.facility_name)}</dd></div><div><dt>Stay on this slip</dt><dd>${E(r.arrival)} to ${E(r.departure)}</dd></div><div><dt>Current booking balance</dt><dd>${E(money(r.balance))}</dd></div><div><dt>Generated</dt><dd>${E(r.created_at)}</dd></div>${r.received_at?`<div><dt>Received by</dt><dd>${E(r.received_by)}</dd></div><div><dt>Received at</dt><dd>${E(r.received_at)}</dd></div>`:''}</dl>${r.notes?`<div class="cash-slip-note"><strong>Message</strong><p>${E(r.notes)}</p></div>`:''}${r.cancelled_reason||r.void_reason?`<div class="alert alert-warning">${E(r.cancelled_reason||r.void_reason)}</div>`:''}<div id="cashError" class="alert alert-danger d-none" role="alert"></div>${manage?`<section class="cash-receive-box"><h3>Receive cash at reception</h3><label class="d-flex gap-2 align-items-start"><input class="form-check-input" type="checkbox" id="cashReceivedCheck"><span>I have received <strong>${E(money(r.amount))}</strong> in cash for this slip.</span></label><button class="btn btn-primary mt-3" id="receiveCash" disabled>Record cash received</button><details class="mt-3"><summary>Cancel this slip</summary><form id="cancelCashForm" class="mt-2"><label for="cashCancelReason" class="form-label">Reason</label><textarea id="cashCancelReason" class="form-control" maxlength="500" required></textarea><button type="submit" class="btn btn-outline-secondary mt-2">Cancel slip</button></form></details></section>`:''}`;
 const el=modal(paid?'Cash payment receipt':'Cash payment details',content,`<a class="btn btn-outline-secondary" href="cash-slip.php?id=${Number(r.id)}" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Print ${paid?'receipt':'slip'}</a><button class="btn btn-light" data-bs-dismiss="modal">Close</button>`);
 if(!manage)return;
 const check=el.querySelector('#cashReceivedCheck'),receive=el.querySelector('#receiveCash'),cancelForm=el.querySelector('#cancelCashForm');
 let busy=false;
 const act=async data=>{
  if(busy)return;busy=true;receive.disabled=true;check.disabled=true;cancelForm.querySelector('button').disabled=true;
  try{
   await R360.api('records','PATCH',data,{module:'cash_requests',id:r.id});
   R360.toast(data.action==='receive'?'Cash received. Balance and receipt updated.':'Payment slip cancelled.');
   await showCashSlip(r.id);await refreshCashList();
  }catch(error){el.querySelector('#cashError').textContent=error.message;el.querySelector('#cashError').classList.remove('d-none');}
  finally{busy=false;receive.disabled=!check.checked;check.disabled=false;cancelForm.querySelector('button').disabled=false;}
 };
 check.onchange=()=>receive.disabled=!check.checked;
 receive.onclick=()=>{if(check.checked)act({action:'receive'});};
 cancelForm.onsubmit=event=>{event.preventDefault();act({action:'cancel',reason:el.querySelector('#cashCancelReason').value});};
}

'use strict';
let reportRequest=0,appliedReport=null;
function reportControls(enabled){document.querySelectorAll('[data-export],#printReport').forEach(b=>b.disabled=!enabled);}
function reportShell(){
 appliedReport=null;++reportRequest;
 $('#workspace').innerHTML=titleBlock('Reports','Turn your resort records into a clear operational picture.','<button class="btn btn-light" id="printReport" disabled><i class="bi bi-printer me-2"></i>Print all results</button>')+`<section class="panel"><form id="reportForm" class="toolbar"><div><label for="reportType">Report</label><select class="form-select" name="module" id="reportType">${['reservations','payments','maintenance','readings','facilities'].map(m=>`<option value="${m}">${config[m].title}</option>`).join('')}</select></div><div><label for="reportFrom">From</label><input class="form-control" type="date" name="from" id="reportFrom"></div><div><label for="reportTo">To</label><input class="form-control" type="date" name="to" id="reportTo"></div><div class="search-wrap"><label for="reportSearch">Search</label><input class="form-control" name="search" id="reportSearch" placeholder="Filter report records"></div><button class="btn btn-primary">Generate</button><div class="d-flex gap-2">${['csv','xls','pdf'].map(f=>`<button type="button" data-export="${f}" class="btn btn-outline-secondary" disabled>${f.toUpperCase()}</button>`).join('')}</div></form><div class="p-3"><p id="reportStatus" role="status"></p><div id="reportSummary"></div><div id="reportTable"></div></div></section>`;
 const form=$('#reportForm');
 form.addEventListener('input',()=>{++reportRequest;appliedReport=null;reportControls(false);$('#reportStatus').textContent='Filters changed. Generate the report to update results and enable export.';});
 form.onsubmit=ev=>{ev.preventDefault();loadReport();};
 $('#printReport').onclick=()=>loadReport(true);
 document.querySelectorAll('[data-export]').forEach(b=>b.onclick=async()=>{
  if(!appliedReport)return;const q={...appliedReport};b.disabled=true;
  try{const response=await fetch('api/export.php?'+new URLSearchParams({...q,format:b.dataset.export}),{credentials:'same-origin'});if(!response.ok){const d=await response.json();throw new Error(d.message);}const blob=await response.blob(),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=`Resort360-${q.module}.${b.dataset.export}`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){R360.error(e);}finally{if(appliedReport)b.disabled=false;}
 });
}
async function loadReport(full=false){
 if(full&&!appliedReport)return;
 const q=full?{...appliedReport}:Object.fromEntries(new FormData($('#reportForm'))),seq=++reportRequest,version=requestVersion;
 appliedReport=null;reportControls(false);$('#reportStatus').textContent=full?'Preparing all matching records for print…':'Loading report…';
 try{
  if(q.from&&q.to&&q.from>q.to)throw new Error('The end date must not precede the start date.');
  const d=await R360.api('report','GET',null,{...q,full:full?'1':'0'});
  if(seq!==reportRequest||version!==requestVersion)return;
  appliedReport={...q};
  $('#reportSummary').innerHTML=`<div class="report-summary"><strong>${d.total} matching records</strong> · ${E(config[q.module].title)} · ${E(q.from||'All dates')} to ${E(q.to||'present')}<br>${d.summary?`<strong>All matching records — ${q.module==='payments'?'valid recorded amount':'booking total'}: ${money(d.summary.amount??d.summary.total)}</strong><br>`:''}<span class="small">Showing ${d.rows.length} of ${d.total} records. Print and export include all matching records, up to 10,000. Reservation totals include every matching status; voided payments are excluded from the payment total. Dates filter reservation arrival, or record creation / receipt for other reports.</span></div>`;
  $('#reportTable').innerHTML=tableHtml(d.rows,config[q.module].columns);$('#reportStatus').textContent='Report ready. Export uses these applied filters.';reportControls(true);
  if(full)window.print();
 }catch(e){if(seq===reportRequest&&version===requestVersion){$('#reportStatus').textContent=e.message;$('#reportSummary').innerHTML='';$('#reportTable').innerHTML='';}}
}

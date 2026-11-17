// Reproduce reordered report responses without a server or database.
'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const nodes=Object.fromEntries(['#reportSummary','#reportTable','#reportStatus'].map(id=>[id,{innerHTML:'',textContent:''}]));
let filters={module:'payments',from:'2026-10-01',to:'2026-10-31'};
const pending=[],controls=[{disabled:false}];
const context={
 $:selector=>selector==='#reportForm'?{...filters}:nodes[selector],
 document:{querySelectorAll:()=>controls},
 FormData:class {constructor(form){this.form=form;}*[Symbol.iterator](){yield* Object.entries(this.form);}},
 R360:{api:(route,method,data,q)=>new Promise(resolve=>pending.push({q,resolve}))},
 E:String,config:{payments:{title:'Payments',columns:[]}},money:String,
 tableHtml:rows=>JSON.stringify(rows),requestVersion:1,window:{print:()=>{}},
};
vm.createContext(context);vm.runInContext(fs.readFileSync('assets/js/reports.js','utf8'),context);
const result=amount=>({total:105,rows:[{amount}],summary:{amount}});
(async()=>{
 const oldRequest=context.loadReport();
 filters={module:'payments',from:'2026-11-01',to:'2026-11-30'};
 const currentRequest=context.loadReport();
 pending[1].resolve(result(200));await currentRequest;
 pending[0].resolve(result(100));await oldRequest;
 assert.match(nodes['#reportSummary'].innerHTML,/2026-11-01/);
 assert.equal(nodes['#reportTable'].innerHTML,'[{"amount":200}]');
 assert.equal(vm.runInContext('appliedReport.from',context),'2026-11-01');
 console.log('PASS older report response cannot overwrite latest result or export filters');
 const before=nodes['#reportTable'].innerHTML;
 const leaving=context.loadReport();context.requestVersion++;
 pending[2].resolve(result(300));await leaving;
 assert.equal(nodes['#reportTable'].innerHTML,before);
 console.log('PASS report response after navigation cannot alter the new page');
 const changed=context.loadReport();
 vm.runInContext('++reportRequest;appliedReport=null;reportControls(false)',context);
 pending[3].resolve(result(400));await changed;
 assert.equal(nodes['#reportTable'].innerHTML,before);
 assert.equal(controls[0].disabled,true);
 console.log('PASS changing filters invalidates pending responses and export controls');
})().catch(e=>{console.error(e);process.exitCode=1;});

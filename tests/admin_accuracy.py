"""Admin regression checks on resort360_test at port 3307 only. Run after run.py."""
from run import *

def check():
 tag='Accuracy-'+secrets.token_hex(4)
 ledger=tag+'-ledger'
 hashed=subprocess.run([PHP,'-r','echo password_hash(getenv("R360_TEST_PASSWORD"), PASSWORD_DEFAULT);'],env={**os.environ,'R360_TEST_PASSWORD':PASSWORD},capture_output=True,text=True,check=True).stdout
 sql("USE resort360_test; UPDATE users SET active=1,password_hash='"+hashed+"' WHERE id IN (1,4,5); DELETE FROM auth_attempts;")
 admin=Client();guest=Client();other=Client()
 for client,email in [(admin,'admin'),(guest,'guest'),(other,'guest2')]:expect('Accuracy login '+email,client.login(email+'@example.test'))
 today=sql('USE resort360_test; SET time_zone="+08:00"; SELECT CURDATE();')
 departure=(datetime.strptime(today,'%Y-%m-%d')+timedelta(days=1)).strftime('%Y-%m-%d')
 fid=expect('Create accuracy test room',admin.create('facilities',{'name':'Accuracy '+secrets.token_hex(4),'type':'room','capacity':4,'nightly_rate':'1000.00','status':'available','description':'Isolated QA'}),201)['id']
 booking={'facility_id':fid,'arrival':today,'departure':departure,'guests_count':2,'notes':'Accuracy regression'}
 rid=expect('Create pending test booking',guest.create('reservations',booking),201)['id']
 query={k:booking[k] for k in ['arrival','departure','guests_count']}
 ordinary=expect('Normal availability respects held room',admin.api('availability',**query))
 record('Held room absent from new-booking availability',not any(r['id']==fid for r in ordinary))
 editing=expect('Owned pending booking availability',guest.api('availability',reservation_id=rid,**query))
 record('Editing includes own held room',any(r['id']==fid for r in editing))
 expect('Other guest cannot exclude someone else booking',other.api('availability',reservation_id=rid,**query),404)
 expect('Admin can check pending booking',admin.api('availability',reservation_id=rid,**query))
 # Over 100 ledger rows, including one void, distinguish full totals from preview totals.
 values=','.join(f"({rid},1.00,'cash','{ledger}',1)" for _ in range(105))
 sql(f"USE resort360_test; INSERT INTO payments(reservation_id,amount,method,reference,recorded_by) VALUES {values}; UPDATE payments SET voided_at=NOW(),void_reason='QA',voided_by=1 WHERE reservation_id={rid} ORDER BY id LIMIT 1;")
 report=expect('Full filtered payment summary',admin.api('report',module='payments',search=ledger))
 record('Preview limited to 100, total includes all 105',len(report['rows'])==100 and report['total']==105)
 record('Full total excludes voided payment',float(report['summary']['amount'])==104)
 printed=expect('Full print dataset',admin.api('report',module='payments',search=ledger,full=1))
 record('Print includes all matching rows',len(printed['rows'])==105)
 expect('Invalid calendar date rejected',admin.api('report',module='payments',**{'from':'2026-02-30'}),422)
 expect('Reversed export dates rejected',(lambda r:(r[0],json.loads(r[1])))(admin.raw('api/export.php?module=payments&format=csv&from=2026-11-30&to=2026-11-01')),422)
 expect('Guest cannot access report endpoint',guest.api('report',module='payments'),403)
 sql("USE resort360_test; INSERT INTO notifications(user_id,message) VALUES "+','.join("(1,'Accuracy notification')" for _ in range(9))+';')
 dashboard=expect('Dashboard exposes unread total',admin.api('dashboard'))
 actual=int(sql('USE resort360_test; SELECT COUNT(*) FROM notifications WHERE user_id=1 AND read_at IS NULL;'))
 record('Unread count independent of six-item preview',dashboard['unread_count']==actual and actual>6 and len(dashboard['notifications'])==6)
 record('Arrival queue includes today pending booking',any(r['id']==rid for r in dashboard['operations']['arrivals']['rows']))
 record('Maintenance occupancy breakdown present','maintenance' in dashboard['facilities'])
 first=dashboard['notifications'][0]['id'];expect('Mark one notification read',admin.api('notifications-read','POST',{'id':first}))
 record('Exactly one notification marked read',admin.api('dashboard')[1]['data']['unread_count']==actual-1)
 expect('Cannot mark another user notification',guest.api('notifications-read','POST',{'id':dashboard['notifications'][1]['id']}))
 record('Notification ownership protected',admin.api('dashboard')[1]['data']['unread_count']==actual-1)
 device_ids=[]
 for temperature in [20,40]:
  dev=expect('Create same-name sensor',admin.create('devices',{'facility_id':fid,'name':tag,'active':1,'min_temp':15,'max_temp':45,'stale_minutes':5}),201)['id'];device_ids.append(dev)
  values=','.join(f"({dev},'accuracy-{i}',{temperature},'{today} 08:00:00')" for i in range(60))
  sql(f'USE resort360_test; INSERT INTO readings(device_id,sample_id,temperature,received_at) VALUES {values};')
 trend=expect('Range-based sensor trend',admin.api('trend',search=tag,**{'from':today,'to':today}))
 record('Same-name sensors retain distinct identities',len(trend['rows'])==2 and len({r['device_id'] for r in trend['rows']})==2)
 record('Trend includes every sample beyond table page',sum(r['samples'] for r in trend['rows'])==120)
 expect('Trend rejects oversized date range',admin.api('trend',**{'from':'2026-01-01','to':'2026-03-01'}),422)
 expect('Guest denied sensor trend',guest.api('trend'),403)
 # Browser interactions with real server data.
 sys.path.insert(0,str(ROOT/'tests/runtime/python'))
 from playwright.sync_api import sync_playwright
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
  page=browser.new_page(viewport={'width':1440,'height':1000});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(BASE+'login.php');page.locator('#email').fill('admin@example.test');page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click();page.wait_for_url('**/index.php');page.locator('.operations-grid').wait_for()
  record('Dashboard exact unread badge',page.locator('#notificationCount').inner_text()==str(actual-1))
  page.locator('.stat-link[href="#reservations?status=pending"]').click();page.locator('#recordCount').filter(has_text='record').wait_for()
  record('Summary card applies status filter',page.locator('#statusFilter').input_value()=='pending')
  page.locator('button[aria-label="Confirm reservation '+str(rid)+'"]').click();page.locator('.swal2-confirm').click()
  page.locator('.toast-body').filter(has_text='Reservation confirmed.').wait_for()
  record('Admin can confirm pending reservation from its row',admin.get('reservations',id=rid)[1]['data']['status']=='confirmed')
  page.goto(BASE+'index.php#dashboard');page.locator('.operations-grid').wait_for();page.locator('[data-payment="'+str(rid)+'"]').first.click();page.locator('#paymentSummary').wait_for()
  record('Payment form shows current balance', '896.00' in page.locator('#paymentSummary').inner_text())
  record('Payment amount constrained to balance',page.locator('#field-amount').get_attribute('max')=='896')
  page.locator('#recordModal .btn-close').click();page.locator('#recordModal').wait_for(state='hidden')
  page.locator('#dashboardRefresh').click();page.locator('[data-operation="checked_in"][data-id="'+str(rid)+'"]').wait_for()
  page.locator('[data-operation="checked_in"][data-id="'+str(rid)+'"]').click();page.locator('.swal2-confirm').click()
  page.locator('[data-operation="checked_in"][data-id="'+str(rid)+'"]').wait_for(state='detached')
  record('Dashboard check-in action updates reservation',admin.get('reservations',id=rid)[1]['data']['status']=='checked_in')
  page.route('**/api/index.php?route=dashboard',lambda route:route.abort())
  page.locator('#dashboardRefresh').click();page.locator('#connectionNotice').wait_for()
  record('Refresh failure warns about stale data','outdated' in page.locator('#connectionNotice').inner_text())
  page.unroute('**/api/index.php?route=dashboard');page.locator('#dashboardRefresh').click();page.locator('#connectionNotice').wait_for(state='detached')
  record('Refresh recovers after connection failure',page.locator('#refreshStatus').count()==1)
  page.goto(BASE+'index.php#readings');page.locator('#search').fill(tag);page.locator('#trendStatus').filter(has_text='120 samples').wait_for()
  datasets=page.evaluate("Chart.getChart(document.querySelector('#temperatureChart')).data.datasets.map(d=>d.label)")
  record('Chart renders two same-name devices',len(datasets)==2 and datasets[0]!=datasets[1])
  page.locator('#perPage').select_option('50');page.locator('#trendStatus').filter(has_text='120 samples').wait_for()
  record('Trend remains complete across page size changes',len(page.evaluate("Chart.getChart(document.querySelector('#temperatureChart')).data.datasets"))==2)
  page.goto(BASE+'index.php#reports');page.locator('#reportStatus').filter(has_text='Report ready').wait_for();page.locator('#reportType').select_option('payments');page.locator('#reportSearch').fill(ledger)
  record('Changed filters disable exports',page.locator('[data-export="csv"]').is_disabled())
  page.locator('#reportForm button').filter(has_text='Generate').click();page.locator('#reportStatus').filter(has_text='Report ready').wait_for()
  record('Report UI shows full total','104.00' in page.locator('#reportSummary').inner_text())
  page.evaluate('window.print=()=>{window.printed=true}')
  page.locator('#printReport').click();page.locator('#reportTable tbody tr').nth(104).wait_for()
  record('Print renders all rows',page.locator('#reportTable tbody tr').count()==105)
  page.goto(BASE+'index.php#dashboard');page.locator('.operations-grid').wait_for();page.set_viewport_size({'width':390,'height':844})
  page.wait_for_timeout(1200) # Let Chart.js finish rendering before visual evidence.
  record('Operations dashboard fits mobile',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
  page.screenshot(path=str(ROOT/'docs/admin-updated-mobile.png'),full_page=True)
  page.set_viewport_size({'width':1440,'height':1000});page.wait_for_timeout(1200);page.screenshot(path=str(ROOT/'docs/admin-updated-desktop.png'),full_page=True)
  record('Admin regression has no browser exceptions',not errors,'; '.join(errors));browser.close()

if __name__=='__main__':
 success=False
 try:check();success=True
 except Exception as exc:
  print(str(exc),flush=True)
  if not RESULTS or RESULTS[-1]['passed']:RESULTS.append({'name':'Admin accuracy regression completed','passed':False,'detail':str(exc)})
 finally:
  (ROOT/'docs/admin-accuracy-results.json').write_text(json.dumps({'timestamp':datetime.now().isoformat(),'results':RESULTS},indent=2),encoding='utf-8')
  print(f'{sum(x["passed"] for x in RESULTS)}/{len(RESULTS)} admin accuracy checks passed.',flush=True)
 sys.exit(0 if success else 1)

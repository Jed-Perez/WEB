"""Integration and Chrome tests against a disposable local MariaDB/Apache instance.
Never points to the normal resort360 database. See docs/TESTING.md.
"""
from pathlib import Path
import os, sys, subprocess, json, secrets, re, urllib.request, urllib.error, urllib.parse, http.cookiejar
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
from concurrent.futures import ThreadPoolExecutor
ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8088/'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
PHP=r'C:\xampp\php\php.exe'
PASSWORD='Test-'+secrets.token_hex(18)
RESULTS=[]
def record(name,condition,detail=''):
 RESULTS.append({'name':name,'passed':bool(condition),'detail':detail})
 print(('PASS ' if condition else 'FAIL ')+name,flush=True)
 if not condition: raise AssertionError(name+': '+detail)
def sql(query):
 r=subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--default-character-set=utf8mb4','--batch','--skip-column-names'],input=query,encoding='utf-8',capture_output=True)
 if r.returncode:raise RuntimeError(r.stderr)
 return r.stdout.strip()
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar));self.csrf=''
  _,html,_=self.raw('login.php');self.csrf=re.search(r'name="csrf-token" content="([a-f0-9]+)"',html.decode())[1]
 def raw(self,path,method='GET',data=None,headers=None):
  request=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers=headers or {})
  try:
   with self.opener.open(request,timeout=30) as r:return r.status,r.read(),dict(r.headers)
  except urllib.error.HTTPError as e:return e.code,e.read(),dict(e.headers)
 def api(self,route,method='GET',data=None,**q):
  status,body,headers=self.raw('api/index.php?'+urllib.parse.urlencode({'route':route,**q}),method,data,{'Content-Type':'application/json','X-CSRF-Token':self.csrf})
  result=json.loads(body)
  return status,result
 def login(self,email,password=PASSWORD):
  status,r=self.api('login','POST',{'email':email,'password':password})
  if status==200:self.csrf=r['data']['csrf']
  return status,r
 def create(self,module,data):return self.api('records','POST',data,module=module)
 def update(self,module,id,data):return self.api('records','PATCH',data,module=module,id=id)
 def get(self,module,**q):return self.api('records',module=module,**q)
def expect(name,response,status=200):
 s,r=response;record(name,s==status,f'HTTP {s}; {r.get("message")}');return r.get('data',{})
def run():
 # This named database is confined to the newly initialized port-3307 test server.
 schema=(ROOT/'database/database.sql').read_text(encoding='utf-8').replace('resort360','resort360_test')
 sql('DROP DATABASE IF EXISTS resort360_test;\n'+schema)
 env={**os.environ,'RESORT_DB_PORT':'3307','RESORT_DB_NAME':'resort360_test','R360_TEST_PASSWORD':PASSWORD}
 seed=subprocess.run([PHP,str(ROOT/'tests/seed.php')],env=env,capture_output=True,text=True)
 record('Schema import and hashed test account setup',seed.returncode==0,seed.stderr)
 record('SQL sample inventory preserves UTF-8',sql('USE resort360_test; SELECT name FROM facilities WHERE id=1;')=='Ocean Room 2')
 record('SQL sample rooms persist their image paths',sql("USE resort360_test; SELECT image_path FROM facilities WHERE name='Ocean Room 2';")=='assets/img/ocean-room-2-clean.png' and sql("USE resort360_test; SELECT image_path FROM facilities WHERE name='Family Room 1';")=='assets/img/family-room-1-clean.png')
 record('New room photos target the requested facilities',sql("USE resort360_test; SELECT image_path FROM facilities WHERE name='Family Room 2';")=='assets/img/family-room-2-clean.png' and sql("USE resort360_test; SELECT image_path FROM facilities WHERE name='Ocean Room 1';")=='assets/img/ocean-room-1-clean.png')
 anon=Client();expect('Anonymous API denied',anon.get('reservations'),401)
 for directory in ['config/config.php','app/Auth.php','database/database.sql','storage/php-errors.log','tests/seed.php','tools/setup.php','docs/REQUIREMENTS.md','views/layouts/head.php']:
  s,_,_=anon.raw(directory);record('Apache protects '+directory,s==403,str(s))
 admin=Client();reception=Client();staff=Client();guest=Client();guest2=Client()
 for client,name in [(admin,'admin'),(reception,'reception'),(staff,'staff'),(guest,'guest'),(guest2,'guest2')]:expect('Login '+name,client.login(name+'@example.test'))
 original_cookie=next(c.value for c in anon.jar if c.name=='resort360')
 expect('Anonymous session can log in',anon.login('admin@example.test'))
 record('Session ID regenerated on login',original_cookie!=next(c.value for c in anon.jar if c.name=='resort360'))
 s,_,_=admin.raw('api/index.php?route=records&module=guests','POST',{'name':'forbidden'},{'Content-Type':'application/json'})
 record('Mutation without CSRF rejected',s==403,str(s))
 expect('Staff cannot read guests',staff.get('guests'),403)
 expect('Reception cannot administer users',reception.get('users'),403)
 expect('Guest cannot read audit history',guest.get('audit'),403)
 expect('Guest cannot create facilities',guest.create('facilities',{}),403)
 expect('Invalid guest form rejected',admin.create('guests',{'name':'','email':'bad','phone':''}),422)
 expect('Invalid JSON rejected',(lambda x:(x[0],json.loads(x[1])))(admin.raw('api/index.php?route=profile','PATCH',None,{'X-CSRF-Token':admin.csrf})),400)
 special={'name':'<img src=x onerror=alert(1)>','email':'safe@example.test','phone':'09001234567','address':'=CMD("test")'}
 gid=expect('Create guest with safely stored special characters',admin.create('guests',special),201)['id']
 expect('Search guest by literal text',admin.get('guests',search='onerror'))
 data=expect('SQL injection input treated as data',admin.get('guests',search="' OR 1=1 --"));record('Injection returns no unrelated records',data['total']==0)
 expect('Edit guest',reception.update('guests',gid,{**special,'phone':'09007654321'}))
 unicode_guest={'name':'QA José Niño 🌴','email':'unicode@example.test','phone':'09000000000','address':'Batangas'}
 unicode_id=expect('Create Unicode guest',admin.create('guests',unicode_guest),201)['id']
 record('UTF-8 guest text round-trips through PDO and JSON',admin.get('guests',id=unicode_id)[1]['data']['name']==unicode_guest['name'])
 facility={'name':'QA Ocean Suite','type':'room','capacity':4,'nightly_rate':'1000.00','status':'available','description':'QA only','image_path':'assets/img/ocean-room-2-clean.png'}
 fid=expect('Create facility',admin.create('facilities',facility),201)['id']
 saved_facility=expect('Facility image path persists',admin.get('facilities',id=fid));record('Saved image path matches facility record',saved_facility['image_path']==facility['image_path'])
 expect('Invalid facility image path rejected',admin.update('facilities',fid,{**facility,'image_path':'../../config/config.php'}),422)
 today=datetime.now().strftime('%Y-%m-%d')
 # Application and host were confirmed to use Manila for this test environment.
 today=sql('USE resort360_test; SET time_zone="+08:00"; SELECT CURDATE();')
 departure=(datetime.strptime(today,'%Y-%m-%d')+timedelta(days=2)).strftime('%Y-%m-%d')
 booking={'guest_id':gid,'facility_id':fid,'arrival':today,'departure':departure,'guests_count':2,'notes':'QA stay'}
 available=expect('Availability lookup',guest.api('availability',arrival=today,departure=departure,guests_count=2));record('New facility is available',any(x['id']==fid for x in available));record('Availability returns facility image path',any(x['id']==fid and x['image_path']==facility['image_path'] for x in available))
 rid=expect('Guest creates own reservation',guest.create('reservations',booking),201)['id']
 expect('Other guest cannot view reservation',guest2.get('reservations',id=rid),404)
 expect('Overlapping reservation blocked',reception.create('reservations',booking),409)
 expect('Capacity violation blocked',admin.create('reservations',{**booking,'guests_count':8}),422)
 expect('Departure before arrival blocked',admin.create('reservations',{**booking,'departure':today}),422)
 expect('Guest cannot confirm booking',guest.update('reservations',rid,{'action':'status','status':'confirmed'}),403)
 expect('Pending reservation edited',guest.update('reservations',rid,{**booking,'notes':'Updated note'}))
 expect('Archive facility with active booking blocked',admin.api('records','DELETE',{},module='facilities',id=fid),422)
 expect('Maintenance availability conflicting with bookings blocked',admin.update('facilities',fid,{**facility,'status':'maintenance'}),422)
 expect('Confirm reservation',reception.update('reservations',rid,{'action':'status','status':'confirmed'}))
 expect('Edit confirmed reservation rejected',reception.update('reservations',rid,booking),422)
 expect('Check in during reserved stay',reception.update('reservations',rid,{'action':'status','status':'checked_in'}))
 expect('Check out with balance rejected',reception.update('reservations',rid,{'action':'status','status':'checked_out'}),422)
 p={'reservation_id':rid,'amount':'700.00','method':'cash','reference':'QA-01','notes':''}
 pid=expect('Record partial payment',reception.create('payments',p),201)['id']
 r=expect('Read computed balance',guest.get('reservations',id=rid));record('Balance and partial status accurate',r['balance']=='1300.00' and r['payment_status']=='partial')
 expect('Overpayment rejected',admin.create('payments',{**p,'amount':'1300.01'}),422)
 expect('Negative payment rejected',admin.create('payments',{**p,'amount':'-2.00'}),422)
 expect('Reception cannot void payment',reception.update('payments',pid,{'action':'void','reason':'QA'}),403)
 expect('Admin void requires reason',admin.update('payments',pid,{'action':'void','reason':''}),422)
 expect('Admin void keeps ledger',admin.update('payments',pid,{'action':'void','reason':'Incorrect test receipt'}))
 r=expect('Voided payment excluded from balance',guest.get('reservations',id=rid));record('Balance restored after void',r['balance']=='2000.00' and len(r['ledger'])==1 and r['ledger'][0]['voided_at'] is not None)
 expect('Full settlement',admin.create('payments',{**p,'amount':'2000.00','reference':'QA-02'}),201)
 expect('Check out after settlement',reception.update('reservations',rid,{'action':'status','status':'checked_out'}))
 history=expect('Guest booking history',admin.get('guests',id=1));record('History links guest reservation',any(x['id']==rid for x in history['history']))
 expect('Guest sees only own payments',guest2.get('payments'));record('Other guest ledger empty',guest2.get('payments')[1]['data']['total']==0)
 archived=expect('Archive completed-use facility',admin.api('records','DELETE',{},module='facilities',id=fid))
 data=expect('Archived facility view',admin.get('facilities',archived=1));record('Archived record retained',any(x['id']==fid for x in data['rows']))
 expect('Restore facility',admin.update('facilities',fid,{'action':'restore'}))
 expect('Archive guest without active bookings',admin.api('records','DELETE',{},module='guests',id=gid))
 expect('Restore guest',admin.update('guests',gid,{'action':'restore'}))
 maintenance={'facility_id':fid,'title':'QA lighting issue','description':'Inspect cottage light','priority':'high','status':'open','assigned_to':3,'resolution':''}
 mid=expect('Staff creates maintenance',staff.create('maintenance',maintenance),201)['id']
 expect('Maintenance completion requires resolution',staff.update('maintenance',mid,{**maintenance,'status':'completed'}),422)
 expect('Staff updates maintenance progress',staff.update('maintenance',mid,{**maintenance,'status':'in_progress'}))
 expect('Staff completes maintenance',staff.update('maintenance',mid,{**maintenance,'status':'completed','resolution':'Replaced fixture'}))
 device={'facility_id':fid,'name':'QA ESP32','active':1,'min_temp':15,'max_temp':35,'stale_minutes':5}
 dev=expect('Create device with one-time key',admin.create('devices',device),201);did=dev['id'];key=dev['device_key']
 sensor=lambda data,token:key and admin.raw('api/sensor.php','POST',data,{'Authorization':'Bearer '+token,'Content-Type':'application/json'})
 s,b,_=sensor({'sample_id':'qa-1','temperature':28.2},'0'*64);record('Invalid sensor key denied',s==401)
 s,b,_=sensor({'sample_id':'qa-1','temperature':28.2},key);record('Authenticated temperature ingestion',s==200,b.decode())
 s,b,_=sensor({'sample_id':'qa-1','temperature':28.2},key);record('Sensor retry deduplicated',s==200 and json.loads(b)['data']['duplicate'])
 s,b,_=sensor({'sample_id':'qa-1','temperature':29},key);record('Conflicting sample retry rejected',s==409)
 s,b,_=sensor({'sample_id':'qa-2','temperature':29},key);record('Sensor rate limit enforced',s==429)
 s,b,_=sensor({'sample_id':'qa-3','temperature':999},key);record('Sensor range validated',s==422)
 readings=expect('Staff sees recorded temperature',staff.get('readings'));record('One physical sample stored',readings['total']==1)
 data=expect('Device listing contains no credential',admin.get('devices',id=did));record('Device key never returned in listings','key_hash' not in data and 'device_key' not in data and data['status']=='online')
 sql(f'USE resort360_test; UPDATE devices SET last_seen=DATE_SUB(NOW(),INTERVAL 30 MINUTE) WHERE id={did};')
 record('Delayed sensor marked stale',admin.get('devices',id=did)[1]['data']['status']=='stale')
 expect('Rotate device credential',admin.api('device-key','POST',{'id':did}))
 s,_,_=sensor({'sample_id':'qa-4','temperature':29},key);record('Rotated old key revoked',s==401)
 for format,signature in [('csv',b'\xef\xbb\xbf'),('xls',b'<?xml'),('pdf',b'%PDF')]:
  s,b,h=admin.raw('api/export.php?module=reservations&format='+format);record('Export '+format,s==200 and b.startswith(signature),f'HTTP {s}: {b[:100]!r}')
 s,b,_=guest.raw('api/export.php?module=reservations&format=csv');record('Guest export denied',s==403)
 paged=expect('Pagination works',admin.get('facilities',per_page=1,page=2));record('Pagination contains one row and correct page',len(paged['rows'])==1 and paged['page']==2)
 expected=sql('USE resort360_test; SELECT COUNT(*) FROM reservations;')
 dash=expect('Dashboard from database',admin.api('dashboard'));record('Dashboard count matches SQL',str(dash['summary']['reservations'])==expected)
 expect('User cannot remove last admin',admin.update('users',1,{'name':'Test Admin','email':'admin@example.test','role_id':2,'active':1}),422)
 expect('Create staff account through administration',admin.create('users',{'name':'QA New Staff','email':'qa-staff@example.test','role_id':3,'active':1,'password':PASSWORD}),201)
 expiring=Client();expect('Create session for idle-expiry test',expiring.login('qa-staff@example.test'))
 expiring_id=next(c.value for c in expiring.jar if c.name=='resort360')
 subprocess.run([PHP,str(ROOT/'tests/expire_session.php')],env={**env,'R360_TEST_SESSION':expiring_id},check=True,capture_output=True)
 expect('Idle session expiry enforced',expiring.api('session'),401)
 sorted_rows=expect('Server-side alphabetical sorting',admin.get('facilities',sort='name',direction='asc'))['rows']
 record('Sorting matches database order',[r['name'] for r in sorted_rows]==sorted(r['name'] for r in sorted_rows))
 future=(datetime.strptime(departure,'%Y-%m-%d')+timedelta(days=10)).strftime('%Y-%m-%d')
 future_end=(datetime.strptime(future,'%Y-%m-%d')+timedelta(days=1)).strftime('%Y-%m-%d')
 cancel_id=expect('Create booking for cancellation',guest.create('reservations',{**booking,'arrival':future,'departure':future_end}),201)['id']
 expect('Guest cancels own request',guest.update('reservations',cancel_id,{'action':'status','status':'cancelled'}))
 reject_id=expect('Create booking for rejection',reception.create('reservations',{**booking,'arrival':future,'departure':future_end}),201)['id']
 expect('Reception rejects request',reception.update('reservations',reject_id,{'action':'status','status':'rejected'}))
 expect('Reception cannot access management export',(lambda x:(x[0],json.loads(x[1])))(reception.raw('api/export.php?module=payments&format=csv')),403)
 # Concurrent allocation requests use independent sessions and an available adjacent date range.
 race={**booking,'arrival':departure,'departure':(datetime.strptime(departure,'%Y-%m-%d')+timedelta(days=1)).strftime('%Y-%m-%d')}
 with ThreadPoolExecutor(2) as pool:
  statuses=list(pool.map(lambda c:c.create('reservations',race)[0],[guest,guest2]))
 record('Concurrent booking race has one winner',sorted(statuses)==[201,409],str(statuses))
 new_email='newguest@example.test'
 expect('Guest self-registration',Client().api('register','POST',{'name':'New Guest','email':new_email,'phone':'0900000000','password':PASSWORD}),201)
 new_id=int(sql("USE resort360_test; SELECT id FROM users WHERE email='newguest@example.test';"))
 reset=expect('Issue identity-verified reset',admin.api('reset-issue','POST',{'id':new_id}))
 expect('One-use reset accepted',Client().api('reset','POST',{'token':reset['token'],'password':PASSWORD+'a'}))
 expect('Reset token reuse rejected',Client().api('reset','POST',{'token':reset['token'],'password':PASSWORD+'b'}),422)
 newuser=Client();expect('New password works',newuser.login(new_email,PASSWORD+'a'))
 expect('Profile change with wrong current password rejected',newuser.api('profile','PATCH',{'name':'New Guest','email':new_email,'phone':'0900000000','address':'','new_password':PASSWORD,'current_password':'wrong'}),422)
 expect('Profile and password update',newuser.api('profile','PATCH',{'name':'Updated Guest','email':new_email,'phone':'0900000000','address':'Local address','new_password':PASSWORD,'current_password':PASSWORD+'a'}))
 expect('Deactivate account',admin.update('users',new_id,{'name':'Updated Guest','email':new_email,'role_id':4,'active':0}))
 expect('Deactivation invalidates existing session',newuser.api('session'),401)
 expect('Deactivated login denied',Client().login(new_email),401)
 expect('Notifications persist',staff.get('notifications'))
 expect('Mark notifications read',staff.api('notifications-read','POST',{}))
 record('No unread notifications remain',staff.get('notifications',status='unread')[1]['data']['total']==0)
 expect('Audit entries present',admin.get('audit'));record('Audit log contains important operations',int(sql('USE resort360_test; SELECT COUNT(*) FROM audit_logs;'))>30)
 expect('Logout',guest2.api('logout','POST',{}));expect('Logged-out session denied',guest2.api('session'),401)
 bad=Client()
 for i in range(5):expect('Invalid login attempt '+str(i+1),bad.login('nonexistent@example.test','wrong-password'),401)
 expect('Sixth failed login throttled',bad.login('nonexistent@example.test','wrong-password'),429)
 browser_tests()
def browser_tests():
 sys.path.insert(0,str(ROOT/'tests/runtime/python'))
 from playwright.sync_api import sync_playwright
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
  context=browser.new_context(viewport={'width':1440,'height':1000})
  page=context.new_page();errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
  page.goto(BASE+'login.php');page.locator('#email').fill('admin@example.test');page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click();page.wait_for_url('**/index.php');page.locator('h1').filter(has_text='Overview').wait_for();page.locator('#reservationChart').wait_for();page.wait_for_timeout(1100);page.screenshot(path=str(ROOT/'docs/dashboard-desktop.png'),full_page=True)
  record('Chrome login and dynamic dashboard',page.locator('.stat-card').count()==4)
  for name in ['reservations','guests','facilities','payments','maintenance','devices','readings','reports','users','audit','notifications','profile']:
   page.goto(BASE+'index.php#'+name);page.locator('h1').wait_for();page.wait_for_timeout(350);record('Chrome page '+name,not page.locator('#workspace > .alert-danger').count())
  page.goto(BASE+'index.php#guests');page.locator('[data-new="guests"]').click();page.locator('#recordModal.show').wait_for();page.locator('#field-name').fill('Browser Guest');page.locator('#field-email').fill('browser@example.test');page.locator('#field-phone').fill('09123456789');page.locator('[form="recordForm"]').click();page.locator('#recordModal').wait_for(state='hidden');page.locator('#search').fill('Browser Guest');page.get_by_role('cell',name='Browser Guest',exact=True).wait_for();record('Chrome AJAX create and search',page.get_by_role('cell',name='Browser Guest',exact=True).count()==1)
  page.get_by_role('row').filter(has=page.get_by_role('cell',name='Browser Guest',exact=True)).locator('[data-action="edit"]').click();page.locator('#field-phone').fill('09987654321');page.locator('[form="recordForm"]').click();page.locator('#recordModal').wait_for(state='hidden');page.get_by_role('cell',name='09987654321',exact=True).wait_for();record('Chrome modal edit updates table',True)
  page.get_by_role('row').filter(has=page.get_by_role('cell',name='Browser Guest',exact=True)).locator('[data-action="view"]').click();page.locator('#modalTitle').filter(has_text='Record details').wait_for();record('Chrome detail modal',page.locator('.modal-body').get_by_text('Browser Guest',exact=True).count()==1);page.locator('#recordModal .btn-close').click()
  page.locator('#search').fill('onerror');page.get_by_role('cell',name='<img src=x onerror=alert(1)>',exact=True).wait_for();record('Stored XSS rendered as text',page.locator('#tableArea img').count()==0)
  page.set_viewport_size({'width':390,'height':844});page.goto(BASE+'index.php#dashboard');page.locator('h1').filter(has_text='Overview').wait_for();page.wait_for_timeout(1100);page.screenshot(path=str(ROOT/'docs/dashboard-mobile.png'),full_page=True)
  record('Mobile page has no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
  page.get_by_role('button',name='Open navigation').click();page.locator('#sidebar.show').wait_for();page.locator('#sidebar a[href="#reservations"]').click();page.locator('h1').filter(has_text='Reservations').wait_for();record('Mobile navigation works',page.locator('[data-new="reservations"]').count()==1)
  # Exercise complete guest availability/reservation flow in a separate browser context.
  guest_context=browser.new_context(viewport={'width':1280,'height':900})
  guest_page=guest_context.new_page();guest_page.on('pageerror',lambda error:errors.append(str(error)))
  guest_page.goto(BASE+'login.php');guest_page.locator('#email').fill('guest@example.test');guest_page.locator('#password').fill(PASSWORD);guest_page.locator('#authSubmit').click();guest_page.wait_for_url('**/index.php');guest_page.locator('[data-new="reservations"]').first.click();guest_page.locator('#field-arrival').fill(sql('USE resort360_test; SET time_zone="+08:00"; SELECT CURDATE();'))
  guest_page.locator('#field-departure').fill(sql('USE resort360_test; SET time_zone="+08:00"; SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY);'))
  guest_page.locator('#field-guests_count').fill('2');guest_page.locator('#checkAvailability').click();guest_page.locator('[data-choose="1"]').click();guest_page.locator('[form="recordForm"]').click();guest_page.locator('#recordModal').wait_for(state='hidden');record('Chrome guest availability and reservation modal',True)
  guest_page.goto(BASE+'index.php#reservations');guest_page.locator('#recordCount').filter(has_text='record').wait_for();record('Guest navigation hides administration',guest_page.locator('#mainNav a[href="#users"]').count()==0)
  guest_page.screenshot(path=str(ROOT/'docs/guest-reservations.png'),full_page=True)
  guest_context.close()
  record('No browser JavaScript exceptions',not errors,'; '.join(errors))
  browser.close()
if __name__=='__main__':
 success=False
 try:run();success=True
 except Exception as exc:
  print(type(exc).__name__+': '+str(exc),flush=True)
  if not RESULTS or RESULTS[-1]['passed']:RESULTS.append({'name':'Test runner completed','passed':False,'detail':str(exc)})
 finally:
  (ROOT/'docs/test-results.json').write_text(json.dumps({'timestamp':datetime.now().isoformat(),'environment':'PHP 8.2.12 / MariaDB 10.4.32 / Apache XAMPP / isolated ports 3307 and 8088','results':RESULTS},indent=2),encoding='utf-8')
  print(f'{sum(x["passed"] for x in RESULTS)}/{len(RESULTS)} checks passed.',flush=True)
 sys.exit(0 if success else 1)

"""Additional installation and visual checks; isolated databases only."""
from run import *
def review():
 schema=(ROOT/'database/database.sql').read_text(encoding='utf-8').replace('resort360','resort360_test_setup')
 sql('DROP DATABASE IF EXISTS resort360_test_setup;\n'+schema)
 env={**os.environ,'RESORT_DB_PORT':'3307','RESORT_DB_NAME':'resort360_test_setup'}
 inputs='Setup Administrator\nsetup@example.test\n'+PASSWORD+'\n'+PASSWORD+'\n'
 r=subprocess.run([PHP,str(ROOT/'tools/setup.php')],input=inputs,env=env,capture_output=True,text=True)
 record('Initial administrator CLI creates account',r.returncode==0,r.stderr)
 stored=sql('USE resort360_test_setup; SELECT password_hash FROM users WHERE id=1;')
 record('Setup stores a hash instead of plaintext',stored.startswith('$2y$') and PASSWORD not in stored)
 r=subprocess.run([PHP,str(ROOT/'tools/setup.php')],input=inputs,env=env,capture_output=True,text=True)
 record('Initial administrator CLI refuses a second bootstrap',r.returncode==1)
 r=subprocess.run([PHP,str(ROOT/'tools/setup.php'),'reset'],input='setup@example.test\n',env=env,capture_output=True,text=True)
 record('CLI recovery issues one-use token',r.returncode==0 and 'One-use reset code' in r.stdout)
 # Refresh only the generated main test administrator's password for this review.
 hashed=subprocess.run([PHP,'-r','echo password_hash(getenv("R360_TEST_PASSWORD"), PASSWORD_DEFAULT);'],env={**os.environ,'R360_TEST_PASSWORD':PASSWORD},capture_output=True,text=True,check=True).stdout
 sql("USE resort360_test; UPDATE users SET password_hash='"+hashed+"' WHERE id=1;")
 admin=Client();expect('Review administrator login',admin.login('admin@example.test'))
 rid=int(sql("USE resort360_test; SELECT id FROM reservations WHERE status='checked_out' LIMIT 1;"))
 pid=int(sql(f'USE resort360_test; SELECT id FROM payments WHERE reservation_id={rid} AND voided_at IS NULL LIMIT 1;'))
 expect('Void incorrect receipt after check-out',admin.update('payments',pid,{'action':'void','reason':'QA corrective receipt after departure'}))
 options=expect('Payment selector exposes outstanding checked-out stay',admin.api('lookups'))['reservations']
 record('Checked-out correction can be selected',any(x['id']==rid for x in options))
 expect('Corrective payment after check-out',admin.create('payments',{'reservation_id':rid,'amount':'2000.00','method':'cash','reference':'QA-CORRECTION','notes':'Replacement for voided receipt'}),201)
 corrected=expect('Read corrected completed stay',admin.get('reservations',id=rid))
 record('Corrective ledger preserves stay and zero balance',corrected['status']=='checked_out' and corrected['balance']=='0.00')
 sys.path.insert(0,str(ROOT/'tests/runtime/python'))
 from playwright.sync_api import sync_playwright
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
  page=browser.new_page(viewport={'width':390,'height':844})
  errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(BASE+'login.php');page.screenshot(path=str(ROOT/'docs/login-mobile.png'),full_page=True)
  page.locator('#email').fill('admin@example.test');page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click();page.wait_for_url('**/index.php');page.locator('#reservationChart').wait_for();page.wait_for_timeout(1500)
  script='''() => [...document.querySelectorAll('canvas')].map(c=>({id:c.id,width:c.width,height:c.height,drawn:[...c.getContext('2d').getImageData(0,0,c.width,c.height).data].some((x,i)=>i%4===3&&x>0)}))'''
  metrics=page.evaluate(script);print('Mobile canvas checks:',json.dumps(metrics),flush=True)
  record('Mobile chart canvases contain rendered pixels',all(c['drawn'] and c['width']>0 for c in metrics) and len(metrics)==2)
  page.locator('#occupancyChart').scroll_into_view_if_needed();page.wait_for_timeout(300);page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(ROOT/'docs/dashboard-mobile.png'),full_page=True)
  record('Mobile dashboard has no overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
  page.set_viewport_size({'width':1440,'height':1000});page.goto(BASE+'index.php#devices');page.locator('#recordCount').filter(has_text='record').wait_for();page.screenshot(path=str(ROOT/'docs/monitoring-desktop.png'),full_page=True)
  # Exercise modal rendering across every writable module, including lookup controls.
  for module in ['reservations','guests','facilities','payments','maintenance','devices','users']:
   page.goto(BASE+'index.php#'+module);page.locator('[data-new="'+module+'"]').click();page.locator('#recordForm').wait_for();record('Bootstrap form renders for '+module,page.locator('#recordForm [name]').count()>1);page.locator('#recordModal .btn-close').click();page.locator('#recordModal').wait_for(state='hidden')
  record('Review has no JavaScript exceptions',not errors,'; '.join(errors))
  browser.close()
if __name__=='__main__':
 success=False
 try:review();success=True
 except Exception as exc:
  print(str(exc),flush=True)
  if not RESULTS or RESULTS[-1]['passed']:RESULTS.append({'name':'Additional review completed','passed':False,'detail':str(exc)})
 finally:
  (ROOT/'docs/review-results.json').write_text(json.dumps({'timestamp':datetime.now().isoformat(),'results':RESULTS},indent=2),encoding='utf-8')
  print(f'{sum(x["passed"] for x in RESULTS)}/{len(RESULTS)} additional checks passed.',flush=True)
 sys.exit(0 if success else 1)

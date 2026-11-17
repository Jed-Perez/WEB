"""Landing/booking regression on isolated port 8088; live site checks are read-only."""
from run import *

def landing_checks():
 anon=Client()
 status,html,_=anon.raw('index.php')
 record('Anonymous root serves the landing page',status==200 and b'A little room' in html and b'Please sign in' not in html)
 status,body,_=anon.raw('api/public.php')
 catalog=json.loads(body)['data']
 record('Public catalog is database backed',status==200 and len(catalog)>0)
 allowed={'id','name','type','capacity','nightly_rate','description'}
 record('Public catalog exposes only accommodation fields',all(set(row)==allowed for row in catalog))
 record('Non-bookable facilities excluded from catalog',all(row['type'] in ['room','cottage'] for row in catalog))
 status,_,_=anon.raw('api/public.php','POST',{})
 record('Public endpoint rejects mutations',status==405)
 status,_,_=anon.raw('api/public.php?action=availability&arrival=not-a-date&departure=2027-01-01&guests_count=2')
 record('Public availability validates dates',status==422)
 arrival=sql('USE resort360_test; SET time_zone="+08:00"; SELECT DATE_ADD(CURDATE(),INTERVAL 60 DAY);')
 departure=sql('USE resort360_test; SET time_zone="+08:00"; SELECT DATE_ADD(CURDATE(),INTERVAL 62 DAY);')
 q=urllib.parse.urlencode({'action':'availability','arrival':arrival,'departure':departure,'guests_count':2})
 status,body,_=anon.raw('api/public.php?'+q);available=json.loads(body)['data']
 record('Public availability returns valid units',status==200 and len(available)>0)
 fid=available[0]['id']
 env={**os.environ,'RESORT_DB_PORT':'3307','RESORT_DB_NAME':'resort360_test'}
 before=sql('USE resort360_test; SELECT COUNT(*) FROM reservations;')
 result=subprocess.run([PHP,str(ROOT/'tools/install_database.php')],env=env,capture_output=True,text=True)
 record('Database installer refuses existing tables',result.returncode==1 and 'Nothing was imported or overwritten' in result.stderr)
 record('Installer refusal preserves data',before==sql('USE resort360_test; SELECT COUNT(*) FROM reservations;'))
 sql('DROP DATABASE IF EXISTS resort360_test_invite;')
 invite_env={**env,'RESORT_DB_NAME':'resort360_test_invite'}
 result=subprocess.run([PHP,str(ROOT/'tools/install_database.php')],env=invite_env,capture_output=True,text=True)
 record('CLI installer supports a fresh configured database',result.returncode==0,result.stderr)
 result=subprocess.run([PHP,str(ROOT/'tools/setup.php'),'invite-admin'],input='Invite Admin\ninvite@example.test\n',env=invite_env,capture_output=True,text=True)
 match=re.search(r'One-use reset code \(30 minutes\): ([a-f0-9]{64})',result.stdout)
 record('Administrator invitation creates a one-use code',result.returncode==0 and bool(match))
 record('Invited administrator has an active role and hashed password',sql('USE resort360_test_invite; SELECT COUNT(*) FROM users WHERE role_id=1 AND active=1 AND password_hash LIKE \'$2y$%\';')=='1')
 sys.path.insert(0,str(ROOT/'tests/runtime/python'))
 from playwright.sync_api import sync_playwright
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
  page=browser.new_page(viewport={'width':1440,'height':1000})
  errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
  page.goto(BASE);page.locator('.stay-card').first.wait_for()
  record('Landing inventory cards render',page.locator('.stay-card').count()==len(catalog))
  page.locator('#arrival').fill(arrival);page.locator('#departure').fill(departure);page.locator('#guestsCount').fill('2');page.locator('#searchStays').click()
  page.locator('[data-select-space="'+str(fid)+'"]').filter(has_text='Review this stay').wait_for()
  record('AJAX search displays selected dates',arrival in page.locator('#staySummary').inner_text() and departure in page.locator('#staySummary').inner_text())
  page.locator('[data-select-space="'+str(fid)+'"]').click();page.locator('#stayRegister').click();page.wait_for_url('**/login.php?mode=register&return=stay')
  record('Booking link opens guest registration',page.locator('#name').count()==1 and page.locator('#phone').count()==1)
  email='landing-'+secrets.token_hex(5)+'@example.test'
  page.locator('#name').fill('Landing Flow Guest');page.locator('#email').fill(email);page.locator('#phone').fill('09000000000');page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click()
  page.locator('#name').wait_for(state='detached');page.locator('#email').fill(email);page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click();page.wait_for_url('**/home.php#availability');page.locator('#submitStay').wait_for()
  record('Sign-in restores reservation dates',arrival in page.locator('#stayModalBody').inner_text() and departure in page.locator('#stayModalBody').inner_text())
  record('Sign-in restores selected facility and guest count',page.locator('#guestsCount').input_value()=='2' and page.locator('.review-space-name').inner_text()==available[0]['name'])
  page.locator('#stayAcknowledgement').check();page.locator('#submitStay').click();page.locator('#stayModal').wait_for(state='hidden');page.locator('#bookingSuccess').wait_for()
  record('Landing handoff creates a real reservation',sql("USE resort360_test; SELECT COUNT(*) FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE g.email='"+email+"' AND r.facility_id="+str(fid)+" AND r.arrival='"+arrival+"';")=='1')
  page.goto(BASE+'home.php');page.locator('#publicNav a[href="#my-stays"]').wait_for();record('Signed-in guests can manage stays on landing',page.locator('#publicNav a[href="#my-stays"]').inner_text().strip().startswith('My reservations'))
  fresh=browser.new_page(viewport={'width':390,'height':844});fresh.on('pageerror',lambda error:errors.append(str(error)))
  fresh.goto(BASE);fresh.locator('.stay-card').first.wait_for()
  record('Mobile landing has no horizontal overflow',fresh.evaluate('document.documentElement.scrollWidth <= innerWidth'))
  fresh.get_by_role('button',name='Open menu',exact=True).click();fresh.locator('#publicNav.show').wait_for();fresh.locator('#publicNav a[href="#questions"]').click();fresh.locator('#publicNav').wait_for(state='hidden')
  record('Mobile public navigation works',fresh.url.endswith('#questions'))
  fresh.get_by_role('button',name='How do payments work?',exact=True).click();fresh.locator('#answerPayments.show').wait_for();record('FAQ accordion works',fresh.locator('#answerPayments').is_visible())
  # Use a real token for the newly created isolated guest to verify reset links.
  # This email exists only in the test database, and the CLI verifies it before issuance.
  reset=subprocess.run([PHP,str(ROOT/'tools/setup.php'),'reset'],input=email+'\n',env=env,capture_output=True,text=True)
  token=re.search(r'One-use reset code \(30 minutes\): ([a-f0-9]{64})',reset.stdout)[1]
  page.goto(BASE+'login.php?mode=reset#reset='+token);page.locator('#token').wait_for()
  record('Signed-in account can open reset-link form','mode=reset' in page.url)
  record('Reset link prefills the one-use token',page.locator('#token').input_value()==token)
  record('Reset token fragment removed from address bar','#reset=' not in page.url)
  page.locator('#password').fill(PASSWORD+'x');page.locator('#authSubmit').click();page.locator('#token').wait_for(state='detached');record('Reset-link password change succeeds',page.locator('#authError.d-none').count()==1)
  # Read-only verification of the actual user-requested installation on port 80.
  live=browser.new_page(viewport={'width':1440,'height':1000});live.on('pageerror',lambda error:errors.append(str(error)))
  response=live.goto('http://localhost/webngakingbaby/');live.locator('.stay-card').first.wait_for()
  record('Live XAMPP landing serves successfully',response.status==200)
  record('Live installation shows three seeded bookable spaces',live.locator('.stay-card').count()==3)
  live.screenshot(path=str(ROOT/'docs/landing-desktop.png'),full_page=True)
  live.set_viewport_size({'width':390,'height':844});live.screenshot(path=str(ROOT/'docs/landing-mobile.png'),full_page=True)
  record('Live landing has no mobile overflow',live.evaluate('document.documentElement.scrollWidth <= innerWidth'))
  record('Landing and handoff have no JavaScript exceptions',not errors,'; '.join(errors))
  browser.close()

if __name__=='__main__':
 success=False
 try:landing_checks();success=True
 except Exception as exc:
  print(str(exc),flush=True)
  if not RESULTS or RESULTS[-1]['passed']:RESULTS.append({'name':'Landing test runner completed','passed':False,'detail':str(exc)})
 finally:
  (ROOT/'docs/landing-results.json').write_text(json.dumps({'timestamp':datetime.now().isoformat(),'results':RESULTS},indent=2),encoding='utf-8')
  print(f'{sum(x["passed"] for x in RESULTS)}/{len(RESULTS)} landing checks passed.',flush=True)
 sys.exit(0 if success else 1)

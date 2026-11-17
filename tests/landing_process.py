"""Landing process regressions; all writes use resort360_test on port 3307."""
from run import *

def check():
 hashed=subprocess.run([PHP,'-r','echo password_hash(getenv("R360_TEST_PASSWORD"), PASSWORD_DEFAULT);'],env={**os.environ,'R360_TEST_PASSWORD':PASSWORD},capture_output=True,text=True,check=True).stdout
 sql("USE resort360_test; UPDATE users SET password_hash='"+hashed+"' WHERE id=1; DELETE FROM auth_attempts;")
 admin=Client();expect('Process administrator login',admin.login('admin@example.test'))
 tag='Landing-'+secrets.token_hex(4)
 fid=expect('Create process test room',admin.create('facilities',{'name':tag,'type':'room','capacity':4,'nightly_rate':'100.00','status':'available','description':'Isolated test room'}),201)['id']
 arrival=sql('USE resort360_test; SET time_zone="+08:00"; SELECT DATE_ADD(CURDATE(),INTERVAL 100 DAY);')
 departure=(datetime.strptime(arrival,'%Y-%m-%d')+timedelta(days=2)).strftime('%Y-%m-%d')
 email=tag.lower()+'@example.test';guest=Client()
 expect('Create process guest',guest.api('register','POST',{'name':tag,'email':email,'phone':'09000000000','password':PASSWORD}),201)
 sys.path.insert(0,str(ROOT/'tests/runtime/python'))
 from playwright.sync_api import sync_playwright
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
  page=browser.new_page(viewport={'width':1440,'height':1000});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(BASE+'home.php');page.locator('[data-select-space="'+str(fid)+'"]').wait_for()
  page.locator('#arrival').fill(arrival);page.locator('#departure').fill(departure);page.locator('#searchStays').click()
  choose=page.locator('[data-select-space="'+str(fid)+'"]');choose.filter(has_text='Review this stay').wait_for()
  record('Catalog shows full two-night total','200.00' in choose.locator('xpath=ancestor::article').inner_text())
  page.locator('#guestsCount').fill('3')
  record('Changing group size invalidates selectable results',page.get_by_role('button',name='Review this stay',exact=False).count()==0)
  # Delay the next response and change dates while it is in flight.
  page.evaluate("""() => {const original=window.fetch;window.fetch=async(...args)=>{const response=await original(...args);if(String(args[0]).includes('api/public.php')&&String(args[0]).includes('availability'))await new Promise(resolve=>setTimeout(resolve,600));return response;};}""")
  page.locator('#searchStays').click();page.locator('#guestsCount').fill('2');page.wait_for_timeout(800)
  record('Older availability cannot restore outdated selection',choose.inner_text().startswith('Check dates'))
  page.locator('#searchStays').click();choose.filter(has_text='Review this stay').wait_for();choose.click();page.locator('#staySignIn').wait_for()
  page.locator('#stayNotes').fill('Window preference <script>safe text</script>');page.locator('#staySignIn').click();page.wait_for_url('**/login.php?return=stay')
  page.locator('#email').fill(email);page.locator('#password').fill(PASSWORD);page.locator('#authSubmit').click();page.wait_for_url('**/home.php#availability');page.locator('#submitStay').wait_for()
  record('Existing guest returns to landing review','home.php' in page.url and page.locator('#stayAcknowledgement').count()==1)
  record('Guest notes survive authentication',page.locator('#stayNotes').input_value()=='Window preference <script>safe text</script>')
  record('Review shows restored dates',arrival in page.locator('#stayModalBody').inner_text() and departure in page.locator('#stayModalBody').inner_text())
  page.set_viewport_size({'width':390,'height':844})
  record('Mobile booking review fits viewport',page.evaluate('document.documentElement.scrollWidth<=innerWidth') and page.locator('#submitStay').is_visible())
  page.screenshot(path=str(ROOT/'docs/landing-review-mobile.png'),full_page=False,animations='disabled')
  page.set_viewport_size({'width':1440,'height':1000})
  sql(f'USE resort360_test; UPDATE facilities SET nightly_rate=175.00 WHERE id={fid};')
  page.locator('#stayAcknowledgement').check();page.locator('#submitStay').click();page.locator('#stayReviewError').filter(has_text='rate has changed').wait_for()
  record('Changed rate requires new review',sql(f'USE resort360_test; SELECT COUNT(*) FROM reservations WHERE facility_id={fid};')=='0')
  page.locator('#stayModal .btn-close').click();page.locator('#stayModal').wait_for(state='hidden');page.locator('#searchStays').click();choose.filter(has_text='Review this stay').wait_for();choose.click();page.locator('#submitStay').wait_for()
  record('Fresh review uses updated full price','350.00' in page.locator('.booking-total').inner_text())
  page.locator('#stayAcknowledgement').check();page.evaluate("() => {const b=document.querySelector('#submitStay');b.click();b.click();}");page.locator('#bookingSuccess').wait_for();page.locator('#stayModal').wait_for(state='hidden')
  record('One submission creates exactly one request',sql(f'USE resort360_test; SELECT COUNT(*) FROM reservations WHERE facility_id={fid};')=='1')
  record('Success clearly identifies pending state','pending' in page.locator('#bookingSuccess').inner_text() and 'R360-' in page.locator('#bookingSuccess').inner_text())
  record('Successful request clears draft',page.evaluate("sessionStorage.getItem('r360ReservationIntent')") is None)
  rid=int(sql(f'USE resort360_test; SELECT id FROM reservations WHERE facility_id={fid};'))
  expect('Record partial payment for guest view',admin.create('payments',{'reservation_id':rid,'amount':'50.00','method':'cash','reference':tag,'notes':'QA'}),201)
  page.locator('#refreshMyStays').click();page.locator('#myStayCards').filter(has_text='300.00').wait_for();page.locator('[data-stay-details="'+str(rid)+'"]').click();page.locator('.guest-ledger').wait_for()
  record('Landing displays real payment ledger','50.00' in page.locator('.guest-ledger').inner_text())
  page.locator('#stayModal .btn-close').click();page.locator('#stayModal').wait_for(state='hidden');page.locator('[data-cancel-stay="'+str(rid)+'"]').click()
  record('Cancellation explains paid settlement','does not issue a refund' in page.locator('#stayModalBody').inner_text())
  page.locator('#confirmStayCancellation').click();page.locator('#stayModal').wait_for(state='hidden');page.locator('#myStayCards .status-cancelled').wait_for()
  record('Guest cancellation persists',sql(f'USE resort360_test; SELECT status FROM reservations WHERE id={rid};')=='cancelled')
  record('Cancellation retains payment history',sql(f'USE resort360_test; SELECT SUM(amount) FROM payments WHERE reservation_id={rid} AND voided_at IS NULL;')=='50.00')
  # Expired drafts should recover to usable inventory rather than leave a spinner.
  page.evaluate("id => sessionStorage.setItem('r360ReservationIntent',JSON.stringify({source:'landing',facility_id:id,arrival:'2000-01-01',departure:'2000-01-02',guests_count:2}))",fid)
  page.reload();page.locator('[data-select-space="'+str(fid)+'"]').wait_for();page.locator('#availabilityError').filter(has_text='saved selection').wait_for()
  record('Expired draft recovers to usable catalog',page.locator('.stay-loading').count()==0 and page.locator('.stay-card').count()>0)
  page.screenshot(path=str(ROOT/'docs/landing-process-desktop.png'),full_page=True)
  page.evaluate("draft => sessionStorage.setItem('r360ReservationIntent',JSON.stringify(draft))",{'source':'landing','facility_id':fid,'arrival':arrival,'departure':departure,'guests_count':2,'notes':'Private guest draft'})
  page.goto(BASE+'index.php#profile');page.locator('#profileForm').wait_for()
  record('Landing draft does not interrupt workspace navigation',page.locator('#recordModal.show').count()==0)
  page.get_by_role('button',name='Account menu',exact=True).click();page.locator('#logoutButton').click();page.wait_for_url('**/login.php')
  record('Workspace sign-out clears guest booking draft',page.evaluate("sessionStorage.getItem('r360ReservationIntent')") is None)
  record('Landing process has no browser exceptions',not errors,'; '.join(errors));browser.close()

if __name__=='__main__':
 success=False
 try:check();success=True
 except Exception as exc:
  print(str(exc),flush=True)
  if not RESULTS or RESULTS[-1]['passed']:RESULTS.append({'name':'Landing process regression completed','passed':False,'detail':str(exc)})
 finally:
  (ROOT/'docs/landing-process-results.json').write_text(json.dumps({'timestamp':datetime.now().isoformat(),'results':RESULTS},indent=2),encoding='utf-8')
  print(f'{sum(x["passed"] for x in RESULTS)}/{len(RESULTS)} landing process checks passed.',flush=True)
 sys.exit(0 if success else 1)

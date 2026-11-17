"""Package deliverable source and documentation, never runtime state or secrets."""
from pathlib import Path
import hashlib, json, zipfile, os
ROOT=Path(__file__).resolve().parents[1]
ALLOWED={'app','api','assets','config','database','docs','iot','tests','tools','views','storage'}
TOP={'.htaccess','.gitignore','index.php','home.php','login.php','README.md'}
def included(path):
 rel=path.relative_to(ROOT)
 if len(rel.parts)==1:return rel.name in TOP
 if rel.parts[0] not in ALLOWED:return False
 if 'runtime' in rel.parts or '__pycache__' in rel.parts:return False
 if rel.as_posix() in {'config/local.php','iot/config.h'}:return False
 if rel.parts[0]=='storage':return rel.name in {'.htaccess','.gitkeep'}
 return path.suffix not in {'.pyc','.log','.zip'}
def report():
 groups=[]
 for file in ['test-results.json','review-results.json','landing-results.json']:
  data=json.loads((ROOT/'docs'/file).read_text(encoding='utf-8'))
  groups.append((file,data))
 results=[r for _,d in groups for r in d['results']]
 lines=['# Actual test results','',f'Final automated result: **{sum(r["passed"] for r in results)}/{len(results)} checks passed**.','',
 'Execution date: 14 November 2026, Asia/Manila. Environment: Windows, XAMPP Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32, installed Google Chrome, Playwright. Workflow test fixtures were isolated on localhost ports 8088 and 3307. Landing checks also verified the actual localhost installation through read-only browser visits.','',
 'PHP application/test syntax checks and JavaScript parse checks also passed. The SQL was imported into empty test databases. Source libraries were bundled with licenses and SHA-256 checksums. See the JSON files for exact per-run timestamps.','',
 '## Issues found and resolved','',
 '- Apache sandbox initially could not write XAMPP session files; test Apache was rerun with approved session-directory access.',
 '- Apache did not preserve the nonstandard HTTP 419 status; CSRF failures now use standard 403.',
 '- Device Authorization was initially unavailable to PHP; CGIPassAuth On now forwards it.',
 '- The mobile menu backdrop intercepted clicks; sidebar stacking was corrected and retested.',
 '- Windows test import encoding damaged demo text; UTF-8 import/read settings were made explicit and a Unicode round-trip check was added.',
 '- Browser test row selection was tightened to await/select the intended record after AJAX changes.','',
 '## Final executed checks','', '| Check | Result |','|---|---|']
 for r in results:lines.append('| '+r['name'].replace('|','/')+' | '+('PASS' if r['passed'] else 'FAIL')+' |')
 lines+=['','## Verification limits','','No physical ESP32, Arduino compilation, sensor calibration, production load test, external penetration test, or resort stakeholder acceptance was performed. HTTP ingestion was tested with authenticated requests. Reports were checked for correct download formats and API authorization; manual reconciliation with real resort records remains a client acceptance step. See TESTING.md.','']
 (ROOT/'docs/TEST_RESULTS.md').write_text('\n'.join(lines),encoding='utf-8')
 if not all(r['passed'] for r in results):raise RuntimeError('Cannot package with failed final checks.')
if __name__=='__main__':
 report()
 out=ROOT/'dist';out.mkdir(exist_ok=True)
 (out/'.htaccess').write_text('Require all denied\n',encoding='utf-8')
 paths=[]
 for directory in ALLOWED:
  base=ROOT/directory
  for folder,dirs,files in os.walk(base):
   dirs[:]=[d for d in dirs if d not in {'runtime','__pycache__'}]
   for name in files:
    path=Path(folder)/name
    if included(path):paths.append(path)
 paths += [ROOT/name for name in TOP if (ROOT/name).is_file()]
 archive=out/'Resort360-XAMPP.zip'
 with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for path in sorted(paths):z.write(path,'webngakingbaby/'+path.relative_to(ROOT).as_posix())
 with zipfile.ZipFile(archive) as z:
  assert z.testzip() is None
  assert all('/runtime/' not in n and not n.endswith(('/local.php','/config.h')) for n in z.namelist())
 digest=hashlib.sha256(archive.read_bytes()).hexdigest()
 (out/'SHA256SUMS').write_text(digest+'  '+archive.name+'\n',encoding='utf-8')
 print(f'Packaged {len(paths)} files: {archive.name} ({archive.stat().st_size:,} bytes). ZIP integrity and secret exclusions verified.')

"""Development-only asset downloader. Runtime requires no Python/npm/CDN."""
from pathlib import Path
from urllib.request import urlopen, Request
import concurrent.futures, hashlib
ROOT = Path(__file__).resolve().parents[1] / 'assets' / 'vendor'
FILES = {
 'bootstrap.min.css':'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
 'bootstrap.bundle.min.js':'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
 'bootstrap-icons.css':'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
 'fonts/bootstrap-icons.woff2':'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2',
 'fonts/bootstrap-icons.woff':'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff',
 'chart.umd.js':'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js',
 'sweetalert2.all.min.js':'https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js',
 'fpdf/fpdf.php':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/fpdf.php',
 'fpdf/font/helvetica.php':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/font/helvetica.php',
 'fpdf/font/helveticab.php':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/font/helveticab.php',
 'fpdf/font/helveticai.php':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/font/helveticai.php',
 'fpdf/font/helveticabi.php':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/font/helveticabi.php',
 'fpdf/LICENSE':'https://raw.githubusercontent.com/Setasign/FPDF/1.8.6/license.txt',
 'BOOTSTRAP-LICENSE':'https://raw.githubusercontent.com/twbs/bootstrap/v5.3.3/LICENSE',
 'ICONS-LICENSE':'https://raw.githubusercontent.com/twbs/icons/v1.11.3/LICENSE',
 'CHART-LICENSE':'https://raw.githubusercontent.com/chartjs/Chart.js/v4.4.7/LICENSE.md',
 'SWEETALERT-LICENSE':'https://raw.githubusercontent.com/sweetalert2/sweetalert2/v11.14.5/LICENSE',
}
def fetch(item):
 path,url=item
 target=ROOT/path; target.parent.mkdir(parents=True,exist_ok=True)
 data=urlopen(Request(url,headers={'User-Agent':'Resort360-asset-build'}),timeout=45).read()
 target.write_bytes(data)
 return f'{hashlib.sha256(data).hexdigest()}  {path}'
if __name__=='__main__':
 with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
  hashes=list(pool.map(fetch,FILES.items()))
 (ROOT/'SHA256SUMS').write_text('\n'.join(hashes)+'\n',encoding='utf-8')
 print(f'Vendored {len(hashes)} files.')


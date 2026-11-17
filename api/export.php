<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
try {
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new HttpError('GET required.',405);
    $u=Auth::user();Auth::require($u,'reports.read');$m=choice($_GET,'module',['reservations','payments','maintenance','readings','facilities']);$format=choice($_GET,'format',['csv','xls','pdf']);
    $result=Resort::list($u,$m,array_diff_key($_GET,['page'=>1]),true);if($result['total']>10000)throw new HttpError('Narrow your report to 10,000 records or fewer.');
    $columns=[
        'reservations'=>['reference','guest','facility','arrival','departure','status','total','paid','balance'],
        'payments'=>['id','booking','guest','amount','method','status','created_at'],
        'maintenance'=>['id','facility','title','priority','status','assignee','created_at'],
        'readings'=>['device','facility','temperature','sample_id','received_at'],
        'facilities'=>['name','type','capacity','nightly_rate','status','occupied'],
    ][$m];
    $rows=$result['rows'];audit('export','reports',null,$m.' / '.$format.' / '.count($rows).' rows');
    $filename='Resort360-'.$m.'-'.date('Ymd').'.'.$format;
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    if($format==='csv'){
        header('Content-Type: text/csv; charset=utf-8');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$columns);
        foreach($rows as $r)fputcsv($out,array_map(function($c)use($r){$v=(string)($r[$c]??'');return preg_match('/^[\s]*[=+@-]/u',$v)?"'".$v:$v;},$columns));fclose($out);exit;
    }
    if($format==='xls'){
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        // SpreadsheetML: explicit string cells prevent spreadsheet formula interpretation.
        echo '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Report"><Table>';
        foreach([$columns,...array_map(fn($r)=>array_map(fn($c)=>(string)($r[$c]??''),$columns),$rows)] as $row){echo '<Row>';foreach($row as $cell)echo '<Cell><Data ss:Type="String">'.htmlspecialchars($cell,ENT_XML1|ENT_QUOTES,'UTF-8').'</Data></Cell>';echo '</Row>';}
        echo '</Table></Worksheet></Workbook>';exit;
    }
    require __DIR__.'/../assets/vendor/fpdf/fpdf.php';
    $pdf=new FPDF('L','mm','A4');$pdf->SetAutoPageBreak(true,15);
    $ascii=fn($s)=>iconv('UTF-8','windows-1252//TRANSLIT',(string)$s);
    $head=function()use($pdf,$columns,$m){$pdf->AddPage();$pdf->SetFont('Helvetica','B',15);$pdf->Cell(0,10,'Resort360 | '.ucfirst($m),0,1);$pdf->SetFont('Helvetica','',8);$pdf->Cell(0,6,'Generated '.date('Y-m-d H:i').' Asia/Manila | Date range: '.($_GET['from']??'all').' to '.($_GET['to']??'all'),0,1);$pdf->SetFillColor(225,240,236);$pdf->SetFont('Helvetica','B',8);foreach($columns as $c)$pdf->Cell(277/count($columns),8,ucwords(str_replace('_',' ',$c)),1,0,'L',true);$pdf->Ln();$pdf->SetFont('Helvetica','',8);};
    $head();foreach($rows as $r){$w=277/count($columns);$lineCount=1;foreach($columns as $c){$text=$ascii($r[$c]??'');$lineCount=max($lineCount,(int)ceil(max(1,$pdf->GetStringWidth($text))/($w-4)));}$h=max(8,$lineCount*5+2);if($pdf->GetY()+$h>190)$head();$x=$pdf->GetX();$y=$pdf->GetY();foreach($columns as $c){$pdf->Rect($x,$y,$w,$h);$pdf->SetXY($x+1,$y+1);$pdf->MultiCell($w-2,5,$ascii($r[$c]??''),0);$x+=$w;}$pdf->SetXY(10,$y+$h);}
    $pdf->Output('D',$filename);exit;
}catch(Throwable $e){header_remove('Content-Disposition');fail_response($e);}

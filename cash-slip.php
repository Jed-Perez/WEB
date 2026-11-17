<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
try {
    $u=Auth::user();$id=integer($_GET,'id');
    $rows=Resort::list($u,'cash_requests',['id'=>$id])['rows'];
    if(!$rows)throw new HttpError('Payment slip not found.',404);
    $r=$rows[0];$paid=$r['status']==='paid';
} catch(Throwable $error) {
    http_response_code($error instanceof HttpError?$error->status:500);
    echo e($error instanceof HttpError?$error->getMessage():'Unable to load payment slip.');exit;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($r['reference'])?> · MNB Beach Resort</title><link rel="stylesheet" href="assets/css/cash-slip.css"></head><body>
<nav class="print-controls"><button id="printSlip" type="button">Print / Save as PDF</button><a href="index.php#cash_requests">Cash payments</a></nav>
<main><header><div><strong>MNB BEACH RESORT</strong><p>resort360 · Cash payments</p></div><span class="status"><?=e(strtoupper($r['status']))?></span></header>
<h1><?=$paid?'Cash payment receipt':'Cash payment slip'?></h1><p class="reference"><?=e($r['reference'])?></p>
<?php if($r['status']==='pending'): ?><p>Present this slip at reception and pay in cash. This slip is a request for payment, not a receipt.</p>
<?php elseif($paid): ?><p>Cash received and recorded for the booking below.</p>
<?php else: ?><p class="invalid">This document is <?=e($r['status'])?> and is not valid for payment or proof of payment.</p><?php endif; ?>
<div class="amount"><span><?=$paid?'Cash received':($r['status']==='pending'?'Cash to pay':'Slip amount')?></span><strong>PHP <?=e(number_format((float)$r['amount'],2))?></strong></div>
<dl><?php foreach(['Booking'=>$r['booking'],'Guest'=>$r['guest'],'Room / cottage'=>$r['facility_name'],'Arrival on this slip'=>$r['arrival'],'Departure on this slip'=>$r['departure'],'Booking total when issued'=>'PHP '.number_format((float)$r['total_at_issue'],2),'Generated at'=>$r['created_at']] as $label=>$value): ?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach; ?>
<?php if($r['received_at']): ?><div><dt>Recorded payment</dt><dd>#<?=e($r['payment_id'])?> · Cash</dd></div><div><dt>Received by</dt><dd><?=e($r['received_by'])?></dd></div><div><dt>Received at</dt><dd><?=e($r['received_at'])?></dd></div><?php endif; ?></dl>
<?php if($r['notes']): ?><h2>Message</h2><p class="note"><?=e($r['notes'])?></p><?php endif; ?>
<?php if($r['cancelled_reason']||$r['void_reason']): ?><p class="invalid"><?=e($r['cancelled_reason']?:$r['void_reason'])?></p><?php endif; ?>
<footer>All times are Asia/Manila. Status as of <?=e(date('Y-m-d H:i:s'))?>. Check your guest account for the latest status.</footer></main><script src="assets/js/cash-slip.js"></script></body></html>

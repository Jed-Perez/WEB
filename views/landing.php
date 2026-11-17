<?php
if (!isset($config)) { http_response_code(404); exit; }
if (!defined('APP_VIEW')) define('APP_VIEW',true);
$title='A little room to unwind';$extraCss='assets/css/landing.css?v='.filemtime(__DIR__.'/../assets/css/landing.css');
$signedIn=false;$landingUser=null;
if(isset($_SESSION['user_id'])) {try {$landingUser=Auth::user();$signedIn=true;} catch(Throwable) {}}
$isGuest=$landingUser&&$landingUser['role']==='guest';
require __DIR__.'/layouts/head.php';
$workspaceUrl=$isGuest?'index.php#reservations':'index.php';
$bookingUrl=$signedIn?$workspaceUrl:'login.php';
$bookingLabel=$signedIn?($isGuest?'Open guest portal':'Open workspace'):'Sign in to reserve';
?>
<body class="landing-page" data-signed-in="<?=$signedIn?'1':'0'?>" data-role="<?=e($landingUser['role']??'')?>">
<a class="visually-hidden-focusable skip-link" href="#mainContent">Skip to content</a>
<header class="landing-header">
  <nav class="navbar navbar-expand-md landing-container" aria-label="Resort navigation">
    <a class="brand landing-brand" href="home.php"><img src="assets/img/mark.svg" width="42" height="42" alt=""><span>resort<span class="brand-light">360</span><small>MNB BEACH RESORT</small></span></a>
    <div class="collapse navbar-collapse" id="publicNav">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-4">
        <li class="nav-item"><a class="nav-link" href="#how-it-works">Plan your stay</a></li>
        <li class="nav-item"><a class="nav-link" href="#questions">Good to know</a></li>
        <li class="nav-item"><a class="nav-link sign-in-link" href="<?=e($signedIn?$workspaceUrl:'login.php')?>"><?=$isGuest?'My reservations':($signedIn?'My workspace':'Sign in')?> <i class="bi bi-arrow-up-right"></i></a></li>
        <?php if($signedIn): ?><li class="nav-item"><button class="quiet-link" type="button" id="landingLogout">Sign out</button></li><?php endif; ?>
        <li class="nav-item"><a class="btn btn-primary" href="<?=e($bookingUrl)?>"><?=e($bookingLabel)?> <i class="bi bi-arrow-right ms-2"></i></a></li>
      </ul>
    </div>
    <div class="landing-header-controls">
      <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode"><i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i></button>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Open menu"><i class="bi bi-list"></i></button>
    </div>
  </nav>
</header>
<main id="mainContent">
  <section class="landing-container hero-section">
    <div class="hero-copy">
      <span class="landing-kicker"><span></span> A WELCOME CHANGE OF PACE</span>
      <h1>A little room<br>to <em>unwind.</em></h1>
      <p>Your next seaside pause starts at MNB Beach Resort. Sign in to your guest account to explore rooms and cottages, check dates, and manage your stay.</p>
      <div class="hero-actions"><a class="btn btn-primary" href="<?=e($bookingUrl)?>"><?=e($bookingLabel)?> <i class="bi bi-arrow-right ms-3"></i></a><a href="#how-it-works" class="quiet-link">How to reserve <i class="bi bi-arrow-down-right ms-2"></i></a></div>
      <div class="hero-detail"><i class="bi bi-calendar2-check"></i><span>Choose your dates.<br><strong>We’ll take it from there.</strong></span><span class="hero-divider"></span><i class="bi bi-water"></i><span>Rooms & cottages.<br><strong>A space for your plans.</strong></span></div>
    </div>
    <div class="hero-art">
      <img class="hero-photo" src="assets/img/resort-entrance.jpeg" alt="M&amp;B resort entrance with a bamboo wall, leafy trees and wooden outdoor seating" width="640" height="620" fetchpriority="high">
    </div>
  </section>
  <section class="planning-section" id="how-it-works">
    <div class="landing-container planning-layout"><div class="planning-intro"><span class="landing-kicker">A SIMPLE WAY TO GET AWAY</span><h2>From “let’s go”<br>to looking forward<br>to it.</h2><p>Keep your plans in one place, from your first request to your reservation updates.</p><a href="<?=e($signedIn?$workspaceUrl:'login.php?mode=register')?>" class="quiet-link"><?=$signedIn?'Open my workspace':'Create your guest account'?> <i class="bi bi-arrow-up-right ms-2"></i></a></div><div class="planning-steps">
      <article><span>01</span><div><h3>Open your guest account</h3><p>Create an account or sign in to access reservations in your guest workspace.</p></div><i class="bi bi-door-open"></i></article>
      <article><span>02</span><div><h3>Send a reservation request</h3><p>In your workspace, choose dates, check available rooms and cottages, and submit your reservation request.</p></div><i class="bi bi-calendar2-heart"></i></article>
      <article><span>03</span><div><h3>Keep an eye on your updates</h3><p>Reception reviews your request. Your account shows confirmation, payment records, and any updates.</p></div><i class="bi bi-check2-circle"></i></article>
    </div></div>
  </section>
  <section class="landing-container questions-section" id="questions"><div class="section-heading"><div><span class="landing-kicker">BEFORE YOU PACK</span><h2>A few things to know.</h2></div><p>A little clarity for a more relaxed start.</p></div>
    <div class="accordion" id="guestQuestions">
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#answerConfirmation" aria-expanded="true" aria-controls="answerConfirmation">Is my reservation confirmed right away?</button></h3><div id="answerConfirmation" class="accordion-collapse collapse show" data-bs-parent="#guestQuestions"><div class="accordion-body">Your request starts as pending and holds the selected space while reception reviews it. Check your account for confirmation before making final travel arrangements.</div></div></div>
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#answerPayments" aria-expanded="false" aria-controls="answerPayments">How do payments work?</button></h3><div id="answerPayments" class="accordion-collapse collapse" data-bs-parent="#guestQuestions"><div class="accordion-body">Arrange payment directly with the resort. Your account displays payments recorded by reception and the remaining balance. This website does not charge cards or collect online payments.</div></div></div>
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#answerChanges" aria-expanded="false" aria-controls="answerChanges">Can I change or cancel my request?</button></h3><div id="answerChanges" class="accordion-collapse collapse" data-bs-parent="#guestQuestions"><div class="accordion-body">You can edit a pending request in My reservations. Pending and confirmed bookings can be cancelled there too. If a payment has already been recorded, contact reception to discuss settlement.</div></div></div>
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#answerUnits" aria-expanded="false" aria-controls="answerUnits">Can I book more than one room or cottage?</button></h3><div id="answerUnits" class="accordion-collapse collapse" data-bs-parent="#guestQuestions"><div class="accordion-body">Each reservation covers one room or cottage. For several spaces, submit a separate request for each. The current booking system uses nightly stays; discuss day-use arrangements with reception.</div></div></div>
    </div>
  </section>
  <section class="landing-container closing-section"><div><span class="landing-kicker">YOUR NEXT CHAPTER, BY THE COAST</span><h2>Something to look forward to.</h2><p>Sign in to plan and manage your stay.</p></div><a class="btn btn-primary" href="<?=e($bookingUrl)?>"><?=e($bookingLabel)?> <i class="bi bi-arrow-up-right ms-3"></i></a><i class="bi bi-water closing-water" aria-hidden="true"></i></section>
</main>
<footer class="landing-footer landing-container"><a class="brand landing-brand" href="home.php"><img src="assets/img/mark.svg" width="32" height="32" alt=""><span>resort<span class="brand-light">360</span><small>MNB BEACH RESORT</small></span></a><span>Thoughtfully connected. From request to arrival.</span><a href="login.php">Guest & staff sign in <i class="bi bi-arrow-up-right ms-1"></i></a></footer>
<div id="landingError" class="landing-container alert alert-danger d-none" role="alert"></div>
<script src="assets/vendor/bootstrap.bundle.min.js"></script><script src="assets/js/common.js"></script><script src="assets/js/landing.js"></script>
</body></html>

<?php
// Only the isolated test runner supplies this randomly created test-session identifier.
if(PHP_SAPI!=='cli'||getenv('RESORT_DB_NAME')!=='resort360_test')exit(1);
$id=getenv('R360_TEST_SESSION');if(!$id||!preg_match('/^[a-zA-Z0-9,-]{20,128}$/',$id))exit(1);
session_name('resort360');session_id($id);session_start();$_SESSION['last_seen']=time()-1900;session_write_close();

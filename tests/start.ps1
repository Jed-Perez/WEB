param([string]$XamppRoot = 'C:\xampp')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeDir = Join-Path $PSScriptRoot 'runtime'
New-Item -ItemType Directory -Force -Path $runtimeDir | Out-Null
$dataDir = Join-Path $runtimeDir 'mysql'
if (-not (Test-Path -LiteralPath (Join-Path $dataDir 'my.ini'))) {
    & (Join-Path $XamppRoot 'mysql\bin\mysql_install_db.exe') "--datadir=$dataDir" '--port=3307'
    if ($LASTEXITCODE -ne 0) { throw 'Test database initialization failed.' }
}
$projectSlash = $projectRoot.Replace('\','/')
$xamppSlash = $XamppRoot.Replace('\','/')
$runtimeSlash = $runtimeDir.Replace('\','/')
$apacheConfig = Join-Path $runtimeDir 'httpd.conf'
$configText = @"
ServerRoot "$xamppSlash/apache"
Listen 127.0.0.1:8088
ServerName localhost
PidFile "$runtimeSlash/httpd.pid"
ErrorLog "$runtimeSlash/apache-error.log"
LogLevel warn
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule env_module modules/mod_env.so
LoadModule headers_module modules/mod_headers.so
LoadFile "$xamppSlash/php/php8ts.dll"
LoadFile "$xamppSlash/php/libpq.dll"
LoadFile "$xamppSlash/php/libsqlite3.dll"
LoadModule php_module "$xamppSlash/php/php8apache2_4.dll"
PHPINIDir "$xamppSlash/php"
TypesConfig "$xamppSlash/apache/conf/mime.types"
DocumentRoot "$projectSlash"
DirectoryIndex index.php
SetEnv RESORT_DB_PORT 3307
SetEnv RESORT_DB_NAME resort360_test
<FilesMatch "\.php$">
SetHandler application/x-httpd-php
</FilesMatch>
<Directory "$projectSlash">
Options -Indexes
AllowOverride All
Require all granted
</Directory>
"@
[System.IO.File]::WriteAllText($apacheConfig, $configText, (New-Object System.Text.UTF8Encoding($false)))
& (Join-Path $XamppRoot 'apache\bin\httpd.exe') -t -f $apacheConfig
if ($LASTEXITCODE -ne 0) { throw 'Apache test configuration is invalid.' }
$dbProcess = Start-Process -FilePath (Join-Path $XamppRoot 'mysql\bin\mysqld.exe') -ArgumentList "--defaults-file=`"$dataDir\my.ini`"",'--bind-address=127.0.0.1','--port=3307','--console' -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDir 'mysql-out.log') -RedirectStandardError (Join-Path $runtimeDir 'mysql-error.log') -PassThru
$webProcess = Start-Process -FilePath (Join-Path $XamppRoot 'apache\bin\httpd.exe') -ArgumentList '-f',"`"$apacheConfig`"" -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDir 'apache-out.log') -RedirectStandardError (Join-Path $runtimeDir 'apache-stderr.log') -PassThru
Write-Output "Test MariaDB PID: $($dbProcess.Id); test Apache parent PID: $($webProcess.Id)"
Write-Output 'Test URL: http://127.0.0.1:8088/ — run tests/run.py to initialize fixtures.'

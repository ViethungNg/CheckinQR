$env:PATH = "C:\xampp\php;C:\xampp\apache\bin;" + $env:PATH
$phpExe = "C:\xampp\php\php.exe"
if (-not (Test-Path $phpExe)) {
    $phpExe = (Get-Command php -ErrorAction SilentlyContinue).Source
}

$argList = @(
    "-d", "extension_dir=C:\xampp\php\ext",
    "-d", "extension=php_pdo_pgsql.dll",
    "-d", "extension=php_pgsql.dll",
    "-S", "127.0.0.1:8000"
)

# Chay PHP background khong chiem man hinh
Start-Process -FilePath $phpExe -ArgumentList $argList -WindowStyle Hidden

# Doi 1.5 giay roi mo trinh duyet
Start-Sleep -Milliseconds 1500
Start-Process "http://127.0.0.1:8000/admin/index.php"

Write-Output "SERVER_STARTED_SUCCESSFULLY"

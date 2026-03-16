@echo off
REM Batch file para sa Windows Task Scheduler

REM Set PHP path
SET PHP_PATH="C:\xampp\php\php.exe"

REM Set project path (PALITAN MO TO!)
SET PROJECT_PATH="C:\xampp\htdocs\catering\staff\booking_sms_cron.php"

REM Run the cron script
%PHP_PATH% %PROJECT_PATH%

REM Para makita ang results
pause
```

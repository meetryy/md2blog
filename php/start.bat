@echo off

set "pathToPHP=C:\xampp\php\php.exe"

set currentDirectory=%cd%
REM echo currentDirectory

%pathToPHP% %currentDirectory%\md2blog.php

pause
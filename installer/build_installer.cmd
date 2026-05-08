@echo off
setlocal
set "INNO=C:\Program Files (x86)\Inno Setup 6\Compil32.exe"
if not exist "%INNO%" (
  echo Inno Setup compiler not found at %INNO%.
  echo Install Inno Setup 6 and rerun this script.
  pause
  exit /b 1
)
"%INNO%" /cc "pos_installer.iss"
if %errorlevel% neq 0 (
  echo Compilation failed.
  pause
  exit /b %errorlevel%
)
echo Installer compiled successfully.
pause

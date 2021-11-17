@echo off
:Menu
cls
echo Servicio de conexion de romana con idempiere.
echo.
echo Elija su sistema operativo:
echo 1) Windows 10 64Bits
echo 2) Windows 10 32Bits
echo 3) Windows 7 32Bits
echo 4) Windows 7 64Bits
echo 5) Salir
set /p var=
if %var%==1 goto :Primero
if %var%==2 goto :Segundo
if %var%==3 goto :Tercero
if %var%==4 goto :Cuarto
if %var%==5 goto exit
if %var% GTR 5 echo Error
goto :Menu
:Primero
cls 
Echo Instalando opcion 1
Xcopy /E /R /I Win10_64\php C:\php
Xcopy /E /R /I frontuari C:\php\frontuari
Start Win10_64\VC_redist.x64.exe
Echo Instale Microsoft Visual C++ REDIST y presione una tecla para continuar
Pause>Nul
goto :Datos
:Segundo
cls 
Echo Instalando opcion 2
Xcopy /E /R /I Win10_32\php C:\php
Xcopy /E /R /I frontuari C:\php\frontuari
Start Win10_32\VC_redist.x86.exe
Echo Instale Microsoft Visual C++ REDIST y presione una tecla para continuar
Pause>Nul
goto :Datos
:Tercero
cls 
Echo Instalando opcion 3
Xcopy /E /R /I Win7_32\php C:\php
Xcopy /E /R /I frontuari C:\php\frontuari
Start Win7_32\vcredist_x86.exe
Echo Instale Microsoft Visual C++ REDIST y presione una tecla para continuar
Pause>Nul
goto :Datos
:Cuarto
cls 
Echo Instalando opcion 4
Xcopy /E /R /I Win7_64\php C:\php
Xcopy /E /R /I frontuari C:\php\frontuari
Start Win7_64\vcredist_x64.exe
Echo Instale Microsoft Visual C++ REDIST y presione una tecla para continuar
Pause>Nul
goto :Datos
:Datos
cls
start C:\php\frontuari\romana\configurarCorte.bat
echo Configure el CORTE de la bascula y al terminar presione continuar.
Pause>Nul
start C:\php\frontuari\romana\configurarCorteInicioFin.bat
echo Configure el CORTE de INICIO Y FIN de la bascula y al terminar presione continuar.
Pause>Nul
echo Proceda agregar los datos de conexion a la base de datos de idempiere.
echo.
echo Ingrese el HOST (Ej. 192.168.0.1):
set /p host=
Echo $host='%host%'; >> C:\php\frontuari\romana\comOrigen.php
echo Ingrese el Nombre de la base de datos (Ej. mibasedatoQA22):
set /p dbname=
echo $base_dato='%dbname%'; >> C:\php\frontuari\romana\comOrigen.php
echo Ingrese el PUERTO de conexion a la base de datos (Ej. 5432):
set /p portdb=
echo $puerto='%portdb%'; >> C:\php\frontuari\romana\comOrigen.php
echo Ingrese el USUARIO de la base de datos (Ej. adempiere):
set /p userdb=
echo $usuario='%userdb%'; >> C:\php\frontuari\romana\comOrigen.php
echo Ingrese la CLAVE de la base de datos (Ej. adempiere):
set /p clavedb=
echo $clave='%clavedb%'; >> C:\php\frontuari\romana\comOrigen.php
cls
echo $con=conectar_db($host,$base_dato,$usuario,$clave,$puerto); >> C:\php\frontuari\romana\comOrigen.php

set SCRIPT="%TEMP%\%RANDOM%-%RANDOM%-%RANDOM%-%RANDOM%.vbs"
echo Set oWS = WScript.CreateObject("WScript.Shell") >> %SCRIPT%
echo sLinkFile = "%USERPROFILE%\Desktop\Romana.lnk" >> %SCRIPT%
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> %SCRIPT%
echo oLink.TargetPath = "C:\php\frontuari\romana\romana.bat" >> %SCRIPT%
echo oLink.Save >> %SCRIPT%
cscript /nologo %SCRIPT%
del %SCRIPT%
echo.
Echo Instalacion finalizada. Hemos creado un acceso directo en el escritorio para ti,
Echo Presione una tecla iniciar el programa.
Pause>Nul
start C:\php\frontuari\romana\romana.bat
goto exit

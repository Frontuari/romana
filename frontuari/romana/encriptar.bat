@echo off
echo Frontuari C.A. Encriptando V-1.1 Beta
CD C:\php\
php frontuari/codi.php
Echo Presione una tecla para eliminar los temporales
pause
del C:\php\frontuari\codi.php
del C:\php\frontuari\Obfuscator.php
del C:\php\frontuari\romana\comOrigen.php
del C:\php\frontuari\romana\libraryOrigen.php
exit
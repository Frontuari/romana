@echo off
echo Presione una tecla si seguro de eliminar el software de romana.
Pause>Nul
rd /s /q "C:/php"
del /f /q "%USERPROFILE%\Desktop\Romana.lnk" 
echo Desinstalacion realizada!, presione una tecla para salir.
Pause>Nul
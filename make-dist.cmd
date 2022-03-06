@echo off
SETLOCAL ENABLEDELAYEDEXPANSION
for /f "delims=':  tokens=1,2,*" %%i in ( main.inc.php ) do @if "%%i" EQU "Version" (
	set VERSION=%%j
)
set FILES=*.php LICENSE README.md
set SUBDIRS=css include js language template 
rd /s /q dist\MugShot
for %%f in ( %FILES% ) do (
	xcopy %%f dist\MugShot\ /exclude:dist-excluded
)
for %%d in ( %SUBDIRS% ) do (
	xcopy %%d\ dist\MugShot\%%d\ /e /exclude:dist-excluded
)
cd dist
del MugShot-!VERSION: =!.zip 
"C:\Program Files\7-Zip\7z.exe" a -r MugShot-!VERSION: =!.zip MugShot
cd ..

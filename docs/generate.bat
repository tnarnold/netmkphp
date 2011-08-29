@ECHO Off
::Setting target direcoty
SET "target=%cd%\Generated Documentation"

::Discovering examples directory
IF EXIST %cd%\examples (SET examplesdir=%cd%\examples) ELSE (SET examplesdir=%cd%\..\examples)

SET PHP_PEAR_INSTALL_DIR
IF ERRORLEVEL 1 (GOTO fileSource) ELSE (GOTO pearSourcePossibly)

:pearSourcePossibly
IF EXIST %PHP_PEAR_INSTALL_DIR%\Net\RouterOS (GOTO pearSource) ELSE (GOTO fileSource)
GOTO ready

:pearSource
SET sourcedir=%PHP_PEAR_INSTALL_DIR%\Net\RouterOS
GOTO ready

:fileSource
SET sourcedir=%cd%\..\src
GOTO ready

:ready
phpdoc --title "Net_RouterOS Documentaion" --output "HTML:frames:default,HTML:frames:l0l33t,HTML:frames:phpdoc.de,HTML:frames:phphtmllib,HTML:frames:DOM/default,HTML:frames:DOM/l0l33t,HTML:frames:DOM/phpdoc.de,HTML:frames:phpedit,HTML:Smarty:default,HTML:Smarty:HandS,HTML:Smarty:PHP,PDF:default:default,XML:DocBook/peardoc2:default,CHM:default:default" --undocumentedelements --examplesdir "%examplesdir%" --directory "%sourcedir%,%cd%" --ignore "%target%,%examplesdir%" --target "%target%" --sourcecode "off"
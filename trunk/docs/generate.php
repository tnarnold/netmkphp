<?php

/**
 * Generates documentation with PhpDocumentor, if it is available.
 */

$target = getcwd() . DIRECTORY_SEPARATOR . 'Documentation';

if (!isset($_SERVER['PHP_PEAR_BIN_DIR'])) {
    die(
        "For this script to work, you need to have the PHP_PEAR_BIN_DIR
        environment variable registered"
    );
}

if (!is_file($_SERVER['PHP_PEAR_BIN_DIR'] . DIRECTORY_SEPARATOR . 'phpdoc')) {
    die('PhpDocumentor is not installed.');
}

if (!isset($_SERVER['PHP_PEAR_INSTALL_DIR'])
    || !is_dir(
        $sourceDir = $_SERVER['PHP_PEAR_INSTALL_DIR'] . DIRECTORY_SEPARATOR
        . 'Net' . DIRECTORY_SEPARATOR . 'RouterOS')
) {
    $sourceDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'src';
}

if (!isset($_SERVER['PHP_PEAR_DOC_DIR'])
    || !is_dir($docsDir = $_SERVER['PHP_PEAR_DOC_DIR'] . DIRECTORY_SEPARATOR
        . 'Net_RouterOS')
) {
    $docsDir = __DIR__;
}

if (!is_dir($examplesDir = $docsDir . DIRECTORY_SEPARATOR . 'examples')
) {
    $examplesDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'examples';
}

$phpDocBin = $_SERVER['PHP_PEAR_BIN_DIR'] . DIRECTORY_SEPARATOR . 'phpdoc';
$args = ' --examplesdir "' . $examplesDir
    . '" --directory "' . $sourceDir . ',' . $docsDir
    . '" --ignore "'
    . $target . DIRECTORY_SEPARATOR . '*,'
    . $examplesDir . DIRECTORY_SEPARATOR . '*,'
    . __FILE__
    . '" --target "' . $target
    . '" --title "Net_RouterOS Documentaion"'
    . ' --defaultcategoryname "Net" --defaultpackagename "Net_RouterOS"'
    . ' --undocumentedelements --sourcecode "off"'
    . ' --output "HTML:frames:default,HTML:frames:l0l33t,HTML:frames:phpdoc.de,HTML:frames:phphtmllib,HTML:frames:DOM/default,HTML:frames:DOM/l0l33t,HTML:frames:DOM/phpdoc.de,HTML:frames:phpedit,HTML:Smarty:default,HTML:Smarty:HandS,HTML:Smarty:PHP,PDF:default:default,XML:DocBook/peardoc2:default,CHM:default:default"';

system($phpDocBin . $args, $exitcode);
exit($exitcode);
?>

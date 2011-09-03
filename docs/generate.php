<?php

/**
 * Generates documentation with PhpDocumentor, if it is available.
 */
$target = getcwd() . DIRECTORY_SEPARATOR . 'Documentation';

//PhpDocumentor is not installable with Pyrus, so here goes PEAR detection
if (!isset($_SERVER['PHP_PEAR_BIN_DIR'])) {
    die(
        "For this script to work, you need to have the PHP_PEAR_BIN_DIR
        environment variable registered"
    );
}

if (!is_file($_SERVER['PHP_PEAR_BIN_DIR'] . DIRECTORY_SEPARATOR . 'phpdoc')) {
    die('PhpDocumentor is not installed.');
}

$pyrusConfigLocation
    = (defined('PHP_WINDOWS_VERSION_MAJOR') ? getenv('userprofile') . DIRECTORY_SEPARATOR
            : '~' . DIRECTORY_SEPARATOR . '.')
    . 'pear' . DIRECTORY_SEPARATOR . 'pearconfig.xml';

if (is_file($pyrusConfigLocation)) {
    $pyrusConfig = new DOMDocument();
    $pyrusConfig->load($pyrusConfigLocation);

    function searchFilename($filename, $include_path)
    {
        foreach(explode(PATH_SEPARATOR, $include_path) as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($path)) {
                return $path;
            }
        }
        die("Unable to find {$filename} in any of the directories in '{$include_path}'");
    }

    $userConfigLocation = searchFilename(
        '.configsnapshots', $pyrusConfig->getElementsByTagName('my_pear_path')
        ->item(0)->nodeValue
    );
    $userConfigSnapshotsLocation = scandir(
        $userConfigLocation, 1
    );
    $currentUserConfigLocation = $userConfigLocation . DIRECTORY_SEPARATOR .
        $userConfigSnapshotsLocation[0];
    $currentUserConfig = new DOMDocument();
    $currentUserConfig->load($currentUserConfigLocation);

    $sourceBaseDir = $currentUserConfig->getElementsByTagName('php_dir')
            ->item(0)->nodeValue;
    if (!is_dir($sourceDir = $sourceBaseDir . DIRECTORY_SEPARATOR . 'Net' . DIRECTORY_SEPARATOR . 'RouterOS')) {
        $sourceDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'src';
    }

    $docsBaseDir = $currentUserConfig->getElementsByTagName('doc_dir')
            ->item(0)->nodeValue;
    if (!is_dir($docsDir = $docsBaseDir . DIRECTORY_SEPARATOR . 'Net_RouterOS')) {
        $docsDir = __DIR__;
    }
    
    
    if (!is_dir($examplesDir = $docsDir . DIRECTORY_SEPARATOR . 'examples')) {
        $examplesDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'examples';
    }
} else {

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

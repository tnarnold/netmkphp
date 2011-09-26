<?php
$dependencyLocations = array();

$dependencyLocations[] = __DIR__ . DIRECTORY_SEPARATOR
    . '../../PEAR2_Net_Transmitter@sourceforge.net/trunk';

$extrafiles = array(
    new \PEAR2\Pyrus\Package(
        __DIR__ . DIRECTORY_SEPARATOR
        . '../../PEAR2_Net_Transmitter@sourceforge.net/trunk/package.xml'
    )
);

//$extrafiles = array();
//
//$oldCwd = getcwd();
//foreach ($dependencyLocations as $location) {
//    chdir($location);
//    foreach (
//        new RecursiveIteratorIterator(
//            new RecursiveDirectoryIterator(
//                'src',
//                RecursiveDirectoryIterator::UNIX_PATHS
//            ),
//            RecursiveIteratorIterator::LEAVES_ONLY
//        ) as $path) {
//            var_dump($path->getPathname());
//            $extrafiles[$path->getPathname()] = $path->getRealPath();
//    }
//}
//chdir($oldCwd);
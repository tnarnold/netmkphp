<?php
$dependencyLocations = array();

$dependencyLocations[]
    = __DIR__ . '../PEAR2_Net_Transmitter@sourceforge.net/trunk';

$extrafiles = array();

$oldCwd = getcwd();
foreach ($dependencyLocations as $location) {
    chdir($location);
    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                'src',
                RecursiveDirectoryIterator::UNIX_PATHS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        ) as $path) {
            $extrafiles[$path->getPathname()] = $path->getRealPath();
    }
}
chdir($oldCwd);
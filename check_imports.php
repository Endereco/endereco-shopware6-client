<?php 

require_once 'vendor/autoload.php';

function findAllPhpFiles($dir, &$allFiles = []) {
    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            findAllPhpFiles($path, $allFiles);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $allFiles[] = $path;
        }
    }

    return $allFiles;
}

function extractUseStatements($filePath) {
    $content = file_get_contents($filePath);
    preg_match_all('/^use\s+([\w\\\]+);/m', $content, $matches);
    return $matches[1];
}

function checkClassExistence($classes) {
    $missingClasses = [];
    foreach ($classes as $class) {
        if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
            $missingClasses[] = $class;
        }
    }
    return $missingClasses;
}

// Main process
$directoryToScan = 'src';
$phpFiles = findAllPhpFiles($directoryToScan);
$allClasses = [];

foreach ($phpFiles as $file) {
    $classes = extractUseStatements($file);
    $allClasses = array_merge($allClasses, $classes);
}

$allClasses = array_unique($allClasses);
$missingClasses = checkClassExistence($allClasses);

if (!empty($missingClasses)) {
    echo "\nMissing classes:\n";
    print_r($missingClasses);
    exit(1);
} else {
    echo "\nAll classes are accounted for.\n";
    exit(0);
}
?>

<?php

$root = "../";
$extractDir = "../composer";

if (file_exists($root . 'vendor')) {
    echo "Composer already installed, redirect to index...";
    header('Location: /');
    exit();
}

if (file_exists($extractDir . '/vendor/autoload.php') === true) {
    echo "Extracted autoload already exists. Skipping phar extraction as presumably it's already extracted.";
} else {
    $composerPhar = $root . 'composer.phar';
    if (!file_exists($composerPhar)) {
        $composer = file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
        file_put_contents($composerPhar, $composer);
    }
    $composerExtract = new Phar($composerPhar);
    $composerExtract->extractTo($extractDir);
}

require_once($extractDir . '/vendor/autoload.php');

chdir('../');

//Use the Composer classes
use Composer\Console\Application;
use Composer\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArrayInput as ArrayInput;

$input = new ArrayInput(array('command' => 'update'));
$application = new Application();
$application->setAutoExit(false);
echo $application->run($input);

echo "Composer successfully installed, redirect to index...";
header('Location: /');
exit();

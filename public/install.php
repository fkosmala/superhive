<?php

declare(strict_types=1);

$root = '../';
$extractDir = '../composer';

if (file_exists($root . 'vendor')) {
    echo 'Composer already installed, redirect to index...';
    header('Location: /');
    exit;
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

require $extractDir . '/vendor/autoload.php';

chdir('../');

//Use the Composer classes
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

$input = new ArrayInput(['command' => 'install']);
$application = new Application();
$application->setAutoExit(false);
$application->run($input);

header('Location: /');
exit;

<?php
define('ROOT', "../");
define('EXTRACT_DIRECTORY', "../composer");

if(file_exists(ROOT.'vendor')) {
  return header("Refresh:0; url=/");
}

if (file_exists(EXTRACT_DIRECTORY.'/vendor/autoload.php') == true) {
  echo "Extracted autoload already exists. Skipping phar extraction as presumably it's already extracted.";
}
else{
  $composerPhar = ROOT.'composer.phar';
  if (!file_exists($composerPhar)) {
    $composer = file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
    file_put_contents($composerPhar, $composer);
  }
  $composerExtract = new Phar($composerPhar);
  $composerExtract->extractTo(EXTRACT_DIRECTORY);
}

require_once (EXTRACT_DIRECTORY.'/vendor/autoload.php');

//Use the Composer classes
use Composer\Console\Application;
use Composer\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;

chdir('../');

$input = new ArrayInput(array('command' => 'update'));
$application = new Application();
$application->setAutoExit(false);
echo $application->run($input);
return header("Refresh:0; url=/");

?>

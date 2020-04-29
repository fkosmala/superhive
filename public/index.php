<?php
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Set Twig view in container
$container->set('view', function() {
    $twig = Twig::create(__DIR__ . "/../views/", ['cache' => false]);
    return $twig;
});

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

// Let's go for define the route
$app->get('/', function ($request, $response, $args) {

  global $settings;

  // Set data from config file to create query
  $query = '{"jsonrpc":"2.0","method":"condenser_api.get_discussions_by_blog","params":[{"tag":"'.$settings['author'].'","limit":10}],"id":0}';

  // The file with the latest posts.
  $file = __DIR__ . '/../blog.json';

  // if the JSON file doesn't exist, take it from API
  if (!file_exists($file)) {
    // Go take articles from Hive api
    $ch = curl_init($settings['api']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    file_put_contents($file, $result);
  } else{
    if ($settings['cron'] == false) {
      if (time()-filemtime($file) > 4 * 3600) {
        file_put_contents($file, '');
        $ch = curl_init($settings['api']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        file_put_contents($file, $result);
      }
    }
  }

  $blog = json_decode(file_get_contents($file), true);
  $articles = $blog['result'];
  return $this->get('view')->render($response, 'default/index.html', [
      'articles' => $articles,
      'settings' => $settings
  ]);
})->setName('index');

// Run app
$app->run();

<?php

namespace App;

use App\Controllers\HomeController;
use App\Controllers\AdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Tuupola\Middleware\HttpBasicAuthentication as BasicAuth;

require __DIR__ . '/../vendor/autoload.php';

// Create the container
$container = new Container();
// Set all dirs & files paths
$container->set('basedir', __DIR__ . '/../');
$container->set('themesdir', __DIR__ . '/../public/themes/');
$container->set('commentsdir', __DIR__ . '/../app/comments/');
$container->set('pagesdir', __DIR__ . '/../pages/');
$container->set('configfile', __DIR__ . '/../config.json');
$container->set('blogfile', __DIR__ . '/../app/blog.json');
$container->set('password', __DIR__ . '/../password');
// Set settings array in container for use in all routes
$container->set('settings', function() {
	$config = file_get_contents(__DIR__ . '/../config.json');
  $settings = json_decode($config, true);
  return $settings;
});
// Set Twig engine for templating
$container->set('view', function() {
    $twig = Twig::create(
			[
				__DIR__ . "/../app/views/",
				__DIR__ . "/../pages/",
				__DIR__ . "/../public/themes/"
			],
			[
				'cache' => false
			]
		);
    return $twig;
});

// Set container in App factory
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(
	TwigMiddleware::createFromContainer($app)
);

// Check if password file exist or create a random one for initialize the installation script
if (!file_exists($container->get('password'))) {
  $user = substr(md5(microtime()),rand(0,26),5);
  $passwd = substr(md5(microtime()),rand(0,26),5);
  $cred = array($user => $passwd);
} else {
  $cred = unserialize(file_get_contents($container->get('password')));
}

// Add Basic Auth for admin panel
$app->add(
	new BasicAuth([
    "path" => "/admin",
    "secure" => true,
    "realm" => "SuperHive Protected Area",
    "relaxed" => ["localhost"],
    "users" => $cred
   ]
 )
);

// Normal user routes
$app->get('/', HomeController::class . ":index")->setName('index');
$app->post('/', HomeController::class . ":install")->setName('install');
$app->get('/post/{permlink}', HomeController::class . ":post")->setName('post');
$app->get('/feed', HomeController::class . ":feed")->setName('feed');
$app->get('/sitemap', HomeController::class . ":sitemap")->setName('sitemap');

// Admin routes
$app->get('/admin', AdminController::class . ":adminIndex")->setName('admin');
$app->post('/admin/save', AdminController::class . ":save")->setName('save');

// generate routes from static pages
$pagesDir = $container->get('pagesdir');
$pages = preg_grep('~\.(html)$~', scandir($pagesDir));

foreach ($pages as $page) {
	$route = substr($page, 0, strrpos($page, "."));
	$app->get('/'.$route, function ($request, $response) {
		$settings = $this->get('settings');
		$theme = $settings['theme'].'/layout.html';
		$uri = $request->getUri();
		$route = substr(strrchr($uri, "/"), 1);
		return $this->get('view')->render($response, $route.'.html', [
			"settings"=>$settings,
			"theme"=>$theme
		]);
	})->setName($route);

}

return $app;

<?php

namespace App;

use App\Controllers\HomeController;
use App\Controllers\AdminController;
use App\Controllers\PagesController;
use App\Controllers\PostsController;
use App\Controllers\WalletController;
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
$container->set('cachedir', __DIR__ . '/../cache/');
$container->set('datadir', __DIR__ . '/../data/');
$container->set('themesdir', __DIR__ . '/../public/themes/');
$container->set('commentsdir', __DIR__ . '/../data/comments/');
$container->set('pagesdir', __DIR__ . '/../pages/');
$container->set('configfile', __DIR__ . '/../config.json');
$container->set('blogfile', __DIR__ . '/../data/blog.json');
$container->set('accountfile', __DIR__ . '/../data/account.json');
$container->set('password', __DIR__ . '/../password');

// Set settings array in container for use in all routes
$container->set('settings', function() {
	$config = file_get_contents(__DIR__ . '/../config.json');
  $settings = json_decode($config, true);
  return $settings;
});

$settings = $container->get('settings');

// Rename config.sample json to config.json
if ((file_exists($container->get('basedir').'config.sample.json')) && (!file_exists($container->get('basedir').'config.json'))) {
	rename($container->get('basedir').'config.sample.json', $container->get('basedir').'config.json');
}

// Create folders that doesn't exist
// Pages Dir
if (!file_exists($container->get('pagesdir'))) {
	mkdir($container->get('pagesdir'), 0755, true);
}

// Data dir (to store blockchain data)
if (!file_exists($container->get('datadir'))) {
	mkdir($container->get('datadir'), 0755, true);
}

// Comments
if (!file_exists($container->get('commentsdir'))) {
	mkdir($container->get('commentsdir'), 0755, true);
}

// Create Cache dir only in Production mode
if ((!file_exists($container->get('cachedir'))) && ($settings["devMode"] == false)) {
	mkdir($container->get('cachedir'), 0755, true);
} else {
	// Flush Cache folder if disabled
	if ((file_exists($container->get('cachedir'))) && ($settings["devMode"] == true )) {
		function removeDirectory($path) {
			$files = glob($path . '/*');
			foreach ($files as $file) {
				is_dir($file) ? removeDirectory($file) : unlink($file);
			}
			rmdir($path);
			return;
		}
		removeDirectory($container->get('cachedir'));
	}
}

// Set container in App factory
AppFactory::setContainer($container);

// Set Twig engine for templating
$container->set('view', function() {
	$settings = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
	$tpls = [
		__DIR__ . "/../app/views/",
		__DIR__ . "/../pages/",
		__DIR__ . "/../public/themes/"
	];
	// Disable Cache on DevMode
	if ($settings['devMode'] == true ) {
    $twig = Twig::create(
			$tpls,
			[
				'cache' => false
			]
		);
    return $twig;
   } else {
   	$twig = Twig::create(
			$tpls,
			[
				'cache' => __DIR__ . '/../cache/'
			]
		);
    return $twig;
   }
});

//Set Session engine
$container->set('session', function () {
  return new \SlimSession\Helper();
});

// Create App
$app = AppFactory::create();
$app->add(TwigMiddleware::createFromContainer($app));

// Add Error Middleware on DevMode
if ($settings['devMode'] == true ) {
	$app->addErrorMiddleware(true, false, false);
}

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
	new \Slim\Middleware\Session([
    'name' => 'sh_session',
    'autorefresh' => true,
    'lifetime' => '1 hour',
  ])
);

// Global routes
$app->get('/', HomeController::class . ":index")->setName('index');
$app->post('/', HomeController::class . ":install")->setName('install');
$app->get('/feed', HomeController::class . ":feed")->setName('feed');
$app->get('/sitemap', HomeController::class . ":sitemap")->setName('sitemap');
$app->get('/about', HomeController::class . ":about")->setName('about');
$app->get('/login', HomeController::class . ":login")->setName('login');
$app->post('/login', HomeController::class . ":loginPost")->setName('login-post');

// Admin routes
$app->get('/admin', AdminController::class . ":adminIndex")->setName('admin');
$app->get('/admin/social', AdminController::class . ":adminSocial")->setName('admin-social');
$app->post('/admin/save', AdminController::class . ":save")->setName('admin-save');
$app->get('/admin/wallet', WalletController::class . ":viewWallet")->setName('admin-wallet');
$app->get('/admin/logout', AdminController::class . ":logout")->setName('admin-logout');

// Posts routes
$app->get('/post/{permlink}', PostsController::class . ":post")->setName('post');

$app->get('/admin/posts', PostsController::class . ":adminPosts")->setName('admin-posts');
$app->get('/admin/newpost', PostsController::class . ":adminNewPost")->setName('admin-newpost');
$app->get('/admin/editpost/{post}', PostsController::class . ":adminEditPost")->setName('admin-editpost');

// Other Admin Pages
$app->get('/admin/pages', PagesController::class . ":adminPages")->setName('admin-pages');
$app->get('/admin/newpage', PagesController::class . ":adminNewPage")->setName('admin-newpage');
$app->get('/admin/editpage/{file}', PagesController::class . ":adminEditPage")->setName('admin-editpage');
$app->get('/admin/delpage/{file}', PagesController::class . ":adminDelPage")->setName('admin-delpage');
$app->post('/admin/savepage', PagesController::class . ":adminSavePage")->setName('admin-savepage');


// generate routes from static pages
$pagesDir = $container->get('pagesdir');
$pages = preg_grep('~\.(html)$~', scandir($pagesDir));

foreach ($pages as $page) {
	$route = substr($page, 0, strrpos($page, "."));
	$app->get('/'.$route, function ($request, $response) {
		$settings = $this->get('settings');
		$uri = $request->getUri();
		$route = substr(strrchr($uri, "/"), 1);
		return $this->get('view')->render($response, $route.'.html', [
			"settings"=>$settings
		]);
	})->setName($route);

}

return $app;

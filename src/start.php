<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AdminController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Controllers\PagesController;
use App\Controllers\PostsController;
use App\Controllers\WalletController;
use DI\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware as Whoops;

require __DIR__ . '/../vendor/autoload.php';

// Create the container
$container = new Container();
// Set all dirs & files paths
$container->set('basedir', __DIR__ . '/../');
$container->set('cachedir', __DIR__ . '/../cache/');

$container->set('configdir', __DIR__ . '/../config/');
$container->set('configfile', __DIR__ . '/../config/config.json');
$container->set('password', __DIR__ . '/../config/password');

$container->set('pluginsdir', __DIR__ . '/../resources/plugins/');
$container->set('datadir', __DIR__ . '/../resources/blog/');
$container->set('accountfile', __DIR__ . '/../resources/blog/account.json');
$container->set('blogfile', __DIR__ . '/../resources/blog/blog.json');
$container->set('commentsdir', __DIR__ . '/../resources/blog/comments/');
$container->set('pagesdir', __DIR__ . '/../resources/blog/pages/');

$container->set('themesdir', __DIR__ . '/../public/themes/');

// Set settings array in container for use in all routes
$container->set('settings', static function () {
    $config = file_get_contents(__DIR__ . '/../config/config.json');
    return json_decode($config, true);
});

$settings = $container->get('settings');

// Rename config.sample.json to config.json
$confDir = $container->get('configdir');
if ((file_exists($confDir . 'config.sample.json')) && (! file_exists($confDir . 'config.json'))) {
    rename($confDir . 'config.sample.json', $confDir . 'config.json');
}

// Create folders that doesn't exist

// Plugins Dir
if (! file_exists($container->get('pluginsdir'))) {
    mkdir($container->get('pluginsdir'), 0755, true);
}

// Pages Dir
if (! file_exists($container->get('pagesdir'))) {
    mkdir($container->get('pagesdir'), 0755, true);
}

// Data dir (to store blockchain data)
if (! file_exists($container->get('datadir'))) {
    mkdir($container->get('datadir'), 0755, true);
}

// Comments
if (! file_exists($container->get('commentsdir'))) {
    mkdir($container->get('commentsdir'), 0755, true);
}

// Create Cache dir only in Production mode
if ((! file_exists($container->get('cachedir'))) && ($settings['devMode'] === false)) {
    mkdir($container->get('cachedir'), 0755, true);
} else {
    // Flush Cache folder if disabled
    if (file_exists($container->get('cachedir')) && ($settings['devMode'] === true)) {
        $path = $container->get('cachedir');

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($path);
    }
}

// Set container in App factory
AppFactory::setContainer($container);

// Set Twig engine for templating
$container->set('view', static function () {
    $settings = json_decode(file_get_contents(__DIR__ . '/../config/config.json'), true);
    $tpls = [
        __DIR__ . '/../resources/views/',
        __DIR__ . '/../resources/blog/pages/',
        __DIR__ . '/../public/themes/',
    ];
    // Disable Cache on DevMode
    if ($settings['devMode'] === true) {
        return Twig::create(
            $tpls,
            [
                'cache' => false,
            ]
        );
    }
    return Twig::create(
        $tpls,
        [
            'cache' => __DIR__ . '/../cache/',
        ]
    );
});

//Set Session engine
$container->set('session', static function () {
    return new \SlimSession\Helper();
});

// Create App
$app = AppFactory::create();
$app->add(TwigMiddleware::createFromContainer($app));

// Add Error Middleware on DevMode
if ($settings['devMode'] === true) {
    $app->add(new Whoops());
    $minify = false;
} else {
    $minify = true;
}

// Add Basic Auth for admin panel
$app->add(
    new \Slim\Middleware\Session([
        'name' => 'sh_session',
        'autorefresh' => true,
        'lifetime' => '1 hour',
    ])
);

//Check if password file exists
$link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}";
$actualLink = $link . "{$_SERVER['REQUEST_URI']}";
$installLink = $link . '/prepare';

if ((! file_exists($container->get('password'))) && ($actualLink !== $installLink)) {
    header('Location: ' . $installLink);
    exit;
}

// Install routes
$app->get('/prepare', InstallController::class . ':prepare')->setName('prepare');
$app->post('/prepare', InstallController::class . ':install')->setName('install');

// Global routes
$app->get('/', HomeController::class . ':index')->setName('index')->add(new \Slim\Middleware\Minify($minify));
$app->post('/search', HomeController::class . ':search')->setName('search')->add(new \Slim\Middleware\Minify($minify));
$app->get('/about', HomeController::class . ':about')->setName('about')->add(new \Slim\Middleware\Minify($minify));
$app->get('/tag/{tag}', PostsController::class . ':tag')->setName('tag')->add(new \Slim\Middleware\Minify($minify));
$app->get('/post/{permlink}', PostsController::class . ':post')->setName('post')
    ->add(new \Slim\Middleware\Minify($minify));

// SEO routes
$app->get('/feed', HomeController::class . ':feed')->setName('feed');
$app->get('/sitemap', HomeController::class . ':sitemap')->setName('sitemap');

// Admin routes
$app->get('/login', HomeController::class . ':login')->setName('login');
$app->post('/login', HomeController::class . ':loginPost')->setName('login-post');
$app->group('/admin', function (RouteCollectorProxy $group): void {
    $group->get('', AdminController::class . ':adminIndex')->setName('admin');

    $group->get('/settings', AdminController::class . ':adminSettings')->setName('admin-settings');
    $group->get('/themes', AdminController::class . ':adminThemes')->setName('admin-themes');

    $group->get('/wallet', WalletController::class . ':viewWallet')->setName('admin-wallet');
    $group->get('/logout', AdminController::class . ':logout')->setName('admin-logout');

    $group->get('/posts', PostsController::class . ':adminPosts')->setName('admin-posts');
    $group->get('/newpost', PostsController::class . ':adminNewPost')->setName('admin-newpost');
    $group->get('/editpost/{post}', PostsController::class . ':adminEditPost')->setName('admin-editpost');

    $group->get('/pages', PagesController::class . ':adminPages')->setName('admin-pages');
    $group->get('/newpage', PagesController::class . ':adminNewPage')->setName('admin-newpage');
    $group->get('/editpage/{file}', PagesController::class . ':adminEditPage')->setName('admin-editpage');
    $group->get('/delpage/{file}', PagesController::class . ':adminDelPage')->setName('admin-delpage');
    $group->post('/savepage', PagesController::class . ':adminSavePage')->setName('admin-savepage');

    $group->get('/savetheme/{theme}', AdminController::class . ':saveTheme')->setName('admin-savetheme');
    $group->post('/save', AdminController::class . ':save')->setName('admin-save');
});

// generate routes from static pages
$app->group('/pages/', function (RouteCollectorProxy $group): void {
    $pagesDir = $this->get('pagesdir');
    $pages = preg_grep('~\.(html)$~', scandir($pagesDir));
    foreach ($pages as $page) {
        $route = substr($page, 0, strrpos($page, '.'));
        $group->get('' . $route, function ($request, $response) {
            $settings = $this->get('settings');
            $uri = $request->getUri();
            $route = substr(strrchr($uri, '/'), 1);
            return $this->get('view')->render($response, $route . '.html', [
                'settings' => $settings,
            ]);
        })->setName($route);
    }
});

return $app;

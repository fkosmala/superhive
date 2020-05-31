<?php
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Create Container
$container = new Container();
$container->set('password', __DIR__ . '/../password');
AppFactory::setContainer($container);

// Set Twig view in container
$container->set('view', function() {
    $twig = Twig::create([__DIR__ . "/../views/", __DIR__ . "/../public/themes/"], ['cache' => false]);
    return $twig;
});

// Settings in container for use in all routes
$container->set('settings', function() {
	$config = file_get_contents(__DIR__ . '/../config.json');
  $settings = json_decode($config, true);
  return $settings;
});

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

// Check if password file exist or create a random one for initialize the installation script
if (!file_exists(__DIR__ . '/../password')) {
  $user = substr(md5(microtime()),rand(0,26),5);
  $passwd = substr(md5(microtime()),rand(0,26),5);
  $cred = array($user => $passwd);
} else {
  $cred = unserialize(file_get_contents(__DIR__ . '/../password'));
}

// Add Basic Auth for admin panel
$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/admin",
    "secure" => true,
    "realm" => "SuperHive Protected Area",
    "relaxed" => ["localhost"],
    "users" => $cred
]));

// Post the username/password in the first time
$app->post('/', function ($request, $response, $args) {
  $data = $request->getParsedBody();
  $passwd = password_hash($data['passwd'], PASSWORD_BCRYPT, ['cost' => 10]);
  $cred = array($data['username'] => $passwd);
  if (!file_exists(__DIR__ . '/../password')) {
    file_put_contents(__DIR__ . '/../password', serialize($cred));
    return $response->withHeader('Location', '/admin')->withStatus(302);
  } else return $response->withHeader('Location', '/')->withStatus(302);
})->setName('install');

/* Index route */
$app->get('/', function ($request, $response, $args) {
	$settings = $this->get('settings');

  //Check if password file exists
  if (!file_exists($this->get('password'))) {
    return $this->get('view')->render($response, '/install.html');
  }

  // Set data from config file to create query
  $query = '{
		"jsonrpc":"2.0",
		"method":"condenser_api.get_discussions_by_blog",
		"params":[{"tag":"'.$settings['author'].'","limit":10}],
		"id":0
	}';

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

	// Get the JSON 
  $blog = json_decode(file_get_contents($file), true);
  $articles = $blog['result'];
  
  // Return view with articles
  return $this->get('view')->render($response, $settings['theme'].'/index.html', [
      'articles' => $articles,
      'settings' => $settings
  ]);
})->setName('index');

/* View one article */
$app->get('/post/{permlink}', function ($request, $response, $args) {	
	$settings = $this->get('settings');
	
	if (isset($args['permlink'])) {
		$permlink = $args['permlink'];
		
		// Check if comments exists for this post
		$comments = __DIR__ . '/../comments/'.$permlink;
		if (!file_exists($comments) || file_exists($comments) && time()-filemtime($comments) > 2 * 3600) {
		  $query = '{
				"jsonrpc":"2.0",
				"method":"condenser_api.get_content_replies",
				"params":[
					"'.$settings['author'].'", 
					"'.$permlink.'"
				], 
				"id":1
			}';
			$ch = curl_init($settings['api']);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close($ch);
			file_put_contents($comments, $result);
		}
		$replies = json_decode(file_get_contents($comments), true);
		$replies = $replies['result'];

		$file = __DIR__ . '/../blog.json';
		$blog = json_decode(file_get_contents($file), true);
		$articles = $blog['result'];
		foreach($articles AS $index=>$article) {
        if($article['permlink'] == $permlink) {
          return $this->get('view')->render($response, $settings['theme'].'/post.html', [
						'settings' => $settings,
						'article' => $article,
						'replies' => $replies
					]);
        }
    }
		
	}
})->setName('post');

/* Admin panel */
$app->get('/admin', function ($request, $response, $args) {
  // Create array from config file
  $settings = $this->get('settings');
  $themes = array_map('basename', glob(__DIR__ . '/themes/*' , GLOB_ONLYDIR));
  return $this->get('view')->render($response, '/admin.html', [
      'settings' => $settings,
      'themes' => $themes
  ]);
})->setName('admin');

$app->post('/admin/save', function ($request, $response, $args) {
  $data = $request->getParsedBody();
  $crosspost = (!isset($data["crosspost"])) ? false : true;
  $cron = (!isset($data["cron"])) ? false : true;
  $settings = array(
    'author' => $data["author"],
    'title' => $data["title"],
    'baseline' => $data["baseline"],
    'nextbutton' => $data["nextbutton"],
    'social' => array(
      'description' => $data["socialDesc"],
      'image' => $data["socialImage"],
      'twittername' => $data["twittername"]
    ),
    'theme' => $data["theme"],
    'crosspost' => $crosspost,
    'api' => $data["api"],
    'cron' => $cron
  );
  $file = json_encode($settings, JSON_PRETTY_PRINT);
  // Create array from config file
  file_put_contents(__DIR__ . '/../config.json', $file);
  unlink(__DIR__ . '/../blog.json');

  return $response->withHeader('Location', '/')->withStatus(302);
})->setName('save');

$app->get('/feed', function ($request, $response, $args) {
	$settings = $this->get('settings');

	$file = __DIR__ . '/../blog.json';
	$blog = json_decode(file_get_contents($file), true);
  $articles = $blog['result'];

  header('Content-Type: text/xml');
	return $this->get('view')->render($response, '/feed.html', [
      'articles' => $articles,
      'settings' => $settings,
      'base_url' => $url
  ]);
})->setName('feed');

// Run app
$app->run();

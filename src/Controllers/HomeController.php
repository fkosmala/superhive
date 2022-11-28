<?php

namespace App\Controllers;

use \DI\Container;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Container\ContainerInterface;
use \Slim\Factory\AppFactory;

use DragosRoua\PHPHiveTools\HiveApi as HiveApi;
use League\CommonMark\CommonMarkConverter;

final class HomeController
{

	private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    public function index(Request $request, Response $response) : Response {
		$settings = $this->app->get('settings');

		//Check if password file exists
		if (!file_exists($this->app->get('password'))) {
			$requirements = array();

			// Check if PHP version is ok tu run SuperHive
			if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
			$req = [
					"status" => "success",
					"message" => "Your PHP version can run SuperHive."
				];
			} else {
				$req = [
					"status" => "error",
					"message" => "Please, update your PHP version to run SuperHive. (PHP 7.4 minimum)"
				];
			}
			$requirements[] = $req;

			// Check if data folder is writeable
			$datadir = $this->app->get('datadir');
			if (is_writable($datadir)) {
				$req = [
					"status" => "success",
					"message" => "The data folder is writable to store blockchain data."
				];
			} else {
				$req = [
					"status" => "error",
					"message" => "Make the data folder writable."
				];
			}
			$requirements[] = $req;

			return $this->app->get('view')->render($response, '/install.html', [
				'requirements' => $requirements
			]);
		}

		// Hive API communication init
		$apiConfig = ["webservice_url" => $settings['api'], "debug" => false];
		$api = new HiveApi($apiConfig);
		
		// The file with the latest posts.
		$file = $this->app->get('blogfile');
		
		// if the JSON file doesn't exist or if it's old, take it from API
		if (!file_exists($file) || (time()-filemtime($file) > 120)) {
			// Prepare API call according to displayed posts type
			$displayType = $settings['displayType']['type'];
			if ($displayType === 'author') {
				$dateNow = (new \DateTime())->format('Y-m-d\TH:i:s');
				$params = [$settings['author'], "", $dateNow, 100];
				$result = json_encode($api->getDiscussionsByAuthorBeforeDate($params), JSON_PRETTY_PRINT);
			} elseif (($displayType === 'tag')) {
				$displayTag = $settings['displayType']['tag'];
				$taggedPosts = array();
				$params = [$settings['author'], "", "", 100];
				$allPosts = json_encode($api->getDiscussionsByAuthorBeforeDate($params));
				$allPosts = json_decode($allPosts, true);
				//print_r($allPosts);
				foreach ($allPosts as &$post) {
					//$postTags = json_encode($post['json_metadata'], JSON_PRETTY_PRINT);
					$postMeta = json_decode($post['json_metadata'], true);
					$postTags = $postMeta['tags'];
					if(in_array($displayTag, $postTags)) {
						$taggedPosts[] = $post;
					}
				}
				
				$result = json_encode($taggedPosts, JSON_PRETTY_PRINT);
			} elseif ($displayType === 'reblog') {
				$params = [["tag" => $settings['author'],"limit" => 100, "truncate_body" => 0]];
				$result = json_encode($api->getDiscussionsByBlog($params), JSON_PRETTY_PRINT);
			}  elseif (strpos($settings['author'], "hive-") === 0) {
				$params = [["tag" => $settings['author'],"limit" => 100]];
				$result = json_encode($api->getDiscussionsByCreated($params), JSON_PRETTY_PRINT);
			}
			file_put_contents($file, $result);
			unset($taggedPosts);
		}

		// Get the JSON
		$articles = json_decode(file_get_contents($file), true);
		
		//Get ready to parse the mardown
		$converter = new CommonMarkConverter();
		$parsedPosts = array();
		
		foreach($articles as &$article){
			// Create HTML from Markdown
			$article['body'] = $converter->convert($article['body']);
			$tags= '';
			
			//Get featured image
			$meta = json_decode($article['json_metadata'], true);
			
			$tags.= implode(",", $meta['tags']).',';
			if ((isset($meta['image'])) && (!empty($meta['image']))) {
				$featured = $meta['image'][0];
			} else $featured = '/themes/'.$settings['theme'].'/no-img.png';
			$article['featured'] = $featured;
			
			$parsedPosts[] = $article;
		}
		
		$tags = explode(',',$tags);
		$tagsArray = array_count_values($tags);
		array_multisort($tagsArray, SORT_DESC);
		$mostUsedTags = array_slice($tagsArray, 0, 15);

		// Return view with articles
		return $this->app->get('view')->render($response, $settings['theme'].'/index.html', [
				'articles' => $parsedPosts,
				'tags' => $mostUsedTags,
				'settings' => $settings
		]);
	}
	
	public function search(Request $request, Response $response, $args) : Response {
		$data = $request->getParsedBody();
		$term = $data['term'];
		
		$settings = $this->app->get('settings');
		
		if ($term == '') {
			$result = [];
		} else {
			$file = $this->app->get('blogfile');
			$articles = json_decode(file_get_contents($file), true);
			
			$matches = array();
			
			foreach ($articles as $article) {
				// Check in Title
				if(preg_match("/\b$term\b/i", $article['title'])) {
					$matches[] = $article['title'];
				}
				
				// Check in Body
				if(preg_match("/\b$term\b/i", $article['body'])) {
					$matches[] = $article['title'];
				}
				
				//Check in Tags
				$metadata = json_decode($article['json_metadata'], true);
				$tags = implode(",",$metadata['tags']);
				if(preg_match("/\b$term\b/i", $tags)) {
					$matches[] = $article['title'];
				}
			}
			$result = array_unique($matches);
			
			$posts = array();
			
			foreach ($articles as $article) {
				if (in_array($article['title'], $result)) {
					$posts[] = $article;
				}
			}
		}

		return $this->app->get('view')->render($response, $settings['theme'].'/search.html', [
			'term' => $term,
			'posts' => $posts,
			'settings' => $settings
		]);
	}

	public function install(Request $request, Response $response, $args) : Response {
		if (!file_exists($this->app->get('password'))) {
			$data = $request->getParsedBody();
            // Create password file with  username and password (signed msg))
			$cred = array($data['username'] => $data['passwd']);
			file_put_contents($this->app->get('password'), serialize($cred));
            
            // Changeaccount name in config file
            $settings = $this->app->get('settings');
            $settings['author'] = $data['username'];
            $file = json_encode($settings, JSON_PRETTY_PRINT);
            file_put_contents($this->app->get('configfile'), $file);
            
            $response->getBody()->write('ok');
		} else {
			$response->getBody()->write('notok');
		}
        return $response;
	}
	
	public function login(Request $request, Response $response, $args) : Response {
		$settings = $this->app->get('settings');
		$session = $this->app->get('session');
		
		return $this->app->get('view')->render($response, 'login.html', [
			'settings' => $settings
		]);
	}
	
	public function loginPost(Request $request, Response $response, $args) : Response {
		$settings = $this->app->get('settings');
		$session = $this->app->get('session');
		$data = $request->getParsedBody();
		$author = $settings['author'];
		
		if ($author != $data['username']) {
			$session::destroy();
			$msg = "Not Ok";
		} else {
			$cred = unserialize(file_get_contents($this->app->get('password')));
			$passwd = $cred[$author];
			if ($data['passwd'] == $passwd) {
				$session['sh_author'] = $author;
				$session['sh_sign'] = $passwd;
				$msg = "OK";
			} else {
				$session::destroy();
				$msg = "Not Ok";
			}	
		}
		$response->getBody()->write($msg);
		return $response;
	}

	public function feed(Request $request, Response $response) : Response {
		$settings = $this->app->get('settings');

		$file = $this->app->get('blogfile');
		$articles = json_decode(file_get_contents($file), true);

		header('Content-Type: text/xml');
		return $this->app->get('view')->render($response, '/feed.xml', [
			'articles' => $articles,
			'settings' => $settings
		]);
	}

	public function sitemap(Request $request, Response $response) : Response {
		$settings = $this->app->get('settings');

		$file = $this->app->get('blogfile');
		$articles = json_decode(file_get_contents($file), true);

		header('Content-Type: text/xml');
		return $this->app->get('view')->render($response, '/sitemap.xml', [
			'articles' => $articles,
			'settings' => $settings
		]);
	}
	
	public function about(Request $request, Response $response) : Response {
		$settings = $this->app->get('settings');
		$accountFile = $this->app->get('accountfile');
		$account = json_decode(file_get_contents($accountFile), true);
		
		$accountBio = json_decode($account[0]['posting_json_metadata'], true);
		
		return $this->app->get('view')->render($response, $settings['theme'].'/about.html', [
				'settings' => $settings,
				'account' => $accountBio['profile']
		]);
	}
}

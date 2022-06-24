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
			if (!file_exists($file) || (time()-filemtime($file) > 600)) {
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

		public function install(Request $request, Response $response, $args) : Response {
			if (!file_exists($this->app->get('password'))) {
				$data = $request->getParsedBody();
				$passwd = password_hash($data['passwd'], PASSWORD_BCRYPT, ['cost' => 10]);
				$cred = array($data['username'] => $passwd);
				file_put_contents($this->app->get('password'), serialize($cred));
				return $response->withHeader('Location', '/admin')->withStatus(302);
			} else {
				return $response->withHeader('Location', '/')->withStatus(302);
			}
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
}

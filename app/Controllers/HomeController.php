<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;

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
				return $this->app->get('view')->render($response, '/install.html');
			}

			// Set data from config file to create query
			$query = '{
				"jsonrpc":"2.0",
				"method":"condenser_api.get_discussions_by_blog",
				"params":[{"tag":"'.$settings['author'].'","limit":10}],
				"id":0
			}';

			// The file with the latest posts.
			$file = $this->app->get('blogfile');

			// if the JSON file doesn't exist, take it from API
			if (!file_exists($file)) {
				// Go take articles from Hive api
				$ch = curl_init($settings['api']);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$result = curl_exec($ch);
				curl_close($ch);
				file_put_contents($file, $result);
			} else {
				if ($settings['cron'] == false) {
					if (time()-filemtime($file) > 1 * 3600) {
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
			return $this->app->get('view')->render($response, $settings['theme'].'/index.html', [
					'articles' => $articles,
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
		
		public function post(Request $request, Response $response, $args) : Response {
			$settings = $this->app->get('settings');
	
			if (isset($args['permlink'])) {
				$permlink = $args['permlink'];
				
				// Check if comments exists for this post
				$comments = $this->app->get('commentsdir').$permlink.'.comments';
				if ((!file_exists($comments)) || (file_exists($comments)) && (time()-filemtime($comments) > 1 * 3600)) {
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

				$file = $this->app->get('blogfile');
				$blog = json_decode(file_get_contents($file), true);
				$articles = $blog['result'];
				foreach($articles AS $index=>$article) {
					if($article['permlink'] == $permlink) {
						return $this->app->get('view')->render($response, $settings['theme'].'/post.html', [
							'settings' => $settings,
							'article' => $article,
							'replies' => $replies
						]);
					}
				}
				
			}
		}
		
		public function feed(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');

			$file = $this->app->get('blogfile');
			$blog = json_decode(file_get_contents($file), true);
			$articles = $blog['result'];

			header('Content-Type: text/xml');
			return $this->app->get('view')->render($response, '/feed.xml', [
				'articles' => $articles,
				'settings' => $settings
			]);
		}
		
		public function sitemap(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');

			$file = $this->app->get('blogfile');
			$blog = json_decode(file_get_contents($file), true);
			$articles = $blog['result'];

			header('Content-Type: text/xml');
			return $this->app->get('view')->render($response, '/sitemap.xml', [
				'articles' => $articles,
				'settings' => $settings
			]);
		}
}

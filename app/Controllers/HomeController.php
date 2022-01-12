<?php

namespace App\Controllers;

use \DI\Container;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Container\ContainerInterface;
use \Slim\Factory\AppFactory;

use DragosRoua\PHPHiveTools\HiveApi as HiveApi;

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

			$apiConfig = ["webservice_url" => $settings['api'], "debug" => false];

			$api = new HiveApi($apiConfig);

			// If Author start with "hive-", it's a community account
			if (strpos($settings['author'], "hive-") === 0) {
				$params = [["tag" => $settings['author'],"limit" => 100]];
			} else {
				$params = [$settings['author'], "", "", 100];
			}

			// The file with the latest posts.
			$file = $this->app->get('blogfile');

			// if the JSON file doesn't exist, take it from API
			if (!file_exists($file)) {

				if (strpos($settings['author'], "hive-") === 0) {
					$result = json_encode($api->getDiscussionsByCreated($params), JSON_PRETTY_PRINT);
				} else {
					$result = json_encode($api->getDiscussionsByAuthorBeforeDate($params), JSON_PRETTY_PRINT);
				}
				file_put_contents($file, $result);
			} else {
				if (time()-filemtime($file) > 1 * 600) {
					if (strpos($settings['author'], "hive-") === 0) {
						$result = json_encode($api->getDiscussionsByCreated($params), JSON_PRETTY_PRINT);
					} else {
						$result = json_encode($api->getDiscussionsByAuthorBeforeDate($params), JSON_PRETTY_PRINT);
					}
					file_put_contents($file, $result);
				}
			}

		// Get the JSON
			$articles = json_decode(file_get_contents($file), true);

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

<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

use DragosRoua\PHPHiveTools\HiveApi as HiveApi;

final class AdminController
{

		private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    public function adminIndex(Request $request, Response $response) : Response {
			// Create array from config file
			$settings = $this->app->get('settings');
			$accountFile = $this->app->get('accountfile');
			$langFile = $this->app->get('basedir').'app/languages.json';

			$apiConfig = ["webservice_url" => $settings['api'],"debug" => false];
			$api = new HiveApi($apiConfig);

			$cache_interval = 300;
			$params = [$settings['author']];

			$current_time = time();
			if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
				$result = json_encode($api->getAccounts($params), JSON_PRETTY_PRINT);
				file_put_contents($accountFile, $result);
			}

			$account = json_decode(file_get_contents($accountFile), true);
			$langs = json_decode(file_get_contents($langFile), true);

			$themes = array_map('basename', glob($this->app->get('themesdir').'*' , GLOB_ONLYDIR));
			return $this->app->get('view')->render($response, '/admin/admin-index.html', [
					'settings' => $settings,
					'account' => $account[0],
					'themes' => $themes,
					'languages' => $langs
			]);
		}

		public function adminSocial(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			return $this->app->get('view')->render($response, '/admin/admin-social.html', [
					'settings' => $settings
			]);
		}

		public function save(Request $request, Response $response, $args) : Response {
			$data = $request->getParsedBody();
			$redirect = $data["redirect"];
			$settings = $this->app->get('settings');
			$crosspost = (!isset($data["crosspost"])) ? false : true;
			$devMode = (!isset($data["devMode"])) ? false : true;
			$api = ($data["api"] == "") ? "https://api.hive.blog" : $data["api"];
			$displayedPosts = ($data["displayedPosts"] == "") ? 15 : (int)$data["displayedPosts"];
			$author = ($data["author"] == "") ? $settings["author"] : $data["author"];
			$title = ($data["title"] == "") ? $settings["title"] : $data["title"];
			$baseline = ($data["baseline"] == "") ? $settings["baseline"] : $data["baseline"];
			$socialDesc = ($data["socialDesc"] == "") ? $settings["social"]["description"] : $data["socialDesc"];
			$socialImage = ($data["socialImage"] == "") ? $settings["social"]["image"] : $data["socialImage"];
			$twitter = ($data["twitter"] == "") ? $settings["social"]["twitter"] : $data["twitter"];
			$facebook = ($data["facebook"] == "") ? $settings["social"]["facebook"] : $data["facebook"];
			$instagram = ($data["instagram"] == "") ? $settings["social"]["instagram"] : $data["instagram"];
			$linkedin = ($data["linkedin"] == "") ? $settings["social"]["linkedin"] : $data["linkedin"];
			$language = ($data["lang"] == "") ? "mul" : $data["lang"];
			$theme = ($data["theme"] == "") ? "default" : $data["theme"];
			$newSettings = array(
				'author' => $author,
				'title' => $title,
				'baseline' => $baseline,
				'social' => array(
					'description' => $socialDesc,
					'image' => $socialImage,
					'twitter' => $twitter,
					'facebook' => $facebook,
					'instagram' => $instagram,
					'linkedin' => $linkedin
				),
				'theme' => $theme,
				'lang' => $language,
				'crosspost' => $crosspost,
				'api' => $api,
				'devMode' => $devMode,
				'displayedPosts' => (int)$displayedPosts
			);
			$file = json_encode($newSettings, JSON_PRETTY_PRINT);
			// Create array from config file
			file_put_contents($this->app->get('configfile'), $file);
			unlink($this->app->get('blogfile'));

			return $response->withHeader('Location',  $redirect)->withStatus(302);
		}

}

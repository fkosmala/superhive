<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

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
			$themes = array_map('basename', glob($this->app->get('themesdir').'*' , GLOB_ONLYDIR));
			return $this->app->get('view')->render($response, '/admin/admin-index.html', [
					'settings' => $settings,
					'themes' => $themes
			]);
		}

		public function adminSocial(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			return $this->app->get('view')->render($response, '/admin/admin-social.html', [
					'settings' => $settings
			]);
		}
		
		public function adminPages(Request $request, Response $response) : Response {
			$pagesDir = $this->app->get('pagesdir');
			$settings = $this->app->get('settings');
			
			return $this->app->get('view')->render($response, '/admin/admin-pages.html', [
					'settings' => $settings,
					'pages' => array()
			]);
		}
		
		public function adminNewPage(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			
			return $this->app->get('view')->render($response, '/admin/admin-newpage.html', [
					'settings' => $settings
			]);
		}
		
		public function save(Request $request, Response $response, $args) : Response {
			$data = $request->getParsedBody();
			$redirect = $data["redirect"];
			$settings = $this->app->get('settings');
			$crosspost = (!isset($data["crosspost"])) ? false : true;
			$cron = (!isset($data["cron"])) ? false : true;
			$devMode = (!isset($data["devMode"])) ? false : true;
			$api = ($data["api"] == "") ? "https://api.hive.blog" : $data["api"];
			$author = ($data["author"] == "") ? $settings["author"] : $data["author"];
			$title = ($data["title"] == "") ? $settings["title"] : $data["title"];
			$baseline = ($data["baseline"] == "") ? $settings["baseline"] : $data["baseline"];
			$socialDesc = ($data["socialDesc"] == "") ? $settings["social"]["description"] : $data["socialDesc"];
			$socialImage = ($data["socialImage"] == "") ? $settings["social"]["image"] : $data["socialImage"];
			$twitter = ($data["twitter"] == "") ? $settings["social"]["twitter"] : $data["twitter"];
			$facebook = ($data["facebook"] == "") ? $settings["social"]["facebook"] : $data["facebook"];
			$instagram = ($data["instagram"] == "") ? $settings["social"]["instagram"] : $data["instagram"];
			$linkedin = ($data["linkedin"] == "") ? $settings["social"]["linkedin"] : $data["linkedin"];
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
				'crosspost' => $crosspost,
				'api' => $api,
				'cron' => $cron,
				'devMode' => $devMode
			);
			$file = json_encode($newSettings, JSON_PRETTY_PRINT);
			// Create array from config file
			file_put_contents($this->app->get('configfile'), $file);
			unlink($this->app->get('blogfile'));
			

			return $response->withHeader('Location',  $redirect)->withStatus(302);
		}
		
		/*public function logout(Request $request, Response $response) : Response {
			$routeContext = RouteContext::fromRequest($request);
    	$routeParser = $routeContext->getRouteParser();
      $url = $routeParser->fullUrlFor($request->getUri(),'index');
      $url= "//logout:lol".strstr($url, "@");
      
      unset($_COOKIE[session_name()]);
    	session_destroy();
		
			return $response->withHeader('Location',  $url)->withStatus(302);
		}*/
}

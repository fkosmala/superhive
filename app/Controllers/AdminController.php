<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;

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
			return $this->app->get('view')->render($response, '/admin.html', [
					'settings' => $settings,
					'themes' => $themes
			]);
		}
		
		public function save(Request $request, Response $response, $args) : Response {
			$data = $request->getParsedBody();
			$crosspost = (!isset($data["crosspost"])) ? false : true;
			$cron = (!isset($data["cron"])) ? false : true;
			$api = ($data["api"] == "") ? "https://api.hive.blog" : $data["api"] ;
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
				'api' => $api,
				'cron' => $cron
			);
			$file = json_encode($settings, JSON_PRETTY_PRINT);
			// Create array from config file
			file_put_contents($this->app->get('configfile'), $file);
			unlink($this->app->get('blogfile'));

			return $response->withHeader('Location', '/admin')->withStatus(302);
		}
}

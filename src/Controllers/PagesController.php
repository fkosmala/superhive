<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

final class PagesController
{
		
		private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }
		
		public function adminPages(Request $request, Response $response) : Response {
			$pagesDir = $this->app->get('pagesdir');
			$settings = $this->app->get('settings');

			$allPages = preg_grep('~\.(html)$~', scandir($pagesDir));
			foreach ($allPages as $page) {
				$pages[] = substr($page, 0, strrpos($page, "."));
			}
			
			return $this->app->get('view')->render($response, '/admin/admin-pages.html', [
					'settings' => $settings,
					'pages' => $pages
			]);
		}
		
		public function adminNewPage(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			
			return $this->app->get('view')->render($response, '/admin/admin-newpage.html', [
					'settings' => $settings
			]);
		}
		
		public function adminEditPage(Request $request, Response $response, array $args) : Response {
			$file = $args['file'];
			
			$pagesDir = $this->app->get('pagesdir');
			$settings = $this->app->get('settings');
			
			$content = file_get_contents($pagesDir.$file.'.html');
			
			
			$pageTitle = preg_match('/\{% block title %\}(.*?)\{% endblock %\}/s', $content, $match);
			$pageTitle = $match[1];
			$pageContent = strstr($content, '{% block page %}');
			$pageContent = preg_replace("/\{%(.*?)%\}/", "", $pageContent);
			
			return $this->app->get('view')->render($response, '/admin/admin-newpage.html', [
				'pageTitle' => $pageTitle,
				'pageFile' => $file,
				'pageContent' => $pageContent,
				'settings' => $settings
			]);
		}
		
		public function adminDelPage(Request $request, Response $response, array $args) : Response {
			$name = $args['file'];
			
			$pagesDir = $this->app->get('pagesdir');
			
			$filePath = $pagesDir.$name.'.html';
					
			if (unlink($filePath)) {
				$response->getBody()->write('OK');
			} else {
				$response->getBody()->write('Error');
			}
			
			return $response;
		}
		
		public function adminSavePage(Request $request, Response $response) : Response {
			$data = $request->getParsedBody();
			$settings = $this->app->get('settings');
			$pagesDir = $this->app->get('pagesdir');
			
			$pageTitle = $data['title'];
			$pageContent = $data['mde'];
			
			// Some functions to slugify title to create very cool URL
			$slug = mb_strtolower(strtr(utf8_decode($pageTitle), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
			$slug = preg_replace('~[^\pL\d]+~u', "-", $slug);
			$slug = preg_replace('~[^-\w]+~', '', $slug);
			$slug = strtolower($slug);
			$slug = preg_replace('~-+~', "-", $slug);
			
			// apply Twig to the page to display with selected theme
			$page = '{% extends settings.theme ~ "/page.html" %}';
			$page.= "\n{% block title %}".$pageTitle."{% endblock %}\n";
			$page.= "\n{% block page %}\n".$pageContent."\n{% endblock %}\n";
			
			$file = $pagesDir.$slug.'.html';
			
			if (file_put_contents($file, $page)) {
				$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".'/'.$slug;
				$response->getBody()->write($pageUrl);
			} else {
				$response->getBody()->write('Error');
			}
			return $response;

		}
		
}

<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

final class PostsController
{
		
		private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }
    
    // Read Post
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
						$metadata = json_decode($article['json_metadata'], true);
						return $this->app->get('view')->render($response, $settings['theme'].'/post.html', [
							'settings' => $settings,
							'article' => $article,
							'metadata' => $metadata,
							'replies' => $replies
						]);
					}
				}
				
			}
		}
		
		// Admin Functions
		
		public function adminPosts(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			
			$file = $this->app->get('blogfile');
			$blog = json_decode(file_get_contents($file), true);
			$posts = $blog['result'];
			
			return $this->app->get('view')->render($response, '/admin/admin-posts.html', [
					'settings' => $settings,
					'posts' => $posts
			]);
		}
		
		public function adminNewPost(Request $request, Response $response) : Response {
			$settings = $this->app->get('settings');
			
			return $this->app->get('view')->render($response, '/admin/admin-newpost.html', [
					'settings' => $settings
			]);
		}
		
		public function adminEditPost(Request $request, Response $response, array $args) : Response {
			$posted = $args['post'];
			
			$file = $this->app->get('blogfile');
			$settings = $this->app->get('settings');

			$blog = json_decode(file_get_contents($file), true);
			$posts = $blog['result'];
			
			$permlinks = array();
			
			foreach($posts as $post) {
				$permlinks[] = $post["permlink"];
			}
			
			$column = array_column($posts, 'permlink');
			$postIndex = array_search($posted, $column);
			
			if (is_numeric($postIndex)) {
				$post = $posts[$postIndex];
				$postTitle = $post['title'];
				$permlink = $post['permlink'];
				$content = $post['body'];
				$metadata = json_decode($post['json_metadata']);
				
				return $this->app->get('view')->render($response, '/admin/admin-newpost.html', [
					'settings' => $settings,
					'postTitle' => $postTitle,
					'postContent' => $content,
					'postPermlink' => $permlink,
					'postTags' => $metadata->tags
			]);
				
			} else {	
				$response->getBody()->write("No Post Found");
				return $response;
			}
		}
		
		/*public function adminSavePage(Request $request, Response $response) : Response {
			$data = $request->getParsedBody();
			$settings = $this->app->get('settings');
			$pagesDir = $this->app->get('pagesdir');
			
			$pageTitle = $data['title'];
			$pageContent = $data['mde'];
			
			// Some funcitons to slugify title to create very cool URL
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

		}*/
		
}

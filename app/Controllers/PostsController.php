<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

use DragosRoua\PHPHiveTools\HiveApi as HiveApi;

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
		
		$apiConfig = ["webservice_url" => $settings['api'],"debug" => false];

		$api = new HiveApi($apiConfig);

		if (isset($args['permlink'])) {
			$permlink = $args['permlink'];
			
			// Check if comments exists for this post
			$comments = $this->app->get('commentsdir').$permlink.'.comments';
			if ((!file_exists($comments)) || (file_exists($comments)) && (time()-filemtime($comments) > 1 * 3600)) {
				$api = new HiveApi($apiConfig);
				$params = [$settings['author'], $permlink];
				$result = json_encode($api->getContentReplies($params), JSON_PRETTY_PRINT);
				file_put_contents($comments, $result);
			}
			$replies = json_decode(file_get_contents($comments), true);

			$file = $this->app->get('blogfile');
			$articles = json_decode(file_get_contents($file), true);
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
		
		return $this->app->get('view')->render($response, '/admin/admin-posts.html', [
				'settings' => $settings,
				'posts' => $blog
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

		$posts = json_decode(file_get_contents($file), true);
		
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
		
}

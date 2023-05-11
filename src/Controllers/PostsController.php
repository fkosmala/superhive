<?php

/**
 *  * Post controller
 *  *
 * The file contains all the functions used for posts.
 * Display / Save / update / ...
 *
 *  * @category   Controllers
 *  * @package    SuperHive
 *  * @author     Florent Kosmala <kosflorent@gmail.com>
 *  * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 *  */

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\CommonController as Common;
use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use League\CommonMark\CommonMarkConverter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PostsController
{
    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $genPosts = new Common($this->app);
        $genPosts->genPostsFile();
    }

    /**
     *  * Post function
     *  *
     * This function display the selected post in the blog.json (from Blockchain).
     * It also take all comments to display them in the end of post
     *
     * @param object $request
     * @param object $response
     * @param array<string, string> $args
     *
     * @return object $response
     *  */
    public function post(Request $request, Response $response, array $args): Response
    {
        $settings = $this->app->get('settings');

        $apiConfig = [
            'hiveNode' => $settings['api'],
            'debug' => false,
        ];

        if (isset($args['permlink'])) {
            $permlink = $args['permlink'];

            $converter = new CommonMarkConverter();
            $parsedReplies = [];

            $file = $this->app->get('blogfile');
            $articles = json_decode(file_get_contents($file), true);
            foreach ($articles as $index => $article) {
                if ($article['permlink'] === $permlink) {
                    $metadata = json_decode($article['json_metadata'], true);
                    $article['body'] = $converter->convert($article['body']);

                    // Check if comments exists for this post
                    $cmts = $this->app->get('commentsdir') . $permlink . '.comments';
                    if ((! file_exists($cmts)) || (file_exists($cmts)) && (time() - filemtime($cmts) > 120)) {
                        $api = new HiveCondenser($apiConfig);
                        $replies = $api->getContentReplies($article['author'], $permlink);
                        $result = json_encode($replies, JSON_PRETTY_PRINT);
                        file_put_contents($cmts, $result);
                    }
                    $replies = json_decode(file_get_contents($cmts), true);

                    foreach ($replies as $reply) {
                        $reply['body'] = $converter->convert($reply['body']);
                        $parsedReplies[] = $reply;
                    }

                    return $this->app->get('view')->render($response, $settings['theme'] . '/post.html', [
                        'settings' => $settings,
                        'article' => $article,
                        'metadata' => $metadata,
                        'replies' => $parsedReplies,
                    ]);
                }
            }
        }
        return $response;
    }

    /**
     *  * Post function
     *  *
     * This function display the selected post in the blog.json (from Blockchain).
     * It also take all comments to display them in the end of post
     *
     * @param object $request
     * @param object $response
     * @param array<string, string> $args
     *
     * @return object $response
     *  */
    public function tag(Request $request, Response $response, array $args): Response
    {
        $settings = $this->app->get('settings');
        $tag = $args['tag'];
        $posts = [];
        $result = [];

        if (! isset($tag)) {
            $result = [];
        } else {
            $matches = [];
            $file = $this->app->get('blogfile');
            $articles = json_decode(file_get_contents($file), true);

            foreach ($articles as $article) {
                //Check in Tags
                $metadata = json_decode($article['json_metadata'], true);
                $tags = implode(',', $metadata['tags']);
                if (preg_match("/\b{$tag}\b/i", $tags)) {
                    $matches[] = $article['title'];
                }
            }

            $result = array_unique($matches);

            foreach ($articles as $article) {
                if (in_array($article['title'], $result)) {
                    $posts[] = $article;
                }
            }
        }

        return $this->app->get('view')->render($response, $settings['theme'] . '/tag.html', [
            'tag' => $tag,
            'posts' => $posts,
            'settings' => $settings,
        ]);
    }

    /**
     *  * Administration posts function
     *  *
     * This function display the post page in admin panel.
     * Contains every posts in blog.json file (from blockchain)
     *
     * @param object $request
     * @param object $response
     *
     * @return object $response
     *  */
    public function adminPosts(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');

        $file = $this->app->get('blogfile');
        $blog = json_decode(file_get_contents($file), true);

        return $this->app->get('view')->render($response, '/admin/admin-posts.html', [
            'settings' => $settings,
            'posts' => $blog,
        ]);
    }

    /**
     *  * Administration new post function
     *  *
     * This function just display the new post page to write and send post.
     *
     * @param object $request
     * @param object $response
     *
     * @return object $response
     *  */
    public function adminNewPost(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');

        return $this->app->get('view')->render($response, '/admin/admin-newpost.html', [
            'settings' => $settings,
        ]);
    }

    /**
     *  * Administration edit post function
     *  *
     * Same as adminNewPost but with already written content from an old post.
     *
     * @param object $request
     * @param object $response
     * @param array<string, string> $args
     *
     * @return object $response
     *  */
    public function adminEditPost(Request $request, Response $response, array $args): Response
    {
        $posted = $args['post'];

        $file = $this->app->get('blogfile');
        $settings = $this->app->get('settings');

        $posts = json_decode(file_get_contents($file), true);

        $permlinks = [];

        foreach ($posts as $post) {
            $permlinks[] = $post['permlink'];
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
                'postTags' => $metadata->tags,
            ]);
        }
        $response->getBody()->write('No Post Found');
        return $response;
    }
}

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
use Embed\Embed;
use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Embed\EmbedExtension;
use League\CommonMark\Extension\Embed\Bridge\OscaroteroEmbedAdapter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;

final class PostsController
{
    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $genPosts = new Common($this->app);
        $genPosts->genPostsFile();

        $session = $this->app->get('session');

        $this->app->get('view')->getEnvironment()->addGlobal("user", [
            'author' => $session['sh_author'],
            'signature' => $session['sh_sign'],
        ]);
    }

    /**
     *  * Post function
     *  *
     * This function display the selected post in the blog.json (from Blockchain).
     * It also take all comments to display them in the end of post
     *
     * @param string $permlink
     * @param Response $response
     */
    public function post(string $permlink, Response $response): Response
    {
        $settings = $this->app->get('settings');

        $apiConfig = [
            'hiveNode' => $settings['api'],
            'debug' => false,
        ];

        if (!empty($permlink)) {
            $embedLibrary = new Embed();
            $embedLibrary->setSettings([
                'oembed:query_parameters' => [
                    'maxwidth' => 800,
                    'maxheight' => 600,
                ]
            ]);

            $markdownConfig = [
                'embed' => [
                    'adapter' => new OscaroteroEmbedAdapter($embedLibrary),
                    'fallback' => 'link',
                ],
                'heading_permalink' => [
                    'html_class' => 'heading-permalink',
                    'insert' => 'before',
                    'symbol' => '# ',
                    'title' => "Permalink",
                    'id_prefix' => ''
                ]
            ];

            $environment = new Environment($markdownConfig);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new EmbedExtension());
            $converter = new MarkdownConverter($environment);

            $parsedReplies = [];
            $file = $this->app->get('blogfile');
            $articles = json_decode(file_get_contents($file), true);
            foreach ($articles as $index => $article) {
                if ($article['permlink'] === $permlink) {
                    $metadata = json_decode($article['json_metadata'], true);
                    $article['body'] = $converter->convert($article['body']);

                    // Check if comments exists for this post
                    $cmts = $this->app->get('commentsdir') . $permlink . '.comments';
                    $delay = $settings['delay'];
                    if ((!file_exists($cmts)) || (file_exists($cmts)) && (time() - filemtime($cmts) > $delay)) {
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
     * @param string $tag
     * @param object $request
     * @param Response $response
     *
     * @return Response $response
     */
    public function tag(string $tag, Response $response): Response
    {
        $settings = $this->app->get('settings');
        $posts = [];
        $result = [];

        if (!empty($tag)) {
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
                    //Get featured image
                    $meta = json_decode($article['json_metadata'], true);

                    if (
                        array_key_exists('image', $meta)
                        && is_array($meta['image'])
                        && array_key_exists(0, $meta['image'])
                    ) {
                        $featured = $meta['image'][0];
                    } else {
                        $featured = '/themes/' . $settings['theme'] . '/no-img.png';
                    }

                    $article['featured'] = $featured;
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
     * @param Response $response
     */
    public function adminPosts(Response $response): Response
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
     * @param Response $response
     */
    public function adminNewPost(Response $response): Response
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
     * @param string $posted
     * @param Response $response
     */
    public function adminEditPost(string $posted, Response $response): Response
    {
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

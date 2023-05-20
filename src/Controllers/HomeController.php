<?php

/**
 *  * Home controller
 *  *
 * The file contains every necessary functions for Home display.
 *
 *  * @category   Controllers
 *  * @package    SuperHive
 *  * @author     Florent Kosmala <kosflorent@gmail.com>
 *  * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 *  */

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\CommonController as Common;
use League\CommonMark\CommonMarkConverter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HomeController
{
    /** @var array<int|string, int|string> $settings */
    private array $settings;

    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $this->settings = $app->get('settings');

        $common = new Common($this->app);
        $common->genPostsFile();
    }

    /**
     *  * Index function
     *  *
     * This function display the index page with the list of posts.
     * It ocntians also the function to generate the blog.json file with all posts informations.
     * All the posts must be converted from MarkDown to HTML before display.
     *
     * @param Response $response
     */
    public function index(Response $response): Response
    {
        $settings = $this->settings;

        // The file with the latest posts.
        $file = $this->app->get('blogfile');

        // Get the JSON
        $articles = json_decode(file_get_contents($file), true);

        //Get ready to parse the mardown
        $converter = new CommonMarkConverter();
        $parsedPosts = [];

        foreach ($articles as &$article) {
            // Create HTML from Markdown
            $article['body'] = $converter->convert($article['body']);

            //Get featured image
            $meta = json_decode($article['json_metadata'], true);

            if (isset($meta['image']) && (array_key_exists(0, $meta['image']))) {
                $featured = $meta['image'][0];
            } else {
                $featured = '/themes/' . $settings['theme'] . '/no-img.png';
            }
            $article['featured'] = $featured;

            $parsedPosts[] = $article;
        }

        $common = new Common($this->app);
        $mostUsedTags = $common->getMostUsedTags();

        // Return view with articles
        return $this->app->get('view')->render($response, $settings['theme'] . '/index.html', [
            'articles' => $parsedPosts,
            'tags' => $mostUsedTags,
            'settings' => $settings,
        ]);
    }

    /**
     *  * Search function
     *  *
     * This function was called when search form is not empty and the form was send.
     * It displays the posts which contains the selected string.
     *
     * @param Request $request
     * @param Response $response
     */
    public function search(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $term = $data['term'];
        $posts = [];
        $result = [];

        $settings = $this->settings;

        if ($term === '') {
            $result = [];
        } else {
            $file = $this->app->get('blogfile');
            $articles = json_decode(file_get_contents($file), true);

            $matches = [];
            $tags = [];

            foreach ($articles as $article) {
                // Check in Title
                if (preg_match("/\b{$term}\b/i", $article['title'])) {
                    $matches[] = $article['title'];
                }

                // Check in Body
                if (preg_match("/\b{$term}\b/i", $article['body'])) {
                    $matches[] = $article['title'];
                }

                //Check in Tags
                $metadata = json_decode($article['json_metadata'], true);
                $tags = implode(',', $metadata['tags']);
                if (preg_match("/\b{$term}\b/i", $tags)) {
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

        return $this->app->get('view')->render($response, $settings['theme'] . '/search.html', [
            'term' => $term,
            'posts' => $posts,
            'settings' => $settings,
        ]);
    }

    /**
     *  * Login function
     *  *
     * This function displays the login form.
     * It was called everytime an admin page is called without good session.
     *
     * @param object $request
     * @param Response $response
     */
    public function login(Response $response): Response
    {
        $settings = $this->settings;

        return $this->app->get('view')->render($response, 'login.html', [
            'settings' => $settings,
        ]);
    }

    /**
     *  * Login post function
     *  *
     * Called when the login form is send.
     * just compare the entered login and the encrypted key (generated by HiveKeychain)
     * with the credentials in passwod file.
     *
     * @param Request $request
     * @param Response $response
     */
    public function loginPost(Request $request, Response $response): Response
    {
        $settings = $this->settings;
        $session = $this->app->get('session');
        $data = $request->getParsedBody();
        $author = $settings['author'];

        if ($author !== $data['username']) {
            $session::destroy();
            $msg = 'Not Ok';
        } else {
            $cred = unserialize(file_get_contents($this->app->get('password')));
            $passwd = $cred[$author];
            if ($data['passwd'] === $passwd) {
                $session['sh_author'] = $author;
                $session['sh_sign'] = $passwd;
                $msg = 'OK';
            } else {
                $session::destroy();
                $msg = 'Not Ok';
            }
        }
        $response->getBody()->write($msg);
        return $response;
    }

    /*The value "always" should be used to describe documents that change each time they are accessed. T
     * Feed function
     *
     * Generate the RSS feed of account's posts
     *
     * @param object $response
     *
     * @return object $response
     */
    public function feed(Response $response): Response
    {
        $settings = $this->settings;

        $file = $this->app->get('blogfile');
        $articles = json_decode(file_get_contents($file), true);

        header('Content-Type: text/xml');
        return $this->app->get('view')->render($response, '/feed.xml', [
            'articles' => $articles,
            'settings' => $settings,
        ]);
    }

    /**
     *  * Sitemap function
     *  *
     * Generate the Sitemap with all posts links.
     *
     * @param Response $response
     */
    public function sitemap(Response $response): Response
    {
        $settings = $this->settings;

        $pages = [];

        $file = $this->app->get('blogfile');
        $articles = json_decode(file_get_contents($file), true);

        $pagesDir = $this->app->get('pagesdir');
        $files = preg_grep('~\.(html)$~', scandir($pagesDir));
        foreach ($files as $file) {
            $page = [];
            $page['created'] = filemtime($pagesDir . $file);
            $page['name'] = mb_substr($file, 0, -5);
            $pages[] = $page;
        }

        header('Content-Type: text/xml');
        return $this->app->get('view')->render($response, '/sitemap.xml', [
            'articles' => $articles,
            'pages' => $pages,
            'settings' => $settings,
        ]);
    }

    /**
     *  * About function
     *  *
     * Generate the about page with account information.
     *
     * @param Response $response
     */
    public function about(Response $response): Response
    {
        $settings = $this->settings;
        $accountFile = $this->app->get('accountfile');
        $account = json_decode(file_get_contents($accountFile), true);

        $accountBio = json_decode($account[0]['posting_json_metadata'], true);

        return $this->app->get('view')->render($response, $settings['theme'] . '/about.html', [
            'settings' => $settings,
            'data' => $account[0],
            'account' => $accountBio['profile'],
        ]);
    }
}

<?php

/**
 * Home controller
 *
 * The file contains every necessary functions for installation.
 *
 * @category   Controllers
 * @package    SuperHive
 * @author     Florent Kosmala <kosflorent@gmail.com>
 * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use BetterBC\HiveToolboxPhp\Hive\Condenser as HiveCondenser;
use League\CommonMark\CommonMarkConverter;

final class HomeController
{
    private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Index function
     *
     * This function display the index page with the list of posts.
     * It ocntians also the function to generate the blog.json file with all posts informations.
     * All the posts must be converted from MarkDown to HTML before display.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function index(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');

        // Hive API communication init
        $apiConfig = [
            "hiveNode" => $settings['api'],
            "debug" => false
        ];
        $api = new HiveCondenser($apiConfig);
        
        // The file with the latest posts.
        $file = $this->app->get('blogfile');
        
        // if the JSON file doesn't exist or if it's old, take it from API
        if (!file_exists($file) || (time() - filemtime($file) > 120)) {
            // Prepare API call according to displayed posts type
            $displayType = $settings['displayType']['type'];
            if ($displayType === 'author') {
                $dateNow = (new \DateTime())->format('Y-m-d\TH:i:s');
                $result = json_encode(
                    $api->getDiscussionsByAuthorBeforeDate(
                        $settings['author'],
                        "",
                        $dateNow,
                        100
                    ),
                JSON_PRETTY_PRINT);
            } elseif (($displayType === 'tag')) {
                $displayTag = $settings['displayType']['tag'];
                $taggedPosts = array();
                $allPosts = json_encode(
                    $api->getDiscussionsByAuthorBeforeDate(
                        $settings['author'],
                        "",
                        "",
                        100
                    )
                );
                $allPosts = json_decode($allPosts, true);
                //print_r($allPosts);
                foreach ($allPosts as &$post) {
                    //$postTags = json_encode($post['json_metadata'], JSON_PRETTY_PRINT);
                    $postMeta = json_decode($post['json_metadata'], true);
                    $postTags = $postMeta['tags'];
                    if (in_array($displayTag, $postTags)) {
                        $taggedPosts[] = $post;
                    }
                }
                
                $result = json_encode($taggedPosts, JSON_PRETTY_PRINT);
            } elseif ($displayType === 'reblog') {
                $result = json_encode($api->getDiscussionsByBlog($settings['author']), JSON_PRETTY_PRINT);
            } elseif (strpos($settings['author'], "hive-") === 0) {
                $result = json_encode($api->getDiscussionsByCreated($settings['author']), JSON_PRETTY_PRINT);
            }
            file_put_contents($file, $result);
            unset($taggedPosts);
        }

        // Get the JSON
        $articles = json_decode(file_get_contents($file), true);
        
        //Get ready to parse the mardown
        $converter = new CommonMarkConverter();
        $parsedPosts = array();
        
        foreach ($articles as &$article) {
            // Create HTML from Markdown
            $article['body'] = $converter->convert($article['body']);
            $tags = '';
            
            //Get featured image
            $meta = json_decode($article['json_metadata'], true);
            
            $tags .= implode(",", $meta['tags']) . ',';
            if ((isset($meta['image'])) && (!empty($meta['image']))) {
                $featured = $meta['image'][0];
            } else {
                $featured = '/themes/' . $settings['theme'] . '/no-img.png';
            }
            $article['featured'] = $featured;
            
            $parsedPosts[] = $article;
        }
        
        $tags = explode(',', $tags);
        $tagsArray = array_count_values($tags);
        array_multisort($tagsArray, SORT_DESC);
        $mostUsedTags = array_slice($tagsArray, 0, 15);

        // Return view with articles
        return $this->app->get('view')->render($response, $settings['theme'] . '/index.html', [
            'articles' => $parsedPosts,
            'tags' => $mostUsedTags,
            'settings' => $settings
        ]);
    }
    
    /**
     * Search function
     *
     * This function was called when search form is not empty and the form was send.
     * It displays the posts which contains the selected string.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function search(Request $request, Response $response, $args): Response
    {
        $data = $request->getParsedBody();
        $term = $data['term'];
        
        $settings = $this->app->get('settings');
        
        if ($term == '') {
            $result = [];
        } else {
            $file = $this->app->get('blogfile');
            $articles = json_decode(file_get_contents($file), true);
            
            $matches = array();
            
            foreach ($articles as $article) {
                // Check in Title
                if (preg_match("/\b$term\b/i", $article['title'])) {
                    $matches[] = $article['title'];
                }
                
                // Check in Body
                if (preg_match("/\b$term\b/i", $article['body'])) {
                    $matches[] = $article['title'];
                }
                
                //Check in Tags
                $metadata = json_decode($article['json_metadata'], true);
                $tags = implode(",", $metadata['tags']);
                if (preg_match("/\b$term\b/i", $tags)) {
                    $matches[] = $article['title'];
                }
            }
            $result = array_unique($matches);
            
            $posts = array();
            
            foreach ($articles as $article) {
                if (in_array($article['title'], $result)) {
                    $posts[] = $article;
                }
            }
        }

        return $this->app->get('view')->render($response, $settings['theme'] . '/search.html', [
            'term' => $term,
            'posts' => $posts,
            'settings' => $settings
        ]);
    }
    
    /**
     * Login function
     *
     * This function displays the login form.
     * It was called everytime an admin page is called without good session.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function login(Request $request, Response $response, $args): Response
    {
        $settings = $this->app->get('settings');
        $session = $this->app->get('session');
        
        return $this->app->get('view')->render($response, 'login.html', [
            'settings' => $settings
        ]);
    }
    
    /**
     * Login post function
     *
     * Called when the login form is send.
     * just compare the entered login and the encrypted key (generated by HiveKeychain)
     * with the credentials in passwod file.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function loginPost(Request $request, Response $response, $args): Response
    {
        $settings = $this->app->get('settings');
        $session = $this->app->get('session');
        $data = $request->getParsedBody();
        $author = $settings['author'];
        
        if ($author != $data['username']) {
            $session::destroy();
            $msg = "Not Ok";
        } else {
            $cred = unserialize(file_get_contents($this->app->get('password')));
            $passwd = $cred[$author];
            if ($data['passwd'] == $passwd) {
                $session['sh_author'] = $author;
                $session['sh_sign'] = $passwd;
                $msg = "OK";
            } else {
                $session::destroy();
                $msg = "Not Ok";
            }
        }
        $response->getBody()->write($msg);
        return $response;
    }

    /**
     * Feed function
     *
     * Generate the RSS feed of account's posts
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function feed(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');

        $file = $this->app->get('blogfile');
        $articles = json_decode(file_get_contents($file), true);

        header('Content-Type: text/xml');
        return $this->app->get('view')->render($response, '/feed.xml', [
            'articles' => $articles,
            'settings' => $settings
        ]);
    }

    /**
     * Sitemap function
     *
     * Generate the Sitemap with all posts links.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function sitemap(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');

        $file = $this->app->get('blogfile');
        $articles = json_decode(file_get_contents($file), true);

        header('Content-Type: text/xml');
        return $this->app->get('view')->render($response, '/sitemap.xml', [
            'articles' => $articles,
            'settings' => $settings
        ]);
    }
    
    /**
     * About function
     *
     * Generate the about page with account information.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function about(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');
        $accountFile = $this->app->get('accountfile');
        $account = json_decode(file_get_contents($accountFile), true);
        
        $accountBio = json_decode($account[0]['posting_json_metadata'], true);
        
        return $this->app->get('view')->render($response, $settings['theme'] . '/about.html', [
            'settings' => $settings,
            'account' => $accountBio['profile']
        ]);
    }
}

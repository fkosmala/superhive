<?php

/**
 * Common controller
 *
 * The file contains every function needed in many other controllers.
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
use Hive\PhpLib\Hive\Condenser as HiveCondenser;

final class CommonController
{
    private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }
    
    /**
     * getPosts function
     *
     * This function will query the blockchain to have posts
     * It will generate a json file stored in /resources/blog/ folder
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function genPostsFile()
    {
        $settings = $this->app->get('settings');
        $result = "";

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
                    JSON_PRETTY_PRINT
                );
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
                unset($taggedPosts);
            } elseif ($displayType === 'reblog') {
                $result = json_encode($api->getDiscussionsByBlog($settings['author']), JSON_PRETTY_PRINT);
            } elseif (strpos($settings['author'], "hive-") === 0) {
                $result = json_encode($api->getDiscussionsByCreated($settings['author']), JSON_PRETTY_PRINT);
            }
            file_put_contents($file, $result);
        }
    }
}

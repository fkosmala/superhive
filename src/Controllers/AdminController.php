<?php

/**
 * Admin controller
 *
 * The file contains all the functions used in all administration panel.
 * For admin posts function, please go to the Posts Controller
 * For admin pages function, please go to the Pages Controller
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
use Slim\Routing\RouteContext;
use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use App\Controllers\CommonController as Common;

final class AdminController
{
    private $app;

    /**
     * Admin part contructor
     *
     * This constructor is not the same as other controllers.
     * Administration need  to control if session exists with good account & encrypted key.
     *
     * @param object $app
     */
    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $genPosts = new Common($this->app);
        $genPosts->genPostsFile();
        
        /*
         *  Check security in session for admin functions
         */
        $settings = $this->app->get('settings');
        $session = $this->app->get('session');
        $cred = unserialize(file_get_contents($this->app->get('password')));
        $author = $settings['author'];
        $passwd = $cred[$author];
        
        /* If sessons keys are not set */
        if ((!isset($session['sh_author'])) || (!isset($session['sh_sign']))) {
            header("Location: /login");
            die();
        } else {
            /* If session keys are not good */
            if (($settings['author'] != $session['sh_author']) || ($passwd != $session['sh_sign'])) {
                header("Location: /login");
                die();
            }
        }
    }

    /**
     * Admin index function
     *
     * This function display the admin index with some settings ready to be changed.
     * It call the admin save() functionwhen the button is clicked.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminIndex(Request $request, Response $response): Response
    {
        // Create array from config file
        $settings = $this->app->get('settings');
        $accountFile = $this->app->get('accountfile');
        $langFile = $this->app->get('basedir') . 'resources/languages.json';
        $nodesFile = $this->app->get('basedir') . 'resources/nodes.json';

        $apiConfig = [
            "hiveNode" => $settings['api'],
            "debug" => false
        ];
        $api = new HiveCondenser($apiConfig);

        $cache_interval = 300;

        $current_time = time();
        if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
            $result = json_encode($api->getAccounts($settings['author']), JSON_PRETTY_PRINT);
            file_put_contents($accountFile, $result);
        }

        $account = json_decode(file_get_contents($accountFile), true);
        $langs = json_decode(file_get_contents($langFile), true);
        $nodes = json_decode(file_get_contents($nodesFile), true);

        $themes = array_map('basename', glob($this->app->get('themesdir') . '*', GLOB_ONLYDIR));
        return $this->app->get('view')->render($response, '/admin/admin-index.html', [
                'settings' => $settings,
                'account' => $account[0],
                'themes' => $themes,
                'languages' => $langs,
                'nodes' => $nodes
        ]);
    }

    /**
     * Admin social function
     *
     * This function display tthe social pagewith a form.
     * This page contains every social settings which can be modified.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminSocial(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');
        return $this->app->get('view')->render($response, '/admin/admin-social.html', [
                'settings' => $settings
        ]);
    }
    
    /**
     * Admin logout function
     *
     * This function clear ther session, destroy it, and redirect to login page.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function logout(Request $request, Response $response): Response
    {
        $session = $this->app->get('session');
        
        $session->delete('sh_author');
        $session->delete('sh_sign');
        $session::destroy();
        
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    
    /**
     * Admin save function
     *
     * This function Take every fields in the form and convert the into a (human-readable))JSON file.
     * the generated file will be save in config folder.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $redirect = $data["redirect"];
        $settings = $this->app->get('settings');
        $crosspost = (!isset($data["cross"])) ? $settings["crosspost"] : (bool)$data["cross"];
        $devMode = (!isset($data["devel"])) ? $settings["devMode"] : (bool)$data["devel"];
        $api = (!isset($data["api"])) ? $settings["api"] : $data["api"];
        $displayedPosts = (!isset($data["displayedPosts"])) ? $settings["displayedPosts"] : (int)$data["displayedPosts"];
        $author = (!isset($data["author"])) ? $settings["author"] : $data["author"];
        $title = (!isset($data["title"])) ? $settings["title"] : $data["title"];
        $baseline = (!isset($data["baseline"])) ? $settings["baseline"] : $data["baseline"];
        $displayType = (!isset($data["displayTypes"])) ? $settings["displayType"]['type'] : $data["displayTypes"];
        $displayedTag = (!isset($data["tag"])) ? $settings["displayType"]['tag'] : $data["tag"];
        $socialDesc = (!isset($data["socialDesc"])) ? $settings["social"]["description"] : $data["socialDesc"];
        $socialImage = (!isset($data["socialImage"])) ? $settings["social"]["image"] : $data["socialImage"];
        $twitter = (!isset($data["twitter"])) ? $settings["social"]["twitter"] : $data["twitter"];
        $facebook = (!isset($data["facebook"])) ? $settings["social"]["facebook"] : $data["facebook"];
        $instagram = (!isset($data["instagram"])) ? $settings["social"]["instagram"] : $data["instagram"];
        $linkedin = (!isset($data["linkedin"])) ? $settings["social"]["linkedin"] : $data["linkedin"];
        $language = (!isset($data["lang"])) ? $settings["lang"] : $data["lang"];
        $theme = (!isset($data["theme"])) ? $settings["theme"] : $data["theme"];
        $newSettings = array(
            'author' => $author,
            'title' => $title,
            'baseline' => $baseline,
            'displayType' => array(
                'type' => $displayType,
                'tag' => $displayedTag,
            ),
            'social' => array(
                'description' => $socialDesc,
                'image' => $socialImage,
                'twitter' => $twitter,
                'facebook' => $facebook,
                'instagram' => $instagram,
                'linkedin' => $linkedin
            ),
            'theme' => $theme,
            'lang' => $language,
            'crosspost' => $crosspost,
            'api' => $api,
            'devMode' => $devMode,
            'displayedPosts' => (int)$displayedPosts
        );
        $file = json_encode($newSettings, JSON_PRETTY_PRINT);
        // Create array from config file
        file_put_contents($this->app->get('configfile'), $file);
        unlink($this->app->get('blogfile'));

        return $response->withHeader('Location', $redirect)->withStatus(302);
    }
}

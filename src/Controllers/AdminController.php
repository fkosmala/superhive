<?php

/**
 *  * Admin controller
 *  *
 * The file contains all the functions used in all administration panel.
 * For admin posts function, please go to the Posts Controller
 * For admin pages function, please go to the Pages Controller
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
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AdminController
{
    private ContainerInterface $app;

    /**
     * Admin part contructor
     *
     * This constructor is not the same as other controllers.
     * Administration need  to control if session exists with good account & encrypted key.
     *
     * @param \Psr\Container\ContainerInterface $app
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
        if (!isset($session['sh_author']) || (!isset($session['sh_sign']))) {
            header('Location: /login');
            die;
        }
        /* If session keys are not good */
        if (($settings['author'] !== $session['sh_author']) || ($passwd !== $session['sh_sign'])) {
            header('Location: /login');
            die;
        }
    }

    /**
     *  * Admin index function
     *  *
     * This function display the admin index with some settings ready to be changed.
     * It call the admin save() functionwhen the button is clicked.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function adminIndex(Response $response): Response
    {
        // Create array from config file
        $settings = $this->app->get('settings');
        $accountFile = $this->app->get('accountfile');
        $blogFile = $this->app->get('blogfile');

        $posts = json_decode(file_get_contents($blogFile), true);
        $nbPosts = count($posts);

        $apiConfig = [
            'hiveNode' => $settings['api'],
            'debug' => false,
        ];
        $api = new HiveCondenser($apiConfig);

        $cache_interval = $settings['delay'];

        $current_time = time();
        if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
            $result = json_encode($api->getAccounts($settings['author']), JSON_PRETTY_PRINT);
            file_put_contents($accountFile, $result);
        }

        $account = json_decode(file_get_contents($accountFile), true);

        return $this->app->get('view')->render($response, '/admin/admin-index.html', [
            'settings' => $settings,
            'account' => $account[0],
            'nbPosts' => $nbPosts
        ]);
    }

    /**
     *  * Admin settings function
     *  *
     * This function display tthe settings page
     * This page contains every Superhive settings (not plugins settings)..
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function adminSettings(Response $response): Response
    {
        // Create array from config file
        $settings = $this->app->get('settings');
        $accountFile = $this->app->get('accountfile');
        $langFile = $this->app->get('basedir') . 'resources/languages.json';
        $nodesFile = $this->app->get('basedir') . 'resources/nodes.json';

        $apiConfig = [
            'hiveNode' => $settings['api'],
            'debug' => false,
        ];
        $api = new HiveCondenser($apiConfig);

        $cache_interval = $settings['delay'];

        $current_time = time();
        if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
            $result = json_encode($api->getAccounts($settings['author']), JSON_PRETTY_PRINT);
            file_put_contents($accountFile, $result);
        }

        $account = json_decode(file_get_contents($accountFile), true);
        $langs = json_decode(file_get_contents($langFile), true);
        $nodes = json_decode(file_get_contents($nodesFile), true);

        return $this->app->get('view')->render($response, '/admin/admin-settings.html', [
            'settings' => $settings,
            'account' => $account[0],
            'languages' => $langs,
            'nodes' => $nodes,
        ]);
    }

    /**
     *  * Admin theme function
     *  *
     * This function is for the Theme page
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function adminThemes(Response $response): Response
    {
        // Create array from config file
        $settings = $this->app->get('settings');

        $themes = array_map('basename', glob($this->app->get('themesdir') . '*', GLOB_ONLYDIR));
        return $this->app->get('view')->render($response, '/admin/admin-themes.html', [
            'settings' => $settings,
            'themes' => $themes,
        ]);
    }

    /**
     *  * Admin logout function
     *  *
     * This function clear ther session, destroy it, and redirect to login page.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function logout(Response $response): Response
    {
        $session = $this->app->get('session');

        $session->delete('sh_author');
        $session->delete('sh_sign');
        $session::destroy();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    /**
     *  * Admin save function
     *  *
     * This function Take every fields in the form and convert the into a (human-readable))JSON file.
     * the generated file will be save in config folder.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (isset($data['redirect'])) {
            $redirect = $data['redirect'];
        } else {
            $redirect = '/admin/';
        }
        $settings = $this->app->get('settings');

        foreach ($data as $key => $value) {
            if ($value === "true") {
                $value = (bool) true;
            }
            if ($value === "false") {
                $value = (bool) false;
            }
            if (mb_strpos($key, "-") !== false) {
                $pieces = explode("-", $key);
                if (array_key_exists($pieces[1], $settings[$pieces[0]])) {
                    $settings[$pieces[0]][$pieces[1]] = $value;
                }
            } else {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = $value;
                }
            }
        }

        $file = json_encode($settings, JSON_PRETTY_PRINT);
        // Create array from config file
        file_put_contents($this->app->get('configfile'), $file);
        unlink($this->app->get('blogfile'));

        return $response->withHeader('Location', $redirect)->withStatus(302);
    }

    /**
     *  * Admin theme save function
     *  *
     * This function is for save the theme into the JSON config file
     *
     * @param string $theme
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface $response
     *  */
    public function saveTheme(string $theme, Response $response): Response
    {
        $settings = $this->app->get('settings');
        $settings['theme'] = $theme;
        $file = json_encode($settings, JSON_PRETTY_PRINT);
        file_put_contents($this->app->get('configfile'), $file);
        return $response->withHeader('Location', '/admin/themes')->withStatus(302);
    }
}

<?php

/**
 * Pages controller
 *
 * The file contains all the functions used for off-chain pages.
 * Display / Save / update / ...
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

final class PagesController
{
    private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Admin pages function
     *
     * This function display the already written pages and a button to create one.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminPages(Request $request, Response $response): Response
    {
        $pagesDir = $this->app->get('pagesdir');
        $settings = $this->app->get('settings');
        $pages = array();
        
        $allPages = preg_grep('~\.(html)$~', scandir($pagesDir));
        foreach ($allPages as $page) {
            $pages[] = substr($page, 0, strrpos($page, "."));
        }
        
        return $this->app->get('view')->render($response, '/admin/admin-pages.html', [
                'settings' => $settings,
                'pages' => $pages
        ]);
    }
    
    /**
     * Administration new page function
     *
     * This function just display editor to write new page.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminNewPage(Request $request, Response $response): Response
    {
        $settings = $this->app->get('settings');
        
        return $this->app->get('view')->render($response, '/admin/admin-newpage.html', [
                'settings' => $settings
        ]);
    }

    /**
     * Administration edit page function
     *
     * Same as adminNewPage but with already written content from already written page.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminEditPage(Request $request, Response $response, array $args): Response
    {
        $file = $args['file'];
        $pageTitle = array();
        
        $pagesDir = $this->app->get('pagesdir');
        $settings = $this->app->get('settings');
        
        $content = file_get_contents($pagesDir . $file . '.html');
        
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

/**
     * Administration delete page function
     *
     * called to delete fpage and return to administration Pages section
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminDelPage(Request $request, Response $response, array $args): Response
    {
        $name = $args['file'];
        
        $pagesDir = $this->app->get('pagesdir');
        
        $filePath = $pagesDir . $name . '.html';
                
        if (unlink($filePath)) {
            $response->getBody()->write('OK');
        } else {
            $response->getBody()->write('Error');
        }
        
        return $response;
    }
    
    /**
     * Administration save page function
     *
     * Used to generate slug, html code and save page in file.
     *
     * @param object $request
     * @param object $response
     * @param array $args
     *
     * @return object $response
     */
    public function adminSavePage(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $pagesDir = $this->app->get('pagesdir');
        
        $pageTitle = $data['title'];
        $pageContent = $data['mde'];
        
        // Some functions to slugify title to create very cool URL
        $acc = 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';
        $noAcc = 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY';
        $title = mb_convert_encoding($pageTitle, 'UTF-8', mb_list_encodings());
        $acc =  mb_convert_encoding($acc, 'UTF-8', mb_list_encodings());
        $slug = mb_strtolower(strtr($title, $acc, $noAcc));
        $slug = preg_replace('~[^\pL\d]+~u', "-", $slug);
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        $slug = strtolower($slug);
        $slug = preg_replace('~-+~', "-", $slug);
        
        // apply Twig to the page to display with selected theme
        $page = '{% extends settings.theme ~ "/page.html" %}';
        $page .= "\n{% block title %}" . $pageTitle . "{% endblock %}\n";
        $page .= "\n{% block page %}\n" . $pageContent . "\n{% endblock %}\n";
        
        $file = $pagesDir . $slug . '.html';
        
        if (file_put_contents($file, $page)) {
            if (isset($_SERVER["HTTPS"])) {
                $isSecure = $_SERVER["HTTPS"];
            }
            if (isset($isSecure) && $isSecure == 'on') {
                $scheme = "https";
            } else {
                $scheme = "http";
            }
            $pageUrl = $scheme . "://" . $_SERVER["HTTP_HOST"] . '/pages/' . $slug;
            $response->getBody()->write($pageUrl);
        } else {
            $response->getBody()->write('Error');
        }
        return $response;
    }
}

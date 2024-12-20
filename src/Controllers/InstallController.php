<?php

/**
 *  * Install controller
 *  *
 * The file contains every necessary functions for installation.
 *
 *  * @category   Controllers
 *  * @package    SuperHive
 *  * @author     Florent Kosmala <kosflorent@gmail.com>
 *  * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 *  */

declare(strict_types=1);

namespace App\Controllers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class InstallController
{
    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    /**
     *  * Prepare function
     *  *
     * This function called after Composer installation.
     * It will display the page with requirements check, account creation and
     * the form to create password file with encreypted key
     *
     * @param Response $response
     */
    public function prepare(Response $response): Response
    {
        $requirements = [];

        /* Check if PHP version is ok tu run SuperHive */
        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            $req = [
                'status' => 'success',
                'message' => 'Your PHP version can run SuperHive. PHP version: ' . PHP_VERSION,
            ];
        } else {
            $req = [
                'status' => 'error',
                'message' => 'Please, update your PHP version to run SuperHive. (PHP 8.1 minimum)',
            ];
        }
        $requirements[] = $req;

        /* Check if data folder is writeable */
        $datadir = $this->app->get('datadir');
        if (is_writable($datadir)) {
            $req = [
                'status' => 'success',
                'message' => 'The data folder is writable to store blockchain data.',
            ];
        } else {
            $req = [
                'status' => 'error',
                'message' => 'Make the data folder writable.',
            ];
        }
        $requirements[] = $req;

        return $this->app->get('view')->render($response, '/setup.html', [
            'requirements' => $requirements,
        ]);
    }

    /**
     *  * Install function
     *  *
     * This function dis called after the prepare function to generate the file and store it in ocnfig folder.
     *
     * @param Request $request
     * @param Response $response
     */
    public function install(Request $request, Response $response): Response
    {
        if (!file_exists($this->app->get('password'))) {
            $data = $request->getParsedBody();
            // Create password file with username and password (signed msg))
            if ((isset($data['username'])) && (isset($data['passwd']))) {
                $cred = [$data['username'] => $data['passwd']];
                file_put_contents($this->app->get('password'), serialize($cred));

                // Change account name in config file
                $settings = $this->app->get('settings');
                $settings['author'] = $data['username'];
                $file = json_encode($settings, JSON_PRETTY_PRINT);
                file_put_contents($this->app->get('configfile'), $file);

                $response->getBody()->write('ok');
            }
        } else {
            $response->getBody()->write('notok');
        }

        return $response;
    }
}

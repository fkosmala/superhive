<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Composer\Console\Application;
use Composer\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;

final class InstallController
{
    private $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }
    
    public function prepare(Request $request, Response $response, $args): Response
    {
        $requirements = array();

        // Check if PHP version is ok tu run SuperHive
        if (version_compare(PHP_VERSION, '7.2.0', '>=')) {
            $req = [
                "status" => "success",
                "message" => "Your PHP version can run SuperHive. PHP version: " . PHP_VERSION
            ];
        } else {
            $req = [
                "status" => "error",
                "message" => "Please, update your PHP version to run SuperHive. (PHP 7.2 minimum)"
            ];
        }
        $requirements[] = $req;

        // Check if data folder is writeable
        $datadir = $this->app->get('datadir');
        if (is_writable($datadir)) {
            $req = [
                "status" => "success",
                "message" => "The data folder is writable to store blockchain data."
            ];
        } else {
            $req = [
                "status" => "error",
                "message" => "Make the data folder writable."
            ];
        }
        $requirements[] = $req;

        return $this->app->get('view')->render($response, '/install.html', [
            'requirements' => $requirements
        ]);
    }
    
    public function install(Request $request, Response $response, $args): Response
    {
        if (!file_exists($this->app->get('password'))) {
            $data = $request->getParsedBody();
            // Create password file with  username and password (signed msg))
            $cred = array($data['username'] => $data['passwd']);
            file_put_contents($this->app->get('password'), serialize($cred));
            
            // Changeaccount name in config file
            $settings = $this->app->get('settings');
            $settings['author'] = $data['username'];
            $file = json_encode($settings, JSON_PRETTY_PRINT);
            file_put_contents($this->app->get('configfile'), $file);
            
            $response->getBody()->write('ok');
        } else {
            $response->getBody()->write('notok');
        }
        return $response;
    }
}

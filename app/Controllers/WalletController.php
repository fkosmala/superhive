<?php

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;

use DragosRoua\PHPHiveTools\HiveApi as HiveApi;
use Parsedown;

final class WalletController
{
		
	private $app;

	public function __construct(ContainerInterface $app)
	{
		$this->app = $app;
	}
	
	public function viewWallet(Request $request, Response $response, $args) : Response {
		$settings = $this->app->get('settings');
		$accountFile = $this->app->get('accountfile');
		$bcFile = $this->app->get('datadir').'bcVars.json';
		
		$apiConfig = ["webservice_url" => $settings['api'],"debug" => false];
		$api = new HiveApi($apiConfig);
		$params = [$settings['author']];
		
		$cache_interval = 120;
		$current_time = time();
		if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
			$result = json_encode($api->getAccounts($params), JSON_PRETTY_PRINT);
			file_put_contents($accountFile, $result);
		}
		
		if ((!file_exists($bcFile)) || ($current_time - filemtime($bcFile) > 600)) {
			$bcParams = array();
			$result = json_encode($api->getDynamicGlobalProperties($bcParams), JSON_PRETTY_PRINT);
			file_put_contents($bcFile, $result);
		}
		
		$account = json_decode(file_get_contents($accountFile), true);
		
		// Convert VESTS to HP
		$bcVars = json_decode(file_get_contents($bcFile), true);
		$vests['tvfh'] = (float)$bcVars['total_vesting_fund_hive'];
		$vests['tvs'] = (float)$bcVars['total_vesting_shares'];
		$vests['totalVests'] = $vests['tvfh'] / $vests['tvs'];
		$vests['userHP'] = round((float)$account[0]['vesting_shares'] * $vests['totalVests'], 3);
		$vests['delegHP'] = round((float)$account[0]['delegated_vesting_shares'] * $vests['totalVests'], 3);
		
		
		return $this->app->get('view')->render($response, '/admin/admin-wallet.html', [
			'settings' => $settings,
			'vests' => $vests,
			'blockchain' => $bcVars,
			'account' => $account[0]
		]);
		
	}
}

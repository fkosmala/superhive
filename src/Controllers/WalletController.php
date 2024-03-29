<?php

/**
 *  * Wallet controller
 *  *
 * The file contains all the functions use  to display and
 * manage the user wallet in admin panel
 *
 *  * @category   Controllers
 *  * @package    SuperHive
 *  * @author     Florent Kosmala <kosflorent@gmail.com>
 *  * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 *  */

declare(strict_types=1);

namespace App\Controllers;

use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use Hive\PhpLib\HiveEngine\Account as HeAccount;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;

final class WalletController
{
    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;

        $session = $this->app->get('session');

        $this->app->get('view')->getEnvironment()->addGlobal("user", [
            'author' => $session['sh_author'],
            'signature' => $session['sh_sign'],
        ]);
    }

    /**
     *  * View wallet function
     *  *
     * This function display the wallet with all information of account.
     * Account must be specified in config file or admin index.
     *
     * @since available since Realease 0.4.0
     *
     * @param Response $response
     */
    public function viewWallet(Response $response): Response
    {
        $settings = $this->app->get('settings');
        $accountFile = $this->app->get('accountfile');
        $bcFile = $this->app->get('datadir') . 'bcVars.json';
        $heFile = $this->app->get('datadir') . 'heTokens.json';

        $cache_interval = $settings['delay'];
        $current_time = time();

        /*
         *  Get Hive engine tokens from account
         */
        if ((!file_exists($heFile)) || ($current_time - filemtime($heFile) > $cache_interval)) {
            $config = [
                'debug' => false,
                'heNode' => 'api.hive-engine.com/rpc',
                'hiveNode' => 'anyx.io',
            ];

            $heApi = new HeAccount($config);

            $heResponse = $heApi->getAccountBalance($settings['author']);
            $heResult = json_encode($heResponse, JSON_PRETTY_PRINT);
            file_put_contents($heFile, $heResult);
        }

        $heTokens = json_decode(file_get_contents($heFile), true);

        /*
         * Get HIVE/ HBD & Savings from account
         */
        $apiConfig = [
            'webservice_url' => $settings['api'],
            'debug' => false,
        ];
        $api = new HiveCondenser($apiConfig);

        if ((!file_exists($accountFile)) || ($current_time - filemtime($accountFile) > $cache_interval)) {
            $result = json_encode($api->getAccounts($settings['author']), JSON_PRETTY_PRINT);
            file_put_contents($accountFile, $result);
        }

        if ((!file_exists($bcFile)) || ($current_time - filemtime($bcFile) > ($cache_interval * 2))) {
            $result = json_encode($api->getDynamicGlobalProperties(), JSON_PRETTY_PRINT);
            file_put_contents($bcFile, $result);
        }

        $account = json_decode(file_get_contents($accountFile), true);

        /*
         *  Convert VESTS to HP
         */
        $bcVars = json_decode(file_get_contents($bcFile), true);
        $vests = [];
        $vests['tvfh'] = (float)$bcVars['total_vesting_fund_hive'];
        $vests['tvs'] = (float)$bcVars['total_vesting_shares'];
        $vests['totalVests'] = $vests['tvfh'] / $vests['tvs'];
        $vests['userHP'] = round((float)$account[0]['vesting_shares'] * $vests['totalVests'], 3);
        $vests['delegHP'] = round((float)$account[0]['delegated_vesting_shares'] * $vests['totalVests'], 3);

        /*
         * Just render the view with vars
         */
        return $this->app->get('view')->render($response, '/admin/admin-wallet.html', [
            'settings' => $settings,
            'vests' => $vests,
            'blockchain' => $bcVars,
            'hetokens' => $heTokens,
            'account' => $account[0],
        ]);
    }
}

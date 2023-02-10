<?php

/* Schedule this php file from systemd with following exec cmd
 * /usr/bin/php SendByMailJet.php '/path-to-cwf-config'
 */


require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/MailJetWorker.php');


echo "Starting SendByMailJet: " . date('Y-m-d H:i:s') . PHP_EOL;
$sentryInit = false;
const YII_DEBUG = true;

try {
    
    if ($argc != 2 || !file_exists($argv[1])) {
        echo "Error: Config file path required for cwfconfig. Missing input argument" . PHP_EOL;
        return;
    }
    $cwfConfig = require($argv[1]); 
    // Initialise Sentry
    if (isset($cwfConfig['components']['log']['targets']['sentry'])) {
        \Sentry\init([
            'dsn' => $cwfConfig['components']['log']['targets']['sentry']['dsn'],
            'environment' => $cwfConfig['components']['log']['targets']['sentry']['clientOptions']['environment']
        ]);
        $sentryInit = true;
    }

    $msw = new MailJetWorker($cwfConfig);
    $msw->start();

    echo "Send completed: " . date('Y-m-d H:i:s') . PHP_EOL;

} catch (\Exception $ex) {
    echo "Send Exception: " . date('Y-m-d H:i:s') . PHP_EOL . $ex->getMessage() . PHP_EOL;
    if ($sentryInit) {
        \Sentry\captureException($ex);
    }
}
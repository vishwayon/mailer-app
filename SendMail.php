<?php

/* Schedule this php file from systemd with following exec cmd
 * /usr/bin/php SendMail.php '/path-to-cwf-config'
 */


require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/MailSendWorker.php');


echo date('Y-m-d H:i:s').": Starting sendmail\n";
try {
    
    if ($argc != 2 || !file_exists($argv[1])) {
        echo "Error: Config file path required for cwfconfig. Missing input argument" . PHP_EOL;
        return;
    }
    $cwfConfig = require($argv[1]); 

    $msw = new MailSenderWorker();
    $msw->Start($cwfConfig);

    echo date('Y-m-d H:i:s').": Send completed\n";

} catch (\Exception $ex) {
    echo date('Y-m-d H:i:s').": Exception: ".$ex->getMessage(). "\n";
}
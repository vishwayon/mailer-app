<?php

require 'vendor/autoload.php';
use \Mailjet\Resources;

$mj = new \Mailjet\Client('key', 'secret');
$response = $mj->get(Resources::$Message, ['id' => 'mail-id']);
var_dump($response->getData());

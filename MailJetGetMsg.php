<?php

require 'vendor/autoload.php';
use \Mailjet\Resources;

$mj = new \Mailjet\Client('c53d5c922db9927320da27d762afbca2', '0ffd80670fd2bf1ed7c89a76b6c27feb');
$response = $mj->get(Resources::$Message, ['id' => '576460768532263887']);
var_dump($response->getData());

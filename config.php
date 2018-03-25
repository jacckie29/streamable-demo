<?php

/**
 * 
 * The file contans composer setup for the Google Client Api. 
 * Register with Google to get the Outh2 Client Id and Client Secret Key.
 * (https://cloud.google.com/console)
 * Enable the Youtube Data Api for the registered project.
 * 
 * Initial Setup
 *
 * 1. Install composer (https://getcomposer.org)
 * 2. Change to the working directory
 * 3. Require the google/apiclient library
 *    $ composer require google/apiclient:~2.0
 */

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/vendor/autoload.php';
session_start();

$OAUTH2_CLIENT_ID = '477047949257-p84ckdgnqkbkfmuq2l951alb0qpsbteu.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = '3hrUAjYWtCD51KQwTGxypp-o';

?>
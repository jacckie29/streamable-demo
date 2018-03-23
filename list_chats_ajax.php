<?php

/**
 * 
 * This file retrives the Chat Message for the specified Chat Id
 * 
 * Library Requirements
 *
 * 1. Install composer (https://getcomposer.org)
 * 2. On the command line, change to this directory (api-samples/php)
 * 3. Require the google/apiclient library
 *    $ composer require google/apiclient:~2.0
 */
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/vendor/autoload.php';
session_start();

/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * {{ Google Cloud Console }} <{{ https://cloud.google.com/console }}>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
$OAUTH2_CLIENT_ID = '477047949257-p84ckdgnqkbkfmuq2l951alb0qpsbteu.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = '3hrUAjYWtCD51KQwTGxypp-o';

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();
if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION[$tokenSessionKey])) {
  $client->setAccessToken($_SESSION[$tokenSessionKey]);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
  try {
    // Execute an API request that lists the chat message for the specific liveChatId
    $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($_POST['id'],'snippet,authorDetails', array('maxResults' => '300'));
    $htmlBody = "";
    if($streamsResponse)  {
      $htmlBody = "<ul>";
      foreach ($streamsResponse['items'] as $streamItem) {
        $htmlBody .= sprintf('<li style="list-style:none;"><img height="25" width="25" src="%s"> %s : %s</li>',
            $streamItem['authorDetails']['profileImageUrl'], $streamItem['authorDetails']['displayName'] , $streamItem['snippet']['textMessageDetails']["messageText"]);
      }
      $htmlBody .= '</ul>';
    }
  } catch (Google_Service_Exception $e) {      
    $htmlBody = $e->getErrors()[0]["message"];
  } catch (Google_Exception $e) {
    $htmlBody = $e->getErrors()[0]["message"];
  }

  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} elseif ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
  $htmlBody = "failure";
} else {
    $htmlBody = "failure";
}
echo $htmlBody;
?>
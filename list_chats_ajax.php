<?php

include('config.php');

$inactive = 43200;

if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_destroy();
        header("Location: index.php");
    }
}
$_SESSION['timeout'] = time();

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Create an Object to access the data
$youtube = new Google_Service_YouTube($client);

// Check for the required auth token
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

// Check the auth token
if ($client->getAccessToken()) {
  try {
      /* List the Chat Messages for the Livechatid received from the above broadcast
      *
      *  Authordetails parameter shall indicate the user replying to the chat.
      *  One can determine if the replies are from the Owner, Moderator, etc
      *
      */
    $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($_POST['id'],'snippet,authorDetails', array('maxResults' => '300'));
    $htmlBody = "";
    if($streamsResponse)  {
      $htmlBody = "<ul>";
      foreach ($streamsResponse['items'] as $streamItem) {
          if($streamItem['authorDetails']['isChatOwner'] === true)    {
              $htmlBody .= '<li style="list-style:none;"><img height="25" width="25" src="'.$streamItem['authorDetails']['profileImageUrl'].'"> <b style="color:blue">'. $streamItem['authorDetails']['displayName'].'</b> : '.$streamItem['snippet']['textMessageDetails']["messageText"].'</li>';  
          }
          else {
              $htmlBody .= '<li style="list-style:none;"><img height="25" width="25" src="'.$streamItem['authorDetails']['profileImageUrl'].'"> '. $streamItem['authorDetails']['displayName'].' : '.$streamItem['snippet']['textMessageDetails']["messageText"].'</li>';  
          }
      }
      $htmlBody .= '</ul>';
    }
  } catch (Google_Service_Exception $e) {      
    $htmlBody = $e->getErrors()[0]["message"];
  } catch (Google_Exception $e) {
    $htmlBody = $e->getErrors()[0]["message"];
  }

  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} else {
    $htmlBody = "failure";
}
echo $htmlBody;
?>
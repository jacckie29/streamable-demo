<?php

include('config.php');

$inactive = 43200;

if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_destroy();
        //header("Location: index.php");
        $htmlBody = "failure";
    }
}

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
$onemin = $temponemin = 0;
$chatmoderator = array();
if ($client->getAccessToken()) {
  try {
    // Set the MessageText that needs to be sent
    $messageDetails = new Google_Service_YouTube_LiveChatTextMessageDetails();
    $messageDetails->setMessageText($_POST["message"]);

    // Use the ChatMessageSnippet set the LiveChatId and the  Message Details from above.
    $snippetMessageData = new Google_Service_YouTube_LiveChatMessageSnippet();
    $snippetMessageData->setLiveChatId($_POST["chatId"]);
    $snippetMessageData->setType("textMessageEvent");
    $snippetMessageData->setTextMessageDetails($messageDetails);
    
    // Use the above Snippet to pass the same to LiveChatMessage
    $snippetData = new Google_Service_YouTube_LiveChatMessage();
    $snippetData->setSnippet($snippetMessageData);
    
    // Insert the Message to LiveStreaming Video
    $streamsResponse = $youtube->liveChatMessages->insert('snippet',$snippetData);

    /* List the Chat Messages for the Livechatid received from the above broadcast
        *
        *  Authordetails parameter shall indicate the user replying to the chat.
        *  One can determine if the replies are from the Owner, Moderator, etc
        *
        */
    $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($_POST["chatId"],'snippet,authorDetails', array('maxResults' => '300'));
    
    $htmlBody = "<ul>";
    foreach ($streamsResponse['items'] as $streamItem) {
        $cal = date("i",strtotime($streamItem["snippet"]['publishedAt']));
        
        if(($cal) == $temponemin)  
        {
            $onemin += 1;
        }
        else {
            $onemin = 0;
        }
        $temponemin = $cal;
        if($streamItem['authorDetails']['isChatModerator'] === true && !in_array($streamItem['authorDetails']['channelId'], $chatmoderator))    {
            $chatmoderator[] = $streamItem['authorDetails']['channelId'];
        }
        if($streamItem['authorDetails']['isChatOwner'] === true)    {
            $htmlBody .= '<li style="list-style:none;"><img height="25" width="25" src="'.$streamItem['authorDetails']['profileImageUrl'].'"> <b style="color:blue">'. $streamItem['authorDetails']['displayName'].'</b> : '.$streamItem['snippet']['textMessageDetails']["messageText"].'</li>';  
        }
        else {
            $htmlBody .= '<li style="list-style:none;"><img height="25" width="25" src="'.$streamItem['authorDetails']['profileImageUrl'].'"> '. $streamItem['authorDetails']['displayName'].' : '.$streamItem['snippet']['textMessageDetails']["messageText"].'</li>';  
        }
    }
    $htmlBody .= '</ul>';

  } catch (Google_Service_Exception $e) {      
    $htmlBody = $e->getErrors()[0]["message"];
  } catch (Google_Exception $e) {
    $htmlBody = $e->getErrors()[0]["message"];
  }

  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} else {
    $htmlBody = "failure";
}
$data["onemin"] = $onemin;
$data["htmlBody"] = $htmlBody;
$data["chatmoderator"] = count($chatmoderator);

echo json_encode($data);
?>
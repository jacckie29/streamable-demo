<?php

/**
 * 
 * This file will fetch the chat messages of the publicly available Video Id, that is been currently been stream.
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
$inactive = 43200;

if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_destroy();
        header("Location: index.php");
    }
}
$_SESSION['timeout'] = time();
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
    $htmlBody = "";
    $liveChatId= "";
    $videoId = "";
    $error = "";
    if(isset($_GET["videoId"]))  {
        $params = array();
        $videoId = $_GET["videoId"];
        $params['id'] = $videoId;
        $streamsResponse = $youtube->videos->listVideos("liveStreamingDetails",$params);
        
        if(isset($streamsResponse['items'][0]['liveStreamingDetails']['activeLiveChatId']))
            $liveChatId = $streamsResponse['items'][0]['liveStreamingDetails']['activeLiveChatId'];
        else
            $error = "The Streaming for this Video seems to be down";

        // Execute an API request that lists the chat message for the specific liveChatId
        $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($liveChatId,'snippet,authorDetails', array('maxResults' => '300'));

        $htmlBody = "<ul>";
        foreach ($streamsResponse['items'] as $streamItem) {
            if($streamItem['authorDetails']['isChatOwner'] === true)    {
                $htmlBody .= sprintf('<li style="list-style:none;"><img height="25" width="25" src="%s"> <b style="color:blue">%s</b> : %s</li>',
                $streamItem['authorDetails']['profileImageUrl'], $streamItem['authorDetails']['displayName'] , $streamItem['snippet']['textMessageDetails']["messageText"]);  
            }
            else {
                $htmlBody .= sprintf('<li style="list-style:none;"><img height="25" width="25" src="%s"> %s : %s</li>',
                $streamItem['authorDetails']['profileImageUrl'], $streamItem['authorDetails']['displayName'] , $streamItem['snippet']['textMessageDetails']["messageText"]);
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
} elseif ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
    header('Location: index.php');
} else {
    header('Location: index.php');
}
?>

<!doctype html>
<html>
<head>
<title>Live Chat for the stream</title>
</head>
<body>
<h3>To fetch using your Own Broadcast <a href="list_chats.php">Click Here</a></h3>
<h3>Enter the Live Stream Video Id</h3>
<h5>For eg. https://www.youtube.com/watch?v=L5Xc93_ZL60 where `L5Xc93_ZL60` is VideoId</h5>
<input type="text" name="videoId" id="videoId" value="<?=$videoId?>" />
<input type="button" name="fetchChatId" id="fetchChatId" value="Get Chat Messages" onclick="fetchChatID()"/> 
<?php if($liveChatId != "")  { ?>
    <h3>Live Chats</h3>
    <div id="chatMessage" style="height:400px;overflow:auto;border-style: solid;border-width: 1px;margin-bottom:10px;">
    <?=$htmlBody?>
    </div>
    <b> Send Chat: </b><input type="text" name="sendMessage" id="sendMessage" style="width:500px;" />
    <input type="button" onclick="sendChat('<?=$liveChatId?>');" value="Send">
    <h5>*Note: Owner Marked in bold and blue font</h5>
<?php } ?>
<?php if($error != "")  { ?>
    <h3><?=$error?></h3>
<?php } ?>
<input type="hidden" id="chatId" value="<?=$liveChatId?>" />
</body>
</html>
<script type="text/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script type="text/javascript">

$( document ).ready(function() {
    setInterval(function(){ fetchChats(); }, 10000);
    document.getElementById('chatMessage').scrollTop = 9999999;
});

function fetchChatID()   {
    id = $("#videoId").val();
    if(id != "")    {
        window.location.href = "list_chats_video.php?videoId="+id;
    }
    else {
        location.href = "list_chats.php";
    }
}

function fetchChats() {
    if($("#chatId").val() != "")  {
        id = $("#chatId").val();
        $.ajax({
            url: "list_chats_ajax.php",
            method: "POST",
            data: { id: id}
        }).done(function( data ) {
            if(data == "failure")
            {
                window.location.href = "index.php";
            }
            else {
                $("#chatMessage").html(data);
            }
        });
    }
}

function sendChat(chatId) {
    message = $("#sendMessage").val().trim();
    if(message != "" && chatId != "")   {
        clearInterval();
        $.ajax({
            method: "POST",
            url: "send_chat.php",
            data: { message: message, chatId: chatId}
        }).done(function( data ) {
            if(data == "failure")
            {
                window.location.href = "index.php";
            }
            else {
                $("#chatMessage").html(data);
                $("#sendMessage").val("");
            }
        });
        setInterval(function(){ fetchChats(); }, 10000);
    }
}
</script>
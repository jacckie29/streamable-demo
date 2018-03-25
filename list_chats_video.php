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
    $htmlBody = "";
    $liveChatId= "";
    $videoId = "";
    $error = "";
    if(isset($_GET["videoId"]))  {
        $params = array();
        $videoId = $_GET["videoId"];
        $params['id'] = $videoId;

        // Get the video detials for the specific Video Id. Pass the `liveStreamingDetials` paramter along with Video Id to get the LivechatId
        $streamsResponse = $youtube->videos->listVideos("liveStreamingDetails",$params);
        
        if(isset($streamsResponse['items'][0]['liveStreamingDetails']['activeLiveChatId']))
            $liveChatId = $streamsResponse['items'][0]['liveStreamingDetails']['activeLiveChatId'];
        else
            $error = "The Streaming for this Video seems to be down";

        /* List the Chat Messages for the Livechatid received from the above broadcast
        *
        *  Authordetails parameter shall indicate the user replying to the chat.
        *  One can determine if the replies are from the Owner, Moderator, etc
        *
        */
        $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($liveChatId,'snippet,authorDetails', array('maxResults' => '300'));

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
        location.href = "list_chats_video.php";
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
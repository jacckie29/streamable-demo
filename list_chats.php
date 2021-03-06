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
$htmlBody = "";
$liveChatId = "";
$onemin = $temponemin = 0;
$chatmoderator = array();
if ($client->getAccessToken()) {
  try {
    // List the Broadcasts owned by the user who has authorized to get the livechatid
    $broadcastsResponse = $youtube->liveBroadcasts->listLiveBroadcasts(
        'id,snippet',
        array(
            'mine' => 'true',
        ));

    $broadcastBody = "<option value=''>---Select---</option>";
    foreach ($broadcastsResponse['items'] as $broadcastItem) {
      if(isset($broadcastItem['snippet']['liveChatId']))  {
        if(isset($_GET["liveChatId"]))   {
            if($_GET["liveChatId"] == $broadcastItem['snippet']['liveChatId'])  {
                $broadcastBody .= '<option value="'.$broadcastItem['snippet']['liveChatId'].'" selected>'.$broadcastItem['snippet']['title'].'</option>';
            }
            else {
                $broadcastBody .= '<option value="'.$broadcastItem['snippet']['liveChatId'].'">'.$broadcastItem['snippet']['title'].'</option>';
            }
        }
        else {
            $broadcastBody .= '<option value="'.$broadcastItem['snippet']['liveChatId'].'">'.$broadcastItem['snippet']['title'].'</option>';
        }
      }
    }

    if(isset($_GET["liveChatId"]))  {
        $liveChatId = $_GET["liveChatId"];
        /* List the Chat Messages for the Livechatid received from the above broadcast
        *
        *  Authordetails parameter shall indicate the user replying to the chat.
        *  One can determine if the replies are from the Owner, Moderator, etc
        *
        */
        $streamsResponse = $youtube->liveChatMessages->listLiveChatMessages($liveChatId,'snippet,authorDetails', array('maxResults' => '300'));

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
<h3>To fetch using Video Id <a href="list_chats_video.php">Click Here</a></h3>

<h3>Active Broadcast:</h3>
<p>No. of messages in last one min: <span id="onemin"><?=$onemin?></span></p>
<p>Moderators active in chat: <span id="chatmoderator"><?=count($chatmoderator)?></span></p>
<select name="broadcast" id="broadcast" onChange="fetchBroadcast();">
<?=$broadcastBody?>
</select>
<?php if($liveChatId != "")  { ?>
    <h3>Live Chats</h3>
    <div id="chatMessage" style="height:400px;overflow:auto;border-style: solid;border-width: 1px;margin-bottom:10px;">
    <?=$htmlBody?>
    </div>
    <b> Send Chat: </b><input type="text" name="sendMessage" id="sendMessage" style="width:500px;" />
    <input type="button" onclick="sendChat('<?=$liveChatId?>');" value="Send"></button>
    <h5>*Note: Owner Marked in bold and blue font</h5>
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

function fetchBroadcast()   {
    id = $("#broadcast").val();
    if(id != "")    {
        window.location.href = "list_chats.php?liveChatId="+id;
    }
    else {
        location.href = "list_chats.php";
    }
}

function fetchChats() {
    if($("#chatId").val() == null)  {
        id = $("#chatId").val();
        $.ajax({
            url: "list_chats_ajax.php",
            method: "POST",
            data: { id: id}
        }).done(function( data ) {
            response = JSON.parse(data);
            if(response.htmlBody == "failure")
            {
                window.location.href = "index.php";
            }
            else {
                $("#chatMessage").html(response.htmlBody);
                $("#onemin").html(response.onemin);
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
            response = JSON.parse(data);
            if(response.htmlBody == "failure")
            {
                window.location.href = "index.php";
            }
            else {
                $("#chatMessage").html(response.htmlBody);
                $("#onemin").html(response.onemin);
                $("#sendMessage").val("");
            }
        });
        setInterval(function(){ fetchChats(); }, 10000);
    }
}
</script>
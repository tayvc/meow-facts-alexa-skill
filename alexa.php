<?php

include_once("AlexaRequest.php");

$config = json_decode(file_get_contents("config.json"), TRUE);
$jsonRequest = file_get_contents('php://input');

$alexaRequest = new AlexaRequest($config);

$response = $alexaRequest->getResponse($jsonRequest);

if($response === false) {
   http_response_code(400);
   exit;

} else {
    echo $response;
}


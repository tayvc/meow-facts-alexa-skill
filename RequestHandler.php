<?php

include_once("speech.php");

class RequestHandler {

    public $config;

    public $intentMap = array(
        'GetCatFact' => 'GetCatFact',
        'AMAZON.CancelIntent' => 'CancelIntent',
        'AMAZON.StopIntent' => 'CancelIntent'
    );

    public function __construct($config) {
        // Set the config file to access while handling the Alexa request.
        $this->config = $config;
        $this->speech = new Speech($config);
    }

    /**
     * Function handleRequest
     * @param $request
     * @return $response
     */
    public function handleRequest($request) {
        // Handle the request from Alexa based on its type and intent.
        $requestType = $request['request']['type'];

        // LaunchRequest indicates the user invoked the skill without any command mapping to an intent.
        if($requestType == 'LaunchRequest') {
            $ssml = true;
            $prompt = '<speak>'.addslashes($this->config['welcomePrompt']).'</speak>';
            // Return speech output to the user, telling them to ask for a cat fact.
            return $this->speech->speechOut($ssml, $prompt);

        } elseif($requestType == 'IntentRequest') {
            $requestName = $request['request']['intent']['name'];
            // IntentRequest indicates a command that maps to an intent.
            return call_user_func(array($this, $this->intentMap[$requestName]), $request);

        } elseif($requestType == 'SessionEndedRequest') {
            // SessionEndedRequest ends the current session.
            return $this->speech->endSession(false, 'Thank you for using '.$this->config['invocation']);

        }
    }

    /**
     * Function GetCatFact
     * Request to hear a random cat fact from our configured API.
     * @param $request
     * @return $response
     */
    public function GetCatFact($request) {
        //Grab our configured API to request a fact from
        $link = $this->config['apiLink'];
        $output = file_get_contents($link);
        $json = json_decode($output, true);

        if(isset($json['fact']) && !empty($json['fact'])) {
            // Return a cat fact to the user
            return $this->speech->speechOut($json['fact']);
        } else {
            // Unable to return a fact, speak out an error message
            return $this->speech->endSession(false, "Sorry, I could not find a cat fact. Please try again.");
        }
    }
    
    /**
     * Function CancelIntent
     * Request to exit out of the current session.
     * @param $request
     * @return $response
     */
    public function CancelIntent($request) {
        $ssml = false;
        return $this->speech->endSession($ssml, 'Thank you for using '.$this->config['invocation']);
    }

}

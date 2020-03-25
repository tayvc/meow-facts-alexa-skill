<?php

class Speech {

    public $config;

    public function __construct($config) {
        // Set the config file to access while handling the Alexa request.
        $this->config = $config;
    }

    /**
     * Function speechOut
     * Creates an outputSpeech object to send Alexa a command
     * to output speech.
     *
     * @param $ssml boolean
     * @param $words
     * @return outputSpeech response
     */
    public function speechOut($ssml, $words) {
        header('Content-type: application/json');

        if($ssml) {
            $value = '{
                        "version": "1.0",
                        "sessionAttributes":"",
                        "response":{
                            "outputSpeech":{
                                "type": "SSML",
                                "ssml": "'.$words.'"
                            },
                            "shouldEndSession": false
                        }
                    }';
        } else {
            $value = '{
                        "version": "1.0",
                        "sessionAttributes":"",
                        "response":{
                            "outputSpeech":{
                                "type": "PlainText",
                                "text": "'.$words.'"
                            },
                            "shouldEndSession": false
                        }
                    }';
        }

        return $value;
    }
    
    /**
     * Function endSession
     * Creates an outputSpeech object to send Alexa a command
     * to output speech and end the current session.
     *
     * @param $words
     * @return outputSpeech response
     */
    public function endSession($ssml, $words) {
        header('Content-type: application/json');

        if($ssml) {
            $value = '{
                        "version": "1.0",
                        "sessionAttributes":"",
                        "response":{
                            "outputSpeech":{
                                "type": "SSML",
                                "ssml": "'.$words.'"
                            },
                            "shouldEndSession": true
                        }
                    }';
        } else {
            $value = '{
                        "version": "1.0",
                        "sessionAttributes":"",
                        "response":{
                            "outputSpeech":{
                                "type": "PlainText",
                                "text": "'.$words.'"
                            },
                            "shouldEndSession": true
                        }
                    }';
        }

        return $value;
    }

}

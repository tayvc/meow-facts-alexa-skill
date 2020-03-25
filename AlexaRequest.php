<?php

include_once("RequestHandler.php");

class AlexaRequest {

    public $config;

    public function __construct($config) {
        // Set the config file to access while validating the Alexa request.
        $this->config = $config;
        $this->requestHandler = new RequestHandler($config);
    }

    public function getResponse($request) {
        // Get the Alexa request to validate and handle for sending back a response.
        $success = $this->validate($request);

        if($success) {
            // Request is from verified Alexa skill.
            $decodedRequest = json_decode($request, TRUE);

            return $this->requestHandler->handleRequest($decodedRequest);
        } else {
            return false;
        }
    }

    /**
     * Function validate
     * Validate request from Alexa based on Amazon API 
     * documentation. Request signature MUST be signed 
     * by Alexa and the request timestamp MUST be verified.
     * If the timestamp is in the past, this is an old 
     * request being sent as a "replay" attack.
     * @param $jsonRequest
     * @return boolean
     *
     */
    public function validate($jsonRequest) {
        // Decode JSON request from Alexa
        $request = json_decode($jsonRequest, TRUE);
        // Set error log to output any validation errors that occur.
        $errorLog = $this->config['amazonLogFolder'];
        date_default_timezone_set('America/Chicago');
        $date = date('Y-m-d H:i:s');

        // Verify the config file is in proper JSON format
        if(empty($this->config)) {
            error_log($date." Validation Error: Config file is not in proper JSON format.\n", 3, $errorLog);
            return false;
        }

        // Verify the interface is not the command line
        if(php_sapi_name() === 'cli') {
            error_log($date." Validation Error: Request came from command line.\n", 3, $errorLog);
            return false;
        }

        // Validate POST request
        if($_SERVER['REQUEST_METHOD'] == 'GET') {
            // HTTP GET when POST was expected
            error_log($date." Validation Error: HTTP GET when POST was expected.\n", 3, $errorLog);
            return false;
        }

        // Validate public IP address space
        if(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            if(!empty($request['session']['sessionId'])) {
                $sessionId = $request['session']['sessionId'];
                $applicationId = $request['session']['application']['applicationId'];
                $userId = $request['session']['user']['userId'];
            } else {
                $applicationId = $request['context']['System']['application']['applicationId'];
                $userId = $request['context']['System']['user']['userId'];
            }

            if(!is_array($request)) {
                error_log($date." Validation Error: Invalid Alexa request data.\n", 3, $errorLog);
                return false;
            }

            // Validate Application ID
            if($applicationId !== $this->config['amazonSkillId']) {
                error_log($date." Validation Error: Invalid Application ID - ".$applicationId."\n", 3, $errorLog);
                $debug = var_export($request, true);
                return false;
            }

            // Determine if we need to download a new Signature Certificate Chain from Amazon
            $md5 = md5($_SERVER['HTTP_SIGNATURECERTCHAINURL']);
            $md5pem = $md5.'.pem';

            // If we haven't received a certificate with this URL before, store it as a cached copy
            if(!file_exists($this->config['amazonCacheFolder'])) {
                mkdir($this->config['amazonCacheFolder'], 0775);
            }

            if(!file_exists($this->config['amazonCacheFolder'].'/'.$md5pem)) {
                file_put_contents($this->config['amazonCacheFolder'].'/'.$md5pem, file_get_contents($_SERVER['HTTP_SIGNATURECERTCHAINURL']));
            }

            // Validate proper format of Amazon provided certificate chain URL
            $validKeychain = $this->validateKeychainUri($_SERVER['HTTP_SIGNATURECERTCHAINURL']);
            if(!$validKeychain) {
                return false;
            } 

            // Validate certificate chain and signature
            $pem = file_get_contents($this->config['amazonCacheFolder'].'/'.$md5pem);
            // Check that an HTTP signature was passed in the request.
            if(isset($_SERVER['HTTP_SIGNATURE'])) {
                $sslCheck = openssl_verify($jsonRequest, base64_decode($_SERVER['HTTP_SIGNATURE']), $pem);
                if($sslCheck != 1) {
                    return false;
                }
            } else {
                return false;
            }

            // Parse certificate
            $parsedCertificate = openssl_x509_parse($pem);
            if(!$parsedCertificate) {
                error_log($date." Validation Error: x509 certificate parsed failed.\n", 3, $errorLog);
                return false;
            }

            // Check that the domain echo-api.amazon.com
            // is present in the Subject Alternative Names
            // (SANs) section of the signing certificate
            if(strpos($parsedCertificate['extensions']['subjectAltName'], $this->config['amazonEchoServiceDomain']) === false) {
                error_log($date." Validation Error: subjectAltName check failed.\n", 3, $errorLog);
                return false;
            }

            // Check that the signing certificate has not expired
            // Examine both the Not Before and Not After dates
            $validFrom = $parsedCertificate['validFrom_time_t'];
            $validTo = $parsedCertificate['validTo_time_t'];

            $now = new DateTime();
            $time = $now->getTimestamp();
            if(!($validFrom <= $time && $time <= $validTo)) {
                error_log($date." Validation Error: Certificate expiration check failed.\n", 3, $errorLog);
                return false;
            }

            // Check the timestamp of the request and ensure it
            // was within the past minute.
            $alexaRequestTimestamp = $request['request']['timestamp'];
            if($now->getTimestamp() - strtotime($alexaRequestTimestamp) > 60) {
                // Timestamp validation failure
                error_log($date." Validation Error: The request was not sent within the past minute.\n", 3, $errorLog);
                return false;
            }

            // Successfully passed validation test.
            return true;

        } else {
            error_log($date." Validation Error: Request does not originate from public IP space.\n", 3, $errorLog);
            return false;
        }

    }

    /**
     * Function validateKeychainUri
     * Validates the host for the certificate
     * provided in the header is from amazon.
     * @param $keychainUri
     * @return boolean
     */
    public function validateKeychainUri($keychainUri) {
        $uriParts = parse_url($keychainUri);

        $errorLog = $this->config['amazonLogFolder'];
        date_default_timezone_set('America/Chicago');
        $date = date('Y-m-d H:i:s');

        if(strcasecmp($uriParts['host'], 's3.amazonaws.com') != 0) {
            error_log($date." Validation Error: The host for the Certificate provided in the header is invalid.\n", 3, $errorLog);
            return false;
        }

        if(strpos($uriParts['path'], '/echo.api/') !== 0) {
            error_log($date." Validation Error: The URL path for the Certificate provided in the header is invalid.\n", 3, $errorLog);
            return false;
        }

        if(strcasecmp($uriParts['scheme'], "https")) {
            // The URL is using an unspoorted scheme.
            error_log($date." Validation Error: The URL is not served from https.\n", 3, $errorLog);
            return false;
        }

        if(array_key_exists('port', $uriParts) && $uriParts['port'] != '443') {
            error_log($date." Validation Error: The URL is using an unsupported https port.\n", 3, $errorLog);
            return false;
        }

        return true;
    }
}
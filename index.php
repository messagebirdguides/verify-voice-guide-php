<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

// Register Twig component on container to use view templates
$container['view'] = function() {
    return new Slim\Views\Twig('views');
};

// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};

// Start of security process: capture number
$app->get('/', function($request, $response) {
    return $this->view->render($response, 'start.html.twig', []);
});

// Verification initiation step
$app->post('/verify', function($request, $response) {
    $country_code = $request->getParsedBodyParam('country_code');
    $phone_number = $request->getParsedBodyParam('phone_number');

    // Compose number from country code and number
    $number = $country_code .
        ($phone_number[0] == '0' ? substr($phone_number, 1) : $phone_number);
    
    // Prepare verification object
    $verify = new MessageBird\Objects\Verify;
    $verify->recipient = $number;
    $verify->type = 'tts'; // TTS = text-to-speech, otherwise API defaults to SMS
    $verify->template = "Your account security code is %token.";

    try {
        // Send request with MessageBird Verify API
        $verifyResponse = $this->messagebird->verify->create($verify);

        // API request was successful, call is on its way
        return $this->view->render($response, 'verify.html.twig', [
            'id' => $verifyResponse->getId()
        ]);
    } catch (Exception $e) {
        // Something went wrong
        error_log(get_class($e).": ".$e->getMessage());
        return $this->view->render($response, 'start.html.twig', [
            'error' => "Could not initiate call."
        ]);
    }
});

// Confirmation step
$app->post('/confirm', function($request, $response) {
    $id = $request->getParsedBodyParam('id');
    $token = $request->getParsedBodyParam('token');

    try {
        // Complete verification request with MessageBird Verify API
        $this->messagebird->verify->verify($id, $token);

        // Confirmation was successful
        return $this->view->render($response, 'confirm.html.twig', []);
    } catch (Exception $e) {
        // Something went wrong
        error_log(get_class($e).": ".$e->getMessage());
        return $this->view->render($response, 'start.html.twig', [
            'error' => "Verification has failed. Please try again."
        ]);
    }
});

// Start the application
$app->run();
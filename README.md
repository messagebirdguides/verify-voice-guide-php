# Account Security with Voice
### ⏱ 15 min build time 

## Why build voice-based account security?

Websites where users can sign up for an account typically use the email address as a unique identifier and a password as a security credential for users to sign in. At the same time, most websites ask users to add a verified phone number to their profile. Phone numbers are, in general, a better way to identify an account holder as a real person. They can also be used as a second authentication factor (2FA) or to restore access to a locked account.

Verification of a phone number is straightforward:
1. Ask your user to enter their number.
2. Call the number programmatically and use a text-to-speech system to say a security code that acts as a one-time-password (OTP).
3. Let the user enter this code on the website or inside an application as proof that they received the call.

The MessageBird Verify API assists developers in implementing this workflow into their apps. Imagine you're running a social network and want to verify your users' profiles. This MessageBird Developer Guide shows you an example of a PHP application with integrated account security following the steps outlined above.

By the way, it is also possible to replace the second step with an SMS message, as we explain the this [two factor authentication guide](https://developers.messagebird.com/guides/verify). However, using voice for verification has the advantage that it works with every phone number, not just mobile phones, so it can be used to verify, for example, the landline of a business. The [MessageBird Verify API](https://developers.messagebird.com/docs/verify) supports both options; voice and SMS.

## Getting Started

The sample application is built in PHP using the [Slim](https://packagist.org/packages/slim/slim) framework. You can download or clone the complete source code from [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/verify-voice-guide-php) to run the application on your computer and follow along with the guide. To run the sample, you need to have PHP and Composer set up. PHP is already installed on Macs and available through the default package manager of most Linux distributions. Windows users can [download it from windows.php.net](https://windows.php.net/download/). Composer is available from [getcomposer.org](https://getcomposer.org/download/).

Let's now open the directory where you've stored the sample code and run the following command to install the [MessageBird SDK](https://www.npmjs.com/package/messagebird) and other dependencies:

````bash
composer install
````

## Configuring the MessageBird SDK

The MessageBird SDK is defined in `composer.json`:

````javascript
{
    // [...]
    "require": {
        // [...]
        "messagebird/php-rest-api" : "^1.9.4"
    },
    // [...]
}
````

An application can access the SDK, which is made available through Composer autoloading, by creating an instance of the `MessageBird\Client` class. The constructor takes a single argument, your API key. For frameworks like Slim you can add the SDK to the dependency injection container:

````php
// Load and initialize MessageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};
````
As you can see in the code example above, the API key is loaded from an environment variable called MESSAGEBIRD_API_KEY. With [dotenv](https://packagist.org/packages/vlucas/phpdotenv) you can define these variables in a `.env` file. We've prepared an `env.example` file in the repository, which you should rename to `.env` and add the required information. Here's an example:

````env
MESSAGEBIRD_API_KEY=YOUR-API-KEY
````

You can create or retrieve a live API key from the [API access (REST) tab](https://dashboard.messagebird.com/en/developers/access) in the _Developers_ section of your MessageBird account.

## Asking for the Phone number

The sample application contains a form to collect the user's phone number. You can see the HTML as a Twig template in the file `views/start.html.twig` and the route that renders it is `get('/')`. All Twig files use the layout stored in `views/layout.html.twig` to follow a common structure.

The HTML form includes a `<select>` element as a drop-down to choose the country. That allows users to enter their phone number without the country code. In production applications, you could use this to limit access on a country level and preselect the user's current country by IP address. The form field for the number is a simple `<input>` with the `type` set to `tel` to inform compatible browsers that this is an input field for telephone numbers. Finally, there's a submit button. Once the user clicks on that button, the input is sent to the `/verify` route.

## Initiating the Verification Call

When the user submits their submit, the `post('/verify')` routes takes over. The Verify API expects the user's telephone number to be in international format, so the first step is reading the input and concatenating the country code and the number. If the user enters their local number with a leading zero, we remove this digit.

````php
$app->post('/verify', function($request, $response) {
    $country_code = $request->getParsedBodyParam('country_code');
    $phone_number = $request->getParsedBodyParam('phone_number');

    // Compose number from country code and number
    $number = $country_code .
        ($phone_number[0] == '0' ? substr($phone_number, 1) : $phone_number);
````

Next, we can create a `MessageBird\Objects\Verify` object that encapsulates the information that is necessary to initiate the verification call.

````php
    // Prepare verification object
    $verify = new MessageBird\Objects\Verify;
    $verify->recipient = $number;
    $verify->type = 'tts'; // TTS = text-to-speech, otherwise API defaults to SMS
    $verify->template = "Your account security code is %token.";
````

The object has three attributes:
- The `recipient` is the telephone number that we want to verify.
- The `type` is set to `tts` to inform the API that we want to use a voice call for verification.
- The `template` contains the words to speak. It must include the placeholder `%token` so that MessageBird knows where the code goes (note that we use the words token and code interchangeably, they mean the same thing). We don't have to generate this numeric code ourselves; the API takes care of it.

There are a few other available options. For example, you can change the length of the code (it defaults to 6) with `tokenLength`. You can also specify `voice` as `male` or `female` and set the `language` to an ISO language code if you want the synthesized voice to be in a non-English language. You can find more details about these and other options in the [Verify API reference documentation](https://developers.messagebird.com/docs/verify#request-a-verify).

Once the object has been created, we can send it through the API using the `verify->create()` method on the SDK:

````php
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
````

As you can see, the API request is placed inside a try-catch block. If the API does not throw any exception, we can assume our request was successful. In this case, we render a new template. We add the `id` attribute of the API response (using the `getId()` method) to this template because we need the identification of our verification request in the next step to confirm the code.

If there was an error and the application ends up in the catch block, we show the same page to the user as before but add an error parameter which the template displays as a message above the form to notify the user. We also log the raw output to assist with debugging.

## Confirming the Code

The template stored in `views/verify.twig.html`, which we render in the success case, contains an HTML form with a hidden input field to pass forward the `id` from the verification request. It also contains a regular `<input>` with `type` set to `text` so that the user can enter the code that they've heard on the phone. When the user submits this form, it sends this token to the `/confirm` route.

Inside this route, we call another method on the MessageBird SDK, `verify->verify()` and provide the ID and token as two parameters:

````php
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
````

Just as before, the API request is contained in a try-catch block. We inform the user about the status of the verification by showing either a new success response which is stored in `views/confirm.handlebars`, or showing the first page again with an error message. In production applications, you would use the success case to update your user's phone number as verified in your database.

## Testing the Application

Let's go ahead and test your application. All we need to do is enter the following command in your console:

````bash
php -S 0.0.0.0:8080 index.php
````

Open your browser to http://localhost:8080/ and walk through the process yourself!

## Nice work!

You now have a running integration of MessageBird's Verify API!

You can now leverage the flow, code snippets and UI examples from this tutorial to build your own voice-based account security system. Don't forget to download the code from the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/verify-voice-guide).

## Next steps

Want to build something similar but not quite sure how to get started? Please feel free to let us know at support@messagebird.com, we'd love to help!
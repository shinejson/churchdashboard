// config/sms_config.php
<?php
return [
    'provider' => 'twilio', // or 'africastalking'
    
    // Twilio
    'twilio_sid'    => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'twilio_token'  => 'your_auth_token',
    'twilio_from'   => '+1234567890', // Your Twilio number

    // Africa's Talking (Ghana, Nigeria, Kenya, etc.)
    'at_username'   => 'your_username',
    'at_api_key'    => 'your_api_key',
    'at_from'       => 'GRACECHURCH', // Shortcode or Sender ID
];
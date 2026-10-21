// backend/lib/SMS.php
<?php
class SMS {
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../../config/sms_config.php';
    }

    public function send($to, $message) {
        $to = preg_replace('/\D/', '', $to); // Clean phone
        if (strlen($to) < 10) return false;

        // Add country code if missing (Ghana example)
        if (!str_starts_with($to, '233')) {
            $to = '233' . ltrim($to, '0');
        }
        $to = '+'.$to;

        if ($this->config['provider'] === 'twilio') {
            return $this->sendTwilio($to, $message);
        } else {
            return $this->sendAfricasTalking($to, $message);
        }
    }

    private function sendTwilio($to, $message) {
        $sid    = $this->config['twilio_sid'];
        $token  = $this->config['twilio_token'];
        $from   = $this->config['twilio_from'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";

        $data = http_build_query([
            'To'   => $to,
            'From' => $from,
            'Body' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result)->sid ?? false;
    }

    private function sendAfricasTalking($to, $message) {
        $username = $this->config['at_username'];
        $apiKey   = $this->config['at_api_key'];
        $from     = $this->config['at_from'];

        $url = 'https://api.africastalking.com/version1/messaging';

        $postData = [
            'username' => $username,
            'to'      => $to,
            'message' => $message,
            'from'    => $from
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apiKey: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($response);
        return $res->SMSMessageData->Recipients[0]->status ?? false;
    }
}
<?php
/**
 * SMS Service
 * Handles sending SMS via Twilio or Africa's Talking
 */

class SMSService {
    private $provider;
    private $config;
    
    public function __construct() {
        // Load SMS configuration
        $configFile = __DIR__ . '/../config/sms_config.php';
        if (file_exists($configFile)) {
            $this->config = include $configFile;
        } else {
            $this->config = [
                'provider' => getenv('SMS_PROVIDER') ?: 'twilio'
            ];
        }
        
        $this->provider = $this->config['provider'] ?? 'twilio';
    }
    
    /**
     * Send SMS message
     */
    public function send($phone, $message) {
        try {
            // Validate phone number
            if (!$this->isValidPhone($phone)) {
                throw new Exception("Invalid phone number: $phone");
            }
            
            // Format phone number (add country code if needed)
            $phone = $this->formatPhoneNumber($phone);
            
            // Send based on provider
            switch ($this->provider) {
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'africastalking':
                    return $this->sendViaAfricasTalking($phone, $message);
                default:
                    throw new Exception("Unsupported SMS provider: {$this->provider}");
            }
        } catch (Exception $e) {
            error_log("SMS Send Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send via Twilio API
     */
    private function sendViaTwilio($phone, $message) {
        $sid = $this->config['twilio_sid'] ?? getenv('TWILIO_SID');
        $token = $this->config['twilio_token'] ?? getenv('TWILIO_TOKEN');
        $from = $this->config['twilio_from'] ?? getenv('TWILIO_FROM');
        
        if (!$sid || !$token || !$from) {
            throw new Exception("Twilio credentials not configured");
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        
        $postData = [
            'From' => $from,
            'To' => $phone,
            'Body' => $message
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            error_log("Twilio SMS sent successfully. SID: " . ($result['sid'] ?? 'N/A'));
            return true;
        } else {
            throw new Exception("Twilio API error: HTTP $httpCode - $response");
        }
    }
    
    /**
     * Send via Africa's Talking API
     */
    private function sendViaAfricasTalking($phone, $message) {
        $username = $this->config['at_username'] ?? getenv('AT_USERNAME');
        $apiKey = $this->config['at_api_key'] ?? getenv('AT_API_KEY');
        $from = $this->config['at_from'] ?? 'GRACECHURCH';
        
        if (!$username || !$apiKey) {
            throw new Exception("Africa's Talking credentials not configured");
        }
        
        $url = "https://api.sandbox.africastalking.com/version1/messaging";
        
        $postData = [
            'username' => $username,
            'to' => $phone,
            'message' => $message,
            'from' => $from
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apiKey: $apiKey",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            error_log("Africa's Talking SMS sent successfully. Response: " . json_encode($result));
            return true;
        } else {
            throw new Exception("Africa's Talking API error: HTTP $httpCode - $response");
        }
    }
    
    /**
     * Validate phone number format
     */
    private function isValidPhone($phone) {
        // Remove common separators
        $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
        
        // Check if it contains only digits
        return preg_match('/^\d{10,15}$/', $phone) === 1;
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        // Remove common separators
        $phone = preg_replace('/[\s\-\(\).]/', '', $phone);
        
        // Add country code if not present
        if (substr($phone, 0, 1) !== '+') {
            if (substr($phone, 0, 1) === '0') {
                // Likely a local number, prepend country code (assuming Ghana +233)
                $phone = '+233' . substr($phone, 1);
            } else if (substr($phone, 0, 2) !== '44' && substr($phone, 0, 1) !== '+') {
                // Add default country code
                $phone = '+233' . $phone;
            }
        }
        
        return $phone;
    }
}
?>

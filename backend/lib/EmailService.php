<?php
/**
 * Email Service
 * Handles sending emails via SMTP or mail() function
 */

class EmailService {
    private $senderEmail;
    private $senderName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;
    private $useSmtp;
    
    public function __construct() {
        // Load .env file if present (project root) so SMTP values can be set without system env vars
        $this->loadDotEnv(__DIR__ . '/../../.env');

        // Try to use SMTP if configured, otherwise fall back to mail()
        $this->senderEmail = getenv('SMTP_FROM_EMAIL') ?: 'shineakakpo08@gmail.com';
        $this->senderName = getenv('SMTP_FROM_NAME') ?: 'Grace Community Church';

        $this->smtpHost = getenv('SMTP_HOST');
        $this->smtpPort = getenv('SMTP_PORT') ?: 587;
        $this->smtpUser = getenv('SMTP_USER');
        $this->smtpPass = getenv('SMTP_PASS');
        $this->smtpSecure = getenv('SMTP_SECURE') ?: 'tls';

        $this->useSmtp = !empty($this->smtpHost);

        // Try to include Composer autoload if available to enable PHPMailer
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            @require_once $autoload;
        }
    }
    

    /**
     * Load a simple .env file (KEY=VALUE lines). Does not depend on external libs.
     */
    private function loadDotEnv($path) {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    /**
     * Send an email
     */
    public function send($to, $subject, $htmlBody, $textBody = null) {
        try {
            if ($this->useSmtp && class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                return $this->sendViaPHPMailer($to, $subject, $htmlBody, $textBody);
            }

            if ($this->useSmtp && !class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                // PHPMailer not installed; attempt best-effort via mail() with SMTP-like headers
                error_log("PHPMailer not available; falling back to mail() for SMTP host: {$this->smtpHost}");
                return $this->sendViaSMTP($to, $subject, $htmlBody, $textBody);
            }

            return $this->sendViaMailFunction($to, $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send via PHP mail() function
     */
    private function sendViaMailFunction($to, $subject, $htmlBody, $textBody = null) {
        $headers = "From: {$this->senderName} <{$this->senderEmail}>\r\n";
        $headers .= "Reply-To: {$this->senderEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Church Management System\r\n";
        
        return mail($to, $subject, $htmlBody, $headers);
    }
    
    /**
     * Send via SMTP (using fsockopen)
     */
    private function sendViaSMTP($to, $subject, $htmlBody, $textBody = null) {
        // Create MIME boundary
        $boundary = "boundary_" . md5(time());
        
        // Prepare headers
        $headers = "From: {$this->senderName} <{$this->senderEmail}>\r\n";
        $headers .= "Reply-To: {$this->senderEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: Church Management System\r\n";
        
        // Prepare body with both text and HTML
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody ?: strip_tags($htmlBody);
        $body .= "\r\n\r\n";
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody;
        $body .= "\r\n\r\n";
        
        $body .= "--{$boundary}--\r\n";
        
        // For environments without PHPMailer, attempt to send using mail() with constructed multipart body
        // This is best-effort; recommended to install PHPMailer and configure SMTP.
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send using PHPMailer via SMTP
     */
    private function sendViaPHPMailer($to, $subject, $htmlBody, $textBody = null) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = !empty($this->smtpUser);
            if (!empty($this->smtpUser)) {
                $mail->Username = $this->smtpUser;
                $mail->Password = $this->smtpPass;
            }
            $mail->SMTPSecure = $this->smtpSecure ?: 'tls';
            $mail->Port = (int)$this->smtpPort;

            // Recipients
            $mail->setFrom($this->senderEmail, $this->senderName);
            // Support multiple recipients separated by comma
            $tos = is_array($to) ? $to : explode(',', $to);
            foreach ($tos as $recipient) {
                $recipient = trim($recipient);
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($recipient);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate email address
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // Public getters for config (used externally for logging/debug)
    public function getSmtpHost() {
        return $this->smtpHost;
    }

    public function getSmtpPort() {
        return $this->smtpPort;
    }

    public function getSmtpUser() {
        return $this->smtpUser;
    }

    public function getSenderEmail() {
        return $this->senderEmail;
    }
}
?>

<?php
declare(strict_types=1);

final class Mailer
{
    public static function sendOtp(string $toEmail, string $purpose, string $otp, int $expiresInMinutes = 10): void
    {
        if (!MAIL_ENABLED) {
            return;
        }

        if (trim((string)SMTP_PASSWORD) === '') {
            throw new RuntimeException('Email OTP is enabled, but SMTP password is not configured.');
        }

        $purposeLabel = $purpose === 'password_reset' ? 'password reset' : 'login';
        $subject = APP_NAME . ' ' . ucfirst($purposeLabel) . ' OTP';
        $plainBody = implode("\r\n", [
            'Hello,',
            '',
            "Your " . APP_NAME . " {$purposeLabel} OTP is: {$otp}",
            '',
            "This code expires in {$expiresInMinutes} minutes.",
            'If you did not request this code, you can ignore this email.',
            '',
            APP_NAME,
        ]);

        self::send($toEmail, $subject, $plainBody);
    }

    public static function send(string $toEmail, string $subject, string $plainBody): void
    {
        $socket = @stream_socket_client(
            'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
            $errno,
            $errstr,
            SMTP_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server: {$errstr}");
        }

        stream_set_timeout($socket, SMTP_TIMEOUT);

        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO localhost', [250]);
            self::command($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start TLS encryption for SMTP.');
            }

            self::command($socket, 'EHLO localhost', [250]);
            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode((string)SMTP_USERNAME), [334]);
            self::command($socket, base64_encode((string)SMTP_PASSWORD), [235]);
            self::command($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . self::mailbox(MAIL_FROM_NAME, MAIL_FROM_EMAIL),
                'To: <' . $toEmail . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $message = implode("\r\n", $headers)
                . "\r\n\r\n"
                . self::dotStuff($plainBody)
                . "\r\n.";

            self::command($socket, $message, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private static function mailbox(string $name, string $email): string
    {
        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        return '"' . $safeName . '" <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function dotStuff(string $body): string
    {
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    /**
     * @param resource $socket
     * @param int[] $expectedCodes
     */
    private static function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param int[] $expectedCodes
     */
    private static function expect($socket, array $expectedCodes): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }
}

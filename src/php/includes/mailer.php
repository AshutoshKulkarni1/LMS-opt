<?php
/**
 * Mailer — Send OTP emails via SMTP or PHP mail().
 * Includes production-ready socket handling and safe diagnostic logging.
 */

require_once __DIR__ . '/../config/mail.php';

/**
 * Mask an email address for safe diagnostic logging (e.g., s***r@gmail.com).
 * Never exposes full mailbox names or credentials.
 */
function maskEmailForLog(?string $email): string {
    if (!$email || !str_contains($email, '@')) {
        return '[empty/invalid]';
    }
    [$user, $domain] = explode('@', $email, 2);
    $len = strlen($user);
    if ($len <= 2) {
        $maskedUser = substr($user, 0, 1) . '*';
    } else {
        $maskedUser = substr($user, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($user, -1);
    }
    return $maskedUser . '@' . $domain;
}

/**
 * Server-side diagnostic logger for mail events.
 * Logs to error_log() which is captured in real-time by Railway and Docker stdout/stderr.
 */
function logMailDiag(string $stage, array $data = []): void {
    $parts = ["[MAIL_DIAG][STAGE: $stage]"];
    foreach ($data as $k => $v) {
        if ($v === null) $v = 'null';
        elseif (is_bool($v)) $v = $v ? 'true' : 'false';
        $parts[] = "$k=$v";
    }
    error_log(implode(' ', $parts));
}

/**
 * Send an email.
 * Returns: ['success' => bool, 'error' => string|null, 'stage' => string|null, 'code' => string|null]
 */
function sendMail(string $to, string $subject, string $body): array {
    // 3. SMTP configuration loaded stage
    $driver  = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'smtp';
    $devMode = defined('MAIL_DEV_MODE') && MAIL_DEV_MODE;
    $host    = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $port    = defined('SMTP_PORT') ? SMTP_PORT : '587';
    $user    = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $from    = defined('SMTP_FROM') ? SMTP_FROM : $user;

    logMailDiag('smtp_config_loaded', [
        'driver'    => $driver,
        'dev_mode'  => $devMode,
        'host'      => $host,
        'port'      => $port,
        'user'      => maskEmailForLog($user),
        'from'      => maskEmailForLog($from),
        'has_pass'  => (!empty(SMTP_PASSWORD) ? 'yes(' . strlen(SMTP_PASSWORD) . 'c)' : 'no'),
        'to'        => maskEmailForLog($to),
    ]);

    // Dev mode: log to file instead of sending
    if ($devMode) {
        $log = sprintf(
            "[%s] TO: %s | SUBJECT: %s | BODY: %s\n",
            date('Y-m-d H:i:s'), $to, $subject, $body
        );
        $logDir = dirname(MAIL_DEV_LOG);
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        @file_put_contents(MAIL_DEV_LOG, $log, FILE_APPEND | LOCK_EX);
        logMailDiag('dev_mode_captured', ['target' => maskEmailForLog($to)]);
        return ['success' => true, 'dev_mode' => true];
    }

    if ($driver === 'smtp') {
        return sendSmtpMail($to, $subject, $body);
    }

    return sendNativeMail($to, $subject, $body);
}

/**
 * Send via PHP mail() function (requires configured sendmail).
 */
function sendNativeMail(string $to, string $subject, string $body): array {
    $encodedFromName = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $encodedSubject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "From: " . $encodedFromName . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = @mail($to, $encodedSubject, $body, $headers);
    if ($sent) {
        logMailDiag('native_mail_success', ['to' => maskEmailForLog($to)]);
        return ['success' => true];
    }
    $lastErr = error_get_last()['message'] ?? 'PHP mail() rejected';
    logMailDiag('native_mail_failed', ['error' => $lastErr]);
    return ['success' => false, 'error' => 'PHP mail() failed. Check sendmail config.', 'stage' => 'native_mail'];
}

/**
 * Send via direct SMTP connection with RFC compliance and granular diagnostic logging.
 */
function sendSmtpMail(string $to, string $subject, string $body): array {
    $errno = 0;
    $errstr = '';

    // Validate essential credentials before attempting socket
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        $msg = 'SMTP configuration incomplete: SMTP_HOST, SMTP_USERNAME, or SMTP_PASSWORD is not set.';
        logMailDiag('smtp_config_invalid', ['error' => $msg]);
        return ['success' => false, 'error' => $msg, 'stage' => 'smtp_config_loaded'];
    }

    // 4. SMTP connection attempted stage
    logMailDiag('smtp_connection_attempt', [
        'host'    => SMTP_HOST,
        'port'    => SMTP_PORT,
        'timeout' => '15s',
    ]);

    $socket = @fsockopen(SMTP_HOST, (int)SMTP_PORT, $errno, $errstr, 15);
    if (!$socket) {
        logMailDiag('smtp_connection_failed', [
            'errno'  => $errno,
            'errstr' => $errstr ?: 'Socket connect failed',
        ]);
        return [
            'success' => false,
            'error'   => "SMTP connection failed: $errstr ($errno)",
            'stage'   => 'smtp_connection',
            'code'    => (string)$errno,
        ];
    }

    // Set stream timeout for all subsequent socket I/O operations
    stream_set_timeout($socket, 15);

    // Read initial greeting banner
    $greeting = smtpReadResponse($socket);
    $greetingCode = substr(trim($greeting), 0, 3);
    if (!smtpIsSuccess($greeting)) {
        fclose($socket);
        logMailDiag('smtp_greeting_failed', ['code' => $greetingCode, 'resp' => smtpFirstLine($greeting)]);
        return [
            'success' => false,
            'error'   => "SMTP greeting rejected: " . smtpFirstLine($greeting),
            'stage'   => 'smtp_greeting',
            'code'    => $greetingCode,
        ];
    }
    logMailDiag('smtp_connected', ['code' => $greetingCode]);

    // EHLO before TLS
    $clientHost = gethostname() ?: 'localhost';
    $resp = smtpSendAndRead($socket, "EHLO " . $clientHost);
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        logMailDiag('smtp_ehlo_failed', ['resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "EHLO failed: " . smtpFirstLine($resp), 'stage' => 'smtp_ehlo', 'code' => substr($resp, 0, 3)];
    }

    // STARTTLS
    $resp = smtpSendAndRead($socket, "STARTTLS");
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        logMailDiag('smtp_starttls_failed', ['resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "STARTTLS failed: " . smtpFirstLine($resp), 'stage' => 'smtp_starttls', 'code' => substr($resp, 0, 3)];
    }

    // Enable TLS client crypto with TLS 1.2+ support
    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }

    $ok = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
    if (!$ok) {
        $tlsErr = error_get_last()['message'] ?? 'Handshake failed';
        fclose($socket);
        logMailDiag('smtp_tls_handshake_failed', ['error' => $tlsErr]);
        return ['success' => false, 'error' => "TLS handshake failed: $tlsErr", 'stage' => 'smtp_tls'];
    }
    logMailDiag('smtp_tls_handshake_success', ['tls' => 'enabled']);

    // EHLO again (post-TLS)
    $resp = smtpSendAndRead($socket, "EHLO " . $clientHost);
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        logMailDiag('smtp_ehlo_post_tls_failed', ['resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "EHLO (TLS) failed: " . smtpFirstLine($resp), 'stage' => 'smtp_ehlo_tls', 'code' => substr($resp, 0, 3)];
    }

    // 5. Authentication attempted stage
    logMailDiag('smtp_auth_attempt', ['user' => maskEmailForLog(SMTP_USERNAME)]);
    $resp = smtpSendAndRead($socket, "AUTH LOGIN");
    if (!smtpIsSuccess($resp) && !str_contains($resp, '334')) {
        fclose($socket);
        logMailDiag('smtp_auth_unsupported', ['resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "AUTH not supported: " . smtpFirstLine($resp), 'stage' => 'smtp_auth', 'code' => substr($resp, 0, 3)];
    }

    // Username (base64)
    $resp = smtpSendAndRead($socket, base64_encode(SMTP_USERNAME));
    if (!str_contains($resp, '334')) {
        fclose($socket);
        logMailDiag('smtp_auth_username_rejected', ['resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "SMTP username rejected: " . smtpFirstLine($resp), 'stage' => 'smtp_auth_username', 'code' => substr($resp, 0, 3)];
    }

    // Password (base64) — never log password or response details containing token echoes
    $resp = smtpSendAndRead($socket, base64_encode(SMTP_PASSWORD));
    $authCode = substr(trim($resp), 0, 3);
    if (!str_contains($resp, '235')) {
        $detail = smtpFirstLine($resp);
        fclose($socket);
        $hint = '';
        if (str_contains($detail, '535')) {
            $hint = ' — Gmail requires a 16-character Google App Password (not normal account password) with 2FA enabled.';
        }
        logMailDiag('smtp_auth_failed', ['code' => $authCode, 'detail' => $detail]);
        return [
            'success' => false,
            'error'   => "SMTP auth failed: $detail$hint",
            'stage'   => 'smtp_auth',
            'code'    => $authCode,
        ];
    }
    logMailDiag('smtp_auth_success', ['code' => $authCode]);

    // 6. Message accepted or rejected stages
    // MAIL FROM
    $fromAddr = SMTP_FROM ?: SMTP_USERNAME;
    $resp = smtpSendAndRead($socket, "MAIL FROM:<" . $fromAddr . ">");
    if (!smtpIsSuccess($resp)) {
        $code = substr(trim($resp), 0, 3);
        fclose($socket);
        logMailDiag('smtp_message_rejected', ['step' => 'MAIL_FROM', 'code' => $code, 'resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "MAIL FROM failed: " . smtpFirstLine($resp), 'stage' => 'smtp_mail_from', 'code' => $code];
    }

    // RCPT TO
    $resp = smtpSendAndRead($socket, "RCPT TO:<" . $to . ">");
    if (!smtpIsSuccess($resp)) {
        $code = substr(trim($resp), 0, 3);
        fclose($socket);
        logMailDiag('smtp_message_rejected', ['step' => 'RCPT_TO', 'code' => $code, 'to' => maskEmailForLog($to), 'resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "RCPT TO failed: " . smtpFirstLine($resp), 'stage' => 'smtp_rcpt_to', 'code' => $code];
    }

    // DATA
    $resp = smtpSendAndRead($socket, "DATA");
    if (!str_contains($resp, '354')) {
        $code = substr(trim($resp), 0, 3);
        fclose($socket);
        logMailDiag('smtp_message_rejected', ['step' => 'DATA', 'code' => $code, 'resp' => smtpFirstLine($resp)]);
        return ['success' => false, 'error' => "DATA command failed: " . smtpFirstLine($resp), 'stage' => 'smtp_data', 'code' => $code];
    }

    // Prepare RFC 5322 & RFC 2047 compliant headers
    $fromDomain = 'localhost';
    if (!empty($fromAddr) && str_contains($fromAddr, '@')) {
        $fromDomain = substr(strrchr($fromAddr, '@'), 1);
    }
    $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $fromDomain . '>';

    // Encode Subject and From Name to ensure 7-bit ASCII compliance for all MTA hops
    $encodedFromName = '=?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?=';
    $encodedSubject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "From: " . $encodedFromName . " <" . $fromAddr . ">\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: " . $encodedSubject . "\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: " . $messageId . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Send payload followed by end-of-data marker '.'
    $payload = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($socket, $payload);

    $resp = smtpReadResponse($socket);
    $sendCode = substr(trim($resp), 0, 3);

    // QUIT and close
    @smtpSendAndRead($socket, "QUIT");
    fclose($socket);

    if (smtpIsSuccess($resp) || str_contains($resp, '250') || str_contains($resp, 'OK')) {
        logMailDiag('smtp_message_accepted', [
            'code'       => $sendCode,
            'message_id' => $messageId,
            'to'         => maskEmailForLog($to),
        ]);
        return ['success' => true, 'stage' => 'smtp_delivery_accepted', 'code' => $sendCode];
    }

    logMailDiag('smtp_message_rejected', [
        'step' => 'DATA_PAYLOAD',
        'code' => $sendCode,
        'resp' => smtpFirstLine($resp),
    ]);
    return [
        'success' => false,
        'error'   => "SMTP send failed: " . smtpFirstLine($resp),
        'stage'   => 'smtp_payload_rejected',
        'code'    => $sendCode,
    ];
}

/**
 * Send a command and read the full multi-line response.
 */
function smtpSendAndRead($socket, string $cmd): string {
    fputs($socket, $cmd . "\r\n");
    return smtpReadResponse($socket);
}

/**
 * Read SMTP response (handles multi-line continuations per RFC 5321).
 */
function smtpReadResponse($socket): string {
    $response = '';
    while ($line = fgets($socket, 512)) {
        if ($line === false) break;
        $response .= $line;
        // Multi-line responses have '-' at position 3, final line has ' ' or newline
        if (strlen($line) >= 4 && $line[3] !== '-') break;
        if (strlen($line) < 4) break;
    }
    return $response;
}

/**
 * Check if SMTP response is a success (2xx or 3xx).
 */
function smtpIsSuccess(string $response): bool {
    if (strlen($response) < 3) return false;
    $code = substr($response, 0, 3);
    return $code[0] === '2' || $code[0] === '3';
}

/**
 * Get first line of SMTP response.
 */
function smtpFirstLine(string $response): string {
    $nl = strpos($response, "\n");
    return $nl !== false ? trim(substr($response, 0, $nl)) : trim($response);
}

/**
 * Send OTP verification email.
 */
function sendOtpEmail(string $to, string $name, string $otp): array {
    // Pure ASCII subject with clear branding to prevent spam classification
    $subject = "Your OTP for Email Verification - Test Platform";

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>OTP Verification</title></head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 28px; font-weight: 700; color: #0078D4; margin-bottom: 4px;">Test Platform</div>
        <div style="font-size: 13px; color: #666; margin-bottom: 24px;">Email Verification</div>

        <p style="color: #333; font-size: 15px; line-height: 1.6;">Hi <strong>$name</strong>,</p>
        <p style="color: #333; font-size: 15px; line-height: 1.6;">
            Your one-time verification code is:
        </p>

        <div style="text-align: center; margin: 28px 0;">
            <span style="display: inline-block; font-size: 36px; font-weight: 700; letter-spacing: 8px;
                         color: #0078D4; background: #F0F6FC; padding: 16px 32px; border-radius: 8px;">
                $otp
            </span>
        </div>

        <p style="color: #666; font-size: 13px; line-height: 1.5;">
            This code expires in <strong>10 minutes</strong>. If you didn't request this, please ignore this email.
        </p>

        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px; text-align: center;">
            &copy; 2026 Test Platform. All rights reserved.
        </p>
    </div>
</body>
</html>
HTML;

    return sendMail($to, $subject, $body);
}

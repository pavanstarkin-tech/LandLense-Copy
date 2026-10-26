<?php
/**
 * LandLens Enterprise SMTP Mailer
 * Connects directly to Hostinger Secure SMTP (smtp.hostinger.com:465) via SSL
 * Zero external dependencies required.
 */

class Mailer {
    private static $host = 'smtp.hostinger.com';
    private static $port = 465;
    private static $username = 'landlens@acheva.in';
    private static $password = 'LandLens@123';
    private static $fromEmail = 'landlens@acheva.in';
    private static $fromName = 'LandLens Government Land Registry';

    /**
     * Send an HTML email via Hostinger SMTP
     * 
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient name
     * @param string $subject Email subject line
     * @param string $htmlBody HTML content of the message
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send($toEmail, $toName, $subject, $htmlBody) {
        $toEmail = trim($toEmail);
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "Invalid recipient email: $toEmail"];
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = stream_socket_client(
                "ssl://" . self::$host . ":" . self::$port,
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return ['success' => false, 'message' => "Failed to connect to SMTP server: $errstr ($errno)"];
            }

            stream_set_timeout($socket, 15);

            // Read greeting banner
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '220')) {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP greeting error: $response"];
            }

            // EHLO
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $response = self::readMultiline($socket);

            // AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '334')) {
                fclose($socket);
                return ['success' => false, 'message' => "AUTH LOGIN failed: $response"];
            }

            // Send base64 username
            fputs($socket, base64_encode(self::$username) . "\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '334')) {
                fclose($socket);
                return ['success' => false, 'message' => "Username rejected: $response"];
            }

            // Send base64 password
            fputs($socket, base64_encode(self::$password) . "\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '235')) {
                fclose($socket);
                return ['success' => false, 'message' => "Authentication failed: $response"];
            }

            // MAIL FROM
            fputs($socket, "MAIL FROM: <" . self::$fromEmail . ">\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '250')) {
                fclose($socket);
                return ['success' => false, 'message' => "MAIL FROM rejected: $response"];
            }

            // RCPT TO
            fputs($socket, "RCPT TO: <" . $toEmail . ">\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '250')) {
                fclose($socket);
                return ['success' => false, 'message' => "RCPT TO rejected: $response"];
            }

            // DATA
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            if (!str_starts_with($response, '354')) {
                fclose($socket);
                return ['success' => false, 'message' => "DATA rejected: $response"];
            }

            // Headers & Body
            $headers = [];
            $headers[] = "From: " . self::$fromName . " <" . self::$fromEmail . ">";
            $headers[] = "To: " . ($toName ? "$toName <$toEmail>" : "<$toEmail>");
            $headers[] = "Subject: " . $subject;
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
            $headers[] = "Date: " . date('r');
            $headers[] = "X-Mailer: LandLens-Hostinger-SMTP/2.5";
            $headers[] = "Message-ID: <" . time() . "." . bin2hex(random_bytes(8)) . "@acheva.in>";

            $emailMessage = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.\r\n";
            fputs($socket, $emailMessage);

            $response = fgets($socket, 515);
            fputs($socket, "QUIT\r\n");
            fclose($socket);

            if (str_starts_with($response, '250')) {
                return ['success' => true, 'message' => 'Email sent successfully via Hostinger SMTP'];
            } else {
                return ['success' => false, 'message' => "Failed to deliver message: $response"];
            }
        } catch (Exception $e) {
            error_log("[LandLens Mailer Error] " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private static function readMultiline($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    /**
     * Build standard LandLens Land Selling & Ownership Transfer Email Template
     */
    public static function buildTransferEmailHtml($data) {
        $propTitle = htmlspecialchars($data['title'] ?? 'Land Parcel');
        $surveyNo = htmlspecialchars($data['surveyNumber'] ?? 'N/A');
        $area = htmlspecialchars($data['area'] ?? '0');
        $location = htmlspecialchars($data['location'] ?? 'Telangana');
        $price = number_format((float)($data['price'] ?? 0), 2);
        $buyerName = htmlspecialchars($data['buyerName'] ?? 'Citizen Buyer');
        $buyerEmail = htmlspecialchars($data['buyerEmail'] ?? 'buyer@landlens.in');
        $ownerName = htmlspecialchars($data['ownerName'] ?? 'Registered Landholder');
        $approveUrl = $data['approveUrl'];
        $rejectUrl = $data['rejectUrl'];

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Transfer Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0b1120; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #111827; border: 1px solid #1f2937; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                    <!-- Header with Seal -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #059669 0%, #0284c7 100%); padding: 35px 30px; text-align: center;">
                            <div style="font-size: 32px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">
                                Land<span style="color: #6ee7b7;">Lens</span>
                            </div>
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #e0f2fe; margin-top: 4px;">
                                AI-Powered Government Land Registry & Title Protection
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 35px 30px;">
                            <div style="background-color: #1e293b; border-left: 4px solid #10b981; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px;">
                                <span style="font-size: 15px; font-weight: 700; color: #34d399;">🛡️ ACTION REQUIRED: Original Title-Holder Authorization</span>
                            </div>

                            <p style="font-size: 16px; line-height: 24px; color: #cbd5e1; margin-top: 0;">
                                Dear <strong>{$ownerName}</strong>,
                            </p>
                            <p style="font-size: 14px; line-height: 22px; color: #94a3b8;">
                                An official request to sell and transfer the ownership title of your registered land parcel has been submitted on the <strong>LandLens Portal</strong>. To ensure complete fraud prevention and verify that you are the legitimate registered owner, please review this transaction.
                            </p>

                            <!-- Property Card -->
                            <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 14px; padding: 22px; margin: 25px 0;">
                                <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #38bdf8; margin-bottom: 15px;">
                                    📍 Land Parcel Specifications
                                </div>
                                <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px;">
                                    <tr>
                                        <td width="42%" style="color: #64748b; font-weight: 600;">Property Title:</td>
                                        <td style="color: #f1f5f9; font-weight: 700;">{$propTitle}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; font-weight: 600;">Survey Number:</td>
                                        <td style="color: #f1f5f9; font-weight: 700;"><span style="background-color: #0369a1; color: #e0f2fe; padding: 2px 8px; border-radius: 6px; font-family: monospace;">{$surveyNo}</span></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; font-weight: 600;">Land Extent:</td>
                                        <td style="color: #f1f5f9; font-weight: 700;">{$area} Acres</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; font-weight: 600;">Jurisdiction:</td>
                                        <td style="color: #f1f5f9; font-weight: 700;">{$location}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; font-weight: 600;">Declared Value:</td>
                                        <td style="color: #10b981; font-weight: 800; font-size: 16px;">₹ {$price}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding-top: 12px; border-top: 1px solid #1e293b;"></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; font-weight: 600;">Requested By (Buyer):</td>
                                        <td style="color: #f1f5f9; font-weight: 700;">{$buyerName} ({$buyerEmail})</td>
                                    </tr>
                                </table>
                            </div>

                            <p style="font-size: 14px; line-height: 22px; color: #cbd5e1; text-align: center; margin: 30px 0 15px 0;">
                                <strong>Did you authorize this land sale and transfer?</strong><br>
                                Click one of the verification options below to resolve this transaction:
                            </p>

                            <!-- Decision CTA Buttons -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 25px 0;">
                                <tr>
                                    <td align="center" style="padding-bottom: 14px;">
                                        <a href="{$approveUrl}" target="_blank" style="display: block; width: 88%; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 16px 20px; border-radius: 12px; text-align: center; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);">
                                            ✓ ACCEPT & APPROVE TRANSFER
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <a href="{$rejectUrl}" target="_blank" style="display: block; width: 88%; background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 20px; border-radius: 12px; text-align: center; box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.4);">
                                            ✕ REJECT & BLOCK TRANSFER (FRAUD ALERT)
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color: #0b1120; border: 1px dashed #334155; border-radius: 10px; padding: 14px; margin-top: 30px; font-size: 12px; color: #64748b; line-height: 18px;">
                                🔒 <strong>Cryptographic Protection Notice:</strong> If you did NOT initiate or authorize this sale, clicking <strong>REJECT</strong> will immediately lock this survey parcel and notify the Mandal Revenue Officer (MRO) to prevent unauthorized conveyance.
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0b1120; border-top: 1px solid #1f2937; padding: 25px 30px; text-align: center; font-size: 12px; color: #475569;">
                            © 2026 LandLens Technologies & Revenue Registry Division. All Rights Reserved.<br>
                            Official Support: <a href="mailto:landlens@acheva.in" style="color: #38bdf8; text-decoration: none;">landlens@acheva.in</a> • Hosted on Hostinger Cloud Infrastructure.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

<?php

namespace App\Libraries;

/**
 * Real email delivery attempt via CodeIgniter's own Email service
 * (app/Config/Email.php, `protocol`/`SMTPHost`/etc). No real mail
 * transport is configured in this environment (same class of external
 * dependency as the SMS provider and the payment gateway, D-23/SETUP.md)
 * — app/Config/Email.php ships with CI4's stock, empty defaults, and
 * this sandbox's outbound network policy has no route to a real SMTP
 * relay to actually test one against. The send path itself is real and
 * ready: the moment real SMTP credentials are set (via .env's
 * `email.SMTPHost`/`email.SMTPUser`/`email.SMTPPass`/etc, CI4's standard
 * override convention, or a real `sendmail`/MTA on the host for the
 * 'mail' protocol), this actually delivers, with zero code changes.
 *
 * Until then, send() fails closed (returns false) rather than silently
 * pretending to succeed — callers must have an on-screen fallback for
 * the code, exactly the existing devOtp/devEmailOtp pattern already
 * used for the equally-unconfigured SMS path.
 */
class EmailNotificationService
{
    public function sendOtp(string $toEmail, string $otp, string $purpose): bool
    {
        $subject = match ($purpose) {
            'mpin_reset_email' => 'Your AdwitiX Custodian mPIN reset code',
            'admin_login_email' => 'Your AdwitiX Custodian login code',
            default => 'Your AdwitiX verification code',
        };

        $body = "Your verification code is: {$otp}\n\n"
            . "This code expires in 10 minutes. If you did not request this, "
            . "you can safely ignore this email — no change will be made "
            . "without the code above.\n\n— AdwitiX";

        try {
            $email = \Config\Services::email();
            $email->setTo($toEmail);
            $email->setFrom(env('email.fromEmail', 'no-reply@adwitix.example'), env('email.fromName', 'AdwitiX'));
            $email->setSubject($subject);
            $email->setMessage($body);

            $sent = $email->send();
            if (!$sent) {
                log_message('warning', "EmailNotificationService: send() returned false for {$purpose} to {$toEmail} — {$email->printDebugger(['headers'])}");
            }

            return $sent;
        } catch (\Throwable $e) {
            // No real SMTP configured -> this is the expected path in
            // every environment until real credentials are set, not a
            // surprise. Logged, not thrown, so the caller's on-screen
            // fallback can still run.
            log_message('warning', "EmailNotificationService: real send failed for {$purpose} to {$toEmail}: {$e->getMessage()}");
            return false;
        }
    }
}

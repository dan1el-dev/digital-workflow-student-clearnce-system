<?php
/**
 * PHPMailer Integration Helper
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

/**
 * Sends a clearance notification email to a student
 */
function send_clearance_email($user_id, $to_email, $subject, $body_html) {
    global $pdo;
    require_once __DIR__ . '/functions.php';
    
    $mail = new PHPMailer(true);
    $email_sent = 0;

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, 'University Clearance System');
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);

        $mail->send();
        $email_sent = 1;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
    }

    // Log to notifications table
    try {
        $notification_id = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (notification_id, user_id, message, email_sent, sent_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $sent_at = $email_sent ? date('Y-m-d H:i:s') : null;
        $stmt->execute([
            $notification_id,
            $user_id,
            $subject . "\n" . strip_tags($body_html),
            $email_sent,
            $sent_at
        ]);
    } catch (PDOException $ex) {
        error_log("Failed to insert notification: " . $ex->getMessage());
    }

    return $email_sent === 1;
}

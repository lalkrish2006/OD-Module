<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendOdNotification($formData, $odid) {
    $mail = new PHPMailer(true);

    try {
        // SMTP setup - These settings are correct
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'anishrkumar2k5@gmail.com'; // your Gmail ID
        $mail->Password = 'azcezslskxivgadu';  // Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // Sender info
        $mail->setFrom('anishrkumar2k5@gmail.com', 'OD Application System');

        // Recipient - mentor email from formData
        if (!empty($formData['mentor_email'])) {
            $mail->addAddress($formData['mentor_email'], $formData['mentor'] ?? 'Mentor');
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'OD Notification';
        $mail->Body = 'You got a Notification Check the OD Module by Clicking this link <a href="http://localhost/OD-Module/loginin.php">http://localhost/OD-Module/loginin.php</a>';

        $mail->send();
        error_log("Notification email sent successfully to mentor.");
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
    }
}

function sendEmailToHOD($toEmail, $toName, $subject, $body, $replyToEmail, $replyToName) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'anishrkumar2k5@gmail.com'; // Your system's sender email
        $mail->Password = 'azcezslskxivgadu'; // Your app password
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    
        $mail->Port = 465;

        // The "From" address is always the system email
        $mail->setFrom('anishrkumar2k5@gmail.com', 'OD Management System');
        
        // The recipient
        $mail->addAddress($toEmail, $toName);

        // The "Reply-To" address
        if (!empty($replyToEmail)) {
            $mail->addReplyTo($replyToEmail, $replyToName);
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
    }
}
?>
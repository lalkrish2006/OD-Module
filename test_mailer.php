<?php
require_once 'phpMailer.php';

// Sample test form data
$formData = [
    'studentName' => 'Anish Kumar R',
    'registerno' => '953623104007',
    'department' => 'Computer Science',
    'purpose' => 'Testing PHPMailer',
    'odType' => 'internal',
    'email' => 'giveawaynino143@gmail.com', // Your email to receive CC
];

// Arbitrary application ID for testing
$testOdId = 9999;

// Call the mail function`
sendOdNotification($formData, $testOdId);
?>

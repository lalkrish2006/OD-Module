<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";   // change if needed
$pass = "";       // change if needed
$dbname = "college_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Collect form data safely
$registerNumber = $_POST['registerNumber'] ?? '';
$studentName    = $_POST['studentName'] ?? '';
$year           = $_POST['year'] ?? '';
$department     = $_POST['department'] ?? '';
$section        = $_POST['section'] ?? '';
$purpose        = $_POST['purpose'] ?? '';

$fullDayDate = $_POST['fullDayDate'] ?? null;
$fromTime    = $_POST['fromTime'] ?? null;
$toTime      = $_POST['toTime'] ?? null;
$fromDate    = $_POST['fromDate'] ?? null;
$toDate      = $_POST['toDate'] ?? null;

$collegeName = $_POST['collegeName'] ?? null;
$eventName   = $_POST['eventName'] ?? null;
$extFromDate = $_POST['extFromDate'] ?? null;
$extToDate   = $_POST['extToDate'] ?? null;

$mentor        = $_POST['mentor'] ?? '';
$systemRequired = $_POST['systemRequired'] ?? 'No';

// Insert into lab_form
$stmt = $conn->prepare("INSERT INTO lab_form 
    (registerNumber, studentName, year, department, section, purpose, fullDayDate, fromTime, toTime, fromDate, toDate, collegeName, eventName, extFromDate, extToDate, mentor, systemRequired) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sssssssssssssssss",
    $registerNumber, $studentName, $year, $department, $section, $purpose,
    $fullDayDate, $fromTime, $toTime, $fromDate, $toDate,
    $collegeName, $eventName, $extFromDate, $extToDate,
    $mentor, $systemRequired
);

if ($stmt->execute()) {
    $form_id = $stmt->insert_id;

    // Insert team members
    foreach ($_POST as $key => $value) {
        if (strpos($key, "member_name_") === 0) {
            $index = str_replace("member_name_", "", $key);

            $member_name = $_POST["member_name_" . $index];
            $member_regno = $_POST["member_regno_" . $index];
            $member_year = $_POST["member_year_" . $index];
            $member_department = $_POST["member_department_" . $index];
            $member_section = $_POST["member_section_" . $index];

            $stmt2 = $conn->prepare("INSERT INTO lab_team_members 
                (form_id, member_name, member_regno, member_year, member_department, member_section) 
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("isssss", $form_id, $member_name, $member_regno, $member_year, $member_department, $member_section);
            $stmt2->execute();
        }
    }

    $_SESSION['success'] = "Form submitted successfully!";
    header("Location: success.php");
    exit();

} else {
    $_SESSION['error'] = "Error submitting form: " . $stmt->error;
    header("Location: error.php");
    exit();
}

$conn->close();
?>

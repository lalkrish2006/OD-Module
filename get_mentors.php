<?php
// get_mentors.php
header('Content-Type: application/json');
session_start();

if (!isset($_GET['year'], $_GET['department'], $_GET['section'])) {
    echo json_encode([]);
    exit;
}

$year = $_GET['year'];
$department = $_GET['department'];
$section = $_GET['section'];

$conn = new mysqli('localhost', 'root', '', 'college_db');
$conn->set_charset('utf8mb4');

$sql = "SELECT name FROM mentors 
        WHERE year = ? AND department = ? AND section = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["error" => $conn->error]);
    exit;
}

$stmt->bind_param("sss", $year, $department, $section);
$stmt->execute();
$result = $stmt->get_result();

$mentors = [];
while ($row = $result->fetch_assoc()) {
    $mentors[] = $row['name'];
}

echo json_encode($mentors);

$stmt->close();
$conn->close();

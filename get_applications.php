<?php
session_start();
header('Content-Type: application/json');

// Ensure only logged-in students can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$studentRegNo = $_SESSION['register_no'];

$conn = new mysqli('localhost', 'root', '', 'college_db');
$conn->set_charset('utf8mb4');

$sql = "
    SELECT DISTINCT o.id, o.purpose
    FROM od_applications o
    LEFT JOIN od_team_members t ON o.id = t.od_id
    WHERE o.register_no = ? OR t.member_regno = ?
    ORDER BY o.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $studentRegNo, $studentRegNo);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($applications);

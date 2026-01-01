<?php
require_once __DIR__ . '/includes/session_manager.php';
require_once 'db_connection.php';

// Check if logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit;
}

// Get admin details from session
$register_no = $_SESSION['register_no'];

// Fetch department of this admin from DB
$stmt = $conn->prepare("SELECT department FROM admin_users WHERE register_no = ?");
$stmt->bind_param("s", $register_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Admin details not found.";
    exit;
}

$admin = $result->fetch_assoc();
$department = $admin['department'];

// ✅ Redirect directly to the HOD dashboard with department filter
header("Location: hod_dashboard.php?department=" . urlencode($department) . "&access=admin");
exit;
?>
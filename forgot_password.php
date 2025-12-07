<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection setup (assuming connection.php exists or similar)
try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$success = '';
$step = 1; // 1: Authentication (Name Check), 2: Reset Password

// Map all available roles to their respective tables
$tables = [
    "student" => "students",
    "mentor" => "mentors",
    "ca" => "cas",
    "ja" => "jas",
    "hod" => "hods",
    "principal" => "principals",
    "labtech" => "lab_technicians",
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['authenticate'])) {
        // --- STEP 1: AUTHENTICATION (Using Name and Register No) ---
        $role = trim($_POST['role']);
        $register_no = trim($_POST['register_no']);
        $name = trim($_POST['name']); 
        
        if (!isset($tables[$role])) {
            $error = "❌ Invalid role selected!";
        } else {
            $table = $tables[$role];
            
            // Query using Register Number and Name for verification
            $stmt = $conn->prepare("SELECT register_no FROM $table WHERE register_no = ? AND name = ?");
            $stmt->bind_param("ss", $register_no, $name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // Authentication successful, proceed to step 2
                $_SESSION['reset_reg_no'] = $register_no;
                $_SESSION['reset_role_table'] = $table;
                $step = 2; 
            } else {
                $error = "❌ Authentication failed. Check your Register Number and Full Name.";
                $step = 1;
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // --- STEP 2: PASSWORD RESET ---
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $register_no = $_SESSION['reset_reg_no'] ?? null;
        $table = $_SESSION['reset_role_table'] ?? null;
        
        // Validation for date format and match
        if (empty($new_password) || empty($confirm_password)) {
             $error = "❌ Please select a date for the new password.";
             $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "❌ New Date Password and Confirm Date Password do not match.";
            $step = 2;
        } else {
            // Hash the new password (date) before storing
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password in the database
            $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE register_no = ?");
            $stmt->bind_param("ss", $hashed_password, $register_no);

            if ($stmt->execute()) {
                $success = "✅ Password reset successfully! You can now log in with your new Date password.";
                // Clear session variables after successful reset
                unset($_SESSION['reset_reg_no']);
                unset($_SESSION['reset_role_table']);
                $step = 1; // Go back to login/initial step
            } else {
                $error = "❌ Failed to update password: " . $conn->error;
                $step = 2;
            }
        }
    }
} else {
    // Check if coming from a previous step
    if (isset($_SESSION['reset_reg_no']) && isset($_SESSION['reset_role_table'])) {
        $step = 2;
    } else {
        $step = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50, #4ca1af);
            min-height: 100vh; 
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5rem; 
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-11 col-sm-10 col-md-6 col-lg-5">
                <div class="card p-4">
                    <h3 class="text-center text-danger mb-3">Forgot Password</h3>

                    <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                    <?php if ($step === 1): ?>
                    <form method="POST">
                        <input type="hidden" name="authenticate" value="1">
                        <p class="text-center">Enter your role, register number, and **Full Name** to verify your identity.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="" disabled selected>-- Select Role --</option>
                                <option value="student">Student</option>
                                <option value="mentor">Mentor</option>
                                <option value="ca">Class Advisor</option>
                                <option value="ja">Junior Assistant</option>
                                <option value="hod">HOD</option>
                                <option value="principal">Principal</option>
                                <option value="labtech">Lab Technician</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Register Number</label>
                            <input type="text" name="register_no" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">Verify Details</button>
                        </div>
                        <div class="text-center mt-3">
                            <a href="loginin.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    </form>

                    <?php elseif ($step === 2): ?>
                    <form method="POST">
                        <input type="hidden" name="reset_password" value="1">
                        <p class="text-center">Set your New password for Register No: **<?php echo htmlspecialchars($_SESSION['reset_reg_no']); ?>**</p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password (Select Date)</label>
                            <input type="date" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Confirm New Password (Select Date)</label>
                            <input type="date" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">Reset Password</button>
                        </div>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</body>
</html>
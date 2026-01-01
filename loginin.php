<?php
require_once __DIR__ . '/includes/session_manager.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- START SESSION MANAGEMENT: Prevent logged-in users from seeing the login form ---

if (isset($_SESSION['role']) && isset($_SESSION['register_no'])) {
    // Determine the redirection based on the stored role
    $role = $_SESSION['role'];
    switch ($role) {
        case 'student':
            header("Location: student_dashboard.php");
            break;
        case 'mentor':
            header("Location: mentor_dashboard.php");
            break;
        case 'hod':
        case 'admin':
            header("Location: hod_dashboard.php");
            break;
        case 'ca':
            header("Location: ca_dashboard.php");
            break;
        case 'ja':
            header("Location: ja_dashboard.php");
            break;
        case 'principal':
            header("Location: principal_dashboard.php");
            break;
        case 'labtech':
            header("Location: labtech_dashboard.php");
            break;
        default:
            // If role is set but leads nowhere, clear session and proceed to login form
            session_unset();
            session_destroy();
    }
    // Only exit if a redirection happened
    if (in_array($role, ['student', 'mentor', 'hod', 'admin', 'ca', 'ja', 'principal', 'labtech'])) {
        exit;
    }
}

// Add anti-caching headers to prevent back button issues after logout/login
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// --- END SESSION MANAGEMENT ---


try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $role = $_POST['role'];
        $register_no = trim($_POST['register_no']);
        $password = trim($_POST['password']);  // DOB entered by user

        // Map role to table
        $tables = [
            "student" => "students",
            "mentor" => "mentors",
            "ca" => "cas",
            "ja" => "jas",
            "hod" => "hods",
            "principal" => "principals",
            "labtech" => "lab_technicians",
            "admin" => "admin_users"
        ];

        if (!isset($tables[$role])) {
            $error = "❌ Invalid role selected!";
        } else {
            $table = $tables[$role];

            // Query by register_no only
            $stmt = $conn->prepare("SELECT * FROM $table WHERE register_no = ?");
            $stmt->bind_param("s", $register_no);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {

                    // --- SET SESSION VARIABLES ---
                    $_SESSION['user'] = $user;
                    $_SESSION['role'] = $role;
                    $_SESSION['register_no'] = $user['register_no'];
                    // If the user table has name, department, etc., fetch them
                    if (isset($user['name'])) {
                        $_SESSION['name'] = $user['name'];
                    }
                    if (isset($user['department'])) {
                        $_SESSION['department'] = $user['department'];
                    }
                    // --- END SESSION VARIABLES ---

                    // Role-based redirection
                    switch ($role) {
                        case 'student':
                            header("Location: student_dashboard.php");
                            break;
                        case 'mentor':
                            header("Location: mentor_dashboard.php");
                            break;
                        case 'hod':
                            header("Location: hod_dashboard.php");
                            break;
                        case 'ca':
                            header("Location: ca_dashboard.php");
                            break;
                        case 'ja':
                            header("Location: ja_dashboard.php");
                            break;
                        case 'principal':
                            header("Location: principal_dashboard.php");
                            break;
                        case 'labtech':
                            header("Location: labtech_dashboard.php");
                            break;
                        case 'admin':
                            // Assuming admin also goes to hod dashboard (or a dedicated admin_dashboard.php)
                            header("Location: hod_dashboard.php");
                            break;
                        default:
                            header("Location: loginin.php?error=invalid_role");
                    }
                    exit;
                } else {
                    $error = "❌ Invalid password!";
                }
            } else {
                $error = "❌ Register number not found!";
            }
        }
    }
} catch (Exception $e) {
    $error = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            /* NEW CONTRASTED BACKGROUND */
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

        .btn-primary {
            border-radius: 30px;
            padding: 10px 20px;
        }

        .footer {
            width: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            /* Light, semi-transparent text */
            padding-top: 20px;
            /* Add some space above the footer */
            margin-top: auto;

            /* This is what pushes it to the bottom */
        }

        .footer h6 {
            color: white;
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="row justify-content-center">
            <div class="col-11 col-sm-10 col-md-6 col-lg-5">
                <img src="https://www.ritrjpm.ac.in/images/rit-logo-wide-1.png"
                    style="margin-bottom: 20px; width: 100%; height: auto;">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout'): ?>
                    <div class="alert alert-success text-center">
                        ✅ You have been logged out successfully.
                    </div>
                <?php endif; ?>
                <h3 class="text-center text-white mb-4">On - Duty Management System</h3>
                <div class="card p-4">
                    <h3 class="text-center text-primary mb-3">Login</h3>

                    <?php if (!empty($success))
                        echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (!empty($error))
                        echo "<div class='alert alert-danger'>$error</div>"; ?>

                    <form method="POST">
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
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Register Number</label>
                            <input type="text" name="register_no" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Password (DOB)</label>
                            <input type="date" name="password" class="form-control" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                            <a href="http://localhost/OD-Module/sign_up.php"
                                class=" mt-2 btn btn-outline-secondary border-color-blue">
                                Signup
                            </a>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-decoration-none">
                            Forget Password
                        </a>
                    </div>

                </div>
            </div>
        </div>
        <div class="footer">
            <h6>💻 A Product From Computer Science Engineering Student 💻</h6>
        </div>
    </div>
    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            const roleSelect = document.querySelector('select[name="role"]');
            const role = roleSelect ? roleSelect.value : '';
            const registerInput = document.querySelector('input[name="register_no"]');
            const registerNo = registerInput ? registerInput.value : '';

            // Remove existing client-side error if any
            const existingError = document.getElementById('client-error-msg');
            if (existingError) {
                existingError.remove();
            }

            if (role === 'student') {
                const regex = /^9536\d{2}104\d{3}$/;
                if (!regex.test(registerNo)) {
                    e.preventDefault(); // Stop form submission

                    // Create error alert
                    const errorDiv = document.createElement('div');
                    errorDiv.id = 'client-error-msg';
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.innerText = 'Invalid Register Regex';

                    // Insert before the form
                    document.querySelector('form').insertAdjacentElement('beforebegin', errorDiv);

                    // Scroll to top of card so user sees error
                    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>
</body>

</html>
<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
            "labtech" => "lab_technicians" // added Lab Technician
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

                // verify password (DOB stored as hash)
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = $user;
                    $_SESSION['role'] = $role;
                    $_SESSION['register_no'] = $user['register_no'];  // useful for dashboards

                    // ✅ Redirect based on role
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
                        case 'labtech':  // Lab Technician dashboard
                            header("Location: labtech_dashboard.php");
                            break;
                        default:
                            header("Location: login.php?error=invalid_role");
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
    <title>Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #007bff, #6610f2);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            border-radius: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            border-radius: 30px;
            padding: 10px 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout'): ?>
                <div class="alert alert-success text-center">
                    ✅ You have been logged out successfully.
                </div>
                <?php endif; ?>
                <div class="card p-4">
                    <h3 class="text-center text-primary mb-3">Login</h3>

                    <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

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
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</body>

</html>

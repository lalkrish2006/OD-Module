<?php
require_once __DIR__ . '/includes/session_manager.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "college_db");
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $register_no = $_POST['register_no'] ?? null;
    $name = $_POST['name'] ?? null;
    $dob = $_POST['dob'] ?? null;
    $department = $_POST['department'] ?? null;
    $year = $_POST['year'] ?? null;
    $section = $_POST['section'] ?? null;

    // Use DOB as password base
    $password = password_hash($dob, PASSWORD_DEFAULT);

    try {
        switch ($role) {
            case 'student':
            case 'mentor':
            case 'ca':
                $table = ($role === 'student') ? "students" : (($role === 'mentor') ? "mentors" : "cas");
                $stmt = $conn->prepare("INSERT INTO $table (register_no, name, password, department, year, section) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $register_no, $name, $password, $department, $year, $section);
                break;

            case 'hod':
                $stmt = $conn->prepare("INSERT INTO hods (register_no, name, password, department) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $register_no, $name, $password, $department);
                break;

            case 'principal':
                $stmt = $conn->prepare("INSERT INTO principals (register_no, name, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $register_no, $name, $password);
                break;

            case 'ja': // Junior Assistant
                $stmt = $conn->prepare("INSERT INTO jas (register_no, name, password, department) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $register_no, $name, $password, $department);
                break;

            case 'labtech': // Lab Technician
                $stmt = $conn->prepare("INSERT INTO lab_technicians (register_no, name, password, department) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $register_no, $name, $password, $department);
                break;

            case 'admin': // ✅ NEW ROLE
                $stmt = $conn->prepare("INSERT INTO admin_users (register_no, password, department) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $register_no, $password, $department);
                break;

            default:
                throw new Exception("Invalid role selected!");
        }

        $stmt->execute();
        $success = "✅ Signup successful for <strong>$role</strong>!";
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Signup Page</title>
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
                <div class="card p-4">
                    <h3 class="mb-3 text-center text-primary">Signup</h3>

                    <?php if (!empty($success))
                        echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (!empty($error))
                        echo "<div class='alert alert-danger'>$error</div>"; ?>

                    <form method="POST" id="signupForm">
                        <!-- Role Dropdown -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role</label>
                            <select name="role" class="form-select" required onchange="toggleFields(this.value)">
                                <option value="">-- Select Role --</option>
                                <option value="student">Student</option>
                                <option value="mentor">Mentor</option>
                                <option value="ca">Class Advisor</option>
                                <option value="hod">HOD</option>
                                <option value="principal">Principal</option>
                                <option value="ja">Junior Assistant</option>
                                <option value="labtech">Lab Technician</option>
                                <option value="admin">Admin</option> <!-- ✅ NEW ROLE -->
                            </select>
                        </div>

                        <!-- Register No -->
                        <div class="mb-3 role-field register-field d-none">
                            <label class="form-label fw-bold">Register No</label>
                            <input type="text" name="register_no" class="form-control">
                        </div>

                        <!-- Name -->
                        <div class="mb-3 role-field common-field d-none">
                            <label class="form-label fw-bold">Name</label>
                            <input type="text" name="name" class="form-control">
                        </div>

                        <!-- DOB -->
                        <div class="mb-3 role-field common-field d-none">
                            <label class="form-label fw-bold">DOB (Password)</label>
                            <input type="date" name="dob" class="form-control">
                        </div>

                        <!-- Department -->
                        <div class="mb-3 role-field department-field d-none">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="" selected disabled>Select Dept</option>
                                <option value="CSE">CSE</option>
                                <option value="ECE">ECE</option>
                                <option value="EEE">EEE</option>
                                <option value="MECH">MECH</option>
                                <option value="CIVIL">CIVIL</option>
                            </select>
                        </div>

                        <!-- Year -->
                        <div class="mb-3 role-field year-field d-none">
                            <label class="form-label fw-bold">Year</label>
                            <select name="year" class="form-select">
                                <option value="" selected disabled>Select Year</option>
                                <option>1st Year</option>
                                <option>2nd Year</option>
                                <option>3rd Year</option>
                                <option>4th Year</option>
                            </select>
                        </div>

                        <!-- Section -->
                        <div class="mb-3 role-field section-field d-none">
                            <label for="section" class="form-label">Section</label>
                            <select class="form-select" id="section" name="section">
                                <option value="" selected disabled>Select Section</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Signup</button>
                            <a href="http://localhost/OD-Module/loginin.php" class=" mt-2 btn btn-outline-secondary">
                                Login
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>

    </div>



    <script>
        function toggleFields(role) {
            document.querySelectorAll('.role-field').forEach(el => el.classList.add('d-none'));

            if (['student', 'mentor', 'ca'].includes(role)) {
                document.querySelectorAll('.register-field, .common-field, .department-field, .year-field, .section-field')
                    .forEach(el => el.classList.remove('d-none'));
            } else if (['hod', 'ja', 'labtech', 'admin'].includes(role)) { // ✅ Admin added here
                document.querySelectorAll('.register-field, .common-field, .department-field')
                    .forEach(el => el.classList.remove('d-none'));
            } else if (role === 'principal') {
                document.querySelectorAll('.register-field, .common-field')
                    .forEach(el => el.classList.remove('d-none'));
            }
        }
    </script>
</body>

</html>
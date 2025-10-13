<?php
session_start();
include("connection.php");

// Custom error logger
function log_error($message) {
    $timestamp = date("Y-m-d H:i:s");
    $log_file = __DIR__ . "/php_error.log";
    error_log("[$timestamp] $message\n", 3, $log_file);
}

// Sanitize input helper
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = isset($_POST['role']) ? cleanInput($_POST['role']) : '';
    $reg_no = isset($_POST['reg_no']) ? cleanInput($_POST['reg_no']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($role) || empty($reg_no) || empty($password)) {
        log_error("Login attempt failed: missing role, register number, or password.");
        echo "<h3 style='color:red; text-align:center;'>Please fill in all fields.</h3>";
        exit;
    }

    $roleTableMap = [
        "Student" => "student",
        "Mentor"  => "mentor",
        "HOD"     => "hod"
    ];

    if (!array_key_exists($role, $roleTableMap)) {
        log_error("Login failed: Invalid role selected: $role");
        echo "<h3 style='color:red; text-align:center;'>Invalid role selected.</h3>";
        exit;
    }

    $table = $roleTableMap[$role];

    $stmt = $con->prepare("SELECT reg_no, password, name FROM $table WHERE reg_no = ? LIMIT 1");
    if ($stmt === false) {
        log_error("Prepared statement failed: " . $con->error);
        echo "<h3 style='color:red; text-align:center;'>Internal error. Please try again later.</h3>";
        exit;
    }

    $stmt->bind_param("s", $reg_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Hash the entered password with SHA256
        $input_password_hash = hash('sha256', trim($password));

        // Compare the hashes
        if ($input_password_hash === strtolower($user['password'])) {
            log_error("Successful login for user: " . $user['reg_no'] . " as " . $role);
            session_regenerate_id(true);

            $_SESSION['loggedin'] = true;
            $_SESSION['reg_no'] = $user['reg_no'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $role;

            switch ($role) {
                case 'Student':
                    header("Location: student_dashboard.php");
                    exit;
                case 'Mentor':
                    header("Location: mentor_dashboard.php");
                    exit;
                case 'HOD':
                    header("Location: hod_dashboard.php");
                    exit;
                default:
                    log_error("Invalid role '" . $role . "' for user: " . $user['reg_no']);
                    echo "<h3 style='color:red; text-align:center;'>Invalid role.</h3>";
                    exit;
            }
        } else {
            // Debug for troubleshooting
            echo "<pre>Input hash: '$input_password_hash'\nDB hash:   '" . strtolower($user['password']) . "'</pre>";
            log_error("Login failed for user: $reg_no in $table. Incorrect password.");
            echo "<h3 style='color:red; text-align:center;'>Invalid register number or password.</h3>";
            exit;
        }
    } else {
        log_error("Login failed for user: $reg_no in $table. Register number not found.");
        echo "<h3 style='color:red; text-align:center;'>Invalid register number or password.</h3>";
        exit;
    }
    $stmt->close();
}
?>
<?php
if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout') {
    echo "<h3 style='color:green; text-align:center;'>You have been logged out successfully.</h3>";
}
?>


<?php
require_once __DIR__ . '/includes/session_manager.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // --- Student Info ---
    $register_no = $_POST['registerNo'] ?? '';
    $student_name = $_POST['studentName'] ?? '';
    $year = $_POST['year'] ?? '';
    $department = $_POST['department'] ?? '';
    $section = $_POST['section'] ?? '';
    $mentor = $_POST['mentor'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $od_type = $_POST['odType'] ?? '';

    // --- Duration flags ---
    $full_day = isset($_POST['fullDay']);
    $periodwise = isset($_POST['periodwise']);
    $many_day = isset($_POST['daywise']);

    $od_date = $_POST['od_date'] ?? null;
    $from_time = $_POST['from_time'] ?? null;
    $to_time = $_POST['to_time'] ?? null;
    $from_date = $_POST['from_date'] ?? null;
    $to_date = $_POST['to_date'] ?? null;

    // --- External OD ---
    $from_date_ext = $_POST['from_date_ext'] ?? null;
    $to_date_ext = $_POST['to_date_ext'] ?? null;
    $college_name = $_POST['college_name'] ?? null;
    $event_name = $_POST['event_name'] ?? null;

    // --- Lab & system access ---
    $lab_required = isset($_POST['labRequired']) ? 1 : 0;
    $system_required = isset($_POST['systemRequired']) ? 1 : 0;
    $lab_name = ($lab_required && !empty($_POST['labName'])) ? $_POST['labName'] : null;

    // --- Bonafide ---
    $request_bonafide = isset($_POST['request_bonafide']) ? 1 : 0;

    // --- Determine OD Dates ---
    if ($od_type === "internal") {
        if ($full_day) {
            $from_date = $to_date = $od_date;
        } elseif ($periodwise) {
            $from_date = $to_date = date("Y-m-d");
        } elseif ($many_day) {
            // $from_date and $to_date already set
        }
    } elseif ($od_type === "external") {
        $from_date = $from_date_ext;
        $to_date = $to_date_ext;
    }

    // --- Insert into od_applications ---
    $sql = "INSERT INTO od_applications 
        (register_no, student_name, year, department, section, mentor, purpose, od_type,
         od_date, from_time, to_time, from_date, to_date,
         college_name, event_name, lab_required, lab_name, system_required, request_bonafide)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssssssssis",
        $register_no,
        $student_name,
        $year,
        $department,
        $section,
        $mentor,
        $purpose,
        $od_type,
        $od_date,
        $from_time,
        $to_time,
        $from_date,
        $to_date,
        $college_name,
        $event_name,
        $lab_required,
        $lab_name,
        $system_required,
        $request_bonafide
    );
    $stmt->execute();
    $od_id = $stmt->insert_id;
    $stmt->close();

    // --- Applicant as default team member ---
    $applicant_sql = "INSERT INTO od_team_members
        (od_id, member_name, member_regno, member_year, member_department, member_section, mentor)
        VALUES (?,?,?,?,?,?,?)";
    $applicant_stmt = $conn->prepare($applicant_sql);
    $applicant_stmt->bind_param(
        "issssss",
        $od_id,
        $student_name,
        $register_no,
        $year,
        $department,
        $section,
        $mentor
    );
    $applicant_stmt->execute();
    $applicant_stmt->close();

    // --- Additional team members ---
    $member_sql = "INSERT INTO od_team_members
        (od_id, member_name, member_regno, member_year, member_department, member_section, mentor)
        VALUES (?,?,?,?,?,?,?)";
    $member_stmt = $conn->prepare($member_sql);

    $i = 0;
    $notified_mentors = [];
    require_once 'phpMailer.php';

    while (isset($_POST["member_name_$i"])) {
        $m_name = trim($_POST["member_name_$i"] ?? '');
        $m_reg = trim($_POST["member_regno_$i"] ?? '');
        $m_year = $_POST["member_year_$i"] ?? '';
        $m_dept = $_POST["member_department_$i"] ?? '';
        $m_sec = $_POST["member_section_$i"] ?? '';
        $m_mentor = $_POST["member_mentor_$i"] ?? '';

        if ($m_name && $m_reg) {
            $member_stmt->bind_param(
                "issssss",
                $od_id,
                $m_name,
                $m_reg,
                $m_year,
                $m_dept,
                $m_sec,
                $m_mentor
            );
            $member_stmt->execute();

            // Send email notification to this member's mentor if not already notified
            if (!in_array($m_mentor, $notified_mentors)) {
                $mentor_email = "";
                $stmt_email = $conn->prepare("SELECT mentor_email FROM mentors WHERE name = ?");
                $stmt_email->bind_param("s", $m_mentor);
                $stmt_email->execute();
                $stmt_email->bind_result($mentor_email);
                $stmt_email->fetch();
                $stmt_email->close();

                if ($mentor_email) {
                    $formData = [
                        'mentor_email' => $mentor_email,
                        'mentor' => $m_mentor,
                    ];
                    sendOdNotification($formData, $od_id);
                    $notified_mentors[] = $m_mentor;
                } else {
                    error_log("Mentor email not found for team member mentor: " . $m_mentor);
                }
            }
        }
        $i++;
    }
    $member_stmt->close();

    // --- Send email to main applicant's mentor as well if not already notified ---
    if (!in_array($mentor, $notified_mentors)) {
        $mentor_email_main = "";
        $stmt_main = $conn->prepare("SELECT mentor_email FROM mentors WHERE name = ?");
        $stmt_main->bind_param("s", $mentor);
        $stmt_main->execute();
        $stmt_main->bind_result($mentor_email_main);
        $stmt_main->fetch();
        $stmt_main->close();

        if ($mentor_email_main) {
            $formData = [
                'mentor_email' => $mentor_email_main,
                'mentor' => $mentor,
            ];
            sendOdNotification($formData, $od_id);
            $notified_mentors[] = $mentor;
        } else {
            error_log("Mentor email not found for main applicant mentor: " . $mentor);
        }
    }

    $_SESSION['flash_success'] = "✅ OD Request submitted successfully. Application ID: {$od_id}";
    header("Location: student_dashboard.php");
    exit();

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "<pre style='color:#b00020;white-space:pre-wrap'>
    Database Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

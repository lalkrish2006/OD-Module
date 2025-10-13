<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // If POST, handle submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Main student info
        $register_no  = $_POST['register_no'] ?? null;
        $student_name = $_POST['studentName']     ?? null;
        $year         = $_POST['year']            ?? null;
        $department   = $_POST['department']      ?? null;
        $section      = $_POST['section']         ?? null;
        $mentor       = $_POST['mentor']          ?? null;
        $purpose      = $_POST['purpose']         ?? null;
        $od_type      = $_POST['odType']          ?? null; // internal/external

        // Internal OD duration
        $full_day    = isset($_POST['fullDay']) ? true : false;
        $periodwise  = isset($_POST['periodwise']) ? true : false;
        $many_day    = isset($_POST['daywise']) ? true : false;

        $od_date     = $_POST['od_date']         ?? null; // full-day
        $from_time   = $_POST['from_time']       ?? null; // periodwise
        $to_time     = $_POST['to_time']         ?? null;

        $from_date   = $_POST['from_date']       ?? null; // internal/many day
        $to_date     = $_POST['to_date']         ?? null;
        $college_name  = $_POST['college_name']  ?? null;
        $event_name    = $_POST['event_name']    ?? null;
        $request_bonafide = isset($_POST['request_bonafide']) ? 1 : 0;

        // ----- Determine actual OD dates -----
        $from_date_ext = $_POST['from_date_ext'] ?? null;
        $to_date_ext   = $_POST['to_date_ext'] ?? null;

        if ($od_type === "internal") {
            if ($full_day) {
                $from_date = $od_date;
                $to_date   = $od_date;
            } elseif ($periodwise) {
                $from_date = $od_date ?? date("Y-m-d");
                $to_date   = $from_date;
            } elseif ($many_day) {
                $from_date = $_POST['from_date'] ?? null;
                $to_date   = $_POST['to_date'] ?? null;
            }
        } elseif ($od_type === "external") {
            $from_date = $from_date_ext;
            $to_date   = $to_date_ext;
        }

        // Insert main student OD info
        $sql = "INSERT INTO od_applications
            (register_no, student_name, year, department, section, mentor, purpose, od_type,
             od_date, from_time, to_time, from_date, to_date, college_name, event_name, request_bonafide, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $status = "Pending"; // default status
        $stmt->bind_param(
            "ssssssssssssssssi",
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
            $request_bonafide,
            $status
        );
        $stmt->execute();
        $od_id = $stmt->insert_id;
        $stmt->close();

        // Insert applicant also into team_members
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

        // Team members
        $member_sql = "INSERT INTO od_team_members
            (od_id, member_name, member_regno, member_year, member_department, member_section, mentor)
            VALUES (?,?,?,?,?,?,?)";
        $member_stmt = $conn->prepare($member_sql);

        $i = 0;
        $members_saved = 0;
        while (isset($_POST["member_name_$i"])) {
            $m_name   = trim($_POST["member_name_$i"] ?? '');
            $m_reg    = trim($_POST["member_regno_$i"] ?? '');
            $m_year   = $_POST["member_year_$i"]       ?? null;
            $m_dept   = $_POST["member_department_$i"] ?? null;
            $m_sec    = $_POST["member_section_$i"]    ?? null;
            $m_mentor = $_POST["member_mentor_$i"]     ?? null;

            if ($m_name !== '' && $m_reg !== '') {
                $member_stmt->bind_param("issssss", $od_id, $m_name, $m_reg, $m_year, $m_dept, $m_sec, $m_mentor);
                $member_stmt->execute();
                $members_saved++;
            }
            $i++;
        }
        $member_stmt->close();

        // Set a session flash message
        $_SESSION['flash_success'] = "✅ OD Request submitted successfully. Application ID: {$od_id}";

        // Redirect based on logged-in user's register number
        $studentRegNo = $_SESSION['registerNo'] ?? null;

        if ($studentRegNo) {
            // All students go to student dashboard
            header("Location: student_dashboard.php");
            exit;
        } else {
            // Fallback if somehow register_no not set
            header("Location: login.php?error=session_missing");
            exit;
        }
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "<pre style='color:#b00020'>Database error: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Student OD Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f8f9fa; }
        .card-title { background: linear-gradient(90deg, #0062e6, #33aeff); }
    </style>
</head>
<body>
    <div class="container py-5">
      <div class="card shadow-lg p-4 border-0 rounded-4">
        <div class="card-body">
          <h2
            class="card-title text-center text-white fw-bold py-3 rounded-3 shadow-sm"
          >
            Student OD Application Form
          </h2>

          <form id="odForm" class="mt-4" method="POST">
            <!-- Main Student Info -->
            <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">
              Your Details
            </h5>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Register No.</label>
                <input
                  type="text"
                  class="form-control"
                  name="registerNo"
                  id="registerNo"
                  pattern="\d{12}"
                  maxlength="12"
                  title="Register Number must be exactly 12 digits"
                  required
                />
                <div class="invalid-feedback">
                  Register Number must be exactly 12 digits.
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Student Name</label>
                <input
                  type="text"
                  class="form-control"
                  name="studentName"
                  required
                />
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Current Year</label>
                <select class="form-select" name="year" required>
                  <option value="">Select Year</option>
                  <option>1st Year</option>
                  <option>2nd Year</option>
                  <option>3rd Year</option>
                  <option>4th Year</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Department</label>
                <select class="form-select" name="department" required>
                  <option value="">Select Dept</option>
                  <option>CSE</option>
                  <option>ECE</option>
                  <option>EEE</option>
                  <option>MECH</option>
                  <option>CIVIL</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Section</label>
                <select class="form-select" name="section" required>
                  <option value="">Select Section</option>
                  <option>A</option>
                  <option>B</option>
                  <option>C</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Mentor</label>
                <select class="form-select" name="mentor" required>
                  <option value="">Select Mentor</option>
                  <option>Dr. A. Kumar</option>
                  <option>Dr. B. Priya</option>
                  <option>Mr. C. Rajesh</option>
                  <option>Ms. D. Anitha</option>
                  <option>Mr. E. Suresh</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Purpose</label>
                <textarea
                  class="form-control"
                  rows="3"
                  name="purpose"
                  required
                ></textarea>
              </div>
            </div>

            <!-- Team Members -->
            <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">
              Team Details
            </h5>
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold"
                  >No. of Additional Team Members</label
                >
                <input
                  type="number"
                  id="teamCount"
                  class="form-control"
                  min="0"
                  value="0"
                />
              </div>
            </div>
            <div id="teamMembersContainer"></div>

            <!-- OD Type -->
            <h5 class="fw-bold text-primary border-bottom pb-2 mt-4 mb-4">
              OD Type
            </h5>
            <div class="mb-3">
              <label class="form-label fw-semibold">Select OD Type</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    name="odType"
                    id="odInternal"
                    value="internal"
                    checked
                  />
                  <label class="form-check-label" for="odInternal"
                    >Internal</label
                  >
                </div>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    name="odType"
                    id="odExternal"
                    value="external"
                  />
                  <label class="form-check-label" for="odExternal"
                    >External</label
                  >
                </div>
              </div>
            </div>

            <!-- Internal OD Fields -->
            <div id="internalFields">
              <label class="form-label fw-semibold">Select OD Duration</label>
              <div class="d-flex flex-column gap-2 mb-3">
                <div class="form-check">
                  <input
                    type="checkbox"
                    id="fullDay" name="fullDay"
                    class="form-check-input"
                  />
                  <label class="form-check-label" for="fullDay"
                    >Full Day OD</label
                  >
                </div>
                <div class="form-check">
                  <input
                    type="checkbox"
                    id="periodwise" name="periodwise"
                    class="form-check-input"
                  />
                  <label class="form-check-label" for="periodwise"
                    >Period-wise OD</label
                  >
                </div>
                <div class="form-check">
                  <input
                    type="checkbox"
                    id="daywise" name="daywise"
                    class="form-check-input"
                  />
                  <label class="form-check-label" for="daywise"
                    >More than a Day</label
                  >
                </div>
              </div>

              <div id="periodwiseFields" class="row g-3 mb-3 d-none">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">From Time</label>
                  <input type="time" class="form-control" name="from_time" />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">To Time</label>
                  <input type="time" class="form-control" name="to_time" />
                </div>
              </div>

              <div id="daywiseFields" class="row g-3 mb-3 d-none">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">From Date</label>
                  <input type="date" class="form-control" name="from_date" />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">To Date</label>
                  <input type="date" class="form-control" name="to_date" />
                </div>
              </div>

              <div id="fullDayFields" class="row g-3 mb-3 d-none">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">OD Date</label>
                  <input type="date" class="form-control" name="od_date" />
                </div>
              </div>
            </div>

            <!-- External OD Fields -->
            <div id="externalFields" class="d-none">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">College Name</label>
                  <input type="text" class="form-control" name="college_name" />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Event Name</label>
                  <input type="text" class="form-control" name="event_name" />
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">From Date</label>
                  <input
                    type="date"
                    class="form-control"
                    name="from_date_ext"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">To Date</label>
                  <input type="date" class="form-control" name="to_date_ext" />
                </div>
              </div>
              <div class="form-check mb-3">
                <input
                  type="checkbox"
                  class="form-check-input"
                  name="request_bonafide"
                />
                <label class="form-check-label"
                  >Request Bonafide Certificate</label
                >
              </div>
            </div>

            <!-- Submit -->
            <div class="text-center mt-4">
              <button
                type="submit"
                class="btn btn-primary px-5 py-2 fw-bold rounded-3 shadow-sm"
                style="
                  background: linear-gradient(90deg, #0062e6, #33aeff);
                  border: none;
                "
              >
                Submit OD Request
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      const teamCountInput = document.getElementById("teamCount");
      const teamMembersContainer = document.getElementById(
        "teamMembersContainer"
      );

      teamCountInput.addEventListener("input", () => {
        teamMembersContainer.innerHTML = "";
        const count = parseInt(teamCountInput.value) || 0;
        for (let i = 0; i < count; i++) {
          teamMembersContainer.innerHTML += `
          <div class="card p-3 mb-3 shadow-sm border-0 rounded-3">
            <h6 class="fw-bold text-secondary mb-3">Team Member ${i + 1}</h6>
            <div class="row g-3 mb-2">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Name</label>
                <input type="text" class="form-control" name="member_name_${i}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Register No.</label>
                <input type="number" class="form-control" name="member_regno_${i}" required>
              </div>
            </div>
            <div class="row g-3 mb-2">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Year</label>
                <select class="form-select" name="member_year_${i}" required>
                  <option value="">Select Year</option>
                  <option>1st Year</option>
                  <option>2nd Year</option>
                  <option>3rd Year</option>
                  <option>4th Year</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Department</label>
                <select class="form-select" name="member_department_${i}" required>
                  <option value="">Select Dept</option>
                  <option>CSE</option>
                  <option>ECE</option>
                  <option>EEE</option>
                  <option>MECH</option>
                  <option>CIVIL</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Section</label>
                <select class="form-select" name="member_section_${i}" required>
                  <option value="">Select Section</option>
                  <option>A</option>
                  <option>B</option>
                  <option>C</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Mentor</label>
                <select class="form-select" name="member_mentor_${i}" required>
                  <option value="">Select Mentor</option>
                  <option>Dr. A. Kumar</option>
                  <option>Dr. B. Priya</option>
                  <option>Mr. C. Rajesh</option>
                  <option>Ms. D. Anitha</option>
                  <option>Mr. E. Suresh</option>
                </select>
              </div>
            </div>
          </div>
        `;
        }
      });

      // Internal/External toggle
      document.getElementById("odInternal").addEventListener("change", () => {
        document.getElementById("internalFields").classList.remove("d-none");
        document.getElementById("externalFields").classList.add("d-none");
      });
      document.getElementById("odExternal").addEventListener("change", () => {
        document.getElementById("externalFields").classList.remove("d-none");
        document.getElementById("internalFields").classList.add("d-none");
      });

      // Duration toggles
      const fullDay = document.getElementById("fullDay");
      const periodwise = document.getElementById("periodwise");
      const daywise = document.getElementById("daywise");

      fullDay.addEventListener("change", () => {
        periodwise.checked = false;
        daywise.checked = false;
        document
          .getElementById("fullDayFields")
          .classList.toggle("d-none", !fullDay.checked);
        document.getElementById("periodwiseFields").classList.add("d-none");
        document.getElementById("daywiseFields").classList.add("d-none");
      });

      periodwise.addEventListener("change", () => {
        fullDay.checked = false;
        daywise.checked = false;
        document
          .getElementById("periodwiseFields")
          .classList.toggle("d-none", !periodwise.checked);
        document.getElementById("fullDayFields").classList.add("d-none");
        document.getElementById("daywiseFields").classList.add("d-none");
      });

      daywise.addEventListener("change", () => {
        fullDay.checked = false;
        periodwise.checked = false;
        document
          .getElementById("daywiseFields")
          .classList.toggle("d-none", !daywise.checked);
        document.getElementById("fullDayFields").classList.add("d-none");
        document.getElementById("periodwiseFields").classList.add("d-none");
      });

      // Register No validation
      document.getElementById("odForm").addEventListener("submit", function(e) {
        const regNoField = document.getElementById("registerNo");
        const regNo = regNoField.value.trim();

        if (!/^\d{12}$/.test(regNo)) {
          e.preventDefault(); // stop form submission
          regNoField.classList.add("is-invalid");
          alert("Register Number must be exactly 12 digits!");
        } else {
          regNoField.classList.remove("is-invalid");
        }
      });
    </script>
</body>
</html>

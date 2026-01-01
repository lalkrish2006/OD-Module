<?php
require_once __DIR__ . '/includes/session_manager.php';

// Check if user is logged in as student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: loginin.php");
    exit;
}

// Fetch student details from session
$student = $_SESSION['user'] ?? [];
$registerNo = $student['register_no'] ?? '';
$name = $student['name'] ?? '';
$department = $student['department'] ?? '';
$year = $student['year'] ?? ''; // Assuming 'year' column exists in students table
$section = $student['section'] ?? ''; // Assuming 'section' column exists
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Student OD Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card-title {
            background: linear-gradient(90deg, #0062e6, #33aeff);
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="card shadow-lg p-4 border-0 rounded-4">
            <div class="card-body">
                <h2 class="card-title text-center text-white fw-bold py-3 rounded-3 shadow-sm">
                    Student OD Application Form
                </h2>

                <form id="odForm" class="mt-4" action="submit_od.php" method="POST">
                    <!-- Main Student Info -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">
                        Your Details
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Register No.</label>
                            <input type="text" class="form-control" name="registerNo" id="registerNo"
                                value="<?= htmlspecialchars($registerNo) ?>" readonly required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student Name</label>
                            <input type="text" class="form-control" name="studentName"
                                value="<?= htmlspecialchars($name) ?>" readonly required />
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Current Year</label>
                            <input type="text" class="form-control" name="year" value="<?= htmlspecialchars($year) ?>"
                                readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" class="form-control" name="department"
                                value="<?= htmlspecialchars($department) ?>" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Section</label>
                            <input type="text" class="form-control" name="section"
                                value="<?= htmlspecialchars($section) ?>" readonly required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mentor</label>
                            <select class="form-select" name="mentor" id="mentorSelect" required>
                                <option value="">Select Mentor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Purpose</label>
                            <textarea class="form-control" rows="3" name="purpose" required></textarea>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">
                        Team Details
                    </h5>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">No. of Additional Team Members</label>
                            <input type="number" id="teamCount" class="form-control" min="0" value="0" />
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
                                <input class="form-check-input" type="radio" name="odType" id="odInternal"
                                    value="internal" checked />
                                <label class="form-check-label" for="odInternal">Internal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="odType" id="odExternal"
                                    value="external" />
                                <label class="form-check-label" for="odExternal">External</label>
                            </div>
                        </div>
                    </div>

                    <!-- Internal OD Fields -->
                    <div id="internalFields">
                        <label class="form-label fw-semibold">Select OD Duration</label>
                        <div class="d-flex flex-column gap-2 mb-3">
                            <div class="form-check">
                                <input type="checkbox" id="fullDay" name="fullDay" class="form-check-input" />
                                <label class="form-check-label" for="fullDay">Full Day OD</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="periodwise" name="periodwise" class="form-check-input" />
                                <label class="form-check-label" for="periodwise">Period-wise OD</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="daywise" name="daywise" class="form-check-input" />
                                <label class="form-check-label" for="daywise">More than a Day</label>
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

                        <!-- ✅ Lab Access Section -->
                        <div class="mt-4 p-3 border rounded-3 bg-light">
                            <h6 class="fw-bold text-primary mb-3">Lab Access (for Internal OD)</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="labRequired" name="labRequired" />
                                <label class="form-check-label fw-semibold" for="labRequired">
                                    Lab Required
                                </label>
                            </div>

                            <div id="labFields" class="row g-3 d-none">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Lab Name</label>
                                    <select class="form-select" name="labName">
                                        <option value="">Select Lab</option>
                                        <option>AI Lab</option>
                                        <option>IoT Lab</option>
                                        <option>Network Lab</option>
                                        <option>Hardware Lab</option>
                                        <option>Data Science Lab</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-center mt-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="systemRequired"
                                            name="systemRequired" />
                                        <label class="form-check-label fw-semibold" for="systemRequired">
                                            System Required
                                        </label>
                                    </div>
                                </div>
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
                                <input type="date" class="form-control" name="from_date_ext" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">To Date</label>
                                <input type="date" class="form-control" name="to_date_ext" />
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="request_bonafide" />
                            <label class="form-check-label">Request Bonafide Certificate</label>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded-3 shadow-sm" style="
                  background: linear-gradient(90deg, #0062e6, #33aeff);
                  border: none;
                ">
                            Submit OD Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const teamCountInput = document.getElementById("teamCount");
        const teamMembersContainer = document.getElementById("teamMembersContainer");

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
            <select class="form-select member-year" name="member_year_${i}" data-index="${i}" required>
              <option value="">Select Year</option>
              <option>1st Year</option>
              <option>2nd Year</option>
              <option>3rd Year</option>
              <option>4th Year</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Department</label>
            <select class="form-select member-dept" name="member_department_${i}" data-index="${i}" required>
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
            <select class="form-select member-section" name="member_section_${i}" data-index="${i}" required>
              <option value="">Select Section</option>
              <option>A</option>
              <option>B</option>
              <option>C</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Mentor</label>
            <select class="form-select member-mentor" name="member_mentor_${i}" id="mentorSelect_${i}" required>
              <option value="">Select Mentor</option>
            </select>
          </div>
        </div>
      </div>`;
            }

            document.querySelectorAll(".member-year, .member-dept, .member-section").forEach((select) => {
                select.addEventListener("change", () => {
                    const i = select.dataset.index;
                    fetchMentorsForMember(i);
                });
            });
        });

        function fetchMentorsForMember(i) {
            const year = document.querySelector(`select[name="member_year_${i}"]`).value;
            const dept = document.querySelector(`select[name="member_department_${i}"]`).value;
            const section = document.querySelector(`select[name="member_section_${i}"]`).value;
            const mentorSelect = document.getElementById(`mentorSelect_${i}`);
            if (!year || !dept || !section) {
                mentorSelect.innerHTML = `<option value="">Select Mentor</option>`;
                return;
            }
            fetch(`get_mentors.php?year=${year}&department=${dept}&section=${section}`)
                .then((res) => res.json())
                .then((data) => {
                    mentorSelect.innerHTML = `<option value="">Select Mentor</option>`;
                    data.forEach((name) => {
                        const opt = document.createElement("option");
                        opt.value = name;
                        opt.textContent = name;
                        mentorSelect.appendChild(opt);
                    });
                })
                .catch((err) => console.error(err));
        }

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
            document.getElementById("fullDayFields").classList.toggle("d-none", !fullDay.checked);
            document.getElementById("periodwiseFields").classList.add("d-none");
            document.getElementById("daywiseFields").classList.add("d-none");
        });

        periodwise.addEventListener("change", () => {
            fullDay.checked = false;
            daywise.checked = false;
            document.getElementById("periodwiseFields").classList.toggle("d-none", !periodwise.checked);
            document.getElementById("fullDayFields").classList.add("d-none");
            document.getElementById("daywiseFields").classList.add("d-none");
        });

        daywise.addEventListener("change", () => {
            fullDay.checked = false;
            periodwise.checked = false;
            document.getElementById("daywiseFields").classList.toggle("d-none", !daywise.checked);
            document.getElementById("fullDayFields").classList.add("d-none");
            document.getElementById("periodwiseFields").classList.add("d-none");
        });

        // ✅ Lab Required toggle
        const labRequired = document.getElementById("labRequired");
        const labFields = document.getElementById("labFields");
        labRequired.addEventListener("change", () => {
            labFields.classList.toggle("d-none", !labRequired.checked);
        });

        // ✅ Pre-fetch mentors for the logged-in student (Autofilled values)
        const year = "<?= htmlspecialchars($year) ?>";
        const dept = "<?= htmlspecialchars($department) ?>";
        const section = "<?= htmlspecialchars($section) ?>";
        const mentorSelect = document.getElementById("mentorSelect");

        if (year && dept && section) {
            fetch(`get_mentors.php?year=${year}&department=${dept}&section=${section}`)
                .then((res) => res.json())
                .then((data) => {
                    mentorSelect.innerHTML = `<option value="">Select Mentor</option>`;
                    data.forEach((name) => {
                        const opt = document.createElement("option");
                        opt.value = name;
                        opt.textContent = name;
                        mentorSelect.appendChild(opt);
                    });
                })
                .catch((err) => console.error(err));
        }

        // --- NEW: Restrict dates to yesterday onwards ---
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            // Subtract 1 day to get yesterday
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);

            // Format as YYYY-MM-DD
            const minDate = yesterday.toISOString().split('T')[0];

            // Apply to all date inputs
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.setAttribute('min', minDate);
            });
        });
    </script>
</body>

</html>
<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['hod', 'admin'])) {
    header("Location: login.php?error=unauthorized");
    exit;
}

// ✅ If HOD, use their department from session
// ✅ If ADMIN, allow department via ?department= param
if ($_SESSION['role'] === 'hod') {
    $hodDept = $_SESSION['user']['department'];
} elseif ($_SESSION['role'] === 'admin') {
    // Admin must pass department in URL
    $hodDept = $_GET['department'] ?? $_SESSION['department'] ?? ''; // Allow GET param
    if (empty($hodDept)) {
        die("⚠️ Department not specified for admin access. Please add ?department=DEPT_NAME to the URL.");
    }
    $_SESSION['department'] = $hodDept; // Store in session for filter reloads
}

// --- NEW: Get filter values ---
$search = $_GET['search'] ?? '';
$monthFilter = $_GET['month'] ?? '';

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // ✅ Handle HOD override for individual team members
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Case 1: HOD clicked on application-level Approve/Reject
        if (isset($_POST['od_id']) && !isset($_POST['member_id']) && isset($_POST['action'])) {
            $od_id = (int)$_POST['od_id'];
            $hodAction = $_POST['action']; // 'accept' or 'reject'
            $hodStatus = $hodAction === 'accept' ? "HOD Accepted" : "HOD Rejected";

            $sqlUpdate = "UPDATE od_applications SET status=? WHERE id=?";
            $stmt = $conn->prepare($sqlUpdate);
            $stmt->bind_param("si", $hodStatus, $od_id);
            $stmt->execute();
            $stmt->close();
            
            // --- 🚀 NEW LOGIC FOR TECHNICIAN EMAIL NOTIFICATION (WITH DEBUGGING) ---
            
            // Condition 1: Check if HOD accepted
            if ($hodAction === 'accept') {
                
                // var_dump("DEBUG: Condition 1 MET. HOD action is 'accept'.");

                // Fetch app details to check other conditions
                $stmtApp = $conn->prepare("SELECT od_type, lab_required, department FROM od_applications WHERE id = ?");
                $stmtApp->bind_param("i", $od_id);
                $stmtApp->execute();
                $appDetails = $stmtApp->get_result()->fetch_assoc();
                $stmtApp->close();

                if ($appDetails) {
                    // var_dump("DEBUG: Found application details:", $appDetails);

                    $odType = $appDetails['od_type'];
                    $labRequired = (int)($appDetails['lab_required'] ?? 0); 
                    $appDepartment = $appDetails['department'];

                    // var_dump("DEBUG: Checking Condition 2 (od_type): " . $odType);
                    // var_dump("DEBUG: Checking Condition 3 (lab_required): " . $labRequired);

                    // Condition 2: od_type is 'Internal'
                    // Condition 3: lab_required is 1 (ticked)
                    if (strtolower($odType) === 'internal' && $labRequired === 1) {
                        
                        // var_dump("DEBUG: Conditions 2 & 3 MET. Finding technician for department: " . $appDepartment);

                        // All conditions met. Find the technician for this department
                        $stmtTech = $conn->prepare("SELECT email, name FROM lab_technicians WHERE department = ? LIMIT 1");
                        $stmtTech->bind_param("s", $appDepartment);
                        $stmtTech->execute();
                        $stmtTech->bind_result($techEmail, $techName);
                        $stmtTech->fetch();
                        $stmtTech->close();

                        // var_dump("DEBUG: Found Technician Email: " . ($techEmail ?? 'NOT FOUND'));

                        // Find HOD's email for the Reply-To
                        $stmtHod = $conn->prepare("SELECT email, name FROM hods WHERE department = ? LIMIT 1");
                        $stmtHod->bind_param("s", $hodDept); // $hodDept is from the session
                        $stmtHod->execute();
                        $stmtHod->bind_result($hodEmail, $hodName);
                        $stmtHod->fetch();
                        $stmtHod->close();

                        // var_dump("DEBUG: Found HOD Email (for reply-to): " . ($hodEmail ?? 'NOT FOUND'));

                        // Send the email
                        if ($techEmail && $hodEmail) {
                            
                            // var_dump("DEBUG: ALL CONDITIONS MET. Attempting to send email...");
                            
                            require_once 'phpMailer.php'; 
                            
                            $subject = "Lab Booking Required for Internal OD (ID: $od_id)";
                            $body = "
                                Dear $techName,<br><br>
                                The HOD has approved an internal OD application (ID: <b>$od_id</b>) that requires lab facilities for the <b>$appDepartment</b> department.<br><br>
                                Please review the request and make the necessary arrangements.<br><br>
                                <a href='http://localhost/OD-Module/loginin.php'>Click here to open OD Module</a><br><br>
                                Regards,<br>
                                OD Management System
                            ";
                            
                            sendEmailToHOD($techEmail, $techName, $subject, $body, $hodEmail, $hodName);

                            // var_dump("DEBUG: Mail function called. Check your technician's inbox and your PHP error log.");

                        } else {
                            // var_dump("DEBUG: Email NOT sent. Missing technician or HOD email.");
                        }
                    } else {
                        // var_dump("DEBUG: Email NOT sent. Conditions 2 or 3 FAILED.");
                    }
                } else {
                    // var_dump("DEBUG: FAILED. Could not find application details for OD ID: " . $od_id);
                }
            } else {
                //  var_dump("DEBUG: Email NOT sent. HOD action was not 'accept'. Action was: " . $hodAction);
            }

            // We must stop the script to see the output
            // die(" --- END OF DEBUGGING --- ");
            
            // --- END OF NEW LOGIC ---

        }

        // Case 2: HOD override for individual team member
        if (isset($_POST['member_id'], $_POST['od_id'], $_POST['action'])) {
            $member_id = (int) $_POST['member_id'];
            $od_id = (int) $_POST['od_id'];
            // Use HOD status prefixes to be clear
            $hodStatus = $_POST['action'] === 'accept' ? "HOD Accepted" : "HOD Rejected"; 

            $sqlUpdate = "UPDATE od_team_members SET mentor_status=? WHERE id=?";
            $stmt = $conn->prepare($sqlUpdate);
            $stmt->bind_param("si", $hodStatus, $member_id);
            $stmt->execute();
            $stmt->close();
        }
    }


    // --- MODIFIED: Dynamic SQL Query ---
    $searchSql = "";
    $monthSql = "";
    $params = [$hodDept]; // Start with HOD dept
    $types = "s";

    if (!empty($search)) {
        $like = "%" . $search . "%";
        $searchSql = " AND (
            o.id LIKE ? OR
            o.register_no LIKE ? OR
            o.student_name LIKE ? OR
            o.year LIKE ? OR
            o.department LIKE ? OR
            o.section LIKE ? OR
            o.od_type LIKE ? OR
            o.purpose LIKE ? OR
            o.college_name LIKE ? OR
            o.event_name LIKE ? OR
            t.member_name LIKE ? OR
            t.member_regno LIKE ? OR
            t.mentor LIKE ?
        )";
        // Add 13 $like params
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        $types .= str_repeat('s', 13);
    }
    
    // --- NEW: Add month filter logic ---
    if (!empty($monthFilter)) {
        // Filter by the month of the 'from_date'
        $monthSql = " AND DATE_FORMAT(o.from_date, '%Y-%m') = ?";
        $params[] = $monthFilter;
        $types .= "s";
    }

    $sql = "SELECT DISTINCT o.*
            FROM od_applications o
            LEFT JOIN od_team_members t ON o.id = t.od_id
            WHERE o.department = ?
              
              -- ❌ Exclude single-student ODs rejected by mentor
              AND NOT (
                (o.status = 'Mentors Rejected')
                OR EXISTS (
                  SELECT 1 FROM od_team_members t2
                  WHERE t2.od_id = o.id 
                    AND t2.mentor_status = 'Rejected'
                    AND (SELECT COUNT(*) FROM od_team_members t3 WHERE t3.od_id = o.id) = 1
                )
              )

              AND (
                -- Non-internal ODs
                (o.od_type != 'Internal' AND (o.status = 'Mentors Accepted' OR o.status LIKE 'HOD%'))
                OR
                -- Internal ODs: all mentors responded
                (o.od_type = 'Internal'
                 AND NOT EXISTS (
                       SELECT 1 FROM od_team_members tm
                       WHERE tm.od_id = o.id AND tm.mentor_status = 'Pending'
                   )
                 AND (o.status LIKE 'Mentors%' OR o.status LIKE 'HOD%')
                )
              )
              $searchSql
              $monthSql  -- Added month filter SQL
            ORDER BY o.id DESC";

    $stmt = $conn->prepare($sql);
    
    // --- NEW: Dynamic bind_param ---
    $stmt->bind_param($types, ...$params); 
    
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // --- End of Modified Query ---


    // ✅ Fetch team members
    $teamData = [];
    foreach ($applications as $app) {
        $od_id = $app['id'];
        $sqlTeam = "SELECT * FROM od_team_members WHERE od_id=?";
        $stmt = $conn->prepare($sqlTeam);
        $stmt->bind_param("i", $od_id);
        $stmt->execute();
        $teamData[$od_id] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database error: " . htmlspecialchars($e->getMessage()));
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// ✅ AJAX logic is no longer needed for live search
// (The old 'if ajax=1' block was here)

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>HOD Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #0d6efd; /* Bootstrap Blue */
            --danger-color: #dc3545;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --secondary-color: #6c757d;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --dark-text: #212529;
        }

        body {
            background: var(--light-bg);
            font-family: 'Inter', sans-serif;
            color: var(--dark-text);
        }

        .container-fluid {
            max-width: 1600px;
        }

        .dashboard-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-header h2 {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .user-info {
            font-size: 0.9rem;
        }
        .user-info .fw-bold {
            color: var(--success-color);
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        /* --- NEW: Filter Bar Styles --- */
        .filter-bar-card .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        .filter-bar-card .form-control,
        .filter-bar-card .form-select {
            font-size: 0.9rem;
            border-radius: 6px;
        }
        .filter-bar-card .btn-sm {
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 0.375rem 0.75rem;
        }
        /* --- End Filter Bar Styles --- */

        .table-card {
            overflow: hidden;
        }

        .table {
            background: var(--white);
            font-size: 0.85rem;
        }

        .table th,
        .table td {
            text-align: center;
            vertical-align: middle;
            padding: 0.75rem 0.5rem;
            white-space: nowrap;
        }

        .table thead th {
            background: var(--primary-color);
            color: var(--white);
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f7ff;
        }
        
        .table td.detail-group {
            text-align: left;
            white-space: normal;
            min-width: 200px;
        }

        .badge {
            font-size: 0.7rem;
            padding: 0.4em 0.75em;
            border-radius: 50rem;
            font-weight: 600;
        }

        .btn-sm, .btn-group-sm > .btn {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 50rem;
            font-weight: 600;
        }
        
        .action-form .btn {
            width: 100%;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
        
        .modal-content {
             border-radius: 0.75rem;
             border:none;
        }
        
        .table-sm th, .table-sm td {
            font-size: 0.8rem;
            padding: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        
        <header class="dashboard-header d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="bi bi-person-video3 me-2"></i> HOD Dashboard (<?= htmlspecialchars($hodDept) ?>)</h2>
            <div class="dashboard-header-actions">
                <a href="Edit-UserData.php" class="btn btn-primary">
                    ✏️ Edit User Data
                </a>
            </div>
            
            <div class="d-flex align-items-center">
                 <span class="text-secondary me-3 user-info">
                    Logged in as: 
                    <span class="fw-bold text-dark"><?= strtoupper($_SESSION['role']) ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </header>

        <div class="card mb-4 filter-bar-card">
            <div class="card-body p-3">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label for="search" class="form-label">Search by ID, Name, Reg No, etc.</label>
                        <input type="text" class="form-control form-control-sm" id="search" name="search" 
                               placeholder="Enter student name, register number, purpose..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="month" class="form-label">Filter by Month</label>
                        <input type="month" class="form-control form-control-sm" id="month" name="month" 
                               value="<?= htmlspecialchars($monthFilter) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill me-1"></i> Filter</button>
                        <a href="?<?php if ($_SESSION['role'] === 'admin') echo 'department=' . urlencode($hodDept); ?>" 
                           class="btn btn-outline-secondary btn-sm w-100">
                           <i class="bi bi-x-lg me-1"></i> Clear
                        </a>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <input type="hidden" name="department" value="<?= htmlspecialchars($hodDept) ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php if (empty($applications) && empty($search) && empty($monthFilter)): ?>
            <div class="alert alert-info shadow-sm">
                <i class="bi bi-info-circle me-2"></i> No OD requests are currently pending your approval.
            </div>
        <?php elseif (empty($applications)): ?>
             <div class="alert alert-warning shadow-sm">
                <i class="bi bi-search me-2"></i> No applications found matching your filter criteria.
            </div>
        <?php else: ?>
            <div class="card table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>OD ID</th>
                                <th>Student</th>
                                <th>Reg No</th>
                                <th>Year</th>
                                <th>Dept</th>
                                <th>Section</th>
                                <th>OD Type</th>
                                <th>Dates</th>
                                <th>Purpose / Event</th>
                                <th>Status</th>
                                <th>Team</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?= $app['id'] ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($app['student_name']) ?></td>
                                    <td><?= htmlspecialchars($app['register_no']) ?></td>
                                    <td><?= htmlspecialchars($app['year']) ?></td>
                                    <td><?= htmlspecialchars($app['department']) ?></td>
                                    <td><?= htmlspecialchars($app['section']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $app['od_type'] === 'Internal' ? 'info text-dark' : 'primary' ?>">
                                            <?= htmlspecialchars(ucfirst($app['od_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?php
                                        if (!empty($app['from_date']) && !empty($app['to_date'])) {
                                            echo htmlspecialchars($app['from_date']) . " → " . htmlspecialchars($app['to_date']);
                                        } elseif (!empty($app['od_date'])) {
                                            echo htmlspecialchars($app['od_date']);
                                        }
                                        ?>
                                    </td>
                                    <td class="detail-group">
                                        <div class="fw-semibold"><?= htmlspecialchars($app['purpose']) ?></div>
                                        <?php if ($app['college_name']): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-building me-1"></i>
                                                <?= htmlspecialchars($app['college_name']) ?> 
                                                (<?= htmlspecialchars($app['event_name']) ?>)
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted"><i class="bi bi-building me-1"></i> Internal</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $app['status'];
                                        $badgeClass = 'secondary';
                                        if (str_contains($status, 'Accepted')) $badgeClass = 'success';
                                        elseif (str_contains($status, 'Rejected')) $badgeClass = 'danger';
                                        elseif (str_contains($status, 'Reviewed')) $badgeClass = 'info text-dark';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?> status-badge">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($teamData[$app['id']])): ?>
                                            <button class="btn btn-outline-primary btn-sm view-team-btn"
                                                data-team='<?= json_encode($teamData[$app['id']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                                data-bs-toggle="modal" data-bs-target="#teamModal">
                                                <i class="bi bi-people-fill me-1"></i> View Team
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">Individual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['status'] === 'Mentors Accepted' || $app['status'] === 'Mentors Reviewed'): ?>
                                            <form method="post" class="d-flex flex-column gap-1 action-form">
                                                <input type="hidden" name="od_id" value="<?= $app['id'] ?>">
                                                <input type="hidden" name="action" value="">
                                                <button type="button" class="btn btn-success btn-sm btn-accept"><i class="bi bi-check-circle me-1"></i> Approve</button>
                                                <button type="button" class="btn btn-danger btn-sm btn-reject"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                                <button type="submit" class="btn btn-primary btn-sm btn-confirm d-none">Confirm</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-<?= str_contains($app['status'], 'Accepted') ? 'success' : 'danger' ?>">
                                                Action Taken
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="teamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i> Team Members & Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="teamModalBody">
                    </div>
            </div>
        </div>
    </div>

    <script>
        // ✅ Application-level approve/reject (delegated)
        document.addEventListener('click', e => {
            if (e.target.classList.contains('btn-accept') || e.target.classList.contains('btn-reject')) {
                const form = e.target.closest('.action-form');
                // Reset other buttons
                form.querySelectorAll('.btn-accept, .btn-reject').forEach(btn => {
                    btn.classList.remove('active');
                });
                // Activate clicked button
                e.target.classList.add('active');
                
                const hiddenAction = form.querySelector('input[name="action"]');
                hiddenAction.value = e.target.classList.contains('btn-accept') ? 'accept' : 'reject';
                form.querySelector('.btn-confirm').classList.remove('d-none');
            }
        });

        // ✅ Form submission (for both main page and modal)
        document.addEventListener('submit', e => {
            if (e.target.classList.contains('action-form') || e.target.classList.contains('hod-member-form')) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                
                // Keep filter query params on reload
                const postUrl = new URL(window.location.href);
                postUrl.searchParams.delete('ajax'); // Not an ajax load

                fetch(postUrl.href, { // Post to the same URL (which includes filters)
                    method: 'POST',
                    body: formData
                }).then(response => response.text()) 
                .then(text => {
                    // This is your debug logic - it's preserved
                    if (text.includes('DEBUG:')) {
                        document.body.innerHTML = '<pre>' + text.replace(/<pre>|<\/pre>/g, '') + '</pre>';
                    } else {
                        // Normal flow: reload the page to show the changes
                        window.location.reload();
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Check the console.');
                });
            }
        });

        // ✅ Show Team Modal with HOD override options (delegated)
        document.addEventListener('click', e => {
            if (e.target.classList.contains('view-team-btn')) {
                const teamData = JSON.parse(e.target.dataset.team);
                let html = `
                <div class="table-responsive">
                <table class="table table-bordered table-sm text-center table-striped align-middle">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Reg No</th><th>Year</th><th>Dept</th><th>Sec</th><th>Mentor</th><th>Status</th></tr>
                    </thead><tbody>`;
                
                teamData.forEach(m => {
                    let statusCell = '';
                    
                    // Show override buttons only if mentor rejected
                    if (m.mentor_status === 'Rejected') {
                        statusCell = `
                            <form method="post" class="hod-member-form d-flex flex-column gap-1">
                                <input type="hidden" name="member_id" value="${m.id}">
                                <input type="hidden" name="od_id" value="${m.od_id}">
                                <input type="hidden" name="action" value="">
                                <button type="button" class="btn btn-success btn-sm btn-hod-accept"><i class="bi bi-check-circle me-1"></i> HOD Accept</button>
                                <button type="button" class="btn btn-danger btn-sm btn-hod-reject"><i class="bi bi-x-circle me-1"></i> HOD Reject</button>
                                <button type="submit" class="btn btn-primary btn-sm btn-hod-confirm d-none mt-1">Confirm</button>
                            </form>`;
                    } else {
                        // Otherwise, just show status
                        let status = m.mentor_status;
                        let badgeClass = 'secondary';
                        if (status.includes('Accepted')) badgeClass = 'success';
                        else if (status.includes('Rejected')) badgeClass = 'danger';
                        
                        statusCell = `<span class="badge bg-${badgeClass}">${status}</span>`;
                    }

                    html += `<tr>
                        <td class="fw-semibold">${m.member_name}</td>
                        <td>${m.member_regno}</td>
                        <td>${m.member_year}</td>
                        <td>${m.member_department}</td>
                        <td>${m.member_section}</td>
                        <td>${m.mentor}</td>
                        <td>${statusCell}</td>
                    </tr>`;
                });

                html += "</tbody></table></div>";
                document.getElementById("teamModalBody").innerHTML = html;
            }
        });

        // ✅ HOD override actions inside modal (delegated)
        document.addEventListener('click', e => {
            if (e.target.classList.contains('btn-hod-accept') || e.target.classList.contains('btn-hod-reject')) {
                const form = e.target.closest('.hod-member-form');
                 // Reset other buttons
                form.querySelectorAll('.btn-hod-accept, .btn-hod-reject').forEach(btn => {
                    btn.classList.remove('active');
                });
                // Activate clicked button
                e.target.classList.add('active');

                const actionField = form.querySelector('input[name="action"]');
                actionField.value = e.target.classList.contains('btn-hod-accept') ? 'accept' : 'reject';
                form.querySelector('.btn-hod-confirm').classList.remove('d-none');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
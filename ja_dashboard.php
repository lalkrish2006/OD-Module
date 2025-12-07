<?php
// --- NEW: CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    session_start();

    // 1. Authentication Check
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ja') {
        http_response_code(403);
        die("Unauthorized Access");
    }

    // 2. Get Filter Values (same as below)
    $search = $_GET['search'] ?? '';
    $monthFilter = $_GET['month'] ?? '';

    // 3. Database Fetch (same as below)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = null;
    $applications = [];
    $teamData = [];

    try {
        $conn = new mysqli('localhost', 'root', '', 'college_db');
        $conn->set_charset('utf8mb4');

        // --- MODIFIED: Fetch OD Applications with Filters ---
        $searchSql = "";
        $monthSql = "";
        $params = [];
        $types = "";

        // Add search filter if provided
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
                t.member_regno LIKE ?
            )";
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
            $types .= str_repeat('s', 12);
        }
        
        // Add month filter if provided
        if (!empty($monthFilter)) {
            $monthSql = " AND DATE_FORMAT(o.from_date, '%Y-%m') = ?";
            $params[] = $monthFilter;
            $types .= "s";
        }

        $sql = "SELECT DISTINCT o.*
                FROM od_applications o
                LEFT JOIN od_team_members t ON o.id = t.od_id
                WHERE 
                    ( (o.od_type = 'internal' AND o.status = 'HOD Accepted')
                      OR
                      (o.od_type = 'external' AND o.status = 'Principal Accepted') )
                    $searchSql
                    $monthSql
                ORDER BY o.id DESC";

        $stmt = $conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        // --- End of Modified Query ---

        // Fetch team members for each OD (Unchanged)
        foreach ($applications as $app) {
            $od_id = $app['id'];
            $sqlTeam = "SELECT * FROM od_team_members WHERE od_id = ?";
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

    // 4. Generate and Stream CSV
    $filename = "ja_od_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV Headers
    fputcsv($output, [
        'ID', 'Register No', 'Student Name', 'Year', 'Department', 'Section',
        'OD Type', 'Purpose', 'College Name', 'Event Name', 'Dates',
        'Status', 'Bonafide Required', 'Team Members (Name [RegNo])'
    ]);

    // Add Data Rows
    foreach ($applications as $app) {
        // Format Dates
        $dates = "-";
        if (!empty($app['from_date']) && !empty($app['to_date'])) {
            $dates = $app['from_date'] . " to " . $app['to_date'];
        } elseif (!empty($app['od_date'])) {
            $dates = $app['od_date'];
        }

        // Format Status
        $status = ($app['od_type'] === 'internal') ? 'HOD Accepted' : 'Principal Accepted';
        
        // Format Bonafide
        $bonafide = ($app['request_bonafide'] == 1) ? 'Yes' : 'No';

        // Format Team Members
        $teamList = $teamData[$app['id']] ?? [];
        $teamString = "Individual";
        if (!empty($teamList)) {
            $teamString = implode('; ', array_map(
                fn($m) => "{$m['member_name']} [{$m['member_regno']}]",
                $teamList
            ));
        }

        // Write row to CSV
        fputcsv($output, [
            $app['id'],
            $app['register_no'],
            $app['student_name'],
            $app['year'],
            $app['department'],
            $app['section'],
            ucfirst($app['od_type']),
            $app['purpose'],
            $app['college_name'] ?? 'N/A (Internal)',
            $app['event_name'] ?? 'N/A',
            $dates,
            $status,
            $bonafide,
            $teamString
        ]);
    }

    fclose($output);
    exit; // Stop script execution
}
// --- END: CSV EXPORT LOGIC ---
?>
<?php
// Regular page execution starts here
session_start();

// ✅ Only allow logged-in JA users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ja') {
    header("Location: login.php?error=unauthorized");
    exit;
}

$jaName = $_SESSION['user']['name']; // Logged-in JA's name

// --- NEW: Get filter values ---
$search = $_GET['search'] ?? '';
$monthFilter = $_GET['month'] ?? '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = null;
$applications = [];
$teamData = [];

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // --- MODIFIED: Fetch OD Applications with Filters ---
    $searchSql = "";
    $monthSql = "";
    $params = []; // Start with empty params
    $types = "";

    // Add search filter if provided
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
            t.member_regno LIKE ?
        )";
        // Add 12 $like params
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        $types .= str_repeat('s', 12);
    }
    
    // Add month filter if provided
    if (!empty($monthFilter)) {
        $monthSql = " AND DATE_FORMAT(o.from_date, '%Y-%m') = ?";
        $params[] = $monthFilter;
        $types .= "s";
    }

    // ✅ Fetch OD Applications: (Original Logic Preserved)
    // - For "Internal" → show if HOD accepted
    // - For "External" → show if Principal accepted
    $sql = "SELECT DISTINCT o.*
            FROM od_applications o
            LEFT JOIN od_team_members t ON o.id = t.od_id
            WHERE 
                ( (o.od_type = 'internal' AND o.status = 'HOD Accepted')
                  OR
                  (o.od_type = 'external' AND o.status = 'Principal Accepted') )
                $searchSql
                $monthSql
            ORDER BY o.id DESC";

    $stmt = $conn->prepare($sql);
    
    // Dynamically bind params if they exist
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // --- End of Modified Query ---

    // ✅ Fetch team members for each OD (Unchanged)
    $teamData = [];
    foreach ($applications as $app) {
        $od_id = $app['id'];
        $sqlTeam = "SELECT * FROM od_team_members WHERE od_id = ?";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JA Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ... (all your existing CSS <style> rules) ... */
        :root {
            --primary-color: #6f42c1; /* Bootstrap Indigo for JA */
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
            color: var(--primary-color); /* JA theme */
            font-weight: 700;
        }
        
        .user-info {
            font-size: 0.9rem;
        }
        .user-info .fw-bold {
            color: var(--primary-color); /* JA theme */
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
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
            background: var(--primary-color); /* JA theme */
            color: var(--white);
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .table-hover tbody tr:hover {
            background-color: #fbf9ff; /* Light indigo hover */
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
        
        .modal-header {
            background-color: var(--primary-color); /* JA theme */
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
        <h2 class="mb-0"><i class="bi bi-person-workspace me-2"></i> JA Dashboard</h2>
        <div class="d-flex align-items-center">
             <span class="text-secondary me-3 user-info">
                Logged in as: 
                <span class="fw-bold text-dark"><?= htmlspecialchars($jaName) ?></span>
            </span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </header>

    <div class="card mb-4 filter-bar-card">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-5">
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
                
                <input type="hidden" name="export" id="exportValue" value="">

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" id="filterBtn" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel-fill me-1"></i> Filter
                    </button>
                    <a href="?" class="btn btn-outline-secondary btn-sm w-100">
                       <i class="bi bi-x-lg me-1"></i> Clear
                    </a>
                    
                    <?php if (!empty($applications)): ?>
                        <button type="submit" id="downloadBtn" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Download
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php if (empty($applications) && empty($search) && empty($monthFilter)): ?>
        <div class="alert alert-info shadow-sm">
            <i class="bi bi-info-circle me-2"></i> No OD requests are currently available for review.
        </div>
    <?php elseif (empty($applications)): ?>
         <div class="alert alert-warning shadow-sm">
            <i class="bi bi-search me-2"></i> No applications found matching your filter criteria.
        </div>
    <?php else: ?>
        <div class="card table-card">
            <div class="table-responsive">
                <table id="odTable" class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Register No</th>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Dept</th>
                            <th>Section</th>
                            <th>OD Type</th>
                            <th>Purpose / Event</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Bonafide</th>
                            <th>Team Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><span class="badge bg-dark"><?= htmlspecialchars($app['id']) ?></span></td>
                                <td><?= htmlspecialchars($app['register_no']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($app['student_name']) ?></td>
                                <td><?= htmlspecialchars($app['year']) ?></td>
                                <td><?= htmlspecialchars($app['department']) ?></td>
                                <td><?= htmlspecialchars($app['section']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $app['od_type'] === 'Internal' ? 'secondary' : 'primary' ?>">
                                        <?= htmlspecialchars(ucfirst($app['od_type'])) ?>
                                    </span>
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
                                <td class="small">
                                    <?php
                                    if (!empty($app['from_date']) && !empty($app['to_date'])) {
                                        echo htmlspecialchars($app['from_date']) . " → " . htmlspecialchars($app['to_date']);
                                    } elseif (!empty($app['od_date'])) {
                                        echo htmlspecialchars($app['od_date']);
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($app['od_type'] === 'internal'): ?>
                                        <span class="badge bg-success">HOD Accepted</span>
                                    <?php elseif ($app['od_type'] === 'external'): ?>
                                        <span class="badge bg-primary">Principal Accepted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($app['request_bonafide'] == 1): ?>
                                        <span class="badge bg-info text-dark">Required</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Required</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $teamList = $teamData[$app['id']] ?? [];
                                    if (!empty($teamList)): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm view-team-btn"
                                            data-team='<?= json_encode(array_values($teamList), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                            data-bs-toggle="modal"
                                            data-bs-target="#teamModal">
                                            👥 View Team (<?= count($teamList) ?>)
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Individual</span>
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

<div class="modal fade" id="teamModal" tabindex="-1" aria-labelledby="teamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"> <h5 class="modal-title" id="teamModalLabel"><i class="bi bi-people-fill me-2"></i> Team Members</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="teamModalBody">
                </div>
        </div>
    </div>
</div>

<script>
    // 👥 Team Members Modal Logic (Unchanged)
    document.querySelectorAll('.view-team-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const teamData = JSON.parse(btn.dataset.team);

            let tableHtml = `
                <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Reg No</th>
                            <th>Year</th>
                            <th>Dept</th>
                            <th>Section</th>
                            <th>Mentor Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            teamData.forEach(member => {
                let badgeClass = "bg-secondary";
                const status = member.mentor_status?.toLowerCase();
                
                // Add HOD status checks to modal
                if (status === "accepted" || status === "hod accepted") badgeClass = "bg-success";
                else if (status === "rejected" || status === "hod rejected") badgeClass = "bg-danger";
                else if (status === "pending") badgeClass = "bg-warning text-dark";

                tableHtml += `
                    <tr>
                        <td class="fw-semibold">${member.member_name}</td>
                        <td>${member.member_regno}</td>
                        <td>${member.member_year}</td>
                        <td>${member.member_department}</td>
                        <td>${member.member_section}</td>
                        <td><span class="badge ${badgeClass}">${member.mentor_status || "-"}</span></td>
                    </tr>
                `;
            });

            tableHtml += "</tbody></table></div>";
            document.getElementById("teamModalBody").innerHTML = tableHtml;
        });
    });

    // --- NEW: Form Submit Logic for Export ---
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const exportValueInput = document.getElementById('exportValue');
        
        // When clicking "Filter", ensure export is empty
        const filterBtn = document.getElementById('filterBtn');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => {
                exportValueInput.value = '';
                // Form will submit normally
            });
        }

        // When clicking "Download", set export to 'csv'
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                exportValueInput.value = 'csv';
                // Form will submit normally with export=csv
            });
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ✅ Only logged-in lab technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'labtech') {
    header("Location: login.php?error=unauthorized");
    exit;
}
$labtechName = $_SESSION['user']['name'];

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // Handle Lab Name update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lab_id'], $_POST['new_lab'])) {
        $id = (int)$_POST['update_lab_id'];
        $newLab = $conn->real_escape_string($_POST['new_lab']);
        $conn->query("UPDATE od_applications SET lab_name='$newLab' WHERE id=$id");
        header("Location: " . $_SERVER['PHP_SELF'] . "?lab_name=" . urlencode($_GET['lab_name'] ?? ''));
        exit;
    }

    // Filter OD applications by lab if selected
    $labFilter = $_GET['lab_name'] ?? '';
    $labFilterSql = $labFilter ? " AND lab_name='". $conn->real_escape_string($labFilter) ."'" : '';

    $sql = "SELECT * FROM od_applications 
            WHERE status='HOD Accepted' AND lab_required=1 $labFilterSql
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $odApplications = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch team members for each OD
    $teamData = [];
    foreach ($odApplications as $od) {
        $od_id = $od['id'];
        $teamResult = $conn->query("SELECT * FROM od_team_members WHERE od_id=$od_id");
        $teamData[$od_id] = $teamResult->fetch_all(MYSQLI_ASSOC);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database error: " . htmlspecialchars($e->getMessage()));
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
}

// Predefined Lab List
$labs = ["AI Lab", "IoT Lab", "Network Lab", "Hardware Lab", "Data Science Lab"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Technician Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary-color: #1e88e5; --light-bg: #f4f7f9; --dark-text: #2c3e50; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--dark-text); }
        .container-fluid { max-width: 1400px; }
        .dashboard-header { border-bottom: 2px solid #e0e0e0; margin-bottom: 20px; padding-bottom: 10px; }
        h2 { font-weight: 700; color: var(--primary-color); }
        .table-card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        .table thead th { text-align: center; vertical-align: middle; background: var(--primary-color) !important; color: #fff; font-weight: 600; font-size: 0.8rem; padding: 10px 8px; }
        .table tbody td { vertical-align: middle; text-align: center; padding: 12px 8px; font-size: 0.85rem; }
        .badge { font-size: 0.7rem; padding: 0.4em 0.8em; border-radius: 50rem; font-weight: 600; min-width: 80px; text-transform: uppercase; }
        .badge.bg-accepted { background-color: #43a047; }
        .filter-form { margin-bottom: 15px; }
        .btn-change { font-size: 0.7rem; padding: 0.2rem 0.5rem; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <header class="dashboard-header d-flex justify-content-between align-items-center mb-4 pt-3">
        <h2><i class="bi bi-speedometer2 me-2"></i>Lab Technician Dashboard</h2>
        <div class="d-flex align-items-center">
            <span class="me-3 text-secondary">Logged in as: <span class="fw-bold text-success"><?= htmlspecialchars($labtechName) ?></span></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </header>

    <!-- Lab Filter Dropdown -->
    <form method="get" class="filter-form d-flex align-items-center gap-2">
        <label for="lab_name" class="fw-semibold">Filter by Lab:</label>
        <select name="lab_name" id="lab_name" class="form-select form-select-sm w-auto">
            <option value="">Select Lab</option>
            <?php foreach ($labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>" <?= ($lab === $labFilter) ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if($labFilter): ?>
            <a href="labtech_dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($odApplications)): ?>
        <div class="alert alert-info text-center shadow-sm rounded-3 py-4">
            <i class="bi bi-info-circle me-2"></i> No OD applications found for selected lab.
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Register No</th>
                            <th>Student Name</th>
                            <th>Year/Dept/Sec</th>
                            <th>OD Type</th>
                            <th>OD Dates & Time</th>
                            <th>Purpose / Event</th>
                            <th>Lab Name</th>
                            <th>System Required</th>
                            <th>Status</th>
                            <th>Team Members</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($odApplications as $od): ?>
                        <tr>
                            <td><?= htmlspecialchars($od['id']) ?></td>
                            <td><?= htmlspecialchars($od['register_no']) ?></td>
                            <td><?= htmlspecialchars($od['student_name']) ?></td>
                            <td><?= htmlspecialchars($od['year']) ?>/<?= htmlspecialchars($od['department']) ?>/<?= htmlspecialchars($od['section']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars(ucfirst($od['od_type'])) ?></span></td>
                            <td class="small">
                                <?php
                                    if ($od['od_type'] === 'internal') {
                                        echo htmlspecialchars($od['od_date']);
                                        if (!empty($od['from_time']) && !empty($od['to_time'])) {
                                            echo "<br>".date("h:i A", strtotime($od['from_time']))." → ".date("h:i A", strtotime($od['to_time']));
                                        }
                                    } else {
                                        echo htmlspecialchars($od['from_date'])." → ".htmlspecialchars($od['to_date']);
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-truncate d-block" style="max-width: 150px;">
                                    <?= htmlspecialchars(mb_strimwidth($od['purpose'],0,40,'...')) ?>
                                </span>
                                <?php if (!empty($od['college_name'])): ?>
                                    <span class="small text-muted">@ <?= htmlspecialchars($od['college_name']) ?></span>
                                <?php else: ?>
                                    <span class="small text-muted">Internal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="d-flex align-items-center gap-1 justify-content-center">
                                    <input type="hidden" name="update_lab_id" value="<?= $od['id'] ?>">
                                    <select name="new_lab" class="form-select form-select-sm w-auto">
                                        <?php foreach ($labs as $lab): ?>
                                            <option value="<?= htmlspecialchars($lab) ?>" <?= ($lab === $od['lab_name']) ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-success btn-change">Change</button>
                                </form>
                            </td>
                            <td><?= $od['system_required'] ? 'Yes' : 'No' ?></td>
                            <td><span class="badge bg-accepted"><?= htmlspecialchars($od['status']) ?></span></td>
                            <td>
                                <?php if(!empty($teamData[$od['id']])): ?>
                                    <button class="btn btn-outline-primary btn-sm view-team-btn" 
                                        data-team='<?= json_encode($teamData[$od['id']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>' 
                                        data-bs-toggle="modal" data-bs-target="#teamModal">
                                        👥 View Team
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">No team</span>
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

<!-- Team Members Modal -->
<div class="modal fade" id="teamModal" tabindex="-1" aria-labelledby="teamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="teamModalLabel">Team Members</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Reg No</th>
                    <th>Mentor</th>
                    <th>Mentor Status</th>
                </tr>
            </thead>
            <tbody id="teamModalBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const teamModalBody = document.getElementById('teamModalBody');
document.querySelectorAll('.view-team-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const team = JSON.parse(btn.getAttribute('data-team'));
        teamModalBody.innerHTML = '';
        team.forEach(member=>{
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${member.member_name}</td>
                <td>${member.member_regno}</td>
                <td>${member.mentor}</td>
                <td>${member.mentor_status}</td>`;
            teamModalBody.appendChild(row);
        });
    });
});
</script>
</body>
</html>

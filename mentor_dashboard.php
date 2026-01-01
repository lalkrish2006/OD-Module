<?php
// Mentor Dashboard
require_once __DIR__ . '/includes/session_manager.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mentor') {
    header("Location: login.php?error=unauthorized");
    exit;
}
$mentorName = $_SESSION['user']['name'];

// --- MODIFIED: Get filter values from URL (GET request) ---
$searchFilter = $_GET['search'] ?? '';
$odTypeFilter = $_GET['od_type'] ?? '';
$monthFilter = $_GET['month'] ?? ''; // Added month filter

try {
    $conn = new mysqli('localhost', 'root', '', database: 'college_db');
    $conn->set_charset('utf8mb4');

    // Handle Accept / Reject actions (Unchanged)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['member_id'])) {
        $member_id = (int) $_POST['member_id'];
        $status = ($_POST['action'] === 'accept') ? "Accepted" : "Rejected";

        // Update the specific team member's status
        $sqlResp = "UPDATE od_team_members SET mentor_status=? WHERE id=? AND mentor=?";
        $stmt = $conn->prepare($sqlResp);
        $stmt->bind_param("sis", $status, $member_id, $mentorName);
        $stmt->execute();
        $stmt->close();

        // Check the status of all mentors for this OD to set the overall application status
        $sqlCheck = "SELECT mentor_status FROM od_team_members WHERE od_id=(SELECT od_id FROM od_team_members WHERE id=?)";
        $stmt = $conn->prepare($sqlCheck);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $finalStatus = "Pending";
        $hasAccepted = false;
        $hasRejected = false;
        foreach ($results as $r) {
            if ($r['mentor_status'] === "Accepted")
                $hasAccepted = true;
            if ($r['mentor_status'] === "Rejected")
                $hasRejected = true;
        }

        // Get OD type to apply correct logic for final status
        $sqlType = "SELECT od_type FROM od_applications WHERE id=(SELECT od_id FROM od_team_members WHERE id=?)";
        $stmt = $conn->prepare($sqlType);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $odType = $stmt->get_result()->fetch_assoc()['od_type'];
        $stmt->close();

        if ($odType === "external") {
            if ($hasRejected) {
                $finalStatus = "Mentors Rejected";
            } elseif ($hasAccepted) {
                $finalStatus = "Mentors Accepted";
            }
        } else { // internal
            if ($hasAccepted && $hasRejected) {
                $finalStatus = "Mentors Reviewed";
            } elseif ($hasRejected) {
                $finalStatus = "Mentors Rejected";
            } elseif ($hasAccepted) {
                $finalStatus = "Mentors Accepted";
            }
        }

        // Update the main application status
        $sqlUpdate = "UPDATE od_applications SET status=? WHERE id=(SELECT od_id FROM od_team_members WHERE id=?)";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("si", $finalStatus, $member_id);
        $stmt->execute();
        $stmt->close();

        // --- HOD notification logic (Unchanged) ---
        $stmt = $conn->prepare("SELECT od_id FROM od_team_members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $stmt->bind_result($od_id);
        $stmt->fetch();
        $stmt->close();

        $teamCount = 0;
        if ($od_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM od_team_members WHERE od_id = ?");
            $stmt->bind_param("i", $od_id);
            $stmt->execute();
            $stmt->bind_result($teamCount);
            $stmt->fetch();
            $stmt->close();
        }

        $actionTaken = strtolower($status);
        $isTeam = ($teamCount > 1);

        if (($isTeam) || (!$isTeam && $actionTaken === 'accepted')) {
            $stmt = $conn->prepare(
                "SELECT hods.email, hods.name FROM hods 
                 JOIN od_applications ON od_applications.department = hods.department 
                 WHERE od_applications.id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $od_id);
            $stmt->execute();
            $stmt->bind_result($hodEmail, $hodName);
            $stmt->fetch();
            $stmt->close();

            $stmtMentor = $conn->prepare("SELECT mentor_email FROM mentors WHERE name = ?");
            $stmtMentor->bind_param("s", $mentorName);
            $stmtMentor->execute();
            $stmtMentor->bind_result($mentorEmail);
            $stmtMentor->fetch();
            $stmtMentor->close();

            if ($hodEmail && $mentorEmail) {
                require_once 'phpMailer.php';

                $subject = "";
                $body = "";
                $actionText = $actionTaken;

                if ($isTeam) {
                    $subject = "Mentor $mentorName has $actionText a Team OD Application (ID: $od_id)";
                    $body = "
                        Dear HOD,<br><br>
                        Mentor <strong>$mentorName</strong> has <strong>$actionText</strong> an OD application 
                        involving multiple students (Team OD ID: $od_id).<br>
                        Please review the application in the OD Module.<br><br>
                        <a href='http://localhost/OD-Module/loginin.php'>Click here to open OD Module</a><br><br>
                        Regards,<br>
                        OD Management System
                    ";
                } else {
                    $subject = "Mentor $mentorName has $actionText an Individual OD Application (ID: $od_id)";
                    $body = "
                        Dear HOD,<br><br>
                        Mentor <strong>$mentorName</strong> has <strong>$actionText</strong> an individual 
                        student OD application (OD ID: $od_id).<br>
                        Please review the application in the OD Module.<br><br>
                        <a href='http://localhost/OD-Module/loginin.php'>Click here to open OD Module</a><br><br>
                        Regards,<br>
                        OD Management System
                    ";
                }

                sendEmailToHOD($hodEmail, $hodName, $subject, $body, $mentorEmail, $mentorName);
            }
        }
        // --- End of HOD notification logic ---
    }

    // --- MODIFIED: Fetch team members with filters ---
    $sql = "SELECT t.*, o.od_type, o.od_date, o.from_date, o.to_date, o.from_time, o.to_time, o.purpose, o.college_name, o.event_name, o.status AS od_status
            FROM od_team_members t
            LEFT JOIN od_applications o ON o.id = t.od_id
            WHERE t.mentor = ?";

    $params = [$mentorName];
    $types = "s";

    // Add search filter if provided
    if (!empty($searchFilter)) {
        $sql .= " AND (t.member_name LIKE ? OR t.member_regno LIKE ?)";
        $searchTerm = "%{$searchFilter}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    // Add OD type filter if provided
    if (!empty($odTypeFilter)) {
        $sql .= " AND o.od_type = ?";
        $params[] = $odTypeFilter;
        $types .= "s";
    }

    // --- NEW: Add month filter if provided ---
    if (!empty($monthFilter)) {
        // Filter by the month of the 'from_date'
        $sql .= " AND DATE_FORMAT(o.from_date, '%Y-%m') = ?";
        $params[] = $monthFilter;
        $types .= "s";
    }
    // --- End of new filter ---

    $sql .= " ORDER BY t.od_id DESC, t.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $teamMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // --- End of modified fetch ---


    // Fetch all team members grouped by OD for the modal (Unchanged)
    $teamByOD = [];
    $sqlAllTeams = "SELECT * FROM od_team_members ORDER BY od_id ASC, id ASC";
    $resultAllTeams = $conn->query($sqlAllTeams);
    while ($row = $resultAllTeams->fetch_assoc()) {
        $teamByOD[$row['od_id']][] = $row;
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
    <title>Mentor OD Review Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #1e88e5;
            --light-bg: #f4f7f9;
            --dark-text: #2c3e50;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
        }

        .container-fluid {
            max-width: 1400px;
        }

        .dashboard-header {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        h2 {
            font-weight: 700;
            color: var(--primary-color);
        }

        .table-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .table thead th {
            text-align: center;
            vertical-align: middle;
            background: var(--primary-color) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.2px;
            padding: 10px 8px;
        }

        .table tbody td {
            vertical-align: middle;
            text-align: center;
            padding: 12px 8px;
            font-size: 0.85rem;
        }

        .table-hover tbody tr:hover td {
            background-color: #f0f4f7;
        }

        .badge {
            font-size: 0.7rem;
            padding: 0.4em 0.8em;
            border-radius: 50rem;
            font-weight: 600;
            min-width: 80px;
            text-transform: uppercase;
        }

        .badge.bg-pending {
            background-color: #ffb300 !important;
            color: #333 !important;
        }

        .badge.bg-accepted {
            background-color: #43a047 !important;
        }

        .badge.bg-rejected {
            background-color: #e53935 !important;
        }

        .badge.bg-reviewed {
            background-color: #03a9f4 !important;
        }

        .badge.bg-secondary {
            background-color: #b0bec5 !important;
        }

        .btn-action {
            font-size: 0.75rem;
            padding: 0.3rem 0.7rem;
            border-radius: 50rem;
            font-weight: 600;
            min-width: 80px;
            transition: all 0.2s;
        }

        .action-form button:not(.btn-confirm) {
            opacity: 0.8;
        }

        .action-form .btn-primary,
        .action-form .btn-danger {
            opacity: 1;
        }

        .btn-confirm {
            font-weight: 700;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .table-mentor-row {
            background-color: #e6f7ff !important;
            font-weight: 600;
            border-left: 3px solid var(--primary-color);
        }

        .detail-group {
            text-align: left;
            padding-left: 5px;
        }

        .detail-group .small {
            font-size: 0.75rem;
            color: #7f8c8d;
        }

        .view-more-btn {
            font-size: 0.8rem;
            color: #1e88e5;
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: underline;
            margin-left: 2px;
        }

        .filter-bar {
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            font-size: 0.9rem;
            border-radius: 6px;
        }

        .filter-bar .btn {
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <header class="dashboard-header d-flex justify-content-between align-items-center mb-4 pt-3">
            <h2 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Mentor OD Review Dashboard</h2>
            <div class="d-flex align-items-center">
                <span class="me-3 text-secondary">Logged in as: <span
                        class="fw-bold text-success"><?= htmlspecialchars($mentorName) ?></span></span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </header>

        <div class="filter-bar">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label fw-semibold small mb-1">Search by Name or Reg No</label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                        placeholder="Enter student name or register number..."
                        value="<?= htmlspecialchars($searchFilter) ?>">
                </div>
                <div class="col-md-2">
                    <label for="od_type" class="form-label fw-semibold small mb-1">Filter by OD Type</label>
                    <select class="form-select form-select-sm" id="od_type" name="od_type">
                        <option value="">All Types</option>
                        <option value="internal" <?= $odTypeFilter === 'internal' ? 'selected' : '' ?>>Internal</option>
                        <option value="external" <?= $odTypeFilter === 'external' ? 'selected' : '' ?>>External</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="month" class="form-label fw-semibold small mb-1">Filter by Month</label>
                    <input type="month" class="form-control form-control-sm" id="month" name="month"
                        value="<?= htmlspecialchars($monthFilter) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel-fill me-1"></i>
                        Filter</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-lg me-1"></i> Clear</a>
                </div>
            </form>
        </div>
        <?php if (empty($teamMembers)): ?>
            <div class="alert alert-info text-center shadow-sm rounded-3 py-4">
                <i class="bi bi-info-circle me-2"></i>
                <?php if (!empty($searchFilter) || !empty($odTypeFilter) || !empty($monthFilter)): // Added month filter check ?>
                    No OD applications match your current filter criteria.
                <?php else: ?>
                    No OD applications are currently assigned to you for review.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>OD ID</th>
                                <th>Student Name</th>
                                <th>Register No</th>
                                <th>Year/Dept/Sec</th>
                                <th>OD Type</th>
                                <th>Dates & Time</th>
                                <th>Purpose / Event</th>
                                <th>Mentor Status</th>
                                <th>Action</th>
                                <th>Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teamMembers as $member): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($member['od_id']) ?></span></td>
                                    <td><span
                                            class="fw-bold text-start d-block"><?= htmlspecialchars($member['member_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($member['member_regno']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($member['member_year']) ?> /
                                        <?= htmlspecialchars($member['member_department']) ?> /
                                        <?= htmlspecialchars($member['member_section']) ?>
                                    </td>
                                    <td><span
                                            class="badge bg-info text-dark"><?= htmlspecialchars(ucfirst($member['od_type'])) ?></span>
                                    </td>
                                    <td class="small">
                                        <?php
                                        if ($member['od_type'] === 'internal') {
                                            if (!empty($member['from_time']) && !empty($member['to_time'])) {
                                                echo htmlspecialchars($member['from_date']) . "<br>" .
                                                    date("h:i A", strtotime($member['from_time'])) . " → " .
                                                    date("h:i A", strtotime($member['to_time']));
                                            } elseif (!empty($member['from_date']))
                                                echo htmlspecialchars($member['from_date']);
                                        } else {
                                            if (!empty($member['from_date']) && !empty($member['to_date']))
                                                echo htmlspecialchars($member['from_date']) . " → " . htmlspecialchars($member['to_date']);
                                        }
                                        ?>
                                    </td>
                                    <td class="detail-group">
                                        <span class="fw-semibold text-truncate d-block" style="max-width: 150px;">
                                            <?= htmlspecialchars(mb_strimwidth($member['purpose'], 0, 40, '...')) ?>
                                        </span>
                                        <?php if ($member['college_name']): ?>
                                            <span class="small text-muted">@ <?= htmlspecialchars($member['college_name']) ?></span>
                                        <?php else: ?>
                                            <span class="small text-muted">Internal</span>
                                        <?php endif; ?>
                                        <button type="button" class="view-more-btn" data-bs-toggle="modal"
                                            data-bs-target="#purposeModal"
                                            data-purpose="<?= htmlspecialchars($member['purpose']) ?>"
                                            data-event="<?= htmlspecialchars($member['event_name'] ?? '') ?>"
                                            data-college="<?= htmlspecialchars($member['college_name'] ?? '') ?>">
                                            View More
                                        </button>
                                    </td>
                                    <td>
                                        <?php
                                        $mentorStatus = $member['mentor_status'] ?? $member['od_status'];
                                        $badgeClass = match ($mentorStatus) {
                                            'Accepted', 'Mentors Accepted' => 'bg-accepted',
                                            'Rejected', 'Mentors Rejected' => 'bg-rejected',
                                            'Mentors Reviewed' => 'bg-reviewed',
                                            default => 'bg-pending'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($mentorStatus) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($member['mentor_status'] === 'Pending'): ?>
                                            <form method="post" class="d-flex flex-column gap-1 action-form"
                                                data-memberid="<?= $member['id'] ?>">
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                <input type="hidden" name="action" value="">
                                                <button type="button" class="btn btn-outline-success btn-sm btn-action btn-accept">✅
                                                    Accept</button>
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-action btn-reject">❌
                                                    Reject</button>
                                                <button type="submit"
                                                    class="btn btn-primary btn-sm btn-action btn-confirm d-none">Confirm</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Action Taken</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-action view-team-btn"
                                            data-team='<?= json_encode($teamByOD[$member['od_id']] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                            data-mentor="<?= htmlspecialchars($mentorName) ?>" data-bs-toggle="modal"
                                            data-bs-target="#teamModal">
                                            <i class="bi bi-people-fill me-1"></i> Team
                                        </button>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="teamModalLabel"><i class="bi bi-people-fill me-2"></i> Team Members &
                        Review Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="teamModalBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="purposeModal" tabindex="-1" aria-labelledby="purposeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="purposeModalLabel"><i class="bi bi-info-circle me-2"></i> Purpose /
                        Event Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="purposeModalBody"></div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.action-form').forEach(form => {
            const btnAccept = form.querySelector('.btn-accept');
            const btnReject = form.querySelector('.btn-reject');
            const btnConfirm = form.querySelector('.btn-confirm');
            const hiddenAction = form.querySelector('input[name="action"]');

            const setActionStyle = (targetButton, action) => {
                form.querySelectorAll('.btn-action').forEach(btn => {
                    btn.classList.remove('btn-primary', 'btn-danger');
                    if (btn.classList.contains('btn-outline-success')) btn.classList.add('btn-outline-success');
                    if (btn.classList.contains('btn-outline-danger')) btn.classList.add('btn-outline-danger');
                });
                if (action === 'accept') {
                    targetButton.classList.remove('btn-outline-success');
                    targetButton.classList.add('btn-primary');
                } else if (action === 'reject') {
                    targetButton.classList.remove('btn-outline-danger');
                    targetButton.classList.add('btn-danger');
                }
                btnConfirm.classList.remove('d-none');
            };

            btnAccept.addEventListener('click', () => {
                hiddenAction.value = 'accept';
                setActionStyle(btnAccept, 'accept');
            });
            btnReject.addEventListener('click', () => {
                hiddenAction.value = 'reject';
                setActionStyle(btnReject, 'reject');
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                form.querySelectorAll('button').forEach(btn => btn.disabled = true);
                btnConfirm.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...`;

                // This existing logic will automatically include the new month filter
                // because it's part of the page's URL query string (currentUrl.search)
                const currentUrl = new URL(window.location.href);

                fetch(currentUrl.pathname + currentUrl.search, {
                    method: 'POST',
                    body: formData
                }).then(res => res.text()).then(() => {
                    window.location.reload();
                }).catch(error => {
                    console.error('Error submitting form:', error);
                    alert('An error occurred during submission. Please try again.');
                    form.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    btnConfirm.innerHTML = `Confirm`;
                });
            });
        });

        document.querySelectorAll('.view-team-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const teamData = JSON.parse(btn.dataset.team);
                const currentMentor = btn.dataset.mentor;
                let tableHtml = `
                <table class="table table-striped table-hover align-middle text-center table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Reg No</th>
                            <th>Mentor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                teamData.forEach(member => {
                    const highlight = (member.mentor === currentMentor) ? "table-mentor-row" : "";
                    const status = member.mentor_status ?? "Pending";
                    const badgeClass = matchStatusToClass(status);
                    tableHtml += `
                        <tr class="${highlight}">
                            <td><span class="text-dark fw-semibold">${member.member_name}</span></td>
                            <td>${member.member_regno}</td>
                            <td>${member.mentor}</td>
                            <td><span class="badge ${badgeClass}">${status}</span></td>
                        </tr>
                    `;
                });
                tableHtml += "</tbody></table>";
                document.getElementById("teamModalBody").innerHTML = tableHtml;
            });
        });

        function matchStatusToClass(status) {
            status = status.toLowerCase();
            if (status.includes('accepted')) return 'bg-accepted';
            if (status.includes('rejected')) return 'bg-rejected';
            if (status.includes('reviewed')) return 'bg-reviewed';
            return 'bg-pending';
        }

        document.querySelectorAll('.view-more-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var purpose = btn.getAttribute('data-purpose');
                var event = btn.getAttribute('data-event');
                var college = btn.getAttribute('data-college');
                var modalBody = document.getElementById('purposeModalBody');
                modalBody.innerHTML = `
                    <div class="mb-3">
                        <strong>Purpose:</strong>
                        <div class="text-dark" style="white-space: pre-wrap; word-wrap: break-word;">${purpose ? purpose : '—'}</div>
                    </div>
                    <div class="mb-2">
                        <strong>Event Name:</strong>
                        <span class="text-dark">${event ? event : '—'}</span>
                    </div>
                    <div>
                        <strong>College Name:</strong>
                        <span class="text-dark">${college ? college : '—'}</span>
                    </div>
                `;
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
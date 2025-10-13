<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?error=unauthorized");
    exit;
}

// Get student register no from session
$studentRegNo = $_SESSION['register_no'];

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // Fetch OD Applications where student is applicant or team member
    $sql = "
        SELECT DISTINCT o.* FROM od_applications o
        LEFT JOIN od_team_members t ON o.id = t.od_id
        WHERE o.register_no = ? OR t.member_regno = ?
        ORDER BY o.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $studentRegNo, $studentRegNo);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch team members for each OD
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

// Function to determine badge class based on status
function get_status_badge_class_student($displayStatus) {
    return match ($displayStatus) {
        'pending'           => 'bg-warning text-dark',
        'mentor accepted',
        'hod accepted'      => 'bg-success',
        'mentor rejected',
        'hod rejected'      => 'bg-danger',
        default             => 'bg-secondary'
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student OD Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary-color: #007bff;
            --light-bg: #f8f9fa;
            --dark-header: #343a40;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-header);
        }

        .dashboard-content {
            max-width: 1300px;
            margin: 0 auto;
            padding-top: 20px;
        }

        .top-navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 10px 0;
            margin-bottom: 30px;
        }

        .header-title {
            font-weight: 700;
            color: var(--primary-color);
        }

        .table-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .table thead th {
            text-align: center;
            vertical-align: middle;
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            padding: 12px 8px;
            font-size: 0.9rem;
            border-bottom: none;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 15px 10px;
            font-size: 0.88rem;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #fcfcfc;
        }

        .table-hover tbody tr:hover td {
            background-color: #f0f4f7;
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.45em 0.8em;
            min-width: 100px;
            border-radius: 0.5rem;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }

        .detail-text {
            display: block;
            color: #6c757d;
            font-size: 0.8em;
        }

        .view-team-btn {
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
    </style>
</head>

<body>
    <header class="top-navbar sticky-top">
        <div class="container dashboard-content">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0 header-title">
                    <i class="bi bi-person-fill-gear me-2"></i> Student OD Dashboard
                </h3>
                <div class="d-flex align-items-center gap-3">
                    <a href="studentOdForm.html" class="btn btn-primary fw-bold shadow-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i> New Request
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger rounded-pill">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-content container-fluid">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show text-center rounded-3 shadow-sm" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($_SESSION['flash_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <?php unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <h4 class="mb-4 text-dark fw-bold">My Applications History</h4>

        <?php if (empty($applications)): ?>
            <div class="alert alert-info text-center py-4 rounded-3 border-0 shadow-sm">
                <i class="bi bi-info-circle me-2"></i> You have no OD requests yet. Click "New Request" to begin.
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Purpose</th>
                                <th>Dates</th>
                                <th>College / Event</th>
                                <th>Lab Name</th>
                                <th>Status</th>
                                <th>Bonafide</th>
                                <th>Team Members</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td class="text-center text-muted"><?= htmlspecialchars($app['id']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-dark"><?= htmlspecialchars(ucfirst($app['od_type'])) ?></span>
                                    </td>
                                    <td class="text-start text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($app['purpose']) ?>">
                                        <?= htmlspecialchars($app['purpose']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            if (!empty($app['from_date']) && !empty($app['to_date'])) {
                                                echo date('M j', strtotime($app['from_date'])) . " → " . date('M j, Y', strtotime($app['to_date']));
                                            } elseif (!empty($app['od_date'])) {
                                                echo date('M j, Y', strtotime($app['od_date']));
                                            } else {
                                                echo "-";
                                            }
                                        ?>
                                    </td>
                                    <td class="text-start">
                                        <?php if ($app['college_name']): ?>
                                            <span class="fw-bold"><?= htmlspecialchars($app['college_name']) ?></span>
                                            <span class="detail-text"><?= htmlspecialchars($app['event_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Internal / N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= htmlspecialchars($app['lab_name'] ?? '-') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $odStatus = strtolower(trim($app['status']));
                                            $memberStatus = null;
                                            $teamList = $teamData[$app['id']] ?? [];
                                            foreach ($teamList as $member) {
                                                if ($member['member_regno'] === $studentRegNo) {
                                                    $memberStatus = strtolower(trim($member['mentor_status'] ?? ''));
                                                    break;
                                                }
                                            }
                                            if ($odStatus === 'hod accepted' && $memberStatus === 'rejected') {
                                                $displayStatus = 'mentor rejected';
                                            } else {
                                                $displayStatus = $odStatus;
                                            }
                                            $badgeClass = get_status_badge_class_student($displayStatus);
                                            $displayStatusText = match ($displayStatus) {
                                                'mentor accepted' => 'Mentor Approved',
                                                'hod accepted' => 'Approved',
                                                'mentor rejected' => 'Rejected (Mentor)',
                                                'hod rejected' => 'Rejected (HOD)',
                                                default => ucwords($displayStatus)
                                            };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($displayStatusText) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($app['request_bonafide'] == 1): ?>
                                            <span class="badge bg-info">Requested</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $teamListCount = count($teamData[$app['id']] ?? []); ?>
                                        <?php if (!empty($teamList)): ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary view-team-btn"
                                                data-team='<?= json_encode(array_values($teamData[$app['id']]), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                                data-bs-toggle="modal"
                                                data-bs-target="#teamModal">
                                                <i class="bi bi-people-fill"></i> View (<?= $teamListCount ?>)
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Individual</span>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="teamModalLabel"><i class="bi bi-people me-2"></i> Team Members Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="teamModalBody"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.view-team-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const teamData = JSON.parse(btn.dataset.team);
                let tableHtml = `
                    <table class="table table-striped table-hover align-middle text-center table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Reg No</th>
                                <th>Year</th>
                                <th>Dept</th>
                                <th>Section</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                teamData.forEach(member => {
                    tableHtml += `
                        <tr>
                            <td class="text-start">${member.member_name}</td>
                            <td>${member.member_regno}</td>
                            <td>${member.member_year}</td>
                            <td>${member.member_department}</td>
                            <td>${member.member_section}</td>
                        </tr>
                    `;
                });
                tableHtml += "</tbody></table>";
                document.getElementById("teamModalBody").innerHTML = tableHtml;
            });
        });
    </script>
</body>
</html>

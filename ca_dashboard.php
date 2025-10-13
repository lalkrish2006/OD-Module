<?php
session_start();

// ✅ Only allow logged-in CA users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ca') {
    header("Location: login.php?error=unauthorized");
    exit;
}

$caName = $_SESSION['user']['name']; // store logged-in CA's name

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli('localhost', 'root', '', 'college_db');
    $conn->set_charset('utf8mb4');

    // ✅ Fetch OD Applications for CA (only HOD accepted)
    $sql = "SELECT DISTINCT o.* 
            FROM od_applications o 
            LEFT JOIN od_team_members t ON o.id = t.od_id
            WHERE o.status = 'Hod Accepted'
            ORDER BY o.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ✅ Fetch team members for each OD
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
    <title>CA Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .table thead th {
            text-align: center;
            vertical-align: middle;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .badge {
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="p-3">
            <a href="logout.php" class="btn btn-outline-danger ">
                🔒 Logout
            </a>
        </div>
        <h2 class="mb-4 text-center text-primary fw-bold">CA Dashboard</h2>

        <?php if (empty($applications)): ?>
            <div class="alert alert-info text-center">No OD requests available for CA review.</div>
        <?php else: ?>
            <div class="table-responsive shadow-sm rounded">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Register No</th>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Dept</th>
                            <th>Section</th>
                            <th>OD Type</th>
                            <th>Purpose</th>
                            <th>Dates</th>
                            <th>College / Event</th>
                            <th>Status</th>
                            <th>Team Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars($app['id']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($app['register_no']) ?></td>
                                <td><?= htmlspecialchars($app['student_name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($app['year']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($app['department']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($app['section']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($app['od_type'])) ?></span>
                                </td>
                                <td><?= htmlspecialchars($app['purpose']) ?></td>
                                <td class="text-center">
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
                                <td class="text-center">
                                    <?php if ($app['college_name']): ?>
                                        <b><?= htmlspecialchars($app['college_name']) ?></b><br>
                                        <small><?= htmlspecialchars($app['event_name']) ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">HOD Accepted</span>
                                </td>
                                <td>
                                    <?php
                                    $teamList =
                                        $teamData[$app['id']];
                                    ?>

                                    <?php if (!empty($teamList)): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm view-team-btn"
                                            data-team='<?= json_encode(array_values($teamList), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                            data-bs-toggle="modal"
                                            data-bs-target="#teamModal">
                                            👥 View Team (<?= count($teamList) ?>)
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">No team members</span>
                                    <?php endif; ?>
                                </td>


                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <!-- Team Members Modal -->
    <div class="modal fade" id="teamModal" tabindex="-1" aria-labelledby="teamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="teamModalLabel">Team Members</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="teamModalBody">
                    <!-- Team members table will be injected here -->
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.view-team-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const teamData = JSON.parse(btn.dataset.team);

                let tableHtml = `
                <table class="table table-bordered table-sm align-middle text-center">
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
                    if (member.mentor_status?.toLowerCase() === "accepted") badgeClass = "bg-success";
                    else if (member.mentor_status?.toLowerCase() === "rejected") badgeClass = "bg-danger";
                    else if (member.mentor_status?.toLowerCase() === "pending") badgeClass = "bg-warning text-dark";

                    tableHtml += `
                    <tr>
                        <td>${member.member_name}</td>
                        <td>${member.member_regno}</td>
                        <td>${member.member_year}</td>
                        <td>${member.member_department}</td>
                        <td>${member.member_section}</td>
                        <td><span class="badge ${badgeClass}">${member.mentor_status || "-"}</span></td>
                    </tr>
                `;
                });

                tableHtml += "</tbody></table>";
                document.getElementById("teamModalBody").innerHTML = tableHtml;
            });
        });
    </script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
// Principal Dashboard
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'principal') {
  header("Location: login.php?error=unauthorized");
  exit;
}

$principalName = $_SESSION['user']['name']; // Display principal name

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli('localhost', 'root', '', 'college_db');
  $conn->set_charset('utf8mb4');

  // Fetch OD Applications approved by HOD and requested bonafide
  $sql = "SELECT DISTINCT o.* 
            FROM od_applications o
            LEFT JOIN od_team_members t ON o.id = t.od_id
            WHERE o.status = 'HOD Accepted' AND o.request_bonafide=1
            ORDER BY o.id DESC";
  $stmt = $conn->prepare($sql);
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Principal Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
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

    .table-success {
      background-color: #d1e7dd !important;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="container py-4">

    <div class="p-3">
      <a href="logout.php" class="btn btn-outline-danger">🔒 Logout</a>
    </div>

    <h2 class="mb-2 text-center text-danger fw-bold">Principal Dashboard</h2>
    <h5 class="mb-4 text-center">Logged in as: <span class="text-success"><?= htmlspecialchars($principalName) ?></span></h5>

    <?php if (empty($applications)): ?>
      <div class="alert alert-info">No OD requests available for Principal review.</div>
    <?php else: ?>
      <div class="table-responsive shadow-sm rounded">
        <table class="table table-bordered table-striped table-hover align-middle text-center">
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
              <th>Bonafide</th>
              <th>Team Members</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app): ?>
              <tr>
                <td><?= htmlspecialchars($app['id']) ?></td>
                <td><?= htmlspecialchars($app['register_no']) ?></td>
                <td><?= htmlspecialchars($app['student_name']) ?></td>
                <td><?= htmlspecialchars($app['year']) ?></td>
                <td><?= htmlspecialchars($app['department']) ?></td>
                <td><?= htmlspecialchars($app['section']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($app['od_type'])) ?></span></td>
                <td><?= htmlspecialchars($app['purpose']) ?></td>
                <td>
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
                  <?php if ($app['college_name']): ?>
                    <b><?= htmlspecialchars($app['college_name']) ?></b><br>
                    <small><?= htmlspecialchars($app['event_name']) ?></small>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-success">HOD Accepted</span></td>
                <td><span class="badge bg-info">Verified ✅</span></td>
                <td>
                  <?php if (!empty($teamData[$app['id']])): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary view-team-btn"
                      data-team='<?= json_encode($teamData[$app['id']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                      data-bs-toggle="modal" data-bs-target="#teamModal">
                      👥 View Team (<?= count($teamData[$app['id']]) ?>)
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
        <div class="modal-body" id="teamModalBody"></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
                    <th>Sec</th>
                    <th>Mentor</th>
                </tr>
            </thead>
            <tbody>
        `;
        teamData.forEach(member => {
          tableHtml += `
            <tr>
                <td>${member.member_name}</td>
                <td>${member.member_regno}</td>
                <td>${member.member_year}</td>
                <td>${member.member_department}</td>
                <td>${member.member_section}</td>
                <td>${member.mentor}</td>
            </tr>`;
        });
        tableHtml += "</tbody></table>";
        document.getElementById("teamModalBody").innerHTML = tableHtml;
      });
    });
  </script>
</body>

</html>
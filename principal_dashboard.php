<?php
// Principal Dashboard
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'principal') {
  header("Location: login.php?error=unauthorized");
  exit;
}

$principalName = $_SESSION['user']['name']; // Principal name

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli('localhost', 'root', '', 'college_db');
  $conn->set_charset('utf8mb4');

  // ✅ Handle Accept / Reject actions
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['od_id'])) {
    $od_id = intval($_POST['od_id']);
    $action = $_POST['action'];
    if ($action === 'accept') {
      $newStatus = 'Principal Accepted';
    } elseif ($action === 'reject') {
      $newStatus = 'Principal Rejected';
    }

    $updateSql = "UPDATE od_applications SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $newStatus, $od_id);
    $stmt->execute();
    $stmt->close();
  }

  // ✅ Fetch OD Applications approved by HOD and requested bonafide or already acted by Principal
  $sql = "SELECT DISTINCT o.* 
          FROM od_applications o
          LEFT JOIN od_team_members t ON o.id = t.od_id
          WHERE (o.status = 'HOD Accepted' OR o.status = 'Principal Accepted' OR o.status = 'Principal Rejected')
          AND o.od_type = 'external'
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
    .btn-accept {
      background-color: #28a745;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
    }
    .btn-reject {
      background-color: #dc3545;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
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
      <div class="alert alert-info text-center">No OD requests available for Principal review.</div>
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
              <th>Action</th>
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
                <td>
                  <?php if ($app['status'] === 'Principal Accepted'): ?>
                    <span class="badge bg-success">Principal Accepted</span>
                  <?php elseif ($app['status'] === 'Principal Rejected'): ?>
                    <span class="badge bg-danger">Principal Rejected</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">HOD Accepted</span>
                  <?php endif; ?>
                </td>
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
                <td>
                  <?php if ($app['status'] === 'Principal Accepted' || $app['status'] === 'Principal Rejected'): ?>
                    <span class="text-muted"><?= htmlspecialchars($app['status']) ?></span>
                  <?php else: ?>
                    <form method="POST" class="principal-action-form" style="display:inline;">
                      <input type="hidden" name="od_id" value="<?= $app['id'] ?>">
                      <button type="submit" name="action" value="accept" class="btn-accept">Accept</button>
                      <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                    </form>
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
    // ✅ Team modal display
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

    // ✅ Confirm before submitting Accept/Reject
    document.querySelectorAll('.principal-action-form').forEach(form => {
      form.addEventListener('submit', function (e) {
        const action = e.submitter.value === 'accept' ? 'Accept' : 'Reject';
        const confirmMsg = `Are you sure you want to ${action} this OD application?`;
        if (!confirm(confirmMsg)) {
          e.preventDefault(); // Cancel submission
        }
      });
    });
  </script>
</body>
</html>

<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
  header("Location: login.php?error=unauthorized");
  exit;
}
$hodDept = $_SESSION['user']['department'];

try {
  $conn = new mysqli('localhost', 'root', '', 'college_db');
  $conn->set_charset('utf8mb4');

  // ✅ Handle HOD override for individual team members
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Case 1: HOD clicked on application-level Approve/Reject
    if (isset($_POST['od_id']) && !isset($_POST['member_id']) && isset($_POST['action'])) {
      $od_id = (int)$_POST['od_id'];
      $hodStatus = $_POST['action'] === 'accept' ? "HOD Accepted" : "HOD Rejected";

      $sqlUpdate = "UPDATE od_applications SET status=? WHERE id=?";
      $stmt = $conn->prepare($sqlUpdate);
      $stmt->bind_param("si", $hodStatus, $od_id);
      $stmt->execute();
      $stmt->close();
    }

    // Case 2: HOD override for individual team member
    if (isset($_POST['member_id'], $_POST['od_id'], $_POST['action'])) {
      $member_id = (int) $_POST['member_id'];
      $od_id = (int) $_POST['od_id'];
      $hodStatus = $_POST['action'] === 'accept' ? "Accepted" : "Rejected";

      $sqlUpdate = "UPDATE od_team_members SET mentor_status=? WHERE id=?";
      $stmt = $conn->prepare($sqlUpdate);
      $stmt->bind_param("si", $hodStatus, $member_id);
      $stmt->execute();
      $stmt->close();
    }
  }


  // ✅ Fetch OD Applications for this dept
  // ✅ Fetch OD Applications for this dept (with search)
  $search = "";
  $searchSql = "";
  if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
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
  }

  $sql = "SELECT DISTINCT o.*
        FROM od_applications o
        LEFT JOIN od_team_members t ON o.id = t.od_id
        WHERE o.department = ?
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
        ORDER BY o.id DESC";

  $stmt = $conn->prepare($sql);

  if ($searchSql) {
    $stmt->bind_param(
      "ssssssssssssss",
      $hodDept,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like,
      $like
    );
  } else {
    $stmt->bind_param("s", $hodDept);
  }

  $stmt->execute();
  $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();


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
// ✅ If AJAX request → return JSON instead of full page
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  header('Content-Type: application/json');
  echo json_encode(['applications' => $applications, 'teams' => $teamData]);
  exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>HOD Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
      font-size: 0.85rem;
    }

    h2,
    h5 {
      font-family: "Segoe UI", sans-serif;
    }

    .table {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      font-size: 0.8rem;
    }

    .table th,
    .table td {
      text-align: center;
      vertical-align: middle;
      padding: 6px 8px;
    }

    .table thead th {
      background: #dc3545;
      color: #fff;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }

    .table-hover tbody tr:hover {
      background-color: #fff5f5;
    }

    .badge {
      font-size: 0.7rem;
      padding: 0.35em 0.5em;
      border-radius: 6px;
    }

    .btn-sm {
      font-size: 0.7rem;
      padding: 2px 6px;
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
    <h2 class="mb-4 text-center text-danger fw-bold">HOD Dashboard</h2>
    <h5 class="mb-4">Logged in as HOD of Department:
      <span class="text-success"><?= htmlspecialchars($hodDept) ?></span>
    </h5>
    <div class="mb-3 d-flex">
      <input type="text" id="searchInput"
        class="form-control me-2"
        placeholder="🔍 Search by ID, Name, Reg No, Purpose, etc.">
    </div>

    <?php if (empty($applications)): ?>
      <div class="alert alert-info">No OD requests available for HOD approval.</div>
    <?php else: ?>
      <table class="table table-bordered table-hover align-middle shadow rounded">
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
            <th>Purpose</th>
            <th>College / Event</th>
            <th>Status</th>
            <th>Team</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $app): ?>
            <tr>
              <td><span class="badge bg-dark"><?= $app['id'] ?></span></td>
              <td><?= htmlspecialchars($app['student_name']) ?></td>
              <td><?= htmlspecialchars($app['register_no']) ?></td>
              <td><?= htmlspecialchars($app['year']) ?></td>
              <td><?= htmlspecialchars($app['department']) ?></td>
              <td><?= htmlspecialchars($app['section']) ?></td>
              <td><span class="badge bg-info text-dark"><?= ucfirst($app['od_type']) ?></span></td>
              <td>
                <?php
                if (!empty($app['from_date']) && !empty($app['to_date'])) {
                  echo htmlspecialchars($app['from_date']) . " → " . htmlspecialchars($app['to_date']);
                } elseif (!empty($app['od_date'])) {
                  echo htmlspecialchars($app['od_date']);
                }
                ?>
              </td>
              <td><?= htmlspecialchars($app['purpose']) ?></td>
              <td>
                <?php if ($app['college_name']): ?>
                  <span class="fw-semibold"><?= htmlspecialchars($app['college_name']) ?></span><br>
                  <span class="text-muted small"><?= htmlspecialchars($app['event_name']) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($app['status'])): ?>
                  <span class="badge bg-<?= str_contains($app['status'], 'Accepted') ? 'success' : (str_contains($app['status'], 'Rejected') ? 'danger' : 'secondary') ?> status-badge">
                    <?= htmlspecialchars($app['status']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($teamData[$app['id']])): ?>
                  <button class="btn btn-outline-danger btn-sm view-team-btn"
                    data-team='<?= json_encode($teamData[$app['id']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                    data-bs-toggle="modal" data-bs-target="#teamModal">
                    👥 View Team
                  </button>
                <?php else: ?>
                  <span class="text-muted">No team</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($app['status'] === 'Mentors Accepted' || $app['status'] === 'Mentors Reviewed'): ?>
                  <form method="post" class="d-flex flex-column gap-1 action-form">
                    <input type="hidden" name="od_id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="">
                    <button type="button" class="btn btn-success btn-sm btn-accept">✅ Approve</button>
                    <button type="button" class="btn btn-danger btn-sm btn-reject">❌ Reject</button>
                    <button type="submit" class="btn btn-primary btn-sm btn-confirm d-none">Confirm</button>
                  </form>
                <?php else: ?>
                  <span class="badge bg-<?= str_contains($app['status'], 'Accepted') ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($app['status']) ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Team Modal -->
  <div class="modal fade" id="teamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Team Members</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="teamModalBody"></div>
      </div>
    </div>
  </div>

  <script>
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector("tbody");

    // ✅ Live Search
    searchInput.addEventListener('keyup', () => {
      const query = searchInput.value.trim();
      fetch(`hod_dashboard.php?ajax=1&search=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
          tableBody.innerHTML = ""; // Clear old rows
          if (data.applications.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="13" class="text-center text-muted">No results found</td></tr>`;
            return;
          }
          data.applications.forEach(app => {
            const dates = app.from_date && app.to_date ?
              `${app.from_date} → ${app.to_date}` :
              (app.od_date || "—");

            tableBody.innerHTML += `
            <tr>
              <td><span class="badge bg-dark">${app.id}</span></td>
              <td>${app.student_name}</td>
              <td>${app.register_no}</td>
              <td>${app.year}</td>
              <td>${app.department}</td>
              <td>${app.section}</td>
              <td><span class="badge bg-info text-dark">${app.od_type}</span></td>
              <td>${dates}</td>
              <td>${app.purpose}</td>
              <td>
                ${app.college_name 
                  ? `<span class="fw-semibold">${app.college_name}</span><br>
                     <span class="text-muted small">${app.event_name || ""}</span>`
                  : `<span class="text-muted">—</span>`}
              </td>
              <td><span class="badge bg-${app.status.includes('Accepted') ? 'success' : (app.status.includes('Rejected') ? 'danger' : 'secondary')}">${app.status}</span></td>
              <td>
                ${data.teams[app.id]?.length 
                  ? `<button class="btn btn-outline-danger btn-sm view-team-btn"
                             data-team='${JSON.stringify(data.teams[app.id])}'
                             data-bs-toggle="modal" data-bs-target="#teamModal">👥 View Team</button>`
                  : `<span class="text-muted">No team</span>`}
              </td>
              <td>
                ${(app.status === 'Mentors Accepted' || app.status === 'Mentors Reviewed') 
                  ? `<form method="post" class="d-flex flex-column gap-1 action-form">
                       <input type="hidden" name="od_id" value="${app.id}">
                       <input type="hidden" name="action" value="">
                       <button type="button" class="btn btn-success btn-sm btn-accept">✅ Approve</button>
                       <button type="button" class="btn btn-danger btn-sm btn-reject">❌ Reject</button>
                       <button type="submit" class="btn btn-primary btn-sm btn-confirm d-none">Confirm</button>
                     </form>`
                  : `<span class="badge bg-${app.status.includes('Accepted') ? 'success' : 'danger'}">${app.status}</span>`}
              </td>
            </tr>`;
          });
        });
    });

    // ✅ Application-level approve/reject (delegated)
    document.addEventListener('click', e => {
      if (e.target.classList.contains('btn-accept') || e.target.classList.contains('btn-reject')) {
        const form = e.target.closest('.action-form');
        const hiddenAction = form.querySelector('input[name="action"]');
        hiddenAction.value = e.target.classList.contains('btn-accept') ? 'accept' : 'reject';
        form.querySelector('.btn-confirm').classList.remove('d-none');
      }
    });

    document.addEventListener('submit', e => {
      if (e.target.classList.contains('action-form')) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        fetch('', {
          method: 'POST',
          body: formData
        }).then(() => {
          const action = formData.get('action') === 'accept' ? 'HOD Accepted' : 'HOD Rejected';
          const badgeClass = formData.get('action') === 'accept' ? 'success' : 'danger';
          form.innerHTML = `<span class="badge bg-${badgeClass}">${action}</span>`;
        });
      }
    });

    // ✅ Show Team Modal with HOD override options (delegated)
    document.addEventListener('click', e => {
      if (e.target.classList.contains('view-team-btn')) {
        const teamData = JSON.parse(e.target.dataset.team);
        let html = `
        <table class="table table-bordered table-sm text-center">
          <thead class="table-light">
            <tr><th>Name</th><th>Reg No</th><th>Year</th><th>Dept</th><th>Sec</th><th>Mentor</th><th>Status</th></tr>
          </thead><tbody>`;
        teamData.forEach(m => {
          let statusCell = '';
          if (m.mentor_status === 'Rejected') { // <-- only show for members rejected by mentor
            statusCell = `
    <form method="post" class="hod-member-form d-flex gap-1">
      <input type="hidden" name="member_id" value="${m.id}">
      <input type="hidden" name="od_id" value="${m.od_id}">
      <input type="hidden" name="action" value="">
      <button type="button" class="btn btn-success btn-sm btn-hod-accept">✅ HOD Accept</button>
      <button type="button" class="btn btn-danger btn-sm btn-hod-reject">❌ HOD Reject</button>
      <button type="submit" class="btn btn-primary btn-sm btn-hod-confirm d-none">Confirm</button>
    </form>`;
          } else {
            statusCell = `<span class="badge bg-${
    m.mentor_status === 'Accepted' ? 'success' :
    m.mentor_status === 'HOD Accepted' ? 'success' :
    m.mentor_status === 'HOD Rejected' ? 'danger' :
    'secondary'
  }">${m.mentor_status}</span>`;
          }



          html += `<tr>
          <td>${m.member_name}</td>
          <td>${m.member_regno}</td>
          <td>${m.member_year}</td>
          <td>${m.member_department}</td>
          <td>${m.member_section}</td>
          <td>${m.mentor}</td>
          <td>${statusCell}</td>
        </tr>`;
        });

        html += "</tbody></table>";
        document.getElementById("teamModalBody").innerHTML = html;
      }
    });

    // ✅ HOD override actions inside modal (delegated)
    document.addEventListener('click', e => {
      if (e.target.classList.contains('btn-hod-accept') || e.target.classList.contains('btn-hod-reject')) {
        const form = e.target.closest('.hod-member-form');
        const actionField = form.querySelector('input[name="action"]');
        actionField.value = e.target.classList.contains('btn-hod-accept') ? 'accept' : 'reject';
        form.querySelector('.btn-hod-confirm').classList.remove('d-none');
      }
    });

    document.addEventListener('submit', e => {
      if (e.target.classList.contains('hod-member-form')) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        fetch('', {
          method: 'POST',
          body: formData
        }).then(() => {
          const action = formData.get('action') === 'accept' ? 'Accepted' : 'Rejected';
          const badgeClass = formData.get('action') === 'accept' ? 'success' : 'danger';
          form.outerHTML = `<span class="badge bg-${badgeClass}">${action}</span>`;
        });
      }
    });
  </script>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
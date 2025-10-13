<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OD Dashboard - HOD Decisions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background: #f7f9fc;
      font-family: "Poppins", sans-serif;
    }
    .dashboard-container {
      max-width: 1100px;
      margin: 30px auto;
      padding: 20px;
    }
    h2 {
      font-weight: 600;
      text-align: center;
      margin-bottom: 25px;
      color: #2c3e50;
    }
    /* Minimal stat cards */
    .stat-card {
      background: #fff;
      border-radius: 12px;
      padding: 18px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .stat-card h4 {
      font-size: 28px;
      margin-bottom: 5px;
      font-weight: 600;
    }
    .stat-card p {
      margin: 0;
      font-size: 14px;
      color: #6c757d;
    }
    .accepted { border-top: 4px solid #28a745; }
    .rejected { border-top: 4px solid #dc3545; }
    .pending { border-top: 4px solid #ffc107; }
    /* Chart card */
    .chart-card {
      border-radius: 15px;
      background: #fff;
      padding: 20px;
      box-shadow: 0 3px 12px rgba(0,0,0,0.07);
      text-align: center;
    }
    .chart-card h5 {
      font-weight: 600;
      margin-bottom: 15px;
      color: #495057;
    }
    .chart-container {
      max-width: 350px; /* smaller donut */
      margin: auto;
    }
  </style>
</head>
<body>

<div class="dashboard-container">
  <h2>📊 OD Requests Dashboard</h2>

  <!-- Stats Row -->
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="stat-card accepted">
        <h4><?php echo $accepted = 22; ?></h4>
        <p>Accepted</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card rejected">
        <h4><?php echo $rejected = 9; ?></h4>
        <p>Rejected</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card pending">
        <h4><?php echo $pending = 4; ?></h4>
        <p>Pending</p>
      </div>
    </div>
  </div>

  <!-- Donut Chart -->
  <div class="row">
    <div class="col-md-6 offset-md-3">
      <div class="chart-card">
        <h5>HOD Decisions Overview</h5>
        <div class="chart-container">
          <canvas id="hodChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // PHP → JS variables
  const accepted = <?php echo $accepted; ?>;
  const rejected = <?php echo $rejected; ?>;
  const pending  = <?php echo $pending; ?>;

  const ctx = document.getElementById("hodChart").getContext("2d");
  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: ["Accepted", "Rejected", "Pending"],
      datasets: [{
        data: [accepted, rejected, pending],
        backgroundColor: [
          "rgba(40, 167, 69, 0.9)",   // green
          "rgba(220, 53, 69, 0.9)",   // red
          "rgba(255, 193, 7, 0.9)"    // yellow
        ],
        hoverOffset: 12
      }]
    },
    options: {
      responsive: true,
      cutout: "70%",  // smaller donut hole
      plugins: {
        legend: {
          position: "bottom",
          labels: { font: { size: 13 } }
        }
      }
    }
  });
</script>

</body>
</html>

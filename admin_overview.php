<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

// Gender stats
$gender_stats = [];
$res = mysqli_query($conn, "SELECT gender, COUNT(*) as cnt FROM students WHERE gender IN('Male','Female') GROUP BY gender");
while ($row = mysqli_fetch_assoc($res)) $gender_stats[$row['gender']] = (int)$row['cnt'];
$maleCount   = $gender_stats['Male']   ?? 0;
$femaleCount = $gender_stats['Female'] ?? 0;

// Department stats
$dept_names  = [];
$dept_counts = [];
$res = mysqli_query($conn, "SELECT department, COUNT(*) as cnt FROM students GROUP BY department ORDER BY cnt DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $dept_names[]  = $row['department'];
    $dept_counts[] = (int)$row['cnt'];
}

// Top events by registrations
$event_names  = [];
$event_counts = [];
$res = mysqli_query($conn, "SELECT e.title, COUNT(r.registrationID) as cnt FROM events e LEFT JOIN registrations r ON e.eventID = r.eventID GROUP BY e.eventID HAVING cnt > 0 ORDER BY cnt DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res)) {
    $event_names[]  = $row['title'];
    $event_counts[] = (int)$row['cnt'];
}

// Summary stats
$total_students   = $maleCount + $femaleCount;
$total_events     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM events"))['c'];
$pending_events   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM events WHERE status='pending'"))['c'];
$pending_requests = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM requests WHERE status='Pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overview - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .topbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .topbar h4 { margin: 0; color: #333; }
        .wrap { padding: 25px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .chart-wrap     { position: relative; height: 300px; }
        .chart-wrap-bar { position: relative; height: 290px; }
    </style>
</head>
<body>

<div class="wrap">

    <div class="topbar">
        <h4>Dashboard Overview</h4>
        <span style="font-size:13px; color:#888;">Administrator: <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    </div>

    <!-- Stat cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <h2 style="color:#2c3e50;"><?= $total_students ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Registered Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <h2 style="color:#27ae60;"><?= $total_events ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Total Events</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <h2 style="color:#e67e22;"><?= $pending_events ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Pending Approval</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <h2 style="color:#e74c3c;"><?= $pending_requests ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Open Support Tickets</p>
            </div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="row">

        <!-- Gender pie -->
        <div class="col-md-6">
            <div class="card">
                <h5 style="margin-bottom:15px;">Students by Gender</h5>
                <?php if ($maleCount + $femaleCount > 0): ?>
                    <div class="chart-wrap">
                        <canvas id="genderChart"></canvas>
                    </div>
                <?php else: ?>
                    <p style="color:#aaa; text-align:center; padding:40px 0;">No student data yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top events pie -->
        <div class="col-md-6">
            <div class="card">
                <h5 style="margin-bottom:15px;">Top 5 Events by Registrations</h5>
                <?php if (count($event_names) > 0): ?>
                    <div class="chart-wrap">
                        <canvas id="eventsChart"></canvas>
                    </div>
                <?php else: ?>
                    <p style="color:#aaa; text-align:center; padding:40px 0;">No registrations yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Department bar -->
        <div class="col-md-12">
            <div class="card">
                <h5 style="margin-bottom:15px;">Students by Department</h5>
                <?php if (count($dept_names) > 0): ?>
                    <div class="chart-wrap-bar">
                        <canvas id="deptChart"></canvas>
                    </div>
                <?php else: ?>
                    <p style="color:#aaa; text-align:center; padding:40px 0;">No student data yet.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// Shared percentage label plugin for pie charts
var pctPlugin = {
    id: 'pctLabels',
    afterDatasetDraw: function(chart) {
        var ctx = chart.ctx;
        var dataset = chart.data.datasets[0];
        var total = dataset.data.reduce(function(a, b) { return a + b; }, 0);
        chart.getDatasetMeta(0).data.forEach(function(arc, i) {
            var pct = total > 0 ? ((dataset.data[i] / total) * 100).toFixed(1) : 0;
            if (pct < 3) return; // skip tiny slices
            var mid = (arc.startAngle + arc.endAngle) / 2;
            var r   = (arc.outerRadius + arc.innerRadius) / 2 + (arc.outerRadius - arc.innerRadius) * 0.15;
            var x   = arc.x + Math.cos(mid) * r;
            var y   = arc.y + Math.sin(mid) * r;
            ctx.save();
            ctx.fillStyle    = 'white';
            ctx.font         = 'bold 13px Segoe UI, sans-serif';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'middle';
            ctx.shadowColor  = 'rgba(0,0,0,0.35)';
            ctx.shadowBlur   = 3;
            ctx.fillText(pct + '%', x, y);
            ctx.restore();
        });
    }
};

<?php if ($maleCount + $femaleCount > 0): ?>
new Chart(document.getElementById('genderChart').getContext('2d'), {
    type: 'pie',
    plugins: [pctPlugin],
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?= $maleCount ?>, <?= $femaleCount ?>],
            backgroundColor: ['#3498db', '#e74c3c'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                        var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                        return '  ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($event_names) > 0): ?>
new Chart(document.getElementById('eventsChart').getContext('2d'), {
    type: 'pie',
    plugins: [pctPlugin],
    data: {
        labels: <?= json_encode($event_names) ?>,
        datasets: [{
            data: <?= json_encode($event_counts) ?>,
            backgroundColor: ['#1abc9c', '#f1c40f', '#9b59b6', '#34495e', '#e67e22'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                        var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                        return '  ' + ctx.label + ': ' + ctx.parsed + ' registrations (' + pct + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($dept_names) > 0): ?>
new Chart(document.getElementById('deptChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($dept_names) ?>,
        datasets: [{
            label: 'Students',
            data: <?= json_encode($dept_counts) ?>,
            backgroundColor: '#8ebcea',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                        var pct   = total > 0 ? ((ctx.parsed.y / total) * 100).toFixed(1) : 0;
                        return '  Students: ' + ctx.parsed.y + ' (' + pct + '%)';
                    }
                }
            }
        },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
<?php endif; ?>
</script>
</body>
</html>
<?php mysqli_close($conn); ?>

<?php
// Get analytics data
$stats = HLIR_Analytics::get_incident_stats();
?>
<div class="wrap">
    <h1>Incident Analytics</h1>

    <!-- Summary Cards -->
    <div class="hlir-analytics-summary">
        <div class="summary-card">
            <h3>Total Incidents</h3>
            <p class="number"><?php echo esc_html($stats['total_incidents']); ?></p>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="hlir-analytics-grid">
        <!-- Incidents by Type -->
        <div class="chart-container">
            <h2>Incidents by Type</h2>
            <canvas id="incidentTypeChart"></canvas>
        </div>

        <!-- Incidents by Severity -->
        <div class="chart-container">
            <h2>Incidents by Severity</h2>
            <canvas id="severityChart"></canvas>
        </div>

        <!-- Incidents by Status -->
        <div class="chart-container">
            <h2>Incidents by Status</h2>
            <canvas id="statusChart"></canvas>
        </div>

        <!-- Incidents Timeline -->
        <div class="chart-container full-width">
            <h2>Incidents Timeline</h2>
            <canvas id="timelineChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Analytics page loaded');

    // Parse PHP data
    const statsData = <?php echo json_encode($stats); ?>;
    console.log('Stats data:', statsData);

    // Incident Types Chart
    new Chart(document.getElementById('incidentTypeChart'), {
        type: 'pie',
        data: {
            labels: statsData.types.labels,
            datasets: [{
                data: statsData.types.data,
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Severity Chart
    new Chart(document.getElementById('severityChart'), {
        type: 'bar',
        data: {
            labels: statsData.severity.labels,
            datasets: [{
                label: 'Incidents by Severity',
                data: statsData.severity.data,
                backgroundColor: Object.values(statsData.severity.colors)
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Status Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statsData.status.labels,
            datasets: [{
                data: statsData.status.data,
                backgroundColor: Object.values(statsData.status.colors)
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Timeline Chart
    new Chart(document.getElementById('timelineChart'), {
        type: 'line',
        data: {
            labels: statsData.timeline.labels,
            datasets: [{
                label: 'Incidents over Time',
                data: statsData.timeline.data,
                borderColor: '#36A2EB',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
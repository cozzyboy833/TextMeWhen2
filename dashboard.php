<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TextMeWhen Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .reminders-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .reminder-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .reminder-item.ready {
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .reminder-item.completed {
            background: #d4edda;
            border-color: #28a745;
        }
        
        .reminder-text {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .reminder-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 0.9em;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-active { background: #cce5ff; color: #0066cc; }
        .status-completed { background: #ccffcc; color: #006600; }
        .status-failed { background: #ffcccc; color: #cc0000; }
        
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .auto-refresh {
            margin-left: 20px;
        }
        
        .last-update {
            color: #666;
            font-size: 0.9em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🤖 TextMeWhen Dashboard</h1>
            <div>
                <span class="last-update">Last updated: <span id="lastUpdate">Loading...</span></span>
                <button class="refresh-btn" onclick="loadData()">Refresh</button>
                <label class="auto-refresh">
                    <input type="checkbox" id="autoRefresh" checked> Auto-refresh (30s)
                </label>
            </div>
        </div>

        <div class="stats-grid" id="statsGrid">
            <!-- Stats will be loaded here -->
        </div>

        <div class="reminders-section">
            <h2>🔥 Ready to Trigger</h2>
            <div id="readyReminders">Loading...</div>
        </div>

        <div class="reminders-section">
            <h2>⏳ Active Reminders</h2>
            <div id="activeReminders">Loading...</div>
        </div>

        <div class="reminders-section">
            <h2>✅ Recently Completed</h2>
            <div id="completedReminders">Loading...</div>
        </div>
    </div>

    <script>
        let autoRefreshInterval;

        async function loadData() {
            try {
                const response = await fetch('dashboard_api.php');
                const data = await response.json();
                
                updateStats(data.stats);
                updateReminders('readyReminders', data.ready_reminders, 'ready');
                updateReminders('activeReminders', data.active_reminders, 'active');
                updateReminders('completedReminders', data.completed_reminders, 'completed');
                
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                
                // Show alerts for ready reminders
                if (data.ready_reminders.length > 0) {
                    showAlert(`🎯 ${data.ready_reminders.length} reminder(s) ready to trigger!`, 'warning');
                }
                
            } catch (error) {
                console.error('Error loading data:', error);
                showAlert('Error loading dashboard data. Make sure dashboard_api.php exists.', 'warning');
            }
        }

        function updateStats(stats) {
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${stats.total}</div>
                    <div class="stat-label">Total Reminders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.active}</div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.ready}</div>
                    <div class="stat-label">Ready to Trigger</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.completed}</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.notifications}</div>
                    <div class="stat-label">Notifications Sent</div>
                </div>
            `;
        }

        function updateReminders(containerId, reminders, type) {
            const container = document.getElementById(containerId);
            
            if (reminders.length === 0) {
                container.innerHTML = '<p>No reminders in this category.</p>';
                return;
            }

            container.innerHTML = reminders.map(reminder => `
                <div class="reminder-item ${type}">
                    <div class="reminder-text">"${reminder.original_text}"</div>
                    <div class="reminder-meta">
                        <div>📧 ${reminder.email}</div>
                        <div>🏷️ <span class="status-badge status-${reminder.status}">${reminder.status}</span></div>
                        <div>📅 ${new Date(reminder.created_at).toLocaleString()}</div>
                        <div>🔍 ${reminder.current_checks}/${reminder.max_checks} checks</div>
                        ${reminder.target_time ? `<div>⏰ Target: ${new Date(reminder.target_time).toLocaleString()}</div>` : ''}
                        ${reminder.time_remaining ? `<div>⏳ ${reminder.time_remaining}</div>` : ''}
                        ${reminder.event_type ? `<div>🤖 Type: ${reminder.event_type}</div>` : ''}
                    </div>
                </div>
            `).join('');
        }

        function showAlert(message, type) {
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }

            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            document.querySelector('.dashboard').insertBefore(alert, document.querySelector('.stats-grid'));
            
            setTimeout(() => alert.remove(), 5000);
        }

        function setupAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            
            function toggleAutoRefresh() {
                if (checkbox.checked) {
                    autoRefreshInterval = setInterval(loadData, 30000); // 30 seconds
                } else {
                    clearInterval(autoRefreshInterval);
                }
            }
            
            checkbox.addEventListener('change', toggleAutoRefresh);
            toggleAutoRefresh(); // Start if checked
        }

        // Initialize
        loadData();
        setupAutoRefresh();
    </script>
</body>
</html>
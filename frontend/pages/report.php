<?php
/**
 * Reports & Analytics
 *
 * Reporting dashboard: report type picker, date range, filters, overview charts
 * and a DataTables results table, plus an email-report dialog. Data comes from
 * frontend/api/report/apis/ via the large inline script below.
 *
 * This page is the layout's odd one out and stays that way:
 *   - the content id lives on <main class="content" id="main-content"> itself;
 *     there is no .page-content wrapper (contentTag => 'main');
 *   - its topnav is nested inside <main> (topnavPlacement => 'in-content');
 *   - it ships no footer include;
 *   - Chart.js loads in <head> before js/env.js, while jQuery, Bootstrap, moment,
 *     daterangepicker and DataTables load at the top of <body> ahead of the inline
 *     script, which in turn precedes ui.js and main.js. That order is reproduced
 *     exactly via headCdnJs / preFooterScripts / bodyInline / scripts;
 *   - it uses Bootstrap 5 and Font Awesome 6.4.0 (not the 6.6.0 other pages use).
 *     There are no inline page styles to extract: the only <style> block in this
 *     file lives inside a JS template literal in printCombinedServiceReport() and
 *     builds the print popup's own document.
 *
 * The inline script is kept inline on purpose. It interpolates the CSRF token and
 * the session role from PHP, so moving ~1000 lines of jQuery/DataTables code into
 * a static .js file would mean changing how it receives them. It is emitted
 * through the layout's bodyInline slot, so its position in the document is
 * unchanged.
 *
 * Changes made while moving this onto the shared layout:
 *   - the permission fallback redirected to `dashboard.php`, which does not exist
 *     in frontend/pages/ (the dashboard is the root index.php), so a denied user
 *     got a 404. Both redirects now target the real application root;
 *   - the CSRF meta tag is now emitted (the page called init_csrf() but never
 *     printed the tag);
 *   - the hand-rolled theme snippet, which only read localStorage and ignored the
 *     OS colour-scheme preference, was replaced by the shared pre-paint theme
 *     script;
 *   - the duplicated outputApiConfig() require was dropped (the layout emits it).
 *
 * The authentication and permissions logic below is the page's own and is kept
 * exactly as it was: admin/pastor/pcf are allowed outright, and any other role is
 * checked against the permissions table.
 */

// The layout supplies the URL helpers the redirects below use.
require_once __DIR__ . '/../layout/bootstrap.php';

// report.php
// Church Management System - Reports & Analytics Dashboard
// Updated for specific schema: church_management

// ============================
// SECURITY & CONFIGURATION
// ============================
require_once '../../backend/config/security.php';
secure_session_start();
init_csrf();

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// Get user role
$user_role = $_SESSION['role'] ?? 'guest';

// Check if user has permission to access reports
$allowed_roles = ['admin', 'pastor', 'pcf'];
if (!in_array($user_role, $allowed_roles)) {
    // For other roles, check permissions table
    try {
        require_once '../../backend/config/database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("SELECT allowed FROM permissions WHERE role = ? AND module = 'reports'");
        $stmt->execute([$user_role]);
        $permission = $stmt->fetch();
        
        if (!$permission || !$permission['allowed']) {
            header('Location: ' . layout_url_context()['rootUrl']);
            exit();
        }
    } catch(Exception $e) {
        // If we can't check permissions, redirect to dashboard
        header('Location: ' . layout_url_context()['rootUrl']);
        exit();
    }
}

// ============================
// PAGE CONFIGURATION
// ============================
$page_title = "Reports & Analytics";
$current_page = 'reports';

// ============================
// API BASE URL
// ============================
$api_base_url = '../api/report/apis/';

// ============================
// SERVICE OPTIONS FROM DATABASE
// ============================
$services = [];
$fellowships = [];

try {
    require_once '../../backend/config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get services
    $stmt = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fellowships
    $stmt = $pdo->query("SELECT id, name FROM fellowships WHERE status = 'active' ORDER BY name");
    $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    // Continue without service/fellowship data
    error_log("Error fetching services/fellowships: " . $e->getMessage());
}
?>
<?php
$bootstrapCss       = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css';
$bootstrapJs        = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js';
$fontAwesomeCss     = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
$jqueryJs           = 'https://code.jquery.com/jquery-3.6.0.min.js';
$momentJs           = 'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js';
$daterangepickerCss = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css';
$daterangepickerJs  = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js';
$dataTablesCss      = 'https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css';
$dataTablesJs       = 'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js';
$dataTablesBs5Js    = 'https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js';
$chartJs            = 'https://cdn.jsdelivr.net/npm/chart.js';

/** The page's own reporting code, verbatim. It interpolates PHP, hence heredoc. */
$reportInlineScript = <<<'REPORTJS'
    <script>
        // ============================
        // GLOBAL CONFIGURATION
        // ============================
        const API_BASE_URL = '<?php echo htmlspecialchars($api_base_url); ?>';
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        const USER_ROLE = '<?php echo htmlspecialchars($user_role); ?>';
        
        // ============================
        // CHART INSTANCES
        // ============================
        let overviewChart = null;
        let membershipChart = null;
        let dataTable = null;
        
        // ============================
        // INITIALIZATION
        // ============================
        $(document).ready(function() {
            // Initialize date range picker
            $('#dateRange').daterangepicker({
                opens: 'left',
                startDate: moment().startOf('month'),
                endDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'This Quarter': [moment().startOf('quarter'), moment().endOf('quarter')],
                    'This Year': [moment().startOf('year'), moment().endOf('year')]
                },
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
            
            // Load initial report
            loadOverviewReport();
            
            // Event Listeners
            $('#generateReport').click(function() {
                loadSelectedReport();
            });
            
            $('#exportCSV').click(exportReportCSV);
            $('#exportExcel').click(exportReportExcel);
            $('#exportPDF').click(exportReportPDF);
            $('#printReports').click(printReport);
            $('#emailReport').click(showEmailModal);
            $('#sendEmailBtn').click(sendEmailReport);
            
            // Clear data table when changing report type
            $('#reportType').change(function() {
                if (dataTable) {
                    dataTable.destroy();
                    $('#reportTable').empty();
                }
            });
        });
        
        // ============================
        // API CALL FUNCTIONS
        // ============================
        
        /**
         * Make API call to fetch report data
         */
        async function fetchReportData(endpoint, params = {}) {
            showLoading(true);
            
            // Add CSRF token and other default params
            params.csrf_token = CSRF_TOKEN;
            const dateRange = $('#dateRange').data('daterangepicker');
            params.start_date = dateRange.startDate.format('YYYY-MM-DD');
            params.end_date = dateRange.endDate.format('YYYY-MM-DD');
            params.period = $('#timePeriod').val();
            params.fellowship_id = $('#fellowshipFilter').val();
            params.service_id = $('#serviceFilter').val();
            
            try {
                const queryString = new URLSearchParams(params).toString();
                const response = await fetch(`${API_BASE_URL}${endpoint}?${queryString}`, {
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    // Try to get error details from response
                    const text = await response.text();
                    console.error('API Response:', text);
                    throw new Error(`API Error: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error fetching report:', error);
                showError('Failed to load report data. Please try again.');
                return null;
            } finally {
                showLoading(false);
            }
        }
        
        /**
         * Load overview dashboard report
         */
        async function loadOverviewReport() {
            const data = await fetchReportData('overview.php');
            
            if (data && data.success) {
                // Update summary cards
                updateSummaryCards(data.summary);
                
                // Render charts
                renderOverviewChart(data.chart_data);
                renderMembershipChart(data.membership_data);
                
                // Populate table
                populateReportTable(data.table_data, 'Financial Transactions');
            }
        }
        
        /**
         * Load selected report based on dropdown
         */
        async function loadSelectedReport() {
            const reportType = $('#reportType').val();
            let endpoint = '';
            let title = '';
            
            // Map report type to API endpoint
            const reportMap = {
                'overview': { endpoint: 'overview.php', title: 'Overview Dashboard' },
                'members_summary': { endpoint: 'members/summary.php', title: 'Members Summary' },
                'members_by_status': { endpoint: 'members/by_status.php', title: 'Members by Status' },
                'members_by_age': { endpoint: 'members/by_age.php', title: 'Members by Age/Category' },
                'new_members': { endpoint: 'members/new_members.php', title: 'New Members' },
                'members_by_fellowship': { endpoint: 'fellowship/attendance.php', title: 'Members by Fellowship' },
                'offerings_summary': { endpoint: 'finance/offerings.php', title: 'Offerings Summary' },
                'tithes_summary': { endpoint: 'finance/tithes.php', title: 'Tithes Summary' },
                'income_by_type': { endpoint: 'finance/donations.php', title: 'Income by Type' },
                'income_range': { endpoint: 'finance/income_range.php', title: 'Income by Date Range' },
                'by_payment_method': { endpoint: 'finance/by_payment_method.php', title: 'By Payment Method' },
                'partnership_arms': { endpoint: 'finance/partnership_arms.php', title: 'Partnership Arms Giving & Ranking' },
                'attendance_summary': { endpoint: 'attendance/summary.php', title: 'Attendance Summary' },
                'attendance_by_service': { endpoint: 'attendance/by_service.php', title: 'Attendance by Service' },
                'attendance_by_fellowship': { endpoint: 'fellowship/attendance.php', title: 'Attendance by Fellowship' },
                'attendance_trends': { endpoint: 'attendance/trends.php', title: 'Attendance Trends' },
                'top_attendees': { endpoint: 'attendance/top_attendees.php', title: 'Top Attendees with Ranking' },
                'fellowship_summary': { endpoint: 'fellowship/summary.php', title: 'Fellowship Summary' },
                'fellowship_attendance': { endpoint: 'fellowship/attendance.php', title: 'Fellowship Attendance' },
                'service_summary': { endpoint: 'services/combined_report.php', title: 'Service Report (Combined)' },
                'service_details': { endpoint: 'services/detailed.php', title: 'Service Details' },
                'user_activity': { endpoint: 'system/user_activity.php', title: 'User Activity' },
                'system_logs': { endpoint: 'system/logs.php', title: 'System Logs' }
            };
            
            const reportConfig = reportMap[reportType] || reportMap['overview'];
            endpoint = reportConfig.endpoint;
            title = reportConfig.title;
            
            // Update page title
            $('h1').html(`<i class="fas fa-chart-bar text-primary me-2"></i>${title}`);
            $('#tableTitle').text(title);
            
            const data = await fetchReportData(endpoint);
            
            if (data && data.success) {
                updateUIForReport(reportType, data);
            }
        }
        
        // ============================
        // UI UPDATE FUNCTIONS
        // ============================
        
        /**
         * Update summary cards with data
         */
        function updateSummaryCards(summary) {
            if (summary.total_members !== undefined) {
                $('#totalMembers').text(summary.total_members);
                $('#activeMembers').text(summary.active_members || 0);
            }
            
            if (summary.total_offerings !== undefined) {
                $('#totalOfferings').text('GHS ' + formatNumber(summary.total_offerings));
                $('#totalTithes').text('GHS ' + formatNumber(summary.total_tithes || 0));
            }
            
            if (summary.total_attendance !== undefined) {
                $('#totalAttendance').text(summary.total_attendance);
                if (summary.attendance_rate !== undefined) {
                    $('#attendanceRate').text(summary.attendance_rate + '%');
                }
            }
            
            if (summary.total_fellowships !== undefined) {
                $('#totalFellowships').text(summary.total_fellowships);
                $('#activeFellowships').text(summary.active_fellowships || summary.total_fellowships);
            }
        }
        
        /**
         * Render overview chart
         */
        function renderOverviewChart(chartData) {
            const ctx = document.getElementById('overviewChart').getContext('2d');
            
            if (overviewChart) {
                overviewChart.destroy();
            }
            
            $('#chart1Title').text('Monthly Overview');
            
            overviewChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [
                        {
                            label: 'Attendance',
                            data: chartData.attendance || [],
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'New Members',
                            data: chartData.new_members || [],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Offerings (GHS)',
                            data: chartData.offerings || [],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    stacked: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Performance Overview'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 2) { // Offerings
                                        label += 'GHS ' + formatNumber(context.parsed.y);
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Count'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Amount (GHS)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }
        
        /**
         * Render membership distribution chart
         */
        function renderMembershipChart(membershipData) {
            const ctx = document.getElementById('membershipChart').getContext('2d');
            
            if (membershipChart) {
                membershipChart.destroy();
            }
            
            $('#chart2Title').text('Membership Distribution');
            
            membershipChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: membershipData.labels || [],
                    datasets: [{
                        data: membershipData.data || [],
                        backgroundColor: membershipData.colors || [
                            'rgb(54, 162, 235)',
                            'rgb(255, 99, 132)',
                            'rgb(255, 205, 86)'
                        ],
                        borderWidth: 1,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Populate report table with data
         */
        function populateReportTable(tableData, title = 'Report Data') {
            const thead = $('#reportTableHead');
            const tbody = $('#reportTableBody');
            thead.empty();
            tbody.empty();
            
            if (!tableData || tableData.length === 0) {
                tbody.append('<tr><td colspan="10" class="text-center py-5">No data available for the selected period</td></tr>');
                return;
            }
            
            // Create table headers from first row keys
            const firstRow = tableData[0];
            const headers = Object.keys(firstRow);
            
            let headerRow = '<tr>';
            headers.forEach(header => {
                const formattedHeader = formatHeader(header);
                headerRow += `<th>${formattedHeader}</th>`;
            });
            headerRow += '</tr>';
            thead.html(headerRow);
            
            // Create table rows
            tableData.forEach(row => {
                let tr = '<tr>';
                headers.forEach(header => {
                    let cellValue = row[header];
                    
                    // Format cell value based on header
                    if (header.includes('amount') || header.includes('Amount')) {
                        cellValue = cellValue ? 'GHS ' + formatNumber(cellValue) : 'GHS 0.00';
                    } else if (header.includes('date') || header.includes('Date')) {
                        cellValue = cellValue ? formatDate(cellValue) : 'N/A';
                    } else if (header === 'status') {
                        cellValue = getStatusBadge(cellValue);
                    } else if (header === 'attendance_rate' || (header.includes('rate') && typeof cellValue === 'number')) {
                        cellValue = cellValue ? cellValue.toFixed(2) + '%' : '0%';
                    } else if (typeof cellValue === 'number') {
                        cellValue = formatNumber(cellValue);
                    } else if (!cellValue) {
                        cellValue = 'N/A';
                    }
                    
                    tr += `<td>${cellValue}</td>`;
                });
                tr += '</tr>';
                tbody.append(tr);
            });
            
            // Initialize DataTable if not already initialized
            if (!dataTable) {
                dataTable = $('#reportTable').DataTable({
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    order: [[0, 'desc']],
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search..."
                    }
                });
            } else {
                dataTable.clear();
                dataTable.rows.add($('#reportTable tbody tr'));
                dataTable.draw();
            }
        }
        
        /**
         * Render Combined Service Report with special formatting
         */
        function renderCombinedServiceReport(data) {
            // Store report data for email functionality
            currentReportData = data;
            currentReportType = 'service_summary';
            
            // Hide charts and stats sections
            $('#chartsSection').hide();
            $('#statsSection').hide();
            
            // Show data tables section
            $('#dataTables').show();
            
            // Update summary cards if available
            if (data.summary) {
                updateSummaryCards(data.summary);
            }
            
            const tbody = $('#reportTableBody');
            const thead = $('#reportTableHead');
            tbody.empty();
            thead.empty();
            
            if (!data.report || data.report.length === 0) {
                tbody.append('<tr><td colspan="10" class="text-center py-5">No service data available for the selected period</td></tr>');
                return;
            }
            
            // Create custom header for combined service report
            thead.html(`
                <tr>
                    <th>Fellowship Name</th>
                    <th>Service Type</th>
                    <th>Service Date</th>
                    <th>Adults</th>
                    <th>Teens</th>
                    <th>Children</th>
                    <th>Total Present</th>
                    <th>First Timers</th>
                    <th>Offering (GHS)</th>
                    <th>Partnerships</th>
                </tr>
            `);
            
            // Populate table rows
            data.report.forEach(service => {
                const partnerships = service.partnerships.map(p => 
                    `${p.partnership_arm}: ${p.member_name} (GHS ${formatNumber(p.amount)})`
                ).join('<br>') || 'None';
                
                const row = `
                    <tr>
                        <td>${service.fellowship_name}</td>
                        <td>${service.service_type}</td>
                        <td>${formatDate(service.service_date)}</td>
                        <td class="text-center">${service.attendance.adults}</td>
                        <td class="text-center">${service.attendance.teens}</td>
                        <td class="text-center">${service.attendance.children}</td>
                        <td class="text-center"><strong>${service.attendance.total}</strong></td>
                        <td class="text-center">${service.attendance.first_timers}</td>
                        <td class="text-end">GHS ${formatNumber(service.offering)}</td>
                        <td style="max-width: 300px; white-space: normal;">${partnerships}</td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Initialize DataTable if not already initialized
            if (dataTable) {
                dataTable.destroy();
            }
            
            dataTable = $('#reportTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[2, 'desc']], // Sort by date descending
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
            
            // Add print and email buttons functionality
            $('#exportPDF').off('click').on('click', function() {
                printCombinedServiceReport(data.report);
            });
        }
        
        /**
         * Print Combined Service Report
         */
        function printCombinedServiceReport(reportData) {
            const printWindow = window.open('', '_blank');
            
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Service Report (Combined)</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; color: #333; }
                        .report-header { text-align: center; margin-bottom: 30px; }
                        .service-block { 
                            border: 2px solid #333; 
                            padding: 20px; 
                            margin-bottom: 30px; 
                            page-break-inside: avoid;
                        }
                        .service-header { 
                            background-color: #f0f0f0; 
                            padding: 10px; 
                            margin-bottom: 15px;
                            border-bottom: 2px solid #333;
                        }
                        .service-header h3 { margin: 5px 0; }
                        .attendance-section { margin: 15px 0; }
                        .attendance-line { 
                            display: flex; 
                            justify-content: space-between; 
                            padding: 8px 0;
                            border-bottom: 1px dotted #ccc;
                        }
                        .attendance-line strong { min-width: 150px; }
                        .partnerships-section { margin-top: 20px; }
                        .partnership-item { padding: 5px 0; margin-left: 20px; }
                        @media print {
                            .service-block { page-break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Church Management System</h1>
                        <h2>Service Report (Combined)</h2>
                        <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    </div>
            `;
            
            reportData.forEach(service => {
                html += `
                    <div class="service-block">
                        <div class="service-header">
                            <h3>Fellowship: ${service.fellowship_name}</h3>
                            <h3>Service Type: ${service.service_type}</h3>
                            <h3>Date: ${formatDate(service.service_date)}</h3>
                        </div>
                        
                        <div class="attendance-section">
                            <h4>ATTENDANCE</h4>
                            <div class="attendance-line">
                                <strong>Adults:</strong>
                                <span>${service.attendance.adults}</span>
                            </div>
                            <div class="attendance-line">
                                <strong>Teens:</strong>
                                <span>${service.attendance.teens}</span>
                            </div>
                            <div class="attendance-line">
                                <strong>Children:</strong>
                                <span>${service.attendance.children}</span>
                            </div>
                            <div class="attendance-line" style="border-bottom: 2px solid #333; font-weight: bold;">
                                <strong>Total:</strong>
                                <span>${service.attendance.total}</span>
                            </div>
                            <div class="attendance-line">
                                <strong>First Timers:</strong>
                                <span>${service.attendance.first_timers}</span>
                            </div>
                            <div class="attendance-line" style="border-bottom: 2px solid #333;">
                                <strong>Offering:</strong>
                                <span>GHS ${formatNumber(service.offering)}</span>
                            </div>
                        </div>
                        
                        ${service.partnerships.length > 0 ? `
                            <div class="partnerships-section">
                                <h4>PARTNERSHIP CONTRIBUTIONS</h4>
                                ${service.partnerships.map(p => `
                                    <div class="partnership-item">
                                        • ${p.partnership_arm}: ${p.member_name} - GHS ${formatNumber(p.amount)}
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            html += `
                </body>
                </html>
            `;
            
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }
        
        /**
         * Update UI for specific report type
         */
        function updateUIForReport(reportType, data) {
            // Debug logging
            console.log('updateUIForReport called with:', reportType, data);
            
            // Special handling for service_summary (Combined Service Report)
            if (reportType === 'service_summary' && data.report) {
                renderCombinedServiceReport(data);
                return;
            }
            
            // Hide/show sections based on report type
            const showCharts = ['overview', 'attendance_trends', 'income_range'].includes(reportType);
            const showStats = ['members_summary', 'attendance_summary', 'fellowship_summary', 'partnership_arms'].includes(reportType);
            
            console.log('Show charts:', showCharts, 'Show stats:', showStats);
            
            if (showCharts) {
                $('#chartsSection').show();
                if (data.chart_data) {
                    renderOverviewChart(data.chart_data);
                }
                if (data.membership_data) {
                    renderMembershipChart(data.membership_data);
                }
            } else {
                $('#chartsSection').hide();
            }
            
            if (showStats && data.stats) {
                console.log('Rendering stats for', reportType, ':', data.stats);
                $('#statsSection').show();
                renderStats(data.stats, reportType);
            } else {
                $('#statsSection').hide();
            }
            
            // Update summary if available
            if (data.summary) {
                updateSummaryCards(data.summary);
            }
            
            // Update table
            let tableData = data.table_data || data.members || data.transactions || data.details || 
                          data.top_attendees || data.arm_rankings || data.service_days || 
                          data.new_members || data.attendance_records || data.members_by_fellowship || [];
            
            console.log('Initial tableData:', tableData);
            
            // Special handling for different report types
            switch(reportType) {
                case 'top_attendees':
                    tableData = data.top_attendees || [];
                    break;
                case 'partnership_arms':
                    tableData = data.arm_rankings || [];
                    break;
                case 'service_summary':
                    tableData = data.service_details || [];
                    break;
                case 'service_details':
                    if (data.all_attendees && data.all_attendees.length > 0) {
                        tableData = data.all_attendees;
                    } else {
                        tableData = data.service_days || [];
                    }
                    break;
                case 'members_by_fellowship':
                case 'attendance_by_fellowship':
                    if (data.members_by_fellowship && data.members_by_fellowship.length > 0) {
                        // Flatten members from all fellowships into one list
                        tableData = data.members_by_fellowship.reduce((acc, curr) => {
                            return acc.concat(curr.members);
                        }, []);
                    }
                    break;
            }
            
            console.log('Final tableData for', reportType, ':', tableData);
            
            populateReportTable(tableData, $('#reportType option:selected').text());
        }
        
        /**
         * Render statistics cards
         */
        function renderStats(stats, reportType) {
            const statsSection = $('#statsSection');
            statsSection.empty();
            
            let html = '';
            
            if (reportType === 'members_summary') {
                // Members summary stats
                html += '<div class="col-md-4">';
                html += '<div class="card report-card">';
                html += '<div class="card-header"><h6>Members by Status</h6></div>';
                html += '<div class="card-body">';
                stats.by_status.forEach(stat => {
                    const color = stat.status === 'active' ? 'success' : 
                                 stat.status === 'inactive' ? 'danger' : 'warning';
                    html += `<div class="stat-card bg-light">
                        <div class="stat-value text-${color}">${stat.count}</div>
                        <div class="stat-label">${stat.status.toUpperCase()}</div>
                    </div>`;
                });
                html += '</div></div></div>';
                
                html += '<div class="col-md-4">';
                html += '<div class="card report-card">';
                html += '<div class="card-header"><h6>Members by Category</h6></div>';
                html += '<div class="card-body">';
                stats.by_category.forEach(stat => {
                    html += `<div class="stat-card bg-light">
                        <div class="stat-value text-primary">${stat.count}</div>
                        <div class="stat-label">${stat.category.toUpperCase()}</div>
                    </div>`;
                });
                html += '</div></div></div>';
                
                html += '<div class="col-md-4">';
                html += '<div class="card report-card">';
                html += '<div class="card-header"><h6>Members by Fellowship</h6></div>';
                html += '<div class="card-body">';
                stats.by_fellowship.forEach(stat => {
                    html += `<div class="stat-card bg-light">
                        <div class="stat-value text-info">${stat.count}</div>
                        <div class="stat-label">${stat.fellowship}</div>
                    </div>`;
                });
                html += '</div></div></div>';
            } else if (reportType === 'partnership_arms') {
                // Partnership arms ranking
                if (stats.arm_rankings && stats.arm_rankings.length > 0) {
                    html += '<div class="col-12">';
                    html += '<div class="card report-card">';
                    html += '<div class="card-header"><h6>Partnership Arms Ranking</h6></div>';
                    html += '<div class="card-body">';
                    html += '<div class="row">';
                    
                    stats.arm_rankings.forEach((arm, index) => {
                        const rankClass = getRankClass(arm.rank);
                        html += `<div class="col-md-6 mb-3">
                            <div class="stat-card bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="rank-badge ${rankClass} me-2">${arm.rank}</span>
                                        <strong>${arm.partnership_arm}</strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="stat-value text-success">GHS ${formatNumber(arm.total_amount)}</div>
                                        <div class="stat-label">${arm.unique_contributors} contributors</div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += '</div></div></div></div>';
                }
            }
            
            statsSection.html(`<div class="row">${html}</div>`);
        }
        
        // ============================
        // UTILITY FUNCTIONS
        // ============================
        
        /**
         * Show/hide loading spinner
         */
        function showLoading(show) {
            if (show) {
                $('#loadingSpinner').show();
                $('#chartsSection, #statsSection, #dataTables').hide();
            } else {
                $('#loadingSpinner').hide();
                $('#chartsSection, #statsSection, #dataTables').show();
            }
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            // Create alert
            const alert = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
            
            $('.main-content').prepend(alert);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        }
        
        /**
         * Format number with commas
         */
        function formatNumber(num) {
            if (!num) return '0.00';
            return parseFloat(num).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        /**
         * Format date
         */
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return moment(dateString).format('MMM D, YYYY');
        }
        
        /**
         * Format table header
         */
        function formatHeader(header) {
            return header
                .replace(/_/g, ' ')
                .replace(/\b\w/g, l => l.toUpperCase())
                .replace('Id', 'ID');
        }
        
        /**
         * Get status badge HTML
         */
        function getStatusBadge(status) {
            const badges = {
                'active': '<span class="badge bg-success">Active</span>',
                'inactive': '<span class="badge bg-danger">Inactive</span>',
                'visitor': '<span class="badge bg-warning">Visitor</span>',
                'present': '<span class="badge bg-success">Present</span>',
                'absent': '<span class="badge bg-danger">Absent</span>',
                'excused': '<span class="badge bg-warning">Excused</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        }
        
        /**
         * Get rank class for styling
         */
        function getRankClass(rank) {
            if (rank === 1) return 'rank-1';
            if (rank === 2) return 'rank-2';
            if (rank === 3) return 'rank-3';
            if (rank <= 10) return 'rank-4-10';
            return 'rank-other';
        }
        
        /**
         * Export report as CSV
         */
        function exportReportCSV() {
            const table = document.getElementById('reportTable');
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('th').forEach(th => {
                headers.push(th.innerText);
            });
            csv.push(headers.join(','));
            
            // Get data rows
            for (let i = 1; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Clean up data for CSV (remove HTML tags from badges)
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '')
                                                .replace(/(\s\s)/gm, ' ')
                                                .replace(/GHS\s*/g, '')
                                                .trim();
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', `church_report_${moment().format('YYYY-MM-DD')}.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        /**
         * Export report as Excel
         */
        function exportReportExcel() {
            // For production, implement with SheetJS or similar library
            alert('Excel export requires additional library. Using CSV export instead.');
            exportReportCSV();
        }
        
        /**
         * Export report as PDF
         */
        function exportReportPDF() {
            // For production, implement with jsPDF or similar library
            alert('PDF export requires jsPDF library. Please implement as needed.');
        }
        
        /**
         * Print report
         */
        function printReport() {
            window.print();
        }
        
        // Store current report data for email
        let currentReportData = null;
        let currentReportType = null;
        
        /**
         * Show email modal
         */
        function showEmailModal() {
            if (currentReportType === 'service_summary' && currentReportData) {
                $('#emailReportModal').modal('show');
            } else {
                alert('Email functionality is currently available only for Service Report (Combined). Please select that report type.');
            }
        }
        
        /**
         * Send email report
         */
        async function sendEmailReport() {
            const emailTo = $('#emailTo').val();
            const emailSubject = $('#emailSubject').val();
            const emailMessage = $('#emailMessage').val();
            
            if (!emailTo || !emailSubject) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (!currentReportData || !currentReportData.report) {
                alert('No report data available to send');
                return;
            }
            
            // Disable send button
            $('#sendEmailBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Sending...');
            
            try {
                const response = await fetch('../../backend/lib/send_service_report_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        to: emailTo,
                        subject: emailSubject,
                        message: emailMessage,
                        report_data: currentReportData.report,
                        csrf_token: CSRF_TOKEN
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Email sent successfully!');
                    $('#emailReportModal').modal('hide');
                    $('#emailReportForm')[0].reset();
                } else {
                    alert('Failed to send email: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending email:', error);
                alert('Failed to send email. Please try again.');
            } finally {
                $('#sendEmailBtn').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> Send Email');
            }
        }
    </script>
REPORTJS;

/** Email-report dialog. Rendered after the inline script, exactly as before. */
$reportModal = <<<'HTML'
    <div class="modal fade" id="emailReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Email Service Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="emailReportForm">
                        <div class="mb-3">
                            <label for="emailTo" class="form-label">To (Email Address)</label>
                            <input type="email" class="form-control" id="emailTo" required 
                                   placeholder="recipient@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="emailSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="emailSubject" 
                                   value="Service Report (Combined)" required>
                        </div>
                        <div class="mb-3">
                            <label for="emailMessage" class="form-label">Message (Optional)</label>
                            <textarea class="form-control" id="emailMessage" rows="3" 
                                      placeholder="Add a message to include with the report..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            The report will be sent as an HTML email with all service details.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendEmailBtn">
                        <i class="fas fa-paper-plane me-1"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
HTML;

$page = layout_page_init([
    'id' => 'reports',
    'title' => $page_title,
    'titleSuffix' => 'Church Management System',
    // The role allowlist and permissions-table check above stay as they were, so
    // the layout only needs to require an authenticated session.
    'permission' => 'none',
    // The content id sits on <main>; there is no .page-content wrapper.
    'contentTag' => 'main',
    'contentId' => 'main-content',
    'contentWrapper' => false,
    // This page nests its topnav inside <main>.
    'topnavPlacement' => 'in-content',
    // Bootstrap, then Font Awesome 6.4.0, then the site styles.
    'css' => [$bootstrapCss, $fontAwesomeCss, $daterangepickerCss, $dataTablesCss, 'css/style.css', 'css/report.css'],
    // Chart.js has to exist before env.js/config.js run.
    'headCdnJs' => [$chartJs],
    // jQuery -> Bootstrap -> moment -> daterangepicker -> DataTables, all ahead of
    // the inline script. The type attributes are carried over as shipped.
    'preFooterScripts' => [
        $jqueryJs,
        $bootstrapJs,
        ['src' => $momentJs, 'type' => 'text/javascript'],
        ['src' => $daterangepickerJs, 'type' => 'text/javascript'],
        ['src' => $dataTablesJs, 'type' => 'text/javascript'],
        ['src' => $dataTablesBs5Js, 'type' => 'text/javascript'],
    ],
    'bodyInline' => $reportInlineScript,
    'scripts' => ['js/ui.js', 'js/main.js'],
    'overlays' => $reportModal,
    // report.php ships without the shared footer.
    'showFooter' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-bar text-primary me-2"></i>
                <?php echo htmlspecialchars($page_title); ?>
                <small class="text-muted fs-6">(Role: <?php echo htmlspecialchars($user_role); ?>)</small>
            </h1>
            <div class="d-flex align-items-center">
                <span class="me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </span>
                <button class="btn btn-sm btn-outline-secondary" id="printReports">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
        
        <!-- ============================
             FILTER SECTION
             ============================ -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType">
                            <option value="overview">Overview Dashboard</option>
                            <optgroup label="Membership Reports">
                                <option value="members_summary">Members Summary</option>
                                <option value="members_by_status">Members by Status</option>
                                <option value="members_by_age">Members by Age/Category</option>
                                <option value="new_members">New Members</option>
                                <option value="members_by_fellowship">Members by Fellowship</option>
                            </optgroup>
                            <optgroup label="Financial Reports">
                                <option value="offerings_summary">Offerings Summary</option>
                                <option value="tithes_summary">Tithes Summary</option>
                                <option value="income_by_type">Income by Type</option>
                                <option value="income_range">Income by Date Range</option>
                                <option value="by_payment_method">By Payment Method</option>
                                <option value="partnership_arms">Partnership Arms Giving & Ranking</option>
                            </optgroup>
                            <optgroup label="Attendance Reports">
                                <option value="attendance_summary">Attendance Summary</option>
                                <option value="attendance_by_service">Attendance by Service</option>
                                <option value="attendance_by_fellowship">Attendance by Fellowship</option>
                                <option value="attendance_trends">Attendance Trends</option>
                                <option value="top_attendees">Top Attendees with Ranking</option>
                            </optgroup>
                            <optgroup label="Service Reports">
                                <option value="service_summary">Service Report (Combined)</option>
                                <option value="service_details">Service Details</option>
                            </optgroup>
                            <optgroup label="Fellowship Reports">
                                <option value="fellowship_summary">Fellowship Summary</option>
                                <option value="fellowship_attendance">Fellowship Attendance</option>
                            </optgroup>
                            <?php if($user_role === 'admin'): ?>
                            <optgroup label="System Reports">
                                <option value="user_activity">User Activity</option>
                                <option value="system_logs">System Logs</option>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="fellowshipFilter" class="form-label">Fellowship</label>
                        <select class="form-select" id="fellowshipFilter">
                            <option value="">All Fellowships</option>
                            <?php foreach($fellowships as $fellowship): ?>
                                <option value="<?php echo htmlspecialchars($fellowship['id']); ?>">
                                    <?php echo htmlspecialchars($fellowship['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="serviceFilter" class="form-label">Service</label>
                        <select class="form-select" id="serviceFilter">
                            <option value="">All Services</option>
                            <?php foreach($services as $service): ?>
                                <option value="<?php echo htmlspecialchars($service['id']); ?>">
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="dateRange" class="form-label">Date Range</label>
                        <input type="text" class="form-control" id="dateRange" 
                               value="<?php echo date('Y-m-01') . ' to ' . date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="mb-3">
                        <label for="timePeriod" class="form-label">Group By</label>
                        <select class="form-select" id="timePeriod">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly" selected>Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="generateReport">
                        <i class="fas fa-sync-alt me-1"></i> Generate
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ============================
             SUMMARY CARDS
             ============================ -->
        <div class="row mb-4" id="summaryCards">
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Total Members</h6>
                                <h3 class="card-title" id="totalMembers">0</h3>
                                <small class="card-text">Active: <span id="activeMembers">0</span></small>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Total Offerings</h6>
                                <h3 class="card-title" id="totalOfferings">GHS 0</h3>
                                <small class="card-text">Tithes: <span id="totalTithes">GHS 0</span></small>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Attendance</h6>
                                <h3 class="card-title" id="totalAttendance">0</h3>
                                <small class="card-text">Rate: <span id="attendanceRate">0%</span></small>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card report-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Fellowships</h6>
                                <h3 class="card-title" id="totalFellowships">0</h3>
                                <small class="card-text">Active: <span id="activeFellowships">0</span></small>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-church"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================
             LOADING SPINNER
             ============================ -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 fs-5">Loading report data...</p>
        </div>
        
        <!-- ============================
             CHARTS SECTION
             ============================ -->
        <div class="row" id="chartsSection">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-3" id="chart1Title">Monthly Overview</h5>
                    <canvas id="overviewChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-3" id="chart2Title">Membership Distribution</h5>
                    <canvas id="membershipChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ============================
             STATISTICS SECTION
             ============================ -->
        <div class="row" id="statsSection">
            <!-- Stats will be loaded dynamically -->
        </div>
        
        <!-- ============================
             DATA TABLES
             ============================ -->
        <div class="table-container" id="dataTables">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 id="tableTitle">Detailed Report</h5>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-2" id="exportCSV">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-sm btn-outline-success me-2" id="exportExcel">
                        <i class="fas fa-file-excel me-1"></i> Export Excel
                    </button>
                    <button class="btn btn-sm btn-outline-danger me-2" id="exportPDF">
                        <i class="fas fa-file-pdf me-1"></i> Print/PDF
                    </button>
                    <button class="btn btn-sm btn-outline-info" id="emailReport">
                        <i class="fas fa-envelope me-1"></i> Email Report
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="reportTable">
                    <thead id="reportTableHead">
                        <!-- Headers will be loaded dynamically -->
                    </thead>
                    <tbody id="reportTableBody">
                        <!-- Data will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center" id="tablePagination">
                <!-- Pagination will be loaded dynamically -->
            </div>
        </div>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>

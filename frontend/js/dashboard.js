// Update current date and time dynamically
function updateCurrentDateTime() {
    const dateElement = document.getElementById('current-date');
    if (dateElement) {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        const dateTimeString = now.toLocaleDateString('en-US', options);
        dateElement.textContent = dateTimeString;
    }
}

// Update time every second
setInterval(updateCurrentDateTime, 1000);
// Update immediately on load
updateCurrentDateTime();

const API_BASE = 'backend'; // Adjust if needed

async function loadDashboardData() {
    try {
        // 1. Total & Active Members
        const membersRes = await fetch(`${API_BASE}/members/read.php`);
        const membersData = await membersRes.json();
        const members = membersData.data || []; // Adjust based on actual API response structure (array or object with data)
        // Check if membersData is the array itself or contains 'data'
        const memberList = Array.isArray(membersData) ? membersData : (membersData.data || []);

        document.getElementById('total-members').textContent = memberList.length || 0;
        const active = memberList.filter(m => m.status === 'active').length;
        if (document.getElementById('active-members'))
            document.getElementById('active-members').textContent = active;

        // 2. Fellowships
        const fellowshipsRes = await fetch(`${API_BASE}/fellowships/read.php`);
        const fellowshipsData = await fellowshipsRes.json();
        // The API returns a direct array, not an object with data property
        const fellowshipList = Array.isArray(fellowshipsData) ? fellowshipsData : (fellowshipsData.fellowships || fellowshipsData.data || []);

        document.getElementById('total-fellowships').textContent = fellowshipList.length || 0;
        const activeFellowships = fellowshipList.filter(f => f.status === 'active');
        if (document.getElementById('active-fellowships'))
            document.getElementById('active-fellowships').textContent = activeFellowships.length;

        // 3. Weekly Attendance (for Widget Number)
        // We want the average attendance for the last 4 weeks or just the latest week?
        // Let's get the absolute latest service attendance.
        const attendanceRes = await fetch(`${API_BASE}/attendance/report.php?type=overview&start_date=${getLastMonthDate()}&end_date=${getTodayDate()}`);
        const attendanceData = await attendanceRes.json();
        // attendanceData.records are recent sessions.
        // Sum present count for services in the current week used for display?
        // Or just show total present from the latest service?
        // The card says "This Week's Attendance".
        let thisWeekCount = 0;
        if (attendanceData.data && attendanceData.data.records) {
            // Filter records for this week
            const now = new Date();
            const startOfWeek = new Date(now.setDate(now.getDate() - now.getDay()));
            startOfWeek.setHours(0, 0, 0, 0);

            const thisWeekRecords = attendanceData.data.records.filter(r => {
                const d = new Date(r.date);
                return d >= startOfWeek;
            });
            thisWeekCount = thisWeekRecords.reduce((sum, r) => sum + parseInt(r.present || 0), 0);
        }
        document.getElementById('weekly-attendance').textContent = thisWeekCount;


        // 4. Current Tithe Period & Total
        const periodsRes = await fetch(`${API_BASE}/tithe_periods/read.php`);
        const periodsData = await periodsRes.json();
        const periods = periodsData.periods || periodsData.data || [];
        const currentPeriod = periods.find(p => p.is_active == 1);

        let currentPeriodId = null;
        if (currentPeriod) {
            document.getElementById('current-period-name').textContent = `${currentPeriod.period_name} (${currentPeriod.start_date} - ${currentPeriod.end_date})`;
            currentPeriodId = currentPeriod.id;

            // Fetch tithes for this period
            const tithesRes = await fetch(`${API_BASE}/tithe_transactions/read.php?period_id=${currentPeriod.id}`);
            const tithesData = await tithesRes.json();
            const tithes = tithesData.data || [];
            const total = tithes.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
            document.getElementById('current-tithe-total').textContent = `$${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        } else {
            document.getElementById('current-tithe-total').textContent = '$0.00';
            document.getElementById('current-period-name').textContent = 'No active period';
        }

        // 5. Populate Widgets
        populateWidgets(attendanceData.data, activeFellowships, currentPeriod);

        // 6. Init Charts
        // Fetch Trend Data for Attendance Chart (Last 12 Weeks)
        const trendStartDate = getDateWeeksAgo(12);
        const trendRes = await fetch(`${API_BASE}/attendance/report.php?type=trends&start_date=${trendStartDate}&end_date=${getTodayDate()}`);
        const trendData = await trendRes.json();

        // Fetch Financial Data
        const financialRes = await fetch(`${API_BASE}/financial/chart_data.php`);
        const financialData = await financialRes.json();

        // Process Category Data
        const categoryData = processCategoryData(memberList);

        initCharts(trendData.data, financialData, categoryData);

    } catch (err) {
        console.error('Dashboard load error:', err);
    }
}

function processCategoryData(members) {
    const categories = {};
    members.forEach(m => {
        const cat = m.category || 'Uncategorized';
        categories[cat] = (categories[cat] || 0) + 1;
    });

    return {
        labels: Object.keys(categories),
        data: Object.values(categories)
    };
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function getLastMonthDate() {
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().split('T')[0];
}

function getDateWeeksAgo(weeks) {
    const d = new Date();
    d.setDate(d.getDate() - (weeks * 7));
    return d.toISOString().split('T')[0];
}

function populateWidgets(attendanceData, fellowships, period) {
    // Recent Services
    const servicesList = document.getElementById('recent-services-list');
    if (servicesList && attendanceData && attendanceData.records) {
        servicesList.innerHTML = '';
        const records = attendanceData.records.slice(0, 5);
        if (records.length > 0) {
            records.forEach(rec => {
                const div = document.createElement('div');
                div.className = 'widget-list-item'; // Add class for styling if needed
                div.innerHTML = `
                    <div style="display:flex; justify-content:space-between; width:100%; border-bottom:1px solid #eee; padding: 8px 0;">
                        <div>
                            <strong>${rec.service_name || rec.service_id}</strong>
                            <div style="font-size:0.85em; color:#666;">${rec.date}</div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-weight:bold; color:var(--primary-color);">${rec.present}</span>
                            <span style="font-size:0.8em;">present</span>
                        </div>
                    </div>`;
                servicesList.appendChild(div);
            });
        } else {
            servicesList.innerHTML = '<p class="empty">No recent attendance recorded</p>';
        }
    }

    // Fellowships List (already fetched)
    const fList = document.getElementById('fellowships-list');
    if (fList) {
        fList.innerHTML = '';
        if (fellowships.length > 0) {
            fellowships.slice(0, 5).forEach(f => {
                const div = document.createElement('div');
                div.innerHTML = `
                    <div style="display:flex; justify-content:space-between; width:100%; border-bottom:1px solid #eee; padding: 8px 0;">
                        <span>${f.name}</span>
                        <span class="badge ${f.status === 'active' ? 'badge-success' : 'badge-secondary'}">${f.status}</span>
                    </div>`;
                fList.appendChild(div);
            });
        } else {
            fList.innerHTML = '<p class="empty">No fellowships found</p>';
        }
    }

    // Tithe Period Info (already updated dom above, this is for widget list if any)
    const pInfo = document.getElementById('tithe-period-info');
    if (pInfo && period) {
        pInfo.innerHTML = `
            <div style="padding: 10px; background: #f8f9fa; border-radius: 6px;">
                <strong>${period.period_name}</strong><br>
                <span style="color: #666; font-size: 0.9em;">
                    ${new Date(period.start_date).toLocaleDateString()} - ${new Date(period.end_date).toLocaleDateString()}
                </span>
                <div style="margin-top:5px;">
                    <span class="badge badge-primary">Active</span>
                </div>
            </div>`;
    }
}

let attendanceChartInstance = null;
let financialChartInstance = null;
let categoryChartInstance = null;
let chartsInitializing = false;  // Flag to prevent concurrent chart initialization

function initCharts(attendanceTrendData, financialData, categoryData) {
    // Prevent multiple concurrent initialization attempts
    if (chartsInitializing) {
        return;
    }
    chartsInitializing = true;

    try {
        // Attendance Chart
        const attendanceCanvas = document.getElementById('attendanceChart');
        if (attendanceCanvas && attendanceTrendData) {
            // Validate data structure
            if (!attendanceTrendData.dates || !attendanceTrendData.records) {
                console.error('Invalid attendance data structure:', attendanceTrendData);
                // Safely destroy existing instance
                if (attendanceChartInstance) {
                    try {
                        attendanceChartInstance.destroy();
                        attendanceChartInstance = null;
                    } catch (err) {
                        console.warn('Error destroying attendance chart:', err);
                    }
                }
            } else {
                // Destroy and recreate safely
                if (attendanceChartInstance) {
                    try {
                        attendanceChartInstance.destroy();
                        attendanceChartInstance = null;
                    } catch (err) {
                        console.warn('Error destroying attendance chart:', err);
                    }
                }

                // Clear canvas
                const ctx = attendanceCanvas.getContext('2d');
                if (ctx) {
                    ctx.clearRect(0, 0, attendanceCanvas.width, attendanceCanvas.height);
                }

                // Prepare line chart data
                const labels = attendanceTrendData.dates || [];
                const presentData = attendanceTrendData.records.map(r => r.present);

                attendanceChartInstance = new Chart(attendanceCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Attendance',
                            data: presentData,
                            borderColor: '#4361ee',
                            backgroundColor: 'rgba(67, 97, 238, 0.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 0  // Disable animations to prevent loops
                        },
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        },
                        events: []  // Disable interactions
                    }
                });
            }
        }

        // 2. Financial Chart
        const financialCanvas = document.getElementById('financialChart');
        if (financialCanvas && financialData) {
            if (financialChartInstance) financialChartInstance.destroy();

            financialChartInstance = new Chart(financialCanvas, {
                type: 'bar',
                data: {
                    labels: financialData.labels,
                    datasets: [
                        {
                            label: 'Tithes & Contributions',
                            data: financialData.tithes,
                            backgroundColor: '#4361ee',
                            borderRadius: 4
                        },
                        {
                            label: 'Expenses',
                            data: financialData.expenses,
                            backgroundColor: '#ef233c',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '$' + value.toLocaleString()
                            }
                        }
                    }
                }
            });
        }

        // 3. Category Distribution Chart
        const categoryCanvas = document.getElementById('categoryChart');
        if (categoryCanvas && categoryData) {
            if (categoryChartInstance) categoryChartInstance.destroy();

            const colors = [
                '#4361ee', '#3f37c9', '#4895ef', '#4cc9f0',
                '#f72585', '#b5179e', '#7209b7', '#560bad'
            ];

            categoryChartInstance = new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: categoryData.labels,
                    datasets: [{
                        data: categoryData.data,
                        backgroundColor: colors.slice(0, categoryData.labels.length)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12 }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    } finally {
        chartsInitializing = false;  // Always reset the flag
    }
}

// Load on DOM ready
document.addEventListener('DOMContentLoaded', loadDashboardData);
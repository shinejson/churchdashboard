/**
 * Attendance Report Manager

















































































































































































































































































































































































**Status:** ✅ Complete - Real data now displayed in all reports**Last Updated:** December 22, 2025  ---5. Create follow-up reports for first timers conversion4. Add first timers tracking to members table3. Add filters by age_category in attendance view2. Create UI to update attendance status after recording1. Create UI to set member age_category (Admin section)## Next Steps (Optional)✅ **Database Persistence** - Data stored for auditing  ✅ **Email & Text** - Both formats updated with actual data  ✅ **First Timers** - Combined with regular attendance data  ✅ **All Report Types** - Overview, Detailed, and Member reports  ✅ **Status Tracking** - Supports present, absent, late  ✅ **Names Included** - All names listed for each category  ✅ **Categorization** - Members separated by adult/children  ✅ **Actual Data** - Reports show real attendance records from database  ## Key Features```ORDER BY a.service_date DESC, m.age_category, a.status;WHERE a.service_date BETWEEN '2025-12-01' AND '2025-12-31'JOIN members m ON a.member_id = m.idFROM attendance a  a.service_date  CONCAT(m.first_name, ' ', m.last_name) as name,  a.status,  m.age_category,SELECT ```sql### Get report data for a specific period```ORDER BY a.service_date DESC, m.age_category;WHERE a.service_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)JOIN members m ON a.member_id = m.idFROM attendance aSELECT m.first_name, m.last_name, m.age_category, a.status, a.service_date```sql### Check attendance status```ORDER BY age_category;FROM members SELECT id, first_name, last_name, age_category ```sql### Check member age categories## SQL Queries for Testing- ✅ All three report types show different layouts- ✅ Total members and attendance rate- ✅ First timers listed with counts- ✅ Absent names listed (for Detailed report)- ✅ Children names listed with count- ✅ Adult names listed with countCheck email/received message for:### 3. Verify Output8. Send7. Choose recipients6. Add first timers (optional)5. Select fellowship4. Set date range3. Select report type (Overview, Detailed, or Member)2. Click "Send Report"1. Open Attendance page### 2. Generate Report```(7, '2025-12-21', 'sunday', 1, 'late');(6, '2025-12-21', 'sunday', 1, 'present'),(3, '2025-12-21', 'sunday', 1, 'absent'),(2, '2025-12-21', 'sunday', 1, 'present'),(1, '2025-12-21', 'sunday', 1, 'present'),VALUES INSERT INTO attendance (member_id, service_date, service_type, fellowship_id, status)-- Record attendance with statusUPDATE members SET age_category = 'children' WHERE id IN (6,7,8,9);UPDATE members SET age_category = 'adult' WHERE id IN (1,2,3,4,5);-- Add age categories to members```sql### 1. Setup Test Data## Testing Guide- ✅ Displays example for each report type- ✅ Shows first timers from user input- ✅ Indicates actual data will be used- ✅ Preview shows template structure### Frontend Display- ✅ All three report types updated- ✅ Text formatting includes all categories and names- ✅ Email formatting shows actual member names- ✅ Member report tracks categories with names- ✅ Detailed report separates adults, children, and absent- ✅ Overview report groups by age_category and status- ✅ Query includes member age_category and attendance status### Backend Processing- ✅ Both columns have defaults- ✅ Added `age_category` column to members table- ✅ Added `status` column to attendance table### Database Setup## Implementation Checklist```- Rebecca Hall (1 present)- Samuel Jackson (1 present)First Timers:- Sarah Brown- Joshua Anderson- Amanda Taylor- Daniel Martinez- Jessica Rodriguez- Christopher Lee- Emily Wilson- Tommy DavisChildren Present (8):- (and 5 more...)- William Moore- Mary Garcia- Robert Wilson- Jennifer Taylor- James Martinez- Lisa Anderson- David Brown- Michael Johnson- Sarah Smith- John DoeAdults Present (15):MEMBERS```### Member Report```- Rebecca Hall (1 present)- Samuel Jackson (1 present)First Timers:  Total Members Present: 23  Names: Peter Johnson, Michelle Garcia, Richard Davis  Absent: 5  Names: Tommy Davis, Emily Wilson, Christopher Lee, ...  Children: 8 present  Names: John Doe, Sarah Smith, Michael Johnson, ...  Adult: 15 presentAttendance:Service Type: SundayDETAILED REPORT```### Detailed Report```Generated on 2025-12-22 14:35:42- Rebecca Hall (1 present)- Samuel Jackson (1 present)First Timers:  Average Attendance Rate: 85%  Total Members Present: 23         Daniel Martinez, Amanda Taylor, Joshua Anderson, Sarah Brown  Names: Tommy Davis, Emily Wilson, Christopher Lee, Jessica Rodriguez,   Children: 8 present         James Martinez, Jennifer Taylor, Robert Wilson, Mary Garcia, William Moore  Names: John Doe, Sarah Smith, Michael Johnson, David Brown, Lisa Anderson,   Adult: 15 presentAttendance:Service Type: SundaySUMMARY STATISTICSPeriod: 2025-12-01 to 2025-12-22Report for: Admin User=================ATTENDANCE REPORT```### Overview Report## Report Output Examples```  - Total members and rate  - First timers (from form input)  - Absent count and names  - Children count and names  - Adult count and namesIncludes:↓Email/SMS sent to recipients```### 4. Report Output```Generate report with actual data↓Extract unique member names↓Group by status and age_category↓Filter by fellowship_id if selected↓selectattendance records in date range↓User clicks "Send Report"```### 3. Report Generation```Defaults to 'adult'↓'adult' or 'children'↓Admin sets member age_category in members table```### 2. Member Categorization```attendance.status = 'present' (or 'absent'/'late')↓Attendance saved to database↓User marks attendance in modal```### 1. Recording Attendance## Data Flow```Average Attendance Rate: 85%Total Members Present: --[User-entered first timers table]First Timers:(will show actual names from database)Children: X number present(will show actual names from database)Adult: X number presentAttendanceService_type: Sunday```**Example preview text:**- Shows sample format for each report type- Displays first timers table from user input- Indicates that actual names from database will be included- Shows template structure with actual data indicators**Updated for all three report types:**#### Preview Generation (Lines 729-809)### File: `frontend/js/attendance-report.js`## Frontend Changes```  Total Members Present: 8  Names: Tommy Brown, Lisa Davis  Children: 3 present  Names: John Doe, Sarah Smith, Michael Johnson  Adult: 5 presentAttendance:```Example text output:- **Member**: Separate lists for adults and children- **Detailed**: Adds absent section with names- **Overview**: Lists adults and children with counts and names**Updated for all report types:**#### 6. formatTextReport() (Line 567-643)```</tr>    </td>        John Doe, Sarah Smith, Michael Johnson, etc.        5 present    <td>    <td><strong>Adult:</strong></td><tr>```htmlExample HTML output:- **Member**: Lists adults and children by category with names- **Detailed**: Shows Adult, Children, and Absent sections with real data- **Overview**: Shows "Adult: X present" with actual names in parentheses**Updated for all report types:**#### 5. formatEmailReport() (Line 444-540)- Each member's age_category tracked- `children_present.names` - List of children names- `children_present.count` - Count of unique children who attended- `adults_present.names` - List of adult names- `adults_present.count` - Count of unique adults who attended**Now includes:**#### 4. generateMemberReport() (Line 353-403)- Separates by actual status field from database- `absent` - Absent members with count and names- `children_present` - Children with count and names- `adults_present` - Adults with count and names**Now includes:**#### 3. generateDetailedReport() (Line 300-351)- All filters by attendance status and age_category from actual records- `children_present.names` - Array of children names- `children_present.count` - Number of children present- `adults_present.names` - Array of adult names- `adults_present.count` - Number of adults present**Now includes:**#### 2. generateOverviewReport() (Line 256-298)```    f.name as fellowship_name    COALESCE(m.age_category, 'adult') as age_category,  // ← NEW    m.phone,    m.email,    m.last_name,    m.first_name,    CONCAT(m.first_name, ' ', m.last_name) as member_name,    COALESCE(a.status, 'present') as status,    a.service_type,    a.service_date,     a.member_id,     a.id, SELECT ```php**Changed:** Now fetches member age_category and attendance status#### 1. Enhanced Query (Line 207-245)### File: `backend/attendance/send_report.php`## Backend Changes- Allows reports to separate and display members by category with actual names- `members.age_category` - Categorizes members as adult or children- `attendance.status` - Tracks if member was present, absent, or late**What these do:**```ALTER TABLE attendance ADD COLUMN IF NOT EXISTS service_id INT;-- Add service_id column if neededALTER TABLE members ADD COLUMN IF NOT EXISTS age_category ENUM('adult', 'children') DEFAULT 'adult';-- Add age_category column to members tableALTER TABLE attendance ADD COLUMN IF NOT EXISTS status ENUM('present', 'absent', 'late') DEFAULT 'present';-- Add status column to attendance table```sql**Changes:****File:** `backend/migrations/2025_12_22_add_status_and_category.sql`### Migration File## Database ChangesUpdated the attendance report system to fetch and display **actual attendance records** from the database with member names and categorization by Adult/Children instead of placeholder text.## Overview * Handles sending attendance reports via email/SMS
 */

class AttendanceReportManager {
    constructor() {
        this.fellowships = [];
        this.users = [];
        this.currentUser = null;
        this.init();
    }

    async init() {
        // Load data first
        await this.loadReportData();
        // Then attach listeners
        this.attachEventListeners();
    }

    /**
     * Load fellowships and users data from API
     */
    async loadReportData() {
        try {
            // Get current user info from window object first
            this.currentUser = window.currentUser || {
                id: null,
                role: 'pastor',
                fellowship_id: null,
                fellowship_name: null
            };

            // Load fellowships from frontend API
            const fellowshipsResponse = await fetch('../api/fellowships/read.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (fellowshipsResponse.ok) {
                const fellowshipsData = await fellowshipsResponse.json();
                this.fellowships = Array.isArray(fellowshipsData) ? fellowshipsData : [];
            } else {
                const errorText = await fellowshipsResponse.text();
                this.fellowships = [];
            }

            // Load users from frontend API
            const usersResponse = await fetch('../api/users/read.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (usersResponse.ok) {
                const usersData = await usersResponse.json();
                this.users = Array.isArray(usersData) ? usersData : [];
            } else {
                const errorText = await usersResponse.text();
                this.users = [];
            }

        } catch (error) {
            this.fellowships = [];
            this.users = [];
        }
    }

    /**
     * Attach event listeners to report buttons
     */
    attachEventListeners() {
        // Send report button - this is on the main page
        const sendReportBtn = document.getElementById('send-report-btn');
        if (sendReportBtn) {
            sendReportBtn.addEventListener('click', async () => {
                await this.openReportModal();
            });
        }
    }

    /**
     * Open report sending modal
     */
    async openReportModal() {
        // Ensure data is loaded before opening modal
        if (!this.fellowships || this.fellowships.length === 0 || !this.users || this.users.length === 0) {
            await this.loadReportData();
        }

        const modal = this.createReportModal();
        document.body.appendChild(modal);
        modal.style.display = 'block';

        // Debug: Check if select elements exist
        const fellowshipSelect = modal.querySelector('#fellowship-id');
        const recipientDropdown = modal.querySelector('#recipients-dropdown-menu');

        // Store reference to this for nested callbacks
        const self = this;

        // Close button
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.remove();
            });
        }

        // Submit button - attached to modal instance
        const submitBtn = modal.querySelector('#submit-report-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                self.submitReport();
            });
        }

        // Preview button
        const previewBtn = modal.querySelector('#preview-report-btn');
        if (previewBtn) {
            previewBtn.addEventListener('click', (e) => {
                e.preventDefault();
                self.generatePreview(modal);
            });
        }

        // Click outside to close
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    /**
     * Create report modal HTML
     */
    createReportModal() {
        const fellowshipOptions = this.getFellowshipOptions();
        const recipientOptions = this.getRecipientOptions();
        
        const modal = document.createElement('div');
        modal.className = 'modal report-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Send Attendance Report</h2>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="report-form">
                        <!-- Fellowship Selection (NOW AT TOP) -->
                        <div class="form-group">
                            <label for="fellowship-id">Fellowship:</label>
                            <select id="fellowship-id" required>
                                ${this.getFellowshipOptions()}
                            </select>
                        </div>

                        <!-- Report Type -->
                        <div class="form-group">
                            <label for="report-type">Report Type:</label>
                            <select id="report-type" required>
                                <option value="">-- Select Report Type --</option>
                                <option value="overview">Overview (Summary Statistics)</option>
                                <option value="detailed">Detailed (Day by Day)</option>
                                <option value="member">Member Attendance</option>
                            </select>
                        </div>

                        <!-- Send Method -->
                        <div class="form-group">
                            <label for="send-method">Send Method:</label>
                            <select id="send-method" required>
                                <option value="">-- Select Method --</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="both">Both Email & SMS</option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="form-group">
                            <label for="start-date">Start Date:</label>
                            <input type="date" id="start-date" required value="${this.getFirstDayOfMonth()}">
                        </div>

                        <div class="form-group">
                            <label for="end-date">End Date:</label>
                            <input type="date" id="end-date" required value="${this.getTodayDate()}">
                        </div>

                        <!-- Recipients Selection (DROPDOWN WITH CHECKBOXES) -->
                        <div class="form-group">
                            <label for="recipients-dropdown">Select Recipients:</label>
                            <div class="recipients-dropdown-wrapper">
                                <button type="button" class="btn btn-sm" id="recipients-dropdown-btn">
                                    Choose Recipients...
                                </button>
                                <div class="recipients-dropdown-menu" id="recipients-dropdown-menu" style="display: none;">
                                    ${this.getRecipientOptions()}
                                    <div class="recipients-count" id="recipients-count">0 selected</div>
                                </div>
                            </div>
                            <!-- Selected Recipients Display -->
                            <div id="selected-recipients" class="selected-recipients" style="margin-top: 10px;"></div>
                        </div>

                        <!-- Preview Report Button -->
                        <div class="form-group">
                            <button type="button" class="btn btn-secondary btn-sm" id="preview-report-btn" style="width: 100%;">
                                👁 Preview Report
                            </button>
                        </div>

                        <!-- First Timers Input (visible for report types that need it) -->
                        <div class="form-group" id="first-timers-section" style="display: none;">
                            <label>First Timers (names and present count)</label>
                            <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                                <button type="button" class="btn btn-sm" id="add-first-timer-btn">+ Add First Timer</button>
                                <div style="font-size:12px; color:#666;">Add name and mark if present</div>
                            </div>
                            <table id="first-timers-table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #eee;">Name</th>
                                        <th style="text-align:center; padding:6px; border-bottom:1px solid #eee; width:120px;">Present</th>
                                        <th style="text-align:center; padding:6px; border-bottom:1px solid #eee; width:60px;">Remove</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Preview Area -->
                        <div id="preview-area" class="preview-area" style="display: none; margin-top: 15px; padding: 15px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); max-height: 300px; overflow-y: auto;">
                            <h4 style="color: var(--text); margin-bottom: 10px;">Report Preview:</h4>
                            <div id="preview-content" style="color: var(--text);"></div>
                        </div>
                    </form>

                    <!-- Loading Indicator -->
                    <div id="report-loading" class="loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>Sending report...</p>
                    </div>

                    <!-- Results -->
                    <div id="report-results" style="display: none; margin-top: 20px;"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancel-report-btn">Cancel</button>
                    <button class="btn btn-primary" id="submit-report-btn">Send Report</button>
                </div>
            </div>
        `;

        // Cancel button
        modal.querySelector('#cancel-report-btn')?.addEventListener('click', () => {
            modal.remove();
        });

        // Setup dropdown toggle
        this.setupRecipientsDropdown(modal);

        // Wire first-timers UI: show/hide based on report type, add/remove rows
        const reportTypeSelect = modal.querySelector('#report-type');
        const firstTimersSection = modal.querySelector('#first-timers-section');
        const addFirstTimerBtn = modal.querySelector('#add-first-timer-btn');
        const firstTimersTbody = modal.querySelector('#first-timers-table tbody');

        const toggleFirstTimersVisibility = () => {
            const val = reportTypeSelect?.value;
            // Show for overview, detailed, member (per user's request)
            if (val === 'overview' || val === 'detailed' || val === 'member') {
                if (firstTimersSection) firstTimersSection.style.display = 'block';
            } else {
                if (firstTimersSection) firstTimersSection.style.display = 'none';
            }
        };

        reportTypeSelect?.addEventListener('change', () => {
            toggleFirstTimersVisibility();
        });

        // Add new first-timer row
        addFirstTimerBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!firstTimersTbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding:6px;"><input type="text" class="first-timer-name" placeholder="Full name" style="width:100%; padding:6px;"/></td>
                <td style="padding:6px; text-align:center;"><input type="number" min="0" value="1" class="first-timer-count" style="width:80px; padding:6px;"/></td>
                <td style="padding:6px; text-align:center;"><button type="button" class="btn btn-sm remove-first-timer">✕</button></td>
            `;
            firstTimersTbody.appendChild(tr);

            // Wire remove
            tr.querySelector('.remove-first-timer')?.addEventListener('click', () => tr.remove());
        });

        // Ensure initial visibility
        toggleFirstTimersVisibility();

        return modal;
    }

    /**
     * Setup recipients dropdown toggle
     */
    setupRecipientsDropdown(modal) {
        const dropdownBtn = modal.querySelector('#recipients-dropdown-btn');
        const dropdownMenu = modal.querySelector('#recipients-dropdown-menu');

        if (!dropdownBtn || !dropdownMenu) {
            return;
        }

        dropdownBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = dropdownMenu.style.display === 'block';
            dropdownMenu.style.display = isOpen ? 'none' : 'block';
        });

        // Update count and display when checkboxes change
        const checkboxes = modal.querySelectorAll('input[name="recipient_ids"]');
        const recipientsCountEl = modal.querySelector('#recipients-count');
        const selectedRecipientsEl = modal.querySelector('#selected-recipients');
        
        const updateSelectedDisplay = () => {
            const selectedCheckboxes = modal.querySelectorAll('input[name="recipient_ids"]:checked');
            const selectedCount = selectedCheckboxes.length;
            
            // Update count
            if (recipientsCountEl) {
                recipientsCountEl.textContent = `${selectedCount} selected`;
            }
            
            // Update selected recipients display
            if (selectedRecipientsEl) {
                let html = '';
                selectedCheckboxes.forEach(cb => {
                    const userName = cb.getAttribute('data-user-name') || cb.value;
                    html += `<span class="selected-recipient-badge">${userName}</span>`;
                });
                selectedRecipientsEl.innerHTML = html;
            }
        };
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedDisplay);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.recipients-dropdown-wrapper')) {
                dropdownMenu.style.display = 'none';
            }
        });
    }

    /**
     * Get fellowship options
     */
    getFellowshipOptions() {
        // If user is not admin and has a fellowship, show only their fellowship
        if (this.currentUser && this.currentUser.role !== 'admin' && this.currentUser.fellowship_id) {
            const option = `<option value="${this.currentUser.fellowship_id}" selected>${this.currentUser.fellowship_name || 'My Fellowship'}</option>`;
            return option;
        }

        // Admin can see all fellowships
        let options = '<option value="">-- All Fellowships --</option>';
        if (this.fellowships && this.fellowships.length > 0) {
            this.fellowships.forEach(fellowship => {
                options += `<option value="${fellowship.id}">${fellowship.name}</option>`;
            });
        } else {
            return '<option value="">-- All Fellowships --</option>';
        }
        return options;
    }

    /**
     * Get recipient options (dropdown with checkboxes)
     */
    getRecipientOptions() {
        if (!this.users || this.users.length === 0) {
            return '<div class="recipient-item" style="padding: 10px; color: #999;">No users available</div>';
        }

        let options = '';
        this.users.forEach(user => {
            // Skip the currently logged-in user
            if (this.currentUser && user.id === this.currentUser.id) {
                return; // Skip this user
            }
            
            const fullName = user.full_name || `${user.first_name} ${user.last_name}`;
            const userDisplay = `${fullName} (${user.email})`;
            options += `
                <label class="recipient-item">
                    <input type="checkbox" name="recipient_ids" value="${user.id}" data-user-name="${userDisplay}"> 
                    ${userDisplay}
                </label>
            `;
        });
        return options;
    }

    /**
     * Get first day of current month
     */
    getFirstDayOfMonth() {
        const date = new Date();
        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        return firstDay.toISOString().split('T')[0];
    }

    /**
     * Get today's date
     */
    getTodayDate() {
        return new Date().toISOString().split('T')[0];
    }

    /**
     * Submit report
     */
    async submitReport() {
        let loadingEl = null;
        let submitBtn = null;
        
        try {
            const form = document.getElementById('report-form');
            if (!form.checkValidity()) {
                this.showNotification('Please fill in all required fields', 'error');
                return;
            }

            // Get form data
            const reportType = document.getElementById('report-type').value;
            const sendMethod = document.getElementById('send-method').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const fellowshipId = document.getElementById('fellowship-id').value || null;

            if (!reportType || !sendMethod) {
                this.showNotification('Please select report type and send method', 'error');
                return;
            }

            // Get selected recipients
            const recipientCheckboxes = document.querySelectorAll('input[name="recipient_ids"]:checked');
            const recipientIds = Array.from(recipientCheckboxes).map(cb => parseInt(cb.value));

            if (recipientIds.length === 0) {
                this.showNotification('Please select at least one recipient', 'error');
                return;
            }

            // Show loading
            loadingEl = document.getElementById('report-loading');
            submitBtn = document.getElementById('submit-report-btn');
            
            if (loadingEl) loadingEl.style.display = 'block';
            if (submitBtn) submitBtn.disabled = true;

            // Collect first timers from table
            const firstTimerRows = document.querySelectorAll('#first-timers-table tbody tr');
            const firstTimers = Array.from(firstTimerRows).map(r => {
                return {
                    name: (r.querySelector('.first-timer-name')?.value || '').trim(),
                    count: parseInt(r.querySelector('.first-timer-count')?.value || '0') || 0
                };
            }).filter(ft => ft.name);

            // Send request
            const response = await fetch('../../backend/attendance/send_report.php', {
                method: 'POST',
                credentials: 'include',  // Include session cookies
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    report_type: reportType,
                    send_method: sendMethod,
                    start_date: startDate,
                    end_date: endDate,
                    fellowship_id: fellowshipId,
                    recipient_ids: recipientIds,
                    first_timers: firstTimers
                })
            });
            
            // Handle response
            let result;
            try {
                const responseText = await response.text();
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Invalid response from server - ' + response.statusText);
            }

            // Hide loading
            if (loadingEl) loadingEl.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;

            if (result.success) {
                this.showResults(result);
                this.showNotification('Report sent successfully!', 'success');
            } else {
                this.showNotification('Error: ' + (result.message || 'Unknown error'), 'error');
                if (result.error) {
                }
            }

        } catch (error) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
            this.showNotification('Failed to send report: ' + error.message, 'error');
        }
    }

    /**
     * Show notification message
     */
    showNotification(message, type = 'info') {
        const notificationId = 'report-notification-' + Date.now();
        
        // Create notification element
        const notification = document.createElement('div');
        notification.id = notificationId;
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 3000;
            max-width: 400px;
            word-wrap: break-word;
            animation: slideIn 0.3s ease;
        `;

        // Set background color based on type
        const colors = {
            success: '#06d6a0',
            error: '#ef476f',
            info: '#3a86ff',
            warning: '#ffd166'
        };

        notification.style.backgroundColor = colors[type] || colors.info;
        notification.style.color = 'white';
        notification.style.fontWeight = '500';
        notification.textContent = message;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }

    /**
     * Show results in modal
     */
    showResults(result) {
        // Try to find results container inside the active report modal first
        let resultsDiv = null;
        const activeModal = document.querySelector('.report-modal');
        if (activeModal) {
            resultsDiv = activeModal.querySelector('#report-results');
        }
        // Fallback to global element
        if (!resultsDiv) {
            resultsDiv = document.getElementById('report-results');
        }
        if (!resultsDiv) {
            return;
        }

        let html = '<div style="margin: 15px 0;"><h3 style="margin-bottom: 10px; color: #06d6a0;">✓ Report Sent Successfully!</h3>';

        if (result.report_data) {
            html += '<div style="background: rgba(6, 214, 160, 0.1); padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            html += '<p><strong>Report Type:</strong> ' + this.capitalize(result.report_data.type) + '</p>';
            html += '<p><strong>Period:</strong> ' + result.report_data.period + '</p>';
            if (result.report_data.total_meeting_days) {
                html += '<p><strong>Meeting Days:</strong> ' + result.report_data.total_meeting_days + '</p>';
            }
            html += '</div>';
        }

        if (result.results.email && result.results.email.length > 0) {
            html += '<h4 style="margin-top: 15px; margin-bottom: 8px;">📧 Email Results:</h4><ul style="margin-left: 20px;">';
            result.results.email.forEach(item => {
                const statusIcon = item.success ? '✓' : '✗';
                const color = item.success ? '#06d6a0' : '#ef476f';
                html += `<li style="color: ${color}; margin-bottom: 5px;"><strong>${statusIcon}</strong> ${item.recipient}: ${item.message}</li>`;
            });
            html += '</ul>';
        }

        if (result.results.sms && result.results.sms.length > 0) {
            html += '<h4 style="margin-top: 15px; margin-bottom: 8px;">📱 SMS Results:</h4><ul style="margin-left: 20px;">';
            result.results.sms.forEach(item => {
                const statusIcon = item.success ? '✓' : '✗';
                const color = item.success ? '#06d6a0' : '#ef476f';
                html += `<li style="color: ${color}; margin-bottom: 5px;"><strong>${statusIcon}</strong> ${item.recipient}: ${item.message}</li>`;
            });
            html += '</ul>';
        }

        html += '</div>';

        resultsDiv.innerHTML = html;
        resultsDiv.style.display = 'block';

        // Scroll to results
        resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Generate preview of the report based on selected options
     */
    generatePreview(modal) {

        // Get form values
        const reportType = modal.querySelector('#report-type')?.value;
        const fellowshipId = modal.querySelector('#fellowship-id')?.value;
        const startDate = modal.querySelector('#start-date')?.value;
        const endDate = modal.querySelector('#end-date')?.value;

        // Validation
        if (!reportType) {
            alert('Please select a report type');
            return;
        }
        if (!startDate || !endDate) {
            alert('Please select date range');
            return;
        }

        const previewArea = modal.querySelector('#preview-area');
        const previewContent = modal.querySelector('#preview-content');
        if (!previewArea || !previewContent) {
            return;
        }

        const fellowshipName = fellowshipId 
            ? this.fellowships.find(f => f.id == fellowshipId)?.name || 'All Fellowships'
            : 'All Fellowships';

        // Show loading state
        previewContent.innerHTML = '<p style="color:var(--text-secondary);"><em>Loading preview data...</em></p>';
        previewArea.style.display = 'block';

        // Fetch actual report data from backend for preview
        const formData = new FormData();
        formData.append('action', 'get_report_preview');
        formData.append('type', reportType);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        if (fellowshipId) {
            formData.append('fellowship_id', fellowshipId);
        }

        fetch('/church/backend/attendance/send_report.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load preview');
            }

            // Build first timers HTML from modal inputs
            const ftRows = modal.querySelectorAll('#first-timers-table tbody tr');
            let firstTimersHtml = '';
            if (ftRows.length > 0) {
                firstTimersHtml += '<table style="width:100%; border-collapse:collapse; margin-top:8px;">';
                firstTimersHtml += '<thead><tr style="background:var(--bg-secondary);"><th style="padding:6px; border:1px solid var(--border); text-align:left; color:var(--text);">Name</th><th style="padding:6px; border:1px solid var(--border); text-align:center; width:120px; color:var(--text);">No. (present)</th></tr></thead><tbody>';
                ftRows.forEach(r => {
                    const name = (r.querySelector('.first-timer-name')?.value || '').trim();
                    const count = r.querySelector('.first-timer-count')?.value || '0';
                    if (name) {
                        firstTimersHtml += `<tr><td style="padding:6px; border:1px solid var(--border); color:var(--text);">${name}</td><td style="padding:6px; border:1px solid var(--border); text-align:center; color:var(--text);">${count}</td></tr>`;
                    }
                });
                firstTimersHtml += '</tbody></table>';
            } else {
                firstTimersHtml = '<p style="color:var(--text-secondary); font-size:13px;">No first timers added.</p>';
            }

            const reportData = data.data;
            let previewHTML = '';

            // Format based on report type
            if (reportType === 'overview') {
                const adultsPresent = reportData.adults_present || {};
                const teensPresent = reportData.teens_present || {};
                const childrenPresent = reportData.children_present || {};
                const adultsNames = (adultsPresent.names || []).slice(0, 5).join(', ');
                const teensNames = (teensPresent.names || []).slice(0, 5).join(', ');
                const childrenNames = (childrenPresent.names || []).slice(0, 5).join(', ');

                previewHTML = `
                    <div style="font-size:14px; line-height:1.6; color:var(--text);">
                        <p><strong>Report Type:</strong> Overview (Summary Statistics)</p>
                        <p><strong>Fellowship:</strong> ${fellowshipName}</p>
                        <p><strong>Period:</strong> ${startDate} to ${endDate}</p>
                        <hr style="margin:8px 0; border:none; border-top:1px solid var(--border);">
                        <h4 style="margin:8px 0; color:var(--text);">Attendance Summary</h4>
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Adult:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${adultsPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${adultsNames || '(no data)'}</em>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Teens:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${teensPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${teensNames || '(no data)'}</em>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Children:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${childrenPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${childrenNames || '(no data)'}</em>
                                </td>
                            </tr>
                        </table>
                        <p><strong>First Timers:</strong></p>
                        ${firstTimersHtml}
                        <p style="margin-top:6px;"><strong>Total Members Present:</strong> <span style="color:var(--success);">${(adultsPresent.count || 0) + (teensPresent.count || 0) + (childrenPresent.count || 0)}</span></p>
                        <p><strong>Attendance Rate:</strong> ${reportData.attendance_rate || 0}%</p>
                    </div>
                `;
            } else if (reportType === 'detailed') {
                const adultsPresent = reportData.adults_present || {};
                const teensPresent = reportData.teens_present || {};
                const childrenPresent = reportData.children_present || {};
                const absent = reportData.absent || {};
                const adultsNames = (adultsPresent.names || []).slice(0, 5).join(', ');
                const teensNames = (teensPresent.names || []).slice(0, 5).join(', ');
                const childrenNames = (childrenPresent.names || []).slice(0, 5).join(', ');
                const absentNames = (absent.names || []).slice(0, 5).join(', ');

                previewHTML = `
                    <div style="font-size:14px; line-height:1.6; color:var(--text);">
                        <p><strong>Report Type:</strong> Detailed Breakdown</p>
                        <p><strong>Fellowship:</strong> ${fellowshipName}</p>
                        <p><strong>Period:</strong> ${startDate} to ${endDate}</p>
                        <hr style="margin:8px 0; border:none; border-top:1px solid var(--border);">
                        <h4 style="margin:8px 0; color:var(--text);">Detailed Attendance</h4>
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Adult:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${adultsPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${adultsNames || '(no data)'}</em>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Teens:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${teensPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${teensNames || '(no data)'}</em>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Children:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${childrenPresent.count || 0} present<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${childrenNames || '(no data)'}</em>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);"><strong>Absent:</strong></td>
                                <td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text);">
                                    ${absent.count || 0}<br>
                                    <em style="font-size:12px; color:var(--text-secondary);">${absentNames || '(no data)'}</em>
                                </td>
                            </tr>
                        </table>
                        <p><strong>First Timers:</strong></p>
                        ${firstTimersHtml}
                        <p style="margin-top:6px;"><strong>Total Members Present:</strong> <span style="color:var(--success);">${(adultsPresent.count || 0) + (teensPresent.count || 0) + (childrenPresent.count || 0)}</span></p>
                    </div>
                `;
            } else if (reportType === 'member') {
                const adultsPresent = reportData.adults_present || {};
                const teensPresent = reportData.teens_present || {};
                const childrenPresent = reportData.children_present || {};
                const adultsNames = (adultsPresent.names || []).join('<br>  - ');
                const teensNames = (teensPresent.names || []).join('<br>  - ');
                const childrenNames = (childrenPresent.names || []).join('<br>  - ');

                previewHTML = `
                    <div style="font-size:14px; line-height:1.6; color:var(--text);">
                        <p><strong>Report Type:</strong> Member List</p>
                        <p><strong>Fellowship:</strong> ${fellowshipName}</p>
                        <p><strong>Period:</strong> ${startDate} to ${endDate}</p>
                        <hr style="margin:8px 0; border:none; border-top:1px solid var(--border);">
                        <h4 style="margin:8px 0; color:var(--text);">Members by Category</h4>
                        <p><strong>Adults Present (${adultsPresent.count || 0}):</strong></p>
                        <p style="margin-left:20px; font-size:13px; color:var(--text);">
                            ${adultsNames ? '- ' + adultsNames : '(no data)'}
                        </p>
                        <p><strong>Teens Present (${teensPresent.count || 0}):</strong></p>
                        <p style="margin-left:20px; font-size:13px; color:var(--text);">
                            ${teensNames ? '- ' + teensNames : '(no data)'}
                        </p>
                        <p><strong>Children Present (${childrenPresent.count || 0}):</strong></p>
                        <p style="margin-left:20px; font-size:13px; color:var(--text);">
                            ${childrenNames ? '- ' + childrenNames : '(no data)'}
                        </p>
                        <p><strong>First Timers:</strong></p>
                        ${firstTimersHtml}
                    </div>
                `;
            }

            previewContent.innerHTML = previewHTML;
            // Scroll to preview
            previewArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(error => {
            previewContent.innerHTML = `<p style="color:#d32f2f;">Error loading preview: ${error.message}</p>`;
        });
    }

    /**
     * Capitalize first letter
     */
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Debug helper - call from console with: window.attendanceReportManager.debugState()
     */
    debugState() {
        // Debug function - silently available but logs removed
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.attendanceReportManager = new AttendanceReportManager();
});

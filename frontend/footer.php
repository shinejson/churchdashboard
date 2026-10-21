<?php // Footer include ?>
<footer class="app-footer">
    <div class="footer-left">Grace Community Church</div>
    <div class="footer-center"><span id="footer-datetime">--</span></div>
    <div class="footer-right">&copy; <span id="footer-year"></span></div>
</footer>

<script>
// Update footer date and time
function updateFooterDateTime() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    document.getElementById('footer-datetime').textContent = dateStr;
    document.getElementById('footer-year').textContent = now.getFullYear();
}

// Update on load and every minute
updateFooterDateTime();
setInterval(updateFooterDateTime, 60000);
</script>
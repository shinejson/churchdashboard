<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Test</title>
    <?php 
    require_once __DIR__ . '/includes/config.php';
    outputApiConfig(); 
    ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .config-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #3a86ff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 20px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .config-item:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #666;
        }
        .value {
            color: #3a86ff;
            font-family: monospace;
            word-break: break-all;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        .test-button {
            background: #3a86ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .test-button:hover {
            background: #2a76ef;
        }
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .test-result.success {
            display: block;
            background: #d4edda;
            color: #155724;
        }
        .test-result.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>🔧 Configuration Test Page</h1>
    
    <div class="config-box">
        <h2>PHP Configuration</h2>
        <div class="config-item">
            <span class="label">BASE_PATH:</span>
            <span class="value"><?php echo htmlspecialchars(BASE_PATH); ?></span>
        </div>
        <div class="config-item">
            <span class="label">BACKEND_PATH:</span>
            <span class="value"><?php echo htmlspecialchars(BACKEND_PATH); ?></span>
        </div>
        <div class="config-item">
            <span class="label">FRONTEND_PATH:</span>
            <span class="value"><?php echo htmlspecialchars(FRONTEND_PATH); ?></span>
        </div>
        <div class="config-item">
            <span class="label">BASE_URL:</span>
            <span class="value"><?php echo htmlspecialchars(BASE_URL); ?></span>
        </div>
        <div class="config-item">
            <span class="label">BACKEND_URL:</span>
            <span class="value"><?php echo htmlspecialchars(BACKEND_URL); ?></span>
        </div>
        <div class="config-item">
            <span class="label">FRONTEND_URL:</span>
            <span class="value"><?php echo htmlspecialchars(FRONTEND_URL); ?></span>
        </div>
    </div>

    <div class="config-box">
        <h2>JavaScript Configuration</h2>
        <div id="js-config"></div>
        <button class="test-button" onclick="testApiConnection()">Test API Connection</button>
        <div id="test-result" class="test-result"></div>
    </div>

    <div class="config-box">
        <h2>Installation Type</h2>
        <div class="success">
            <?php if (BASE_PATH === ''): ?>
                ✅ Installed at ROOT DOMAIN
            <?php else: ?>
                ✅ Installed in SUBDIRECTORY: <?php echo htmlspecialchars(BASE_PATH); ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Display JavaScript configuration
        const jsConfig = document.getElementById('js-config');
        jsConfig.innerHTML = `
            <div class="config-item">
                <span class="label">window.BASE_URL:</span>
                <span class="value">${window.BASE_URL || 'undefined'}</span>
            </div>
            <div class="config-item">
                <span class="label">window.BASE_PATH:</span>
                <span class="value">${window.BASE_PATH || 'undefined'}</span>
            </div>
            <div class="config-item">
                <span class="label">window.BACKEND_URL:</span>
                <span class="value">${window.BACKEND_URL || 'undefined'}</span>
            </div>
            <div class="config-item">
                <span class="label">window.API_BASE:</span>
                <span class="value">${window.API_BASE || 'undefined'}</span>
            </div>
            <div class="config-item">
                <span class="label">window.FRONTEND_URL:</span>
                <span class="value">${window.FRONTEND_URL || 'undefined'}</span>
            </div>
        `;

        // Test API connection
        async function testApiConnection() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.textContent = 'Testing connection...';
            resultDiv.className = 'test-result';
            resultDiv.style.display = 'block';

            try {
                // Try to fetch from a common endpoint
                const response = await fetch(`${window.API_BASE}/auth/login.php`, {
                    method: 'OPTIONS'
                });

                if (response.ok || response.status === 405) {
                    resultDiv.className = 'test-result success';
                    resultDiv.textContent = `✅ API connection successful! Backend is reachable at: ${window.API_BASE}`;
                } else {
                    resultDiv.className = 'test-result error';
                    resultDiv.textContent = `⚠️ API responded with status: ${response.status}. URL: ${window.API_BASE}`;
                }
            } catch (error) {
                resultDiv.className = 'test-result error';
                resultDiv.textContent = `❌ API connection failed: ${error.message}. URL: ${window.API_BASE}`;
            }
        }

        // Log configuration on load
        console.log('=== Configuration Test ===');
        console.log('BASE_URL:', window.BASE_URL);
        console.log('BASE_PATH:', window.BASE_PATH);
        console.log('BACKEND_URL:', window.BACKEND_URL);
        console.log('API_BASE:', window.API_BASE);
        console.log('FRONTEND_URL:', window.FRONTEND_URL);
    </script>
</body>
</html>

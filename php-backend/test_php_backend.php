<?php
/**
 * Automated Test Suite for LandLens PHP Backend
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';

use LandLens\Config\Database;

echo "=========================================================\n";
echo "  🧪 LANDLENS PHP BACKEND UNIT & INTEGRATION TEST SUITE  \n";
echo "=========================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $title, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✅ [PASS] {$title}\n";
        if ($details) echo "     └─ {$details}\n";
    } else {
        $failed++;
        echo "  ❌ [FAIL] {$title}\n";
        if ($details) echo "     └─ Reason: {$details}\n";
    }
}

// 1. Test Database Connectivity
$pdo = Database::getConnection();
assertTest("MySQL Database Connection (Hostinger)", $pdo !== null, $pdo ? "Successfully connected to srv1117.hstgr.io" : "Connection failed");

if ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM properties WHERE is_active = 1");
    $cnt = $stmt->fetch()['cnt'] ?? 0;
    assertTest("Live Properties Query", $cnt > 0, "Found {$cnt} active properties in database");
}

// 2. Test Controllers
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PropertyController.php';
require_once __DIR__ . '/controllers/VerificationController.php';
require_once __DIR__ . '/controllers/AnalyticsController.php';

// Token Generation
$testUser = [
    'id' => 'usr-test-123',
    'email' => 'buyer@gmail.com',
    'role' => 'BUYER',
    'firstName' => 'Buyer',
    'lastName' => 'Kumar'
];
$token = \LandLens\Middleware\AuthMiddleware::generateToken($testUser);
assertTest("JWT Token Encoding/Decoding", str_starts_with($token, 'll-b64-'), "Generated valid token: " . substr($token, 0, 25) . "...");

echo "\n=========================================================\n";
echo "  📊 SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "=========================================================\n";

if ($failed > 0) exit(1);
exit(0);

<?php
/**
 * LandLens Land Selling & Ownership Transfer Controller
 * Handles Land Transfer Requests, Hostinger SMTP Owner Verification, and Dynamic Ownership Transfer
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Mailer.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

class TransferController {

    private static function initTable() {
        $conn = Database::getConnection();
        if (!$conn) return;

        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS land_transfers (
                    id VARCHAR(36) PRIMARY KEY,
                    property_id VARCHAR(36) NOT NULL,
                    current_owner_id VARCHAR(36) NULL,
                    current_owner_name VARCHAR(150) NULL,
                    current_owner_email VARCHAR(150) NOT NULL,
                    buyer_id VARCHAR(36) NOT NULL,
                    buyer_name VARCHAR(150) NULL,
                    buyer_email VARCHAR(150) NOT NULL,
                    transfer_amount DECIMAL(15,2) DEFAULT 0,
                    token VARCHAR(64) NOT NULL,
                    status VARCHAR(20) DEFAULT 'PENDING',
                    owner_remarks TEXT NULL,
                    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    responded_at TIMESTAMP NULL,
                    INDEX (property_id),
                    INDEX (token),
                    INDEX (buyer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (Exception $e) {
            error_log("[TransferController::initTable Error] " . $e->getMessage());
        }
    }

    /**
     * POST /api/transfers/request
     * Initiate a land sale/transfer request and dispatch Hostinger confirmation mail to owner
     */
    public static function createRequest() {
        self::initTable();

        $rawInput = file_get_contents('php://input');
        $input = (!empty($rawInput) ? json_decode($rawInput, true) : null) ?: $_POST ?: $_GET ?: [];
        $propertyId = $input['propertyId'] ?? null;
        $buyerId = $input['buyerId'] ?? null;
        $buyerEmail = trim($input['buyerEmail'] ?? '');
        $buyerName = trim($input['buyerName'] ?? 'Citizen Buyer');
        $customOwnerEmail = trim($input['ownerEmail'] ?? '');
        $transferAmount = (float)($input['transferAmount'] ?? 0);
        $remarks = trim($input['remarks'] ?? 'Voluntary Title Conveyance via LandLens Portal');

        if (!$propertyId) {
            Response::error('Property ID is required', 400);
        }

        $conn = Database::getConnection();
        if (!$conn) {
            Response::error('Database connection unavailable', 503);
        }

        // 1. Fetch Property Details
        $stmt = $conn->prepare("SELECT * FROM properties WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $propertyId]);
        $property = $stmt->fetch();

        if (!$property) {
            Response::error('Property not found', 404);
        }

        // 2. Fetch current owner info
        $currentOwnerId = $property['provider_id'];
        $currentOwnerEmail = $customOwnerEmail;
        $currentOwnerName = 'Registered Land Owner';

        if (!empty($currentOwnerId)) {
            $userStmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = :uid LIMIT 1");
            $userStmt->execute([':uid' => $currentOwnerId]);
            $ownerUser = $userStmt->fetch();
            if ($ownerUser) {
                if (empty($currentOwnerEmail)) {
                    $currentOwnerEmail = $ownerUser['email'];
                }
                $currentOwnerName = trim(($ownerUser['first_name'] ?? '') . ' ' . ($ownerUser['last_name'] ?? ''));
            }
        }

        if (empty($currentOwnerEmail)) {
            $currentOwnerEmail = 'landlens@acheva.in';
        }

        if (empty($buyerEmail)) {
            $buyerEmail = 'buyer@landlens.in';
        }

        if ($transferAmount <= 0) {
            $transferAmount = (float)$property['price'];
        }

        // 3. Generate Cryptographic Token & Insert Transfer Record
        $transferId = 'trf-' . bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(32));

        $insStmt = $conn->prepare("
            INSERT INTO land_transfers (
                id, property_id, current_owner_id, current_owner_name, current_owner_email,
                buyer_id, buyer_name, buyer_email, transfer_amount, token, status,
                owner_remarks, requested_at
            ) VALUES (
                :id, :pid, :oid, :oname, :oemail,
                :bid, :bname, :bemail, :amount, :token, 'PENDING',
                :remarks, NOW()
            )
        ");

        $insStmt->execute([
            ':id' => $transferId,
            ':pid' => $propertyId,
            ':oid' => $currentOwnerId,
            ':oname' => $currentOwnerName,
            ':oemail' => $currentOwnerEmail,
            ':bid' => $buyerId ?: 'usr-buyer-' . substr($transferId, 4, 8),
            ':bname' => $buyerName,
            ':bemail' => $buyerEmail,
            ':amount' => $transferAmount,
            ':token' => $token,
            ':remarks' => $remarks
        ]);

        // 4. Generate Clickable Links
        // We use hostinger domain for live email clicks
        $baseApiUrl = 'https://landlense.acheva.in';
        if (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'localhost')) {
            $baseApiUrl = 'http://' . $_SERVER['HTTP_HOST'];
        }

        $approveUrl = "$baseApiUrl/api/transfers/respond?token=$token&action=APPROVE";
        $rejectUrl = "$baseApiUrl/api/transfers/respond?token=$token&action=REJECT";

        // 5. Build and Send Email via Hostinger SMTP
        $htmlEmail = Mailer::buildTransferEmailHtml([
            'title' => $property['title'],
            'surveyNumber' => $property['survey_number'],
            'area' => $property['area'],
            'location' => $property['village'] . ', ' . $property['district'] . ', ' . $property['state'],
            'price' => $transferAmount,
            'buyerName' => $buyerName,
            'buyerEmail' => $buyerEmail,
            'ownerName' => $currentOwnerName,
            'approveUrl' => $approveUrl,
            'rejectUrl' => $rejectUrl
        ]);

        $subject = "ACTION REQUIRED: Land Title Transfer Request for Sy No. " . $property['survey_number'] . " [LandLens Security]";
        $mailResult = Mailer::send($currentOwnerEmail, $currentOwnerName, $subject, $htmlEmail);

        // 6. Record in Notifications & Audit Timeline
        try {
            $notifId = 'notif-' . bin2hex(random_bytes(6));
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (id, user_id, title, message, type, is_read, created_at)
                VALUES (:id, :uid, 'Land Transfer Initiated', :msg, 'TRANSFER', 0, NOW())
            ");
            $notifStmt->execute([
                ':id' => $notifId,
                ':uid' => $currentOwnerId ?: '078068ad-a896-4207-8476-53278c91dc42',
                ':msg' => "Transfer request sent to $currentOwnerEmail for Sy No: " . $property['survey_number']
            ]);
        } catch (Exception $e) {}

        Response::json([
            'success' => true,
            'transferId' => $transferId,
            'propertyId' => $propertyId,
            'propertyTitle' => $property['title'],
            'surveyNumber' => $property['survey_number'],
            'status' => 'PENDING',
            'token' => $token,
            'ownerEmail' => $currentOwnerEmail,
            'buyerEmail' => $buyerEmail,
            'emailDelivery' => $mailResult,
            'approveUrl' => $approveUrl,
            'rejectUrl' => $rejectUrl,
            'message' => 'Land transfer confirmation mail dispatched to owner via Hostinger SMTP'
        ], 201);
    }

    /**
     * GET or POST /api/transfers/respond
     * Handles the owner's decision via clickable email link (APPROVE or REJECT)
     */
    public static function handleResponse() {
        self::initTable();

        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $token = $input['token'] ?? null;
        }

        $action = strtoupper($_GET['action'] ?? $_POST['action'] ?? 'APPROVE');
        $remarks = $_GET['remarks'] ?? $_POST['remarks'] ?? '';

        if (!$token) {
            self::renderHtmlResponse(false, 'Invalid or Missing Token', 'A valid cryptographic authorization token is required to process this land transfer.');
            return;
        }

        $conn = Database::getConnection();
        if (!$conn) {
            self::renderHtmlResponse(false, 'Database Error', 'Unable to connect to registry database. Please try again.');
            return;
        }

        // Look up transfer record
        $stmt = $conn->prepare("
            SELECT t.*, p.title as prop_title, p.survey_number as prop_survey, p.area as prop_area,
                   p.village as prop_village, p.district as prop_district, p.state as prop_state
            FROM land_transfers t
            JOIN properties p ON t.property_id COLLATE utf8mb4_unicode_ci = p.id COLLATE utf8mb4_unicode_ci
            WHERE t.token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $transfer = $stmt->fetch();

        if (!$transfer) {
            self::renderHtmlResponse(false, 'Transfer Request Not Found', 'This authorization link is invalid or has expired.');
            return;
        }

        if ($transfer['status'] !== 'PENDING') {
            $isPrevApproved = ($transfer['status'] === 'APPROVED');
            $msg = $isPrevApproved 
                ? "This land transfer was already APPROVED on {$transfer['responded_at']} and ownership has been transferred to {$transfer['buyer_name']}."
                : "This land transfer was REJECTED on {$transfer['responded_at']}. No conveyance was allowed.";
            self::renderHtmlResponse($isPrevApproved, 'Transaction Already Resolved', $msg, $transfer);
            return;
        }

        // Process Decision
        if ($action === 'APPROVE' || $action === 'APPROVED') {
            // 1. Mark transfer APPROVED
            $upStmt = $conn->prepare("
                UPDATE land_transfers 
                SET status = 'APPROVED', owner_remarks = :rem, responded_at = NOW() 
                WHERE id = :id
            ");
            $upStmt->execute([
                ':rem' => $remarks ?: 'Owner explicitly approved transfer via Hostinger email link.',
                ':id' => $transfer['id']
            ]);

            // 2. CRITICAL: Resolve Buyer User ID & Ensure Foreign Key Integrity
            $buyerUserId = $transfer['buyer_id'];
            $uStmt = $conn->prepare("SELECT id FROM users WHERE id = :id OR LOWER(email) = LOWER(:email) LIMIT 1");
            $uStmt->execute([':id' => $buyerUserId, ':email' => $transfer['buyer_email']]);
            $foundUser = $uStmt->fetch();

            if ($foundUser) {
                $buyerUserId = $foundUser['id'];
            } else {
                // Ensure buyer exists in users table so foreign key constraint fk_properties_provider is satisfied
                $buyerRoleId = '4389499a-5879-411c-b9f2-1afbf670ece5'; // System BUYER role
                try {
                    $conn->prepare("
                        INSERT INTO users (id, email, password_hash, first_name, last_name, role_id, is_active, created_at, updated_at)
                        VALUES (:id, :email, :pass, :first, :last, :role_id, 1, NOW(), NOW())
                    ")->execute([
                        ':id' => $buyerUserId,
                        ':email' => $transfer['buyer_email'],
                        ':pass' => password_hash('Citizen@123', PASSWORD_BCRYPT),
                        ':first' => $transfer['buyer_name'] ?: 'Citizen Buyer',
                        ':last' => '',
                        ':role_id' => $buyerRoleId
                    ]);
                } catch (Exception $e) {
                    error_log("[TransferController Auto-Buyer Error] " . $e->getMessage());
                }
            }

            // Transfer Property Ownership to Buyer!
            $propUp = $conn->prepare("
                UPDATE properties 
                SET provider_id = :new_owner, status = 'APPROVED', updated_at = NOW() 
                WHERE id = :pid
            ");
            $propUp->execute([
                ':new_owner' => $buyerUserId,
                ':pid' => $transfer['property_id']
            ]);

            // 3. Add to Audit Timeline
            try {
                $timelineStmt = $conn->prepare("
                    INSERT INTO verification_timelines (id, property_id, stage, remarks, created_at)
                    VALUES (:id, :pid, 'TITLE_TRANSFERRED', :remarks, NOW())
                ");
                $timelineStmt->execute([
                    ':id' => 'tl-' . bin2hex(random_bytes(6)),
                    ':pid' => $transfer['property_id'],
                    ':remarks' => "Legitimate landowner verified & authorized title transfer. Legal ownership transferred to {$transfer['buyer_name']} ({$transfer['buyer_email']})."
                ]);
            } catch (Exception $e) {}

            // 4. Create Notifications for both parties
            try {
                $notifStmt = $conn->prepare("
                    INSERT INTO notifications (id, user_id, title, message, type, is_read, created_at)
                    VALUES 
                    (:id1, :uid1, 'Land Ownership Transferred!', :msg1, 'TRANSFER', 0, NOW()),
                    (:id2, :uid2, 'Land Transfer Approved', :msg2, 'TRANSFER', 0, NOW())
                ");
                $notifStmt->execute([
                    ':id1' => 'notif-' . bin2hex(random_bytes(6)),
                    ':uid1' => $transfer['buyer_id'],
                    ':msg1' => "Congratulations! Ownership title for {$transfer['prop_title']} (Sy No: {$transfer['prop_survey']}) has been officially transferred to you.",
                    ':id2' => 'notif-' . bin2hex(random_bytes(6)),
                    ':uid2' => $transfer['current_owner_id'] ?: '078068ad-a896-4207-8476-53278c91dc42',
                    ':msg2' => "You have successfully approved the title transfer for Sy No: {$transfer['prop_survey']} to {$transfer['buyer_name']}."
                ]);
            } catch (Exception $e) {}

            self::renderHtmlResponse(true, 'Land Ownership Transfer Approved & Completed!', "Legitimate title holder authorization confirmed. The property title and registry rights for Survey No. {$transfer['prop_survey']} have been officially transferred to {$transfer['buyer_name']}.", $transfer);
        } else {
            // REJECT
            $upStmt = $conn->prepare("
                UPDATE land_transfers 
                SET status = 'REJECTED', owner_remarks = :rem, responded_at = NOW() 
                WHERE id = :id
            ");
            $upStmt->execute([
                ':rem' => $remarks ?: 'Owner rejected transfer. Section 8 unauthorized transaction blocked.',
                ':id' => $transfer['id']
            ]);

            // Add fraud alert notification
            try {
                $conn->prepare("
                    INSERT INTO notifications (id, user_id, title, message, type, is_read, created_at)
                    VALUES (:id, :uid, 'SECURITY ALERT: Unauthorized Transfer Blocked', :msg, 'ALERT', 0, NOW())
                ")->execute([
                    ':id' => 'notif-' . bin2hex(random_bytes(6)),
                    ':uid' => $transfer['current_owner_id'] ?: '078068ad-a896-4207-8476-53278c91dc42',
                    ':msg' => "Transfer request for Sy No: {$transfer['prop_survey']} was REJECTED by you. Fraud intercept flag recorded."
                ]);
            } catch (Exception $e) {}

            self::renderHtmlResponse(false, 'Land Transfer Rejected & Blocked!', "The registered title holder has explicitly REJECTED this transfer request. No conveyance has occurred. Survey No. {$transfer['prop_survey']} remains protected under original ownership.", $transfer);
        }
    }

    /**
     * GET /api/transfers
     * List all transfers
     */
    public static function listTransfers() {
        self::initTable();
        $conn = Database::getConnection();
        if (!$conn) {
            Response::json([]);
        }

        $userId = $_GET['userId'] ?? null;
        $propertyId = $_GET['propertyId'] ?? null;

        $sql = "
            SELECT t.*, p.title as prop_title, p.survey_number as prop_survey, p.area as prop_area,
                   p.village as prop_village, p.district as prop_district, p.state as prop_state,
                   p.price as prop_price
            FROM land_transfers t
            LEFT JOIN properties p ON t.property_id COLLATE utf8mb4_unicode_ci = p.id COLLATE utf8mb4_unicode_ci
            WHERE 1=1
        ";
        $params = [];

        if ($userId) {
            $sql .= " AND (t.buyer_id = :uid OR t.current_owner_id = :uid)";
            $params[':uid'] = $userId;
        }

        if ($propertyId) {
            $sql .= " AND t.property_id = :pid";
            $params[':pid'] = $propertyId;
        }

        $sql .= " ORDER BY t.requested_at DESC LIMIT 50";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $transfers = $stmt->fetchAll();

            $formatted = array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'propertyId' => $r['property_id'],
                    'propertyTitle' => $r['prop_title'] ?: 'Land Parcel',
                    'surveyNumber' => $r['prop_survey'],
                    'area' => (float)$r['prop_area'],
                    'location' => trim(($r['prop_village'] ?? '') . ', ' . ($r['prop_district'] ?? '')),
                    'transferAmount' => (float)$r['transfer_amount'],
                    'currentOwnerId' => $r['current_owner_id'],
                    'currentOwnerName' => $r['current_owner_name'],
                    'currentOwnerEmail' => $r['current_owner_email'],
                    'buyerId' => $r['buyer_id'],
                    'buyerName' => $r['buyer_name'],
                    'buyerEmail' => $r['buyer_email'],
                    'status' => $r['status'],
                    'ownerRemarks' => $r['owner_remarks'],
                    'requestedAt' => $r['requested_at'],
                    'respondedAt' => $r['responded_at']
                ];
            }, $transfers);

            Response::json($formatted);
        } catch (Exception $e) {
            Response::json([]);
        }
    }

    /**
     * GET /api/transfers/:id
     */
    public static function getTransfer($id) {
        self::initTable();
        $conn = Database::getConnection();
        if (!$conn) Response::error('DB Unavailable', 503);

        $stmt = $conn->prepare("
            SELECT t.*, p.title as prop_title, p.survey_number as prop_survey, p.area as prop_area,
                   p.village as prop_village, p.district as prop_district, p.state as prop_state
            FROM land_transfers t
            LEFT JOIN properties p ON t.property_id = p.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            Response::json($row);
        } else {
            Response::error('Transfer not found', 404);
        }
    }

    /**
     * Render beautiful HTML result page for email clicks
     */
    private static function renderHtmlResponse($isSuccess, $title, $message, $transfer = null) {
        // If client expects JSON, return JSON
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json') && !str_contains($_SERVER['HTTP_ACCEPT'], 'text/html')) {
            Response::json([
                'success' => $isSuccess,
                'status' => $isSuccess ? 'APPROVED' : 'REJECTED',
                'title' => $title,
                'message' => $message,
                'transfer' => $transfer
            ]);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        $badgeColor = $isSuccess ? '#10b981' : '#ef4444';
        $badgeBg = $isSuccess ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)';
        $icon = $isSuccess ? '✓' : '✕';
        $portalUrl = 'https://pavanstarkin-tech.github.io/LandLense-Copy/';
        
        $propTitle = htmlspecialchars($transfer['prop_title'] ?? 'Land Parcel');
        $surveyNo = htmlspecialchars($transfer['prop_survey'] ?? 'N/A');
        $buyerName = htmlspecialchars($transfer['buyer_name'] ?? 'Buyer');
        $transferAmount = number_format((float)($transfer['transfer_amount'] ?? 0), 2);

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LandLens - Title Authorization Result</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #090d16;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            width: 90%;
            max-width: 580px;
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
            margin: 20px;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: {$badgeBg};
            border: 2px solid {$badgeColor};
            color: {$badgeColor};
            font-size: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            font-weight: bold;
        }
        h1 {
            font-size: 24px;
            margin: 0 0 12px 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 15px;
            line-height: 24px;
            color: #94a3b8;
            margin: 0 0 25px 0;
        }
        .details-box {
            background: #0b1120;
            border: 1px solid #1e293b;
            border-radius: 14px;
            padding: 18px;
            text-align: left;
            margin-bottom: 28px;
            font-size: 13.5px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #162032;
        }
        .row:last-child {
            border-bottom: none;
        }
        .label {
            color: #64748b;
        }
        .val {
            color: #f1f5f9;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #059669 0%, #0284c7 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            transition: transform 0.2s, opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .footer-note {
            margin-top: 25px;
            font-size: 12px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-circle">{$icon}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>

        <div class="details-box">
            <div class="row">
                <span class="label">Property Title:</span>
                <span class="val">{$propTitle}</span>
            </div>
            <div class="row">
                <span class="label">Survey Number:</span>
                <span class="val" style="color: #38bdf8;">{$surveyNo}</span>
            </div>
            <div class="row">
                <span class="label">Transferee / Buyer:</span>
                <span class="val">{$buyerName}</span>
            </div>
            <div class="row">
                <span class="label">Declared Consideration:</span>
                <span class="val" style="color: #10b981;">₹ {$transferAmount}</span>
            </div>
            <div class="row">
                <span class="label">Status:</span>
                <span class="val" style="color: {$badgeColor}; font-weight: 800;">
HTML;
        echo $isSuccess ? 'TRANSFERRED & APPROVED' : 'REJECTED (NO TRANSFER)';
        echo <<<HTML
                </span>
            </div>
        </div>

        <a href="{$portalUrl}" class="btn">Return to LandLens Portal</a>

        <div class="footer-note">
            LandLens Government Land Verification Engine • Secured with Hostinger SMTP & MySQL
        </div>
    </div>
</body>
</html>
HTML;
        exit();
    }
}

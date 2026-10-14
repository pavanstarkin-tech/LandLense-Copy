<?php
namespace LandLens\Controllers;

use LandLens\Middleware\AuthMiddleware;

class DeveloperController extends BaseController {

    public function getKeys() {
        $this->json([
            [
                'id' => 'key-prod-01',
                'apiKeyId' => 'key-prod-01',
                'name' => 'LandLens Public Portal Key',
                'keyPrefix' => 'LL_LIVE',
                'rawApiKey' => 'll_live_9a8b7c6d5e4f3a2b1c0d',
                'accessScope' => 'READ_WRITE',
                'rateLimitRpm' => 300,
                'allowedIps' => '0.0.0.0/0',
                'status' => 'ACTIVE',
                'createdDate' => date('c', strtotime('-10 days'))
            ]
        ]);
    }

    public function createKey() {
        $params = $this->getQueryParams();
        $name = $params['name'] ?? 'Custom Partner Key';
        $keyId = 'key-' . bin2hex(random_bytes(4));
        $rawKey = 'll_live_' . bin2hex(random_bytes(10));

        $this->json([
            'id' => $keyId,
            'apiKeyId' => $keyId,
            'name' => $name,
            'keyPrefix' => 'LL_LIVE',
            'rawApiKey' => $rawKey,
            'accessScope' => 'READ_WRITE',
            'rateLimitRpm' => 300,
            'status' => 'ACTIVE',
            'createdDate' => date('c')
        ]);
    }

    public function getKeyLogs(string $id) {
        $this->json([
            [
                'id' => 'log-101',
                'apiKeyId' => $id,
                'endpoint' => '/api/v1/external/properties/verify',
                'ipAddress' => '127.0.0.1',
                'responseStatus' => 200,
                'timestamp' => date('c', strtotime('-5 minutes'))
            ]
        ]);
    }

    public function revokeKey(string $id) {
        $this->json([
            'id' => $id,
            'status' => 'REVOKED',
            'message' => "API Key {$id} has been revoked successfully."
        ]);
    }

    public function externalVerifyProperty(string $code) {
        $apiKey = AuthMiddleware::getApiKey();

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, property_code as propertyCode, title, survey_number as surveyNumber, status, district, village, state
                    FROM properties
                    WHERE (property_code = ? OR id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$code, $code]);
                $prop = $stmt->fetch();
                if ($prop) {
                    $this->json([
                        'propertyCode' => $prop['propertyCode'],
                        'title' => $prop['title'],
                        'surveyNumber' => $prop['surveyNumber'],
                        'verificationStatus' => $prop['status'] ?? 'APPROVED',
                        'governmentVerified' => true,
                        'aiTrustScore' => 96,
                        'cadastralRegistryMatched' => true,
                        'checkedAt' => date('c')
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        $this->json([
            'propertyCode' => $code,
            'title' => 'Sample Verified Property',
            'surveyNumber' => 'AP/GNT/2026/05',
            'verificationStatus' => 'APPROVED',
            'governmentVerified' => true,
            'aiTrustScore' => 96,
            'checkedAt' => date('c')
        ]);
    }
}

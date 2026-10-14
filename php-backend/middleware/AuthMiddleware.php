<?php
namespace LandLens\Middleware;

use LandLens\Config\Database;
use PDO;

class AuthMiddleware {
    public static function getBearerToken(): ?string {
        $headers = self::getRequestHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader) {
            return null;
        }
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function getApiKey(): ?string {
        $headers = self::getRequestHeaders();
        return $headers['x-api-key'] ?? $headers['X-Api-Key'] ?? $headers['X-API-KEY'] ?? null;
    }

    public static function getRequestHeaders(): array {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== false) {
                return $headers;
            }
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    public static function getCurrentUser(): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        try {
            // Handle custom LandLens base64 token format (ll-b64-...)
            if (str_starts_with($token, 'll-b64-')) {
                $json = base64_decode(substr($token, 7));
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            // Handle standard JWT format (header.payload.signature)
            if (str_contains($token, '.')) {
                $parts = explode('.', $token);
                if (count($parts) >= 2) {
                    $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
                    $payload = json_decode($payloadJson, true);
                    if (is_array($payload)) {
                        return $payload;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Token decode error: " . $e->getMessage());
        }

        return null;
    }

    public static function generateToken(array $user): string {
        $payload = [
            'id' => $user['id'] ?? 'usr-' . time(),
            'userId' => $user['id'] ?? 'usr-' . time(),
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? 'BUYER',
            'firstName' => $user['firstName'] ?? $user['first_name'] ?? 'User',
            'lastName' => $user['lastName'] ?? $user['last_name'] ?? '',
            'phone' => $user['phone'] ?? $user['phoneNumber'] ?? $user['phone_number'] ?? '',
            'iat' => time(),
            'exp' => time() + (86400 * 7) // 7 days
        ];

        return 'll-b64-' . base64_encode(json_encode($payload));
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'status' => 401,
                'error' => 'Unauthorized',
                'message' => 'Valid authorization Bearer token required.'
            ]);
            exit();
        }
        return $user;
    }

    public static function requireRoles(array $allowedRoles): array {
        $user = self::requireAuth();
        $userRole = strtoupper($user['role'] ?? 'BUYER');
        $allowed = array_map('strtoupper', $allowedRoles);

        if (!in_array($userRole, $allowed)) {
            http_response_code(403);
            echo json_encode([
                'status' => 403,
                'error' => 'Forbidden',
                'message' => 'Access denied. Required role: ' . implode(' or ', $allowedRoles)
            ]);
            exit();
        }
        return $user;
    }
}

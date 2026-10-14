<?php
/**
 * LandLens Enterprise PHP API Engine
 * Converts & Replaces Spring Boot Endpoints with Native High-Performance PHP
 */

// Autoload LandLens Namespaces
spl_autoload_register(function ($class) {
    $prefix = 'LandLens\\';
    $base_dir = dirname(__DIR__) . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use LandLens\Middleware\CorsMiddleware;
use LandLens\Routes\Router;
use LandLens\Controllers\AuthController;
use LandLens\Controllers\UserController;
use LandLens\Controllers\PropertyController;
use LandLens\Controllers\VerificationController;
use LandLens\Controllers\FraudController;
use LandLens\Controllers\NotificationController;
use LandLens\Controllers\DeveloperController;
use LandLens\Controllers\AnalyticsController;
use LandLens\Controllers\AiController;

// Handle CORS Preflights
CorsMiddleware::handle();

$router = new Router();

// ==========================================
// 1. System Health & Info
// ==========================================
$router->get('/', [AuthController::class, 'health']);
$router->get('/actuator/health', [AuthController::class, 'health']);
$router->get('/api/health', [AuthController::class, 'health']);

// ==========================================
// 2. Authentication & User Profile
// ==========================================
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);

$router->get('/api/users/me', [UserController::class, 'me']);
$router->get('/api/users/profile', [UserController::class, 'me']);
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);

// ==========================================
// 3. Properties Management & Bookmarks
// ==========================================
$router->get('/api/properties', [PropertyController::class, 'index']);
$router->post('/api/properties', [PropertyController::class, 'store']);
$router->get('/api/properties/saved', [PropertyController::class, 'getSavedProperties']);
$router->get('/api/properties/visits', [PropertyController::class, 'getVisits']);
$router->put('/api/properties/visits/{id}', [PropertyController::class, 'updateVisitStatus']);

$router->get('/api/properties/{id}', [PropertyController::class, 'show']);
$router->put('/api/properties/{id}', [PropertyController::class, 'update']);
$router->delete('/api/properties/{id}', [PropertyController::class, 'destroy']);

// Property Sub-resources
$router->post('/api/properties/{id}/save', [PropertyController::class, 'saveProperty']);
$router->delete('/api/properties/{id}/save', [PropertyController::class, 'unsaveProperty']);
$router->post('/api/properties/{id}/visit', [PropertyController::class, 'scheduleVisit']);

$router->get('/api/properties/{id}/images', [PropertyController::class, 'getImages']);
$router->post('/api/properties/{id}/images', [PropertyController::class, 'uploadImage']);

$router->get('/api/properties/{id}/videos', [PropertyController::class, 'getVideos']);
$router->post('/api/properties/{id}/videos', [PropertyController::class, 'uploadVideo']);

$router->get('/api/properties/{id}/documents', [PropertyController::class, 'getDocuments']);
$router->post('/api/properties/{id}/documents', [PropertyController::class, 'uploadDocument']);
$router->post('/api/documents/{id}/ocr', [PropertyController::class, 'processOcr']);

// ==========================================
// 4. Verification & Government Review
// ==========================================
$router->post('/api/properties/{id}/ai-verify', [VerificationController::class, 'triggerAiVerification']);
$router->get('/api/properties/{id}/ai-verification', [VerificationController::class, 'getAiVerification']);
$router->post('/api/properties/{id}/government-verify', [VerificationController::class, 'submitGovernmentVerification']);
$router->get('/api/properties/{id}/timeline', [VerificationController::class, 'getPropertyTimeline']);
$router->get('/api/verification/timeline', [VerificationController::class, 'getGeneralTimeline']);
$router->post('/api/verification/verify', [VerificationController::class, 'verifyDeed']);
$router->post('/api/verification/owner-request', [VerificationController::class, 'requestOwnerVerification']);
$router->post('/api/verification/owner-response', [VerificationController::class, 'processOwnerResponse']);
$router->get('/api/verification/owner-requests', [VerificationController::class, 'getOwnerRequests']);

// ==========================================
// 5. Fraud Module
// ==========================================
$router->get('/api/fraud-reports', [FraudController::class, 'index']);
$router->get('/api/properties/{id}/fraud-reports', [FraudController::class, 'getPropertyFraudReports']);
$router->post('/api/properties/{id}/fraud-reports', [FraudController::class, 'submitReport']);
$router->put('/api/fraud-reports/{id}/assign', [FraudController::class, 'assignOfficer']);
$router->put('/api/fraud-reports/{id}/resolve', [FraudController::class, 'resolve']);

// ==========================================
// 6. Notifications
// ==========================================
$router->get('/api/notifications', [NotificationController::class, 'index']);
$router->put('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
$router->put('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

// ==========================================
// 7. Developer Portal & External API Keys
// ==========================================
$router->get('/api/developer/keys', [DeveloperController::class, 'getKeys']);
$router->post('/api/developer/keys', [DeveloperController::class, 'createKey']);
$router->get('/api/developer/keys/{id}/logs', [DeveloperController::class, 'getKeyLogs']);
$router->delete('/api/developer/keys/{id}', [DeveloperController::class, 'revokeKey']);
$router->get('/api/v1/external/properties/{code}/verify', [DeveloperController::class, 'externalVerifyProperty']);

// ==========================================
// 8. Analytics & Dashboard Metrics
// ==========================================
$router->get('/api/analytics', [AnalyticsController::class, 'dashboard']);
$router->get('/api/analytics/dashboard', [AnalyticsController::class, 'dashboard']);

// ==========================================
// 9. AI Engine (NVIDIA Llama 3.2 & Valuation)
// ==========================================
$router->post('/api/ai/chat', [AiController::class, 'chat']);
$router->post('/api/ai/message', [AiController::class, 'chat']);
$router->post('/api/ai/estimate-price', [AiController::class, 'estimatePrice']);
$router->get('/api/ai/conversations', [AiController::class, 'getConversations']);
$router->post('/api/ai/conversations', [AiController::class, 'createConversation']);
$router->get('/api/ai/conversations/{id}/messages', [AiController::class, 'getConversationMessages']);
$router->post('/api/ai/conversations/{id}/messages', [AiController::class, 'sendMessageToConversation']);

// Dispatch Request
$router->dispatch();

<?php
namespace LandLens\Middleware;

class CorsMiddleware {
    public static function handle() {
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-key, Accept, Origin, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}

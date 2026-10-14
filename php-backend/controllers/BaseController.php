<?php
namespace LandLens\Controllers;

use LandLens\Config\Database;
use PDO;

abstract class BaseController {
    protected ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    protected function json($data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }

    protected function text(string $content, int $status = 200) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit();
    }

    protected function error(string $message, int $status = 400, $details = null) {
        $response = [
            'status' => $status,
            'error' => $message
        ];
        if ($details !== null) {
            $response['details'] = $details;
        }
        $this->json($response, $status);
    }

    protected function getBody(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST ?? [];
        }
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
        return $_POST ?? [];
    }

    protected function getQueryParams(): array {
        return $_GET ?? [];
    }
}

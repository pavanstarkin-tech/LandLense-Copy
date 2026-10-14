<?php
namespace LandLens\Config;

class Config {
    // Database Configuration
    const DB_HOST = 'srv1117.hstgr.io';
    const DB_USER = 'u833088220_Priya_teamlead';
    const DB_PASS = 'Priya_teamlead@1234567';
    const DB_NAME = 'u833088220_Priya_teamlead';
    const DB_PORT = 3306;

    // AI & External Service Keys
    const NVIDIA_API_KEY = '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';
    const MODEL_NAME = 'meta/llama-3.2-11b-vision-instruct';
    const FALLBACK_MODEL = 'meta/llama-3.2-90b-vision-instruct';

    // JWT Secret
    const JWT_SECRET = 'LandLens_SuperSecret_Security_Key_2026';

    public static function getEnv($key, $default = null) {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        return $default;
    }
}

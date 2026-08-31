<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $allowedOrigins = [
        'https://adwitix.com',
        'https://www.adwitix.com',
    ];

    public array $allowedOriginsPatterns = [];

    public array $allowedHeaders = [
        'Origin',
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'X-API-KEY',
    ];

    public array $allowedMethods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ];

    public array $exposedHeaders = [];

    public int $maxAge = 3600;

    public bool $supportsCredentials = true;
}
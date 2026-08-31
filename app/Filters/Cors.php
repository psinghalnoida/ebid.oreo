<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    private array $allowedOrigins = [
        'https://adwitix.com',
        'https://www.adwitix.com',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');

        $response = service('response');

        if (in_array($origin, $this->allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Vary', 'Origin');
        }

        $response->setHeader(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );

        $response->setHeader(
            'Access-Control-Allow-Headers',
            'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-API-KEY'
        );

        $response->setHeader(
            'Access-Control-Allow-Credentials',
            'true'
        );

        $response->setHeader(
            'Access-Control-Max-Age',
            '3600'
        );

        // Handle browser preflight
        if (strtolower($request->getMethod()) === 'options') {
            return $response->setStatusCode(204);
        }

        return $request;
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ) {
        $origin = $request->getHeaderLine('Origin');

        if (in_array($origin, $this->allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Vary', 'Origin');
        }

        $response->setHeader(
            'Access-Control-Allow-Credentials',
            'true'
        );

        return $response;
    }
}
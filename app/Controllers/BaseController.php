<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    // REST/JWT API migration helper: JSON-body-first request-field reader.
    // The React frontend sends `application/json` bodies; getJsonVar()
    // reads those. Falling back to getPost() lets a converted controller
    // keep accepting classic form-encoded posts too (e.g. from `curl -d`
    // during manual testing) without every call site needing its own
    // null-coalescing boilerplate.
    protected function input(string $key)
    {
        $value = $this->request->getJsonVar($key);
        return $value !== null ? $value : $this->request->getPost($key);
    }

    // Global API response envelope — EVERY controller response, success
    // or error, goes out shaped {status, message, data}. $status is
    // derived purely from the HTTP status code (< 400 = true), never
    // guessed from $data's contents, so callers don't need to classify
    // their own payload — just pass whatever they already built
    // (a single record, a list, or nothing) as $data and, optionally,
    // the HTTP code. $data is returned exactly as given — a single
    // associative array or a list of them both serialize fine as JSON,
    // matching "data may be single array or multiple array".
    protected function apiResponse($data = [], ?string $message = null, int $httpCode = 200)
    {
        return \App\Libraries\ApiResponse::send($this->response, $data, $message, $httpCode);
    }

    // Standard JSON error envelope, used by every migrated controller so
    // API error shapes stay consistent. Now just a thin, backward-
    // compatible wrapper over apiResponse() — every existing call site
    // (jsonError($httpCode, $errorCode, $humanMessage)) keeps working
    // unchanged, but now emits the global {status, message, data} shape:
    // $description becomes the top-level message, $error (the short
    // machine-readable code, e.g. 'invalid_otp') moves into data.
    protected function jsonError(int $status, string $error, string $description)
    {
        return $this->apiResponse(['error' => $error], $description, $status);
    }
}

<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Trait;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

/**
 * Standardized CSRF validation for storefront endpoints.
 *
 * This trait provides consistent CSRF token validation across all site controllers,
 * accepting tokens via multiple methods to support both traditional form submissions
 * and modern JSON API calls from Vue islands.
 *
 * Token Acceptance Priority:
 * 1. X-CSRF-Token HTTP header (preferred for JSON API calls)
 * 2. POST form field with Joomla session token name
 * 3. Query string (disabled by default; enable only for specific use cases)
 *
 * Usage:
 *
 *     use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;
 *
 *     class MyController extends BaseController
 *     {
 *         use CsrfValidation;
 *
 *         public function myAction(): void
 *         {
 *             $this->requireCsrfToken(); // Validates or exits with 403
 *             // ... safe to proceed
 *         }
 *     }
 *
 * @since 0.3.2
 */
trait CsrfValidation
{
    /**
     * Verify CSRF token from request.
     *
     * Checks for valid tokens in this order:
     * 1. X-CSRF-Token header (for JSON API calls from Vue islands)
     * 2. POST body token (for traditional form submissions)
     * 3. Query string token (only if $allowQuery is true)
     *
     * @param bool $allowQuery Whether to accept token in query string (default: false)
     *                         Enable only for email links, payment redirects, or
     *                         other cases where POST isn't feasible.
     *
     * @return bool True if a valid token was found, false otherwise
     *
     * @since 0.3.2
     */
    protected function hasValidCsrfToken(bool $allowQuery = false): bool
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $sessionToken = Session::getFormToken();

        // 1. Check X-CSRF-Token header (preferred for JSON API calls)
        // This is the recommended method for Vue islands using fetch/axios
        $headerToken = (string) $input->server->getString('HTTP_X_CSRF_TOKEN', '');

        if ($headerToken !== '' && hash_equals($sessionToken, $headerToken)) {
            return true;
        }

        // 2. Check POST body token (standard Joomla form submissions)
        // This covers traditional form submissions with HTMLHelper::_('form.token')
        if (Session::checkToken('post')) {
            return true;
        }

        // 3. Check query string token (only if explicitly allowed)
        // Use cases: email links, payment gateway redirects, webhook verifications
        // WARNING: Avoid for state-changing operations like cart mutations
        if ($allowQuery && Session::checkToken('get')) {
            return true;
        }

        return false;
    }

    /**
     * Verify CSRF token and send JSON error response if invalid.
     *
     * This method validates the CSRF token and immediately terminates the request
     * with a 403 Forbidden response if validation fails. Use this at the start
     * of any state-changing endpoint (POST/PUT/DELETE operations).
     *
     * @param bool $allowQuery Whether to accept token in query string (default: false)
     *
     * @return void Exits with 403 JSON response if token is invalid
     *
     * @since 0.3.2
     */
    protected function requireCsrfToken(bool $allowQuery = false): void
    {
        if (!$this->hasValidCsrfToken($allowQuery)) {
            $this->sendCsrfErrorResponse();
        }
    }

    /**
     * Send a standardized CSRF error response and terminate the request.
     *
     * @return never This method never returns; it terminates execution
     *
     * @since 0.3.2
     */
    private function sendCsrfErrorResponse(): void
    {
        $app = Factory::getApplication();

        if (\function_exists('http_response_code')) {
            http_response_code(403);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('Status', '403', true);

        // Use Joomla's standard invalid token message for consistency
        echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
        $app->close();
    }
}

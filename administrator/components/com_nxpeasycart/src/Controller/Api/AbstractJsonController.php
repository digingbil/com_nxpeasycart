<?php

namespace Nxp\EasyCart\Admin\Controller\Api;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use RuntimeException;

\defined('_JEXEC') or die;

/**
 * Base controller for component JSON endpoints.
 */
abstract class AbstractJsonController extends BaseController
{
    /**
     * The active application instance.
     *
     * @var CMSApplicationInterface
     */
    protected CMSApplicationInterface $app;

    /**
     * Constructor.
     *
     * @param array|null                $config  Controller config
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        $this->app = $app ?: Factory::getApplication();

        parent::__construct($config, $factory, $this->app->getInput());
    }

    /**
     * Ensure the current request includes a valid session token.
     *
     * @return void
     */
    protected function requireToken(): void
    {
        if (!Session::checkToken('request')) {
            $this->fail(Text::_('COM_NXPEASYCART_ERROR_INVALID_TOKEN'), 403);
        }
    }

    /**
     * Ensure the active user can perform the requested action.
     *
     * @param string $action ACL action string
     *
     * @return void
     */
    protected function requirePermission(string $action): void
    {
        if (!$this->getUser()->authorise($action, 'com_nxpeasycart')) {
            $this->fail(Text::_('COM_NXPEASYCART_ERROR_NOT_AUTHORISED'), 403);
        }
    }

    /**
     * Return decoded JSON payload from the request body.
     *
     * @return array<string,mixed>
     */
    protected function getJsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');

        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($data)) {
            $this->fail(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400, [
                'json_error' => json_last_error_msg(),
            ]);
        }

        return $data;
    }

    /**
     * Send a JSON success response and terminate execution.
     *
     * @param array<string,mixed> $payload Response payload
     * @param int                 $code    HTTP status code
     *
     * @return void
     */
    protected function succeed(array $payload, int $code = 200): void
    {
        $response = new JsonResponse($payload, '', true, $code);

        $this->app->setHeader('Content-Type', 'application/json', true);
        $this->app->sendHeaders();

        echo $response;
        $this->app->close();
    }

    /**
     * Send a JSON error response and terminate execution.
     *
     * @param string               $message Error message
     * @param int                  $code    HTTP status
     * @param array<string,mixed>  $details Optional error details
     *
     * @return void
     */
    protected function fail(string $message, int $code = 400, array $details = []): void
    {
        $response = new JsonResponse(
            [
                'error' => $message,
                'details' => $details,
            ],
            $message,
            false,
            $code
        );

        $this->app->setHeader('Content-Type', 'application/json', true);
        $this->app->sendHeaders();

        echo $response;
        $this->app->close();
    }

    /**
     * Retrieve the current user.
     *
     * @return User
     */
    protected function getUser(): User
    {
        return $this->app->getIdentity();
    }

    /**
     * Utility to ensure a value is present, otherwise error.
     *
     * @param mixed  $value   Value to inspect
     * @param string $message Message if value is empty
     * @param int    $code    HTTP status code
     *
     * @return void
     */
    protected function requireValue($value, string $message, int $code = 400): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->fail($message, $code);
        }
    }
}

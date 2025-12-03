<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Input\Input;
use RuntimeException;

/**
 * Base controller for JSON API endpoints.
 * Provides methods for rendering JSON responses and handling common tasks.
 *
 * @since 0.1.5
 */
class AbstractJsonController extends BaseController
{
    /**
     * AbstractJsonController constructor.
     *
     * @param array                     $config  Controller configuration
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app  Application instance
     *
     * @since 0.1.5
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null, Input $input = null)
    {
        if (!\is_array($config)) {
            $config = [];
        }

        parent::__construct($config, $factory, $app, $input);

        $this->input = $this->app->getInput();
    }

    /**
     * Render a JSON response payload.
     *
     * @param mixed  $data    Payload to return
     * @param int    $code    HTTP status code
     * @param string $message Optional message (used for errors)
     *
     * @return JsonResponse
     *
     * @since 0.1.5
     */
    protected function respond(mixed $data, int $code = 200, string $message = ''): JsonResponse
    {
        $hasError = $code >= 400;

        // Extract message from data if not provided explicitly
        if ($hasError && $message === '' && \is_array($data) && isset($data['message'])) {
            $message = (string) $data['message'];
            unset($data['message']);
        }

        $response = new JsonResponse($data, $message, $hasError);
        $body     = (string) $response;

        if (\method_exists($response, 'setHttpStatusCode')) {
            $response->setHttpStatusCode($code);
        }

        if ($this->app && \method_exists($this->app, 'setHeader')) {
            $this->app->setHeader('status', $code);
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        } else {
            http_response_code($code);
        }

        if ($this->app && \method_exists($this->app, 'allowCache')) {
            $this->app->allowCache(false);
        }

        if ($this->app && \method_exists($this->app, 'setBody')) {
            $this->app->setBody($body);
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($code);
        }

        echo $body;

        if (PHP_SAPI !== 'cli' && $this->app && \method_exists($this->app, 'close')) {
            $this->app->close();
        }

        return $response;
    }

    /**
     * Fetch an integer ID from the request.
     *
     * @return int
     *
     * @since 0.1.5
     */
    protected function requireId(): int
    {
        $id = $this->input->getInt('id');

        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        return $id;
    }

    /**
     * Implemented by subclasses to handle the requested action.
     *
     * @param string $task Task/action name
     *
     * @return mixed
     *
     * @since 0.1.5
     */
    public function execute($task)
    {
        throw new RuntimeException('JSON controllers must implement their own execute method.', 500);
    }

    /**
     * Ensure the user has the required permission.
     *
     * @param string $action Permission action
     * @return void
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function assertCan(string $action): void
    {
        $user = $this->app?->getIdentity();

        if (!$user || !$user->authorise($action, 'com_nxpeasycart')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Ensure the request has a valid Joomla token.
     *
     * @return void
     * @throws RuntimeException Invalid token
     *
     * @since 0.1.5
     */
    protected function assertToken(): void
    {
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }
}

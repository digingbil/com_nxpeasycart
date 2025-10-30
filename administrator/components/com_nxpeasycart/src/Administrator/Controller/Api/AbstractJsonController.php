<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

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
 */
class AbstractJsonController extends BaseController
{
    /**
     * AbstractJsonController constructor.
     *
     * @param array                     $config  Controller configuration
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app  Application instance
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
     * @param mixed $data Payload to return
     * @param int   $code HTTP status code
     *
     * @return JsonResponse
     */
    protected function respond($data, int $code = 200): JsonResponse
    {
        if ($this->app && \method_exists($this->app, 'setHeader')) {
            $this->app->setHeader('status', $code);
        }

        http_response_code($code);

        $hasError = $code >= 400;

        $response = new JsonResponse($data, '', $hasError);

        if ($this->app && \method_exists($this->app, 'setHeader')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        }

        echo $response;

        return $response;
    }

    /**
     * Fetch an integer ID from the request.
     *
     * @return int
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
     */
    public function execute($task)
    {
        throw new RuntimeException('JSON controllers must implement their own execute method.', 500);
    }

    /**
     * Ensure the user has the required permission.
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
     */
    protected function assertToken(): void
    {
        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }
}

<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use RuntimeException;

/**
 * Base controller for JSON API endpoints.
 */
abstract class AbstractJsonController extends BaseController
{
    /**
     * AbstractJsonController constructor.
     *
     * @param array                     $config  Controller configuration
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app  Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);

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
        return new JsonResponse($data, '', $code);
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
    abstract public function execute($task);
}

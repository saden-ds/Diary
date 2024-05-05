<?php

namespace App\Controllers;

use App\Base\Config;
use App\Base\CurrentUser;
use App\Base\DataStore;
use App\Base\Exceptions\BadRequestException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\Exceptions\NotFoundException;
use App\Base\Logger;
use App\Base\Message;
use App\Base\Request;
use App\Base\Session;
use App\Base\Tmpl;
use App\Base\View;
use App\Middleware\SyncDog\Client;
use Exception;
use Throwable;

class ApplicationController
{
    protected Config $config;
    protected CurrentUser $current_user;
    protected Message $msg;
    protected Request $request;
    protected Session $session;
    protected Tmpl $tmpl;

    public function __construct()
    {
        $this->config = Config::init();
        $this->current_user = CurrentUser::init();
        $this->msg = Message::init();
        $this->request = Request::init();
        $this->session = Session::init();
        $this->tmpl = Tmpl::init();
        $this->timestamp = microtime(true);
    }

    public function __destruct()
    {
        $logger = new Logger('pagegen');
        $logger->info($logger->getTime($this->timestamp));
    }

    public function action(string $method, ...$args): void
    {
        if ($this->current_user->isSignedIn()) {
            $_SERVER['X_REQUEST_TAG'] = $this->current_user->email;
        }

        $view = $this->callAction($method, $args);

        if (!$view) {
            return;
        }

        if ($this->request->isJsonRequest()) {
            header('Content-Type: application/json; charset=utf-8');

            $view->type(View::TYPE_JSON);
        } elseif ($this->request->isXhr()) {
            $view->type(View::TYPE_XHR);
        } else {
            $current_user = null;

            if ($this->current_user->isSignedIn()) {
                $current_user = $this->current_user->toArray();

                if (!$this->current_user->confirmed) {
                    $current_user['unconfirmed_message'] = $this->msg->t(
                        'email_confirmation.description', [
                            'email' => $this->current_user->email
                        ]
                    );
                } else {
                    $current_user['unconfirmed_message'] = null;
                }

                $current_user = [$current_user];
            }

            $header_nav = [[
                'name' => 'Dienasgrāmata',
                'path' => '/',
                'active' => get_class($this) == 'App\Controllers\SchedulesController'
            ], [
                'name' => 'Atzīmes',
                'path' => '/grades',
                'active' => get_class($this) == 'App\Controllers\GradesController'
            ], [
                'name' => 'Kavējumi',
                'path' => '/visits',
                'active' => false
            ], [
                'name' => 'Uzdevumi',
                'path' => '/assignments',
                'active' => get_class($this) == 'App\Controllers\AssignmentsController'
            ], [
                'name' => 'Faili',
                'path' => '',
                'active' => false
            ], [
                'name' => 'Statistika',
                'path' => '',
                'active' => false
            ], [
                'name' => 'Zīmes',
                'path' => '',
                'active' => false
            ], [
                'name' => 'Liecība',
                'path' => '',
                'active' => false
            ], [
                'name' => 'Priekšmeti',
                'path' => '/lessons',
                'active' => get_class($this) == 'App\Controllers\LessonsController'
            ]];

            if (!$view->isException()) {
                $view->csrf($this->session->get('csrf'));
                $view->main([
                    'version' => $this->config->get('version'),
                    'assets_version' => $this->config->get('version_timestamp'),
                    'current_user' => $current_user,
                    'header_nav' => $header_nav,
                    'lesson_invites' => $this->getLessonsInvites($current_user),
                    'navigation' => $this->getMainNavigation(),
                    'recovery_mode' => !!$this->request->get('recovery_token'),
                    'locale' => $this->config->locale,
                    'cookie_confirm' => !isset($_COOKIE['life_cookie_confirm']),
                    'email' => $this->config->get('support_email')
                ]);
            }
        }

        echo $view->toString();
    }

    public function notFoundAction(): ?View
    {
        return $this->handleNotFound();
    }

    public function redirect(string $url)
    {
        $this->request->redirect($url);

        return null;
    }


    protected function beforeAction(string $action): void
    {
    }

    protected function callAction($method, $args)
    {
        if (
            $this->request->isJsonRequest() &&
            !$this->isValidCsrf()
        ) {
            return $this->handleForbidden();
        }

        if (!method_exists($this, $method)) {
            return $this->handleNotFound();
        }

        try {
            if (method_exists($this, 'beforeAction')) {
                $this->beforeAction(str_replace('Action', '', $method));
            }

            return call_user_func_array([$this, $method], $args);
        } catch (ForbiddenException $e) {
            return $this->handleForbidden([
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (NotFoundException $e) {
            return $this->handleNotFound([
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest([
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (\Exception|Throwable $e) {
            error_log($e);
            return $this->handleInternalServerError([
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    protected function isValidCsrf() {
        return $this->session->isValidCsrf($this->request->getCsrf());
    }

    protected function recordAction($record, string $action, $params = null): ?View
    {
        $is_saved = false;

        if (!$this->request->isJsonRequest()) {
            return $this->handleBadRequest();
        }

        if (method_exists($record, $action)) {
            $is_saved = $record->$action($params);
        }

        if ($is_saved) {
            return View::init($record->toArray());
        } else {
            return $this->recordError($record);
        }

    }

    protected function recordError($record, ?string $message = null): ?View
    {
        if (!$this->request->isJsonRequest()) {
            return $this->handleBadRequest();
        }

        if (empty($message)) {
            $message = $this->msg->t('error.message');
        }

        $view = new View();

        if ($record->hasErrors()) {
            return $view->errors(
                $record->errors,
                $record->getBaseError() ?: $message
            );
        } else {
            return $view->error($record->getBaseError() ?: $message);
        }
    }


    private function handleInternalServerError(?array $data = null): ?View
    {
        header("{$this->request->getServerProtocol()} 500 Internal Server Error");

        return $this->handleError(500, $data);
    }

    private function handleForbidden(?array $data = null): ?View
    {
        header("{$this->request->getServerProtocol()} 403 Forbidden");

        return $this->handleError(403, $data);
    }

    private function handleNotFound(?array $data = null): ?View
    {
        header("{$this->request->getServerProtocol()} 404 Not Found");

        return $this->handleError(404, $data);
    }

    private function handleBadRequest(?array $data = null): ?View
    {
        header("{$this->request->getServerProtocol()} 400 Bad Request");

        return $this->handleError(400, $data);
    }

    private function handleError(int $code, ?array $data) {
        $view = new View();
        $logger = new Logger($code);
        $error = $this->msg->t("http_error.error_$code.meta_title");

        $logger->info($data);

        if ($this->request->isJsonRequest()) {
            $view->data(['error' => $error, 'error_code' => $code]);
        } else {
            $view
                ->exception(true)
                ->path("tmpl/_$code.tmpl")
                ->main(['title' => $error]);
        }

        return $view;
    }

    private function getMainNavigation(): ?array
    {
        $current_controller = get_class($this);

        return [[
            'name' => $this->msg->t('navigation.products'),
            'path' => '/products',
            'active' => $current_controller === 'App\Controllers\ProductsController'
        ], [
            'name' => $this->msg->t('navigation.laboratories'),
            'path' => '/laboratories',
            'active' => $current_controller === 'App\Controllers\LaboratoriesController'
        ], [
            'name' => $this->msg->t('navigation.manufacturers'),
            'path' => '/manufacturers',
            'active' => $current_controller === 'App\Controllers\ManufacturersController'
        ]];
    }

    private function getLessonsInvites($current_user): ?array
    {
        if (!$this->current_user->email) {
            return null;
        }

        $db = DataStore::init();
        
        return $db->data('
            select
                l.lesson_name,
                li.lesson_invite_id,
                u.user_firstname,
                u.user_lastname
            from lesson_invite as li
            join lesson as l on l.lesson_id = li.lesson_id
            join user as u on u.user_id = l.user_id
            where li.user_email = ?
        ', [
            $this->current_user->email
        ]);

        return $data;
    }
}
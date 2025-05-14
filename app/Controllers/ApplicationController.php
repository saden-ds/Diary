<?php

namespace App\Controllers;

use App\Base\Config;
use App\Base\CurrentUser;
use App\Base\DataStore;
use App\Base\DataQuery;
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

                if ($this->current_user->organization_user_role === 'admin') {
                    $current_user['organization_user_role'] = $this->msg->t('organization_user.roles.admin');
                } else {
                    $current_user['organization_user_role'] = null;
                }

                $current_user['app_name'] = $this->config->get('title');

                $current_user = [$current_user];
            }

            $header_nav[] = [
                'name' => 'Dienasgrāmata',
                'path' => '/',
                'active' => get_class($this) == 'App\Controllers\MainController',
                'icon' => 'schedule'
            ];

            if (!$this->current_user->organization_id) {
                $header_nav[] = [
                    'name' => 'Atzīmes',
                    'path' => '/grades',
                    'active' => get_class($this) == 'App\Controllers\GradesController',
                    'icon' => 'grade'
                ];
                $header_nav[] = [
                    'name' => 'Kavējumi',
                    'path' => '/visits',
                    'active' => get_class($this) == 'App\Controllers\VisitsController',
                    'icon' => 'calendar_check'
                ];
            }

            if (
                !$this->current_user->organization_id
                || $this->current_user->organization_user_role !== 'admin'
            ) {
                $header_nav[] = [
                    'name' => 'Uzdevumi',
                    'path' => '/assignments',
                    'active' => get_class($this) == 'App\Controllers\AssignmentsController',
                    'icon' => 'assignment'
                ];
            }

            $header_nav[] = [
                'name' => 'Priekšmeti',
                'path' => '/lessons',
                'active' => get_class($this) == 'App\Controllers\LessonsController',
                'icon' => 'lesson'
            ];

            if ($this->current_user->organization_id) {
                // $header_nav[] = [
                //     'name' => 'Žurnāls',
                //     'path' => '/journal',
                //     'active' => get_class($this) == 'App\Controllers\JournalsController',
                //     'icon' => 'journal'
                // ];
                $header_nav[] = [
                    'name' => 'Grupas',
                    'path' => '/groups',
                    'active' => get_class($this) == 'App\Controllers\Organizations\GroupsController',
                    'icon' => 'group'
                ];
                $header_nav[] = [
                    'name' => 'Pārstāvji',
                    'path' => '/organizations/users',
                    'active' => get_class($this) == 'App\Controllers\Organizations\InvitesController',
                    'icon' => 'representative'
                ];
                $header_nav[] = [
                    'name' => 'Grafika plānotājs',
                    'path' => '/schedules/groups',
                    'active' => get_class($this) == 'App\Controllers\Organizations\ScheduleGroupsController',
                    'icon' => 'schedule_edit'
                ];
                // [
                //     'name' => 'Faili',
                //     'path' => '',
                //     'active' => false,
                //     'icon' => 'assignment'
                // ], 
                // [
                //     'name' => 'Statistika',
                //     'path' => '',
                //     'active' => false,
                //     'icon' => 'assignment'
                // ], 
                // [
                //     'name' => 'Zīmes',
                //     'path' => '',
                //     'active' => false,
                //     'icon' => 'assignment'
                // ], [
                //     'name' => 'Liecība',
                //     'path' => '',
                //     'active' => false,
                //     'icon' => 'assignment'
                // ], 
            }

            if (!$view->isException()) {
                $view->csrf($this->session->get('csrf'));
                $view->main([
                    'app_name' => $this->config->get('title'), 
                    'version' => $this->config->get('version'),
                    'assets_version' => $this->config->get('version_timestamp'),
                    'current_user' => $current_user,
                    'header_nav' => $header_nav,
                    'lesson_invites' => $this->getLessonsInvites($current_user),
                    'organization_invites' => $this->getOrganizationsInvites($current_user),
                    'user_grades' => $this->getUserGrades($current_user),
                    'user_assignments' => $this->getUserAssignments($current_user),
                    'navigation' => $this->getMainNavigation(),
                    'recovery_mode' => !!$this->request->get('recovery_token'),
                    'locale' => $this->config->locale,
                    'cookie_confirm' => !isset($_COOKIE['life_cookie_confirm']),
                    'email' => $this->config->get('support_email'),
                    'compact' => false
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
            where li.lesson_invite_email = ?
        ', [
            $this->current_user->email
        ]);

        return $data;
    }

    private function getOrganizationsInvites($current_user): ?array
    {
        if (!$this->current_user->email) {
            return null;
        }

        $db = DataStore::init();


        return $db->data('
            select
                o.organization_name,
                oi.organization_invite_id,
                oiu.user_firstname,
                oiu.user_lastname,
                oiu.user_email
            from organization_invite as oi
            join user as u on u.user_email = oi.organization_invite_email
            join user as oiu on oiu.user_id = oi.user_id
            join organization as o on o.organization_id = oi.organization_id
            left join organization_user as ou on ou.organization_id = o.organization_id
                and ou.user_id = u.user_id
            where oi.organization_invite_email = ?
                and ou.organization_user_id is null
        ', [
            $this->current_user->email
        ]);

    }

    private function getUserGrades($current_user): ?array
    {
        $db = DataStore::init();

        $data = $db->data('
            select
                l.lesson_name,
                g.grade_type,
                g.grade_numeric,
                g.grade_percent,
                g.grade_included,
                a.assignment_type,
                a.assignment_created_at,
                s.schedule_date
            from grade as g
            join assignment as a on a.assignment_id = g.assignment_id
            left join schedule as s on s.schedule_id = a.schedule_id
            left join lesson as l on l.lesson_id = s.lesson_id
            where g.user_id = ?
            order by a.assignment_created_at desc
        ', [
            $this->current_user->id
        ]);

        if (!$data) {
            return null;
        }

        foreach ($data as $k => $v) {
            if ($v['assignment_type'] === 'work') {
                $v['assignment_type'] = 'Mācību stunda';
            } elseif ($v['assignment_type'] === 'test') {
                $v['assignment_type'] = 'Pārbaudes darbs';
            }

            switch ($v['grade_type']) {
                case 'numeric':
                    $v['grade_value'] = $v['grade_numeric'];
                    break;
                case 'percent':
                    $v['grade_value'] = $v['grade_percent'] . '%';
                    break;
                case 'included':
                    $v['grade_value'] = $v['grade_included'] ? 'i' : 'ni';
                    break;
                default:
                    $v['grade_value'] = 'error';
                    break;
            }

             $v['schedule_date'] = $this->msg->date($v['schedule_date']) ?? null;

             $data[$k] = $v;
        }

        return $data;
    }

    private function getUserAssignments($current_user): ?array 
    {
        if (!$this->current_user->id) {
            return null;
        }
        
        $query = new DataQuery();

        $query
            ->select(
                'a.assignment_id',
                'a.assignment_type',
                'a.assignment_created_at',
                'l.lesson_name',
                's.schedule_date'
            )
            ->from('assignment as a')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->leftJoin('lesson_user as lu on lu.lesson_id = s.lesson_id' .
                ' and lu.user_id = ?', $this->current_user->id
            )
            ->leftJoin('group_user as gu on gu.group_id = s.group_id' . 
                ' and gu.user_id = ?', $this->current_user->id
            )
            ->where('lu.user_id is not null or gu.user_id is not null')
            ->order('a.assignment_created_at desc');

        if (!$data = $query->fetchAll()) {
            return null;
        }

        return $data;
    }
}
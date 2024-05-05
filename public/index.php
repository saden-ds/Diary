<?php

include_once('../app.php');

use App\Base\Router;
use App\Base\TokenGenerator;
use App\Controllers\ApplicationController;
use App\Controllers\MainController;
use App\Controllers\ConversationsController;
use App\Controllers\RegistrationsController;
use App\Controllers\GradesController;	
use App\Controllers\NotificationsController;	
use App\Controllers\VisitsController;
use App\Controllers\LessonsController;
use App\Controllers\LessonInvitesController;
use App\Controllers\SessionsController;
use App\Controllers\LessonUsersController;
use App\Controllers\SchedulesController;
use App\Controllers\AssignmentsController;
use App\Controllers\UsersController;
use App\Controllers\TestController;


$x_request_id = (new TokenGenerator())->getUUID();

$_SERVER['X_REQUEST_ID'] = $x_request_id;

header('X-Request-ID: ' . $_SERVER['X_REQUEST_ID']);

$router = new Router($_SERVER, [ApplicationController::class, 'notFoundAction']);

$router->get('/', [MainController::class, 'indexAction']);

$router->get('/conversations', [ConversationsController::class, 'indexAction']);
$router->get('/conversations/new', [ConversationsController::class, 'newAction']);
$router->post('/conversations/create', [ConversationsController::class, 'createAction']);
$router->any('/conversations/{:id}', [ConversationsController::class, 'showAction']);
$router->get('/conversations/{:id}/edit', [ConversationsController::class, 'editAction']);
$router->post('/conversations/{:id}/update', [ConversationsController::class, 'updateAction']);
$router->get('/conversations/{:id}/delete', [ConversationsController::class, 'deleteAction']);

$router->get('/profile', [UsersController::class, 'editAction']);
$router->post('/profile/update', [UsersController::class, 'updateAction']);

$router->any('/notifications/latest', [NotificationsController::class, 'latestAction']);

$router->get('/signup', [RegistrationsController::class, 'newAction']);
$router->post('/signup', [RegistrationsController::class, 'createAction']);

$router->post('/signin', [SessionsController::class, 'createAction']);
$router->get('/signout', [SessionsController::class, 'deleteAction']);


$router->get('/grades', [GradesController::class, 'indexAction']);

$router->get('/visits', [VisitsContynceroller::class, 'indexAction']);

$router->get('/lessons', [LessonsController::class, 'indexAction']);
$router->get('/lessons/new', [LessonsController::class, 'newAction']);
$router->get('/lessons/{:id}', [LessonsController::class, 'showAction']);
$router->get('/lessons/{:id}/edit', [LessonsController::class, 'editAction']);
$router->get('/lessons/{:id}/delete', [LessonsController::class, 'deleteAction']);
$router->post('/lessons/create', [LessonsController::class, 'createAction']);
$router->post('/lessons/{:id}/update', [LessonsController::class, 'updateAction']);

$router->get('/lessons/{:lesson_id}/invites/new', [LessonInvitesController::class, 'newAction']);
$router->get('/lessons/invites/{:id}/accept', [LessonInvitesController::class, 'acceptAction']);
$router->get('/lessons/invites/{:id}/decline', [LessonInvitesController::class, 'declineAction']);
$router->post('/lessons/invites/create', [LessonInvitesController::class, 'createAction']);

$router->get('/lessons/users/{:lesson_user_id}/delete', [LessonUsersController::class, 'deleteAction']);

$router->get('/assignments', [AssignmentsController::class, 'indexAction']);
$router->get('/schedules/{:schedule_id}/assignments/new', [AssignmentsController::class, 'newAction']);
$router->post('/assignments/create', [AssignmentsController::class, 'createAction']);
$router->get('/assignments/{:id}/edit', [AssignmentsController::class, 'editAction']);
$router->post('/assignments/{:id}/update', [AssignmentsController::class, 'updateAction']);
$router->get('/assignments/{:id}/delete', [AssignmentsController::class, 'deleteAction']);
$router->get('/assignments/{:id}', [AssignmentsController::class, 'showAction']);

$router->get('/', [SchedulesController::class, 'indexAction']);
$router->get('/schedules/new', [SchedulesController::class, 'newAction']);
$router->post('/schedules/create', [SchedulesController::class, 'createAction']);
$router->get('/schedules/{:year}/{:month}/{:day}', [SchedulesController::class, 'indexAction']);
$router->get('/schedules/{:id}/edit', [SchedulesController::class, 'editAction']);
$router->post('/schedules/{:id}/update', [SchedulesController::class, 'updateAction']);
$router->get('/schedules/{:id}', [SchedulesController::class, 'showAction']);

$router->get('/test', [TestController::class, 'indexAction']);




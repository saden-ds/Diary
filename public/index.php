<?php

include_once('../app.php');

use App\Base\Router;
use App\Base\TokenGenerator;
use App\Controllers\ApplicationController;
use App\Controllers\MainController;
use App\Controllers\ConversationsController;
use App\Controllers\RegistrationsController;
use App\Controllers\GradesController;	
use App\Controllers\Organizations\GroupsController;   
use App\Controllers\Organizations\GroupUsersController;   
use App\Controllers\Organizations\GroupLessonsController;   
use App\Controllers\Organizations\ScheduleGroupsController;   
use App\Controllers\Organizations\InvitesController as OrganizationInvitesController;   
use App\Controllers\OrganizationInvitesController as UserOrganizationInvitesController;   
use App\Controllers\NotificationsController;	
use App\Controllers\VisitsController;
use App\Controllers\LessonsController;
use App\Controllers\LessonInvitesController;
use App\Controllers\SessionsController;
use App\Controllers\LessonUsersController;
use App\Controllers\SchedulesController;
use App\Controllers\AssignmentsController;
use App\Controllers\AssignmentFilesController;
use App\Controllers\JournalsController;
use App\Controllers\UsersController;
use App\Controllers\TestController;


$x_request_id = (new TokenGenerator())->getUUID();

$_SERVER['X_REQUEST_ID'] = $x_request_id;

header('X-Request-ID: ' . $_SERVER['X_REQUEST_ID']);

$router = new Router($_SERVER, [ApplicationController::class, 'notFoundAction']);

// $router->get('/', [MainController::class, 'indexAction']);
$router->get('/', [SchedulesController::class, 'indexAction']);

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

$router->get('/grades', [GradesController::class, 'indexAction']);
$router->get('/assignments/{:assignment_id}/grades/new', [GradesController::class, 'newAction']);
$router->post('/assignments/{:assignment_id}/grades/create', [GradesController::class, 'createAction']);

// $router->get('/visits', [VisitsContynceroller::class, 'indexAction']);

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
$router->any('/assignments/{:assignment_id}/files', [AssignmentFilesController::class, 'indexAction']);
$router->post('/assignments/{:assignment_id}/files/create', [AssignmentFilesController::class, 'createAction']);
$router->get('/assignments/files/{:id}', [AssignmentFilesController::class, 'showAction']);
$router->get('/assignments/files/{:id}/delete', [AssignmentFilesController::class, 'deleteAction']);


$router->get('/journal', [JournalsController::class, 'indexAction']);

$router->get('/schedules/groups', [ScheduleGroupsController::class, 'indexAction']);
$router->get('/schedules/groups/{:group_id}/lessons/new', [ScheduleGroupsController::class, 'newAction']);
$router->post('/schedules/groups/{:group_id}/lessons/create', [ScheduleGroupsController::class, 'createAction']);
$router->get('/schedules/groups/{:year}/{:month}/{:day}', [ScheduleGroupsController::class, 'indexAction']);
$router->get('/schedules/groups/{:year}/{:month}/{:day}/status/{:status}', [ScheduleGroupsController::class, 'updateStatus']);
$router->get('/schedules/{:schedule_id}/groups/delete', [ScheduleGroupsController::class, 'deleteAction']);

$router->get('/schedules/new', [SchedulesController::class, 'newAction']);
$router->post('/schedules/create', [SchedulesController::class, 'createAction']);
$router->get('/schedules/{:year}/{:month}/{:day}', [SchedulesController::class, 'indexAction']);
$router->get('/schedules/{:id}/edit', [SchedulesController::class, 'editAction']);
$router->post('/schedules/{:id}/update', [SchedulesController::class, 'updateAction']);
$router->get('/schedules/{:id}', [SchedulesController::class, 'showAction']);

$router->get('/test', [TestController::class, 'indexAction']);

$router->get('/groups', [GroupsController::class, 'indexAction']);
$router->get('/groups/new', [GroupsController::class, 'newAction']);
$router->post('/groups/create', [GroupsController::class, 'createAction']);
$router->get('/groups/{:id}', [GroupsController::class, 'showAction']);
$router->get('/groups/{:group_id}/users', [GroupUsersController::class, 'indexAction']);
$router->get('/groups/{:group_id}/users/new', [GroupUsersController::class, 'newAction']);
$router->post('/groups/{:group_id}/users/create', [GroupUsersController::class, 'createAction']);
$router->get('/groups/{:group_id}/users/{:group_user_id}/delete', [GroupUsersController::class, 'deleteAction']);
$router->get('/groups/{:group_id}/lessons/new', [GroupLessonsController::class, 'newAction']);
$router->post('/groups/{:group_id}/lessons/create', [GroupLessonsController::class, 'createAction']);
$router->get('/groups/{:group_id}/lessons/{:group_lesson_id}/delete', [GroupLessonsController::class, 'deleteAction']);

$router->get('/organizations/users', [OrganizationInvitesController::class, 'indexAction']);
$router->get('/organizations/users/new', [OrganizationInvitesController::class, 'newAction']);
$router->post('/organizations/users/create', [OrganizationInvitesController::class, 'createAction']);
$router->get('/organizations/users/{:id}/delete', [OrganizationInvitesController::class, 'deleteAction']);

$router->get('/organizations/invites/{:invite_id}/accept', [UserOrganizationInvitesController::class, 'acceptAction']);
$router->get('/organizations/invites/{:invite_id}/decline', [UserOrganizationInvitesController::class, 'declineAction']);

$router->get('/profiles', [SessionsController::class, 'showAction']);
$router->post('/signin', [SessionsController::class, 'createAction']);
$router->any('/private', [SessionsController::class, 'updateAction']);
$router->any('/organizations/{:organization_id}', [SessionsController::class, 'updateAction']);
$router->get('/signout', [SessionsController::class, 'deleteAction']);




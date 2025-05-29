<?php

namespace App\Controllers\Organizations;

include_once(__ROOT__.'/vendor/autoload.php');

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Base\Tmpl;
use App\Models\Group;
use App\Models\Schedule;
use DateTime;
use Mpdf\Mpdf;

class ScheduleGroupsController extends ApplicationController
{
    public function indexAction(): ?View
    {
        if ($this->request->get('format') === 'pdf') {
            return $this->renderIndexPdf();
        }

        if ($this->request->get('day')) {
            $datetime_start = new DateTime(
                $this->request->get('year') . '-' . 
                $this->request->get('month') . '-' . 
                $this->request->get('day')
            );
        } else {
            $datetime_start = new DateTime();

            $datetime_start->setTimestamp(strtotime("this week"));
        }

        $groups = $this->getGroups();
        $times = $this->getTimes();
        $schedule_lessons = $this->getScheduleLessons(clone $datetime_start);
        $grid = null;
        $active = false;
        $actions = null;

        $datetime_end = clone $datetime_start;
        $datetime_next_week = clone $datetime_start;
        $datetime_previous_week = clone $datetime_start;
        $colspan = count($groups);

        foreach (range(1, 7) as $week_day) {
            $date = $datetime_end->format('Y-m-d');

            $grid[$date] = [
                'date' => $datetime_end->format($this->msg->t('datetime.format.date')),
                'date_raw' => $datetime_end->format('Y-m-d'),
                'colspan' => $colspan,
                'time' => null
            ];

            foreach ($times as $r) {
                $grid[$date]['time'][$r['lesson_time_id']] = [
                    'lesson_time_number' => $r['lesson_time_number'],
                    'lesson_time_start_at' => $r['lesson_time_start_at'],
                    'lesson_time_end_at' => $r['lesson_time_end_at'],
                    'groups' => null
                ];

                foreach ($groups as $g) {
                    $schedule_lesson = $schedule_lessons[$date . '_' . $r['lesson_time_id'] . '_' . $g['group_id']] ?? null;

                    if ($schedule_lesson) {
                        $lesson_delete_path = '/schedules/' . intval($schedule_lesson['schedule_id']) . '/groups/delete';

                        if ($schedule_lesson['schedule_active']) {
                            $active = true;
                            $lesson_delete_path = null;
                        }

                        $grid[$date]['time'][$r['lesson_time_id']]['groups'][] = [
                            'group_id' => $g['group_id'],
                            'lesson_id' => $schedule_lesson['lesson_id'],
                            'lesson_name' => $schedule_lesson['lesson_name'],
                            'lesson_new_path' => null,
                            'lesson_create_path' => null,
                            'lesson_delete_path' => $lesson_delete_path
                        ];
                    } else {
                        $grid[$date]['time'][$r['lesson_time_id']]['groups'][] = [
                            'group_id' => $g['group_id'],
                            'lesson_id' => null,
                            'lesson_name' => null,
                            'lesson_new_path' => '/schedules/groups/' . intval($g['group_id']) 
                                . '/lessons/new?' .http_build_query([
                                    'lesson_time_id' => $r['lesson_time_id'],
                                    'schedule_date' => $date
                                ]),
                            'lesson_create_path' => '/schedules/groups/' . intval($g['group_id']) 
                                . '/lessons/create?' .http_build_query([
                                    'lesson_time_id' => $r['lesson_time_id'],
                                    'schedule_date' => $date
                                ]),
                            'lesson_delete_path' => null
                        ];
                    }
                }
            }

            if ($week_day != 7) {
                $datetime_end->modify('+1 day');
            } 

            $datetime_next_week->modify('+1 day');
            $datetime_previous_week->modify('-1 day');
        }

        if ($this->request->isXhr()) {
            return View::init('tmpl/schedules/groups/_index.tmpl', [
                'lessons' => $this->getLessons()
            ]);
        }

        $tmpl = Tmpl::init();

        if ($this->current_user->organization_user_role === 'admin') {
            if ($active) {
                if ($schedule_lessons) {
                    $actions[] = [
                        'title' => 'Lejupielādēt pdf',
                        'path' => $this->request->getPath() . '.pdf',
                        'class_name' => null,
                        'blank' => true
                    ];
                }

                $actions[] = [
                    'title' => 'Atcelt publicēšanu',
                    'path' => '/schedules/groups/' . $datetime_start->format('Y/m/d') . '/status/disabled',
                    'class_name' => null,
                    'blank' => false
                ];
            } else {
                $actions[] = [
                    'title' => 'Publicēt',
                    'path' => '/schedules/groups/' . $datetime_start->format('Y/m/d') . '/status/active',
                    'class_name' => null,
                    'blank' => false
                ];
            }

            return View::init('tmpl/schedules/groups/index.tmpl', [
                'index' => $tmpl->file('tmpl/schedules/groups/_index.tmpl', [
                    'lessons' => $this->getLessons(),
                ]),
                'groups' => $groups,
                'grid' => $grid,
                'datetime_start' => $datetime_start->format('d.m.Y.'),
                'datetime_end' => $datetime_end->format('d.m.Y.'),
                'previous_path' => '/schedules/groups/' . $datetime_previous_week->format('Y/m/d'),
                'next_path' =>  '/schedules/groups/' . $datetime_next_week->format('Y/m/d'),
                'actions' => $actions
            ])->main([
                'compact' => true
            ]);      
        }

        if ($schedule_lessons) {
            $actions[] = [
                'title' => 'Lejupielādēt pdf',
                'path' => $this->request->getPath() . '.pdf',
                'class_name' => null,
                'blank' => true
            ];
        }

        return View::init('tmpl/schedules/groups/index_readonly.tmpl', [
            'groups' => $groups,
            'grid' => $grid,
            'lessons' => null,
            'datetime_start' => $datetime_start->format('d.m.Y.'),
            'datetime_end' => $datetime_end->format('d.m.Y.'),
            'previous_path' => '/schedules/groups/' . $datetime_previous_week->format('Y/m/d'),
            'next_path' =>  '/schedules/groups/' . $datetime_next_week->format('Y/m/d'),
            'actions' => $actions
        ]);      
    }

    public function newAction(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $group = $this->findGroup();
        $lesson_time_id = $this->request->get('lesson_time_id');
        
        return View::init('tmpl/schedules/groups/new.tmpl', [
            'group_name' => $group->group_name,
            'lesson_options' => $this->getGroupLessonOptions($group),
            'schedule_date' => $this->request->get('schedule_date'),
            'schedule_date_formatted' => $this->msg->date($this->request->get('schedule_date')),
            'lesson_time' => $this->getLessonTime($lesson_time_id),
            'path' => '/schedules/groups/' . $group->group_id . '/lessons/create'
        ]);
    }

    public function createAction(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $view = new View();
        $group = $this->findGroup();
        $schedule = new Schedule($this->request->permit([
            'schedule_date', 'lesson_id', 'lesson_time_id'
        ]));

        if ($schedule->lesson_id) {
            $query = new DataQuery();

            $query
                ->select('1 as one')
                ->from('group_lesson as gl')
                ->where('gl.group_id = ?', $group->group_id)
                ->where('gl.lesson_id = ?', $schedule->lesson_id);

            if (!$query->fetch()) {
                return $view->error('You can not add this lesson to the group');
            }
        }

        $schedule->group_id = $group->group_id;
        
        if ($schedule->create()) {
            return $view->data([
                'schedule_id' => $schedule->schedule_id
            ]);
        } else {
            return $this->recordError($schedule);
        } 
    }

    public function deleteAction(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $view = new View();
        $query = new DataQuery();
        $schedule_id = $this->request->get('schedule_id');

        $query
            ->select('s.*')
            ->from('schedule as s')
            ->where('s.schedule_id = ?', $schedule_id);

        if (!$schedule_id || !$r = $query->fetch()) {
            throw new NotFoundException();
        }

        $schedule = new Schedule($r, true);

        if (!$schedule->delete()) {
            return $view->error();
        }

        return $view->data([
            'schedule_id' => $schedule->schedule_id
        ]);
    }

    public function updateStatus(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $status = $this->request->get('status');

        $from = new DateTime(
            $this->request->get('year') . '-' . 
            $this->request->get('month') . '-' . 
            $this->request->get('day')
        );
        $back_path = '/schedules/groups/' . 
            $this->request->get('year') . '/' .
            $this->request->get('month') . '/' .
            $this->request->get('day');

        $to = clone $from;
        $to->modify('+7 days');

        $view = new View();
        $query = new DataQuery();

        $query
            ->select('s.*')
            ->from('schedule as s')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->where('schedule_date between ? and ?', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('l.organization_id is not null');

        $data = $query->fetchAll();

        if (empty($data)) {
            return $this->redirect($back_path);
        }

        foreach ($data as $r) {
            $schedule = new Schedule($r, true);

            if ($status === 'active') {
                $schedule->schedule_active = true;
            } else {
                $schedule->schedule_active = false;
            }
            
            $schedule->update();
        }

        return $this->redirect($back_path);
    }

    private function getGroups(): ?array
    {
        $query = new DataQuery();

        $query
            ->from('`group`')
            ->where('organization_id = ?', $this->current_user->organization_id);


        return $query->fetchAll();
    }

    private function getTimes(): ?array
    {
        $query = new DataQuery();

        $query->from('lesson_time');

        return $query->fetchAll();
    }

    private function getScheduleLessons(DateTime $datetime_start): ?array
    {
        $query = new DataQuery();
        $lessons = null;
        $from = $datetime_start->format('Y-m-d');

        $datetime_start->modify('+7 days');

        $to = $datetime_start->format('Y-m-d');

        $query
            ->select(
                'l.lesson_id', 
                'l.lesson_name',
                's.schedule_date',
                's.lesson_time_id',
                'g.group_id',
                's.schedule_id',
                's.schedule_active',
                'u.user_firstname',
                'u.user_lastname'
            )
            ->from('lesson as l')
            ->join('group_lesson as gl on gl.lesson_id = l.lesson_id')
            ->join('schedule as s on s.lesson_id = gl.lesson_id')
            ->join('`group` as g on g.group_id = gl.group_id')
            ->join('user as u on u.user_id = l.user_id')
            ->where('s.schedule_date between ? and ?', [$from, $to])
            ->where('s.group_id = gl.group_id')
            ->where('g.organization_id = ?', $this->current_user->organization_id);

        if ($this->current_user->organization_user_role !== 'admin') {
            $query->where('s.schedule_active = 1');
        }

        if (!$data = $query->fetchAll()) {
            return $lessons;
        }

        foreach ($data as $v) {
            $key = $v['schedule_date'] . '_' 
                . $v['lesson_time_id'] . '_'
                . $v['group_id'];

            $lessons[$key] = [
                'lesson_id' => $v['lesson_id'],
                'lesson_name' => $v['lesson_name'],
                'schedule_id' => $v['schedule_id'],
                'schedule_active' => $v['schedule_active'],
                'user_fullname' => $v['user_firstname'] . ' ' . $v['user_lastname']
            ];
        }
     
        return $lessons;
    }

    private function getLessons(): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'l.lesson_id',
                'l.lesson_name',
                'group_concat(gl.group_id) as group_ids',
                'concat(u.user_firstname, " ", u.user_lastname) as user_fullname'
            )
            ->from('lesson l')
            ->join('group_lesson gl on gl.lesson_id = l.lesson_id')
            ->join('user as u on u.user_id = l.user_id')
            ->where('l.organization_id = ?', $this->current_user->organization_id)
            ->group('l.lesson_id');

        if ($value = $this->request->get('q')) {
            $query->where('l.lesson_name like ?', '%' . $value . '%');
        }
        
        if (empty($data = $query->fetchAll())) {
            return null;
        }

        return $data;
    }   

    private function findGroup(): Group
    {
        $group = Group::find($this->request->get('group_id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if (!$this->current_user->canAdmin($group->organization_id)) {
            throw new ForbiddenException();
        }

        return $group;
    }

    private function getGroupLessonOptions($group): ?array
    {
        $options = null;
        $query = new DataQuery();
        $schedule_date = $this->request->get('schedule_date');
        $lesson_time_id = $this->request->get('lesson_time_id');

        $query
            ->select('l.lesson_id', 'l.lesson_name')
            ->from('group_lesson as gl')
            ->join('lesson as l on l.lesson_id = gl.lesson_id')
            ->leftJoin('schedule as s on s.lesson_id = l.lesson_id 
                and s.schedule_date = ? and s.lesson_time_id = ?', [$schedule_date, $lesson_time_id])
            ->where('gl.group_id = ? and s.schedule_id is null', $group->group_id);

        $data = $query->fetchAll();

        if (empty($data)) {
            return $options;
        }

        foreach ($data as $r) {
            $options[] = [
                'name' => $r['lesson_name'],
                'value' => $r['lesson_id'],
                'selected' => false
            ];
        }

        return $options;
    }

    private function getLessonTime($lesson_time_id): ?array
    {
        $query = new DataQuery();

        $query
            ->from('lesson_time')
            ->where('lesson_time_id = ?', $lesson_time_id);

        return [$query->fetch()];
    }

    private function renderIndexPdf(): ?View
    {
        if ($this->request->get('day')) {
            $datetime_start = new DateTime(
                $this->request->get('year') . '-' . 
                $this->request->get('month') . '-' . 
                $this->request->get('day')
            );
        } else {
            $datetime_start = new DateTime();

            $datetime_start->setTimestamp(strtotime("this week"));
        }

        $datetime_end = clone $datetime_start;
        $mpdf_options = [
            'setAutoBottomMargin' => 'stretch',
            'fontDir' => [
                __ROOT__ . '/tmpl/pdf'
            ],
            'fontdata' => [
                'outfit' => [
                    'R' => 'Outfit-Regular.ttf',
                    'B' => 'Outfit-Bold.ttf',
                    'I' => 'Outfit-Regular.ttf'
                ]
            ],
            'default_font' => 'outfit',
            'shrink_tables_to_fit' => 1, 
            // 'format' => 'A4-L',
            // 'orientation' => 'L',
            'tempDir' => $this->config->get('tmp_dir')
        ];

        $tmpl = Tmpl::init();
        $mpdf = new Mpdf($mpdf_options);

        $groups = $this->getGroups();
        $times = $this->getTimes();
        $lessons = $this->getScheduleLessons(clone $datetime_start);
        
        $mpdf->SetTitle('');
        $mpdf->SetCreator('');
        $mpdf->WriteHTML(file_get_contents(__ROOT__ . '/tmpl/pdf/style.css'), 1);
        $mpdf->SetHTMLFooter($tmpl->file('tmpl/pdf/footer.tmpl', [
            'created_at' => date($this->msg->t('datetime.format.default'))
        ]));

        if ($groups) {
            foreach($groups as $k => $v) {
                $datetime_group = clone $datetime_end;

                $v['dates'] = null;

                foreach (range(1, 7) as $week_day) {
                    $date = $datetime_group->format('Y-m-d');

                    $v['dates'][$date] = [
                        'date' => $datetime_group->format($this->msg->t('datetime.format.date')),
                        'date_raw' => $datetime_group->format('Y-m-d'),
                        'time' => null
                    ];

                    foreach ($times as $r) {
                        $lesson = $lessons[$date . '_' . $r['lesson_time_id'] . '_' . $v['group_id']] ?? null;

                        $v['dates'][$date]['time'][$r['lesson_time_id']] = [
                            'lesson_time_number' => $r['lesson_time_number'],
                            'lesson_time_start_at' => $r['lesson_time_start_at'],
                            'lesson_time_end_at' => $r['lesson_time_end_at'],
                            'lesson_name' => $lesson['lesson_name'] ?? null,
                            'user_fullname' => $lesson['user_fullname'] ?? null
                        ];
                    }

                    if ($week_day != 7) {
                        $datetime_group->modify('+1 day');
                    }
                }

                $groups[$k] = $v;
            }
        }

        $mpdf->WriteHTML($tmpl->file('tmpl/schedules/groups/index_pdf.tmpl', [
            'groups' => $groups
        ]));

        $mpdf->Output('schedule.pdf', 'I');

        return null;
    }
}
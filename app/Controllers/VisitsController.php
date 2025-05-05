<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Models\Visit;
use DateTime;

class VisitsController extends ApplicationController
{
    public function indexAction(): ?View
    {
        $datetime = new DateTime('2025-04-01');
        $start = $datetime->format('Y-m-d');
        $month_names = $this->msg->t('date.standalone_abbr_month_names', [
            'default' => []
        ]);
        $months = [];
        $range = 3;

        foreach (range(0, $range - 1) as $r) {
            $months[$datetime->format('n')] = [
                'month' => $month_names[$datetime->format('n')] ?? null
            ];

            $datetime->modify('+1 month');
        }
        
        $end = $datetime->format('Y-m-t');
        $visits = $this->getLessonVisits($start, $end);

        if ($visits) {
            foreach ($visits as $k => $v) {
                foreach ($v['lessons'] as $kk => $vv) {
                    $lesson_visits = null;

                    foreach ($months as $index => $month) {
                        if (isset($vv['visits'][$index])) {
                            $lesson_visits[] = $vv['visits'][$index];
                        } else {
                            $lesson_visits[] = [
                                'lesson_count' => 0,
                                'unjustified_count' => 0,
                            ];
                        }
                    }

                    $visits[$k]['lessons'][$kk]['visits'] = $lesson_visits;
                }

                $visits[$k]['months'] = $months;
            }
        }

        return View::init('tmpl/visits/index.tmpl', [
            'lesson_visits' => $visits
        ]);        
    }

    public function newAction(): ?View
    {
        $schedule_id = $this->request->get('schedule_id');

        return View::init('tmpl/visits/form.tmpl', [
            'users' => $this->getLessonUsers($schedule_id),
            'path' => '/schedules/' . intval($schedule_id) . '/visits/create'
        ]);
    }

    public function createAction(): ?View
    {    
        $view = new View();
        $schedule_id = $this->request->get('schedule_id');
        $users = $this->getLessonUsers($schedule_id);

        if (!$users) {
            throw new ForbiddenException();
        }

        $visits = $this->request->get('visits', []);

        foreach ($users as $r) {
            $user_id = $r['user_id'];
            $presence = isset($visits[$user_id]) ? 1 : 0;

            $visit = new Visit($r, !!$r['visit_id']);

            $visit->setAttributes([
                'visit_presence' => $presence,
                'user_id' => $user_id,
                'schedule_id' => $schedule_id
            ]);

            if (!$visit->save()) {
                return $this->recordError($visit);
            }
        }

        return $view->data([]);
    }


    private function getLessonUsers(int $schedule_id): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'v.visit_id',
                'v.visit_presence',
                'u.user_id',
                'u.user_firstname',
                'u.user_lastname',
            )
            ->from('schedule as s')
            ->where('s.schedule_id = ?', $schedule_id);

        if ($this->current_user->organization_id) {
            $query
                ->join('group_user as gu on gu.group_id = s.group_id')
                ->join('user as u on u.user_id = gu.user_id');
        } else {
            $query
                ->join('lesson_user as lu on lu.lesson_id = s.lesson_id')
                ->join('user as u on u.user_id = lu.user_id');
        }

        $query
            ->leftJoin('visit as v on v.schedule_id = s.schedule_id and v.user_id = u.user_id')
            ->order('u.user_firstname', 'u.user_lastname');

        return $query->fetchAll();
    }

    private function getLessonVisits($start, $end): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'x.*',
                'o.organization_name',
                'month(s.schedule_date) as month',
                'count(s.schedule_id) as lesson_count',
                'sum(case when v.visit_presence = 0 then 1 else 0 end) as unjustified_count'
            )
            ->from('(
                select
                    l.lesson_id,
                    l.lesson_name,
                    g.group_id,
                    g.organization_id,
                    g.group_name,
                    gu.user_id
                from lesson l
                left join lesson_user as lu on lu.lesson_id = l.lesson_id
                left join group_lesson as gl on gl.lesson_id = l.lesson_id
                left join group_user as gu on gu.group_id = gl.group_id
                left join `group` as g on g.group_id = gl.group_id
                join user u on u.user_id = ifnull(lu.user_id,gu.user_id)
                where gu.user_id = ' . intval($this->current_user->id) . '
                group by l.lesson_id, g.group_id
            ) x')
            ->leftJoin(
                'schedule as s on s.lesson_id = x.lesson_id' .
                ' and s.schedule_date >= ?' .
                ' and s.schedule_date <= ?',
                [
                    $start,
                    $end
                ]
            )
            ->join('lesson_time as lt on lt.lesson_time_id = s.lesson_time_id')
            ->leftJoin('visit as v on v.schedule_id = s.schedule_id and v.user_id = x.user_id')
            ->leftJoin('organization as o on o.organization_id = x.organization_id')
            ->where('concat(s.schedule_date, " ", lt.lesson_time_start_at) < now()')
            ->group('x.lesson_id, x.group_id, month(s.schedule_date)');

        $data = $query->fetchAll();
        $visits = null;

        foreach ($data as $r) {
            if (!isset($visits[$r['group_id']])) {
                $visits[$r['group_id']] = [
                    'group_id' => $r['group_id'],
                    'group_name' => $r['group_name'],
                    'organization_id' => $r['organization_id'],
                    'organization_name' => $r['organization_name'],
                    'lessons' => null
                ];
            }

            if (!isset($visits[$r['group_id']]['lessons'][$r['lesson_id']])) {
                $visits[$r['group_id']]['lessons'][$r['lesson_id']] = [
                    'lesson_id' => $r['lesson_id'],
                    'lesson_name' => $r['lesson_name'],
                    'visits' => null
                ];
            }

            $visits[$r['group_id']]['lessons'][$r['lesson_id']]['visits'][$r['month']] = [
                'lesson_count' => $r['lesson_count'],
                'unjustified_count' => $r['unjustified_count']
            ];
        }

        return $visits;
    }
}
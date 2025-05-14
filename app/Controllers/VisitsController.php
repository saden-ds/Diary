<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Models\Visit;
use DateTime;

class VisitsController extends PrivateController
{
    public function indexAction(): ?View
    {
        $datetime = new DateTime('2024-09-01');
        $start = $datetime->format('Y-m-d');
        $month_names = $this->msg->t('date.standalone_abbr_month_names', [
            'default' => []
        ]);
        $months = [];
        $range = 9;

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
                    $lesson_count = 0;
                    $unjustified_count = 0;
                    $justified_count = 0;

                    foreach ($months as $index => $month) {
                        if (isset($vv['visits'][$index])) {
                            $counts = $vv['visits'][$index];

                            $lesson_count += $counts['lesson_count'];
                            $unjustified_count += $counts['unjustified_count'];
                            $justified_count += $counts['justified_count'];

                            $lesson_visits[] = $counts;
                        } else {
                            $lesson_visits[] = [
                                'lesson_count' => 0,
                                'unjustified_count' => 0,
                                'justified_count' => 0
                            ];
                        }
                    }

                    $visits[$k]['lessons'][$kk]['visits'] = $lesson_visits;
                    $visits[$k]['lessons'][$kk]['lesson_count'] = $lesson_count;
                    $visits[$k]['lessons'][$kk]['unjustified_count'] = $unjustified_count;
                    $visits[$k]['lessons'][$kk]['justified_count'] = $justified_count;
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
        $db = DataStore::init();
        $data = $db->data('
            select
                x.lesson_id,
                x.lesson_name,
                x.group_id,
                x.organization_id,
                x.organization_name,
                x.group_name,
                x.month,
                count(x.schedule_id) as lesson_count,
                sum(if(x.visit_presence = 0,if(x.excused_absence,0,1),0)) as unjustified_count,
                sum(if(x.visit_presence = 0,if(x.excused_absence,1,0),0)) as justified_count
            from (
                select 
                    x.*, 
                    o.organization_name, 
                    month(s.schedule_date) as month, 
                    s.schedule_id,
                    v.visit_presence,
                    (
                        select 1 as one
                        from excused_absence ea
                        where ea.group_user_id = x.group_user_id
                            and ea.excused_absence_from <= s.schedule_date
                            and ea.excused_absence_to >= s.schedule_date
                    ) as excused_absence
                from (
                    select
                        l.lesson_id,
                        l.lesson_name,
                        g.group_id,
                        g.organization_id,
                        g.group_name,
                        gu.user_id,
                        gu.group_user_id
                    from lesson l
                    left join lesson_user as lu on lu.lesson_id = l.lesson_id
                    left join group_lesson as gl on gl.lesson_id = l.lesson_id
                    left join group_user as gu on gu.group_id = gl.group_id
                    left join `group` as g on g.group_id = gl.group_id
                    join user u on u.user_id = ifnull(lu.user_id,gu.user_id)
                    where gu.user_id = ?
                    group by l.lesson_id, gu.group_user_id
                ) x
                left join schedule as s on s.lesson_id = x.lesson_id
                    and s.schedule_date >= ?
                    and s.schedule_date <= ?
                join lesson_time as lt on lt.lesson_time_id = s.lesson_time_id
                left join visit as v on v.schedule_id = s.schedule_id and v.user_id = x.user_id
                left join organization as o on o.organization_id = x.organization_id
                where concat(s.schedule_date, " ", lt.lesson_time_start_at) < now()
            ) x
            group by x.lesson_id, x.group_id, x.month
        ', [
            $this->current_user->id,
            $start,
            $end
        ]);

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
                    'lesson_count' => 0,
                    'unjustified_count' => 0,
                    'justified_count' => 0,
                    'visits' => null
                ];
            }

            $visits[$r['group_id']]['lessons'][$r['lesson_id']]['visits'][$r['month']] = [
                'lesson_count' => $r['lesson_count'],
                'unjustified_count' => $r['unjustified_count'],
                'justified_count' => $r['justified_count']
            ];
        }

        return $visits;
    }
}
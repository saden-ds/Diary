<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Base\DataStore;
use App\Models\Grade;
use DateTime;

class GradesController extends PrivateController
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
                'first' => $r === 0,
                'month' => $month_names[$datetime->format('n')] ?? null
            ];

            $datetime->modify('+1 month');
        }

        $end = $datetime->format('Y-m-t');
        $grades = $this->getLessonGrades($start, $end);

        if ($grades) {
            foreach ($grades as $k => $v) {
                foreach ($v['lessons'] as $kk => $vv) {
                    $lesson_grades = null;
                    $lesson_average = 0;

                    if ($vv['average_count']) {
                        $lesson_average = round($vv['average_sum'] / $vv['average_count'], 2);
                    }

                    foreach ($months as $index => $month) {
                        if (isset($vv['months'][$index])) {
                            $r = $vv['months'][$index];
 
                            if ($r['average_count']) {
                                $r['average'] = round($r['average_sum'] / $r['average_count'], 2);
                            }

                            $r['first'] = $month['first'];

                            $lesson_grades[] = $r;
                        } else {
                            $lesson_grades[] = [
                                'first' => $month['first'],
                                'average' => 0,
                                'average_sum' => 0,
                                'average_count' => 0
                            ];
                        }

                        if ($vv['types']) {
                            foreach ($vv['types'] as $kkk => $vvv) {
                                $type_grades = null;
                                $type_average = 0;

                                if ($vvv['average_count']) {
                                    $type_average = round($vvv['average_sum'] / $vvv['average_count'], 2);
                                }

                                foreach ($months as $index => $month) {
                                    if (isset($vvv['months'][$index])) {
                                        $r = $vvv['months'][$index];

                                        $type_grades[] = [
                                            'first' => $month['first'],
                                            'grades' => $r
                                        ];
                                    } else {
                                        $type_grades[] = [
                                            'first' => $month['first'],
                                            'grades' => null
                                        ];
                                    }
                                }

                                $grades[$k]['lessons'][$kk]['types'][$kkk]['average'] = $type_average;
                                $grades[$k]['lessons'][$kk]['types'][$kkk]['months'] = $type_grades;
                            }
                        }
                    }

                    $grades[$k]['lessons'][$kk]['average'] = $lesson_average;
                    $grades[$k]['lessons'][$kk]['months'] = $lesson_grades;
                }

                $grades[$k]['months'] = $months;
            }
        }

        return View::init('tmpl/grades/index.tmpl', [
            'lesson_grades' => $grades
        ]);
            
    }

    public function newAction(): ?View
    {   
        $assignment_id = $this->request->get('assignment_id');
        $data = $this->getAssignmentData($assignment_id);

        if (!$data) {
            throw new ForbiddenException();
        }

        $users = $this->getLessonUsers($assignment_id);

        return View::init('tmpl/grades/form.tmpl', [
            'lesson_name' => $data['lesson_name'],
            'assignment_description' => $data['assignment_description'],
            'users' => $users,
            'assignment_id' => $assignment_id,
            'grade_type_options' => $this->getGradeTypeOptions($users),
            'path' => '/assignments/' . intval($assignment_id) . '/grades/create'
        ]);
    }

    public function createAction(): ?View
    {
        $view = new View();
        $assignment_id = $this->request->get('assignment_id');
        $data = $this->getAssignmentData($assignment_id);

        if (!$data) {
            throw new ForbiddenException();
        }

        $users = $this->getLessonUsers($assignment_id);
        $grades = $this->request->get('grades');

        if (empty($this->request->get('grade_type'))) {
            $grade = new Grade();

            $grade->addError('grade_type', 'empty');

            return $this->recordError($grade);
        }

        foreach ($users as $r) {
            if (!isset($grades[$r['user_id']])) {
                continue;
            }

            $grade = new Grade($r, !!$r['grade_id']);
            $value = $grades[$r['user_id']];

            if (strval($value) === '') {
                $grade->delete();

                continue;
            }

            $grade->setGrade($this->request->get('grade_type'), $value);

            if (!$grade->save()) {
                return $this->recordError($grade);
            }
        }

        $this->flash->notice('Atzīmes ir veiksmīgi saglabātas!');

        return $view->data([]);
    }


    private function getAssignmentData(?int $assignment_id): ?array
    {
        if (empty($assignment_id)) {
            return null;
        }

        $query = new DataQuery();

        $query
            ->select(
                'l.lesson_id',
                's.group_id',
                'l.lesson_name',
                'a.assignment_description'
            )
            ->from('assignment as a')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->where('l.user_id = ?', $this->current_user->id);

        return $query->fetch();
    }

    private function getLessonUsers(int $assignment_id): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'a.assignment_id',
                'u.user_id',
                'u.user_firstname',
                'u.user_lastname',
                'g.grade_id',
                'g.grade_type',
                'g.grade_numeric',
                'g.grade_percent',
                'g.grade_included',
                'ifnull(g.grade_numeric,ifnull(g.grade_percent,g.grade_included)) as grade_value'
            )
            ->from('assignment as a')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
            ->where('a.assignment_id = ?', $assignment_id);

        if ($this->current_user->organization_id) {
            $query
                ->join('group_user as gu on gu.group_id = s.group_id')
                ->join('user as u on u.user_id = gu.user_id');
        } else {
            $query
                ->join('lesson as l on l.lesson_id = s.lesson_id')
                ->leftJoin('group_lesson gl on gl.lesson_id = l.lesson_id')
                ->leftJoin(
                    'group_user gu on gu.group_id = gl.group_id' .
                    ' and gu.group_id = s.group_id'
                )
                ->leftJoin('lesson_user as lu on lu.lesson_id = l.lesson_id')
                ->join('user as u on u.user_id = ifnull(gu.user_id,lu.user_id)');
        }

        $query
            ->leftJoin(
                'grade g on g.assignment_id = a.assignment_id' .
                ' and g.user_id = u.user_id'
            )
            ->order('u.user_firstname', 'u.user_lastname');

        return $query->fetchAll();
    }

    private function getGradeTypeOptions(?array $users): array
    {
        $grade_type = null;

        foreach ($users as $user) {
            if ($user['grade_type']) {
                $grade_type = $user['grade_type'];

                break;
            }
        }

        $options = [[
            'name' => '1-10',
            'value' => 'numeric',
            'selected' => 'numeric' === $grade_type
        ], [
            'name' => 'procenti',
            'value' => 'percent',
            'selected' => 'percent' === $grade_type
        ], [
            'name' => 'i/ni',
            'value' => 'included',
            'selected' => 'included' === $grade_type
        ]];

        return $options;
    }

    private function getGroupLessons(): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'lessson_id',
                'lesson_name'
            )
            ->from('group_lesson as gl')
            ->join('lesson as l on l.lesson_id = gl.lesson_id')
            ->join('`group` as g on g.group_id = gl.group_id')
            ->join('group_user as gu on gu.group_id = g.group_id')
            ->where('gu.user_id = ?', $this->current_user->id)
            ->where('g.organization_id = ?', $this->current_user->organization_id);

        if (!$data = $query->fetchAll()) {
            return null;
        }

        return $data;
    }

    private function getLessonGrades($start, $end): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'x.*',
                'o.organization_name',
                'month(s.schedule_date) as month',
                'a.assignment_id',
                'a.assignment_type',
                'gr.grade_id',
                'gr.grade_type',
                'gr.grade_numeric',
                'gr.grade_percent',
                'gr.grade_included'
            )
            ->from('(
                select
                    l.lesson_id,
                    l.lesson_name,
                    g.group_id,
                    g.organization_id,
                    g.group_name,
                    u.user_id
                from lesson l
                left join lesson_user as lu on lu.lesson_id = l.lesson_id
                left join group_lesson as gl on gl.lesson_id = l.lesson_id
                left join group_user as gu on gu.group_id = gl.group_id
                left join `group` as g on g.group_id = gl.group_id
                join user u on u.user_id = ifnull(lu.user_id,gu.user_id)
                where u.user_id = ' . intval($this->current_user->id) . '
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
            ->leftJoin('assignment as a on a.schedule_id = s.schedule_id')
            ->leftJoin(
                'grade as gr on gr.assignment_id = a.assignment_id' .
                ' and gr.user_id = x.user_id'
            )
            ->leftJoin('organization as o on o.organization_id = x.organization_id')
            ->group(
                'x.group_id',
                'x.lesson_id',
                'month(s.schedule_date)',
                'a.assignment_id',
                'gr.grade_id'
            );

        $data = $query->fetchAll();
        $grades = null;

        foreach ($data as $r) {
            if (!isset($grades[$r['group_id']])) {
                $grades[$r['group_id']] = [
                    'group_id' => $r['group_id'],
                    'group_name' => $r['group_name'],
                    'organization_id' => $r['organization_id'],
                    'organization_name' => $r['organization_name'],
                    'lessons' => null
                ];
            }

            if (!isset($grades[$r['group_id']]['lessons'][$r['lesson_id']])) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']] = [
                    'lesson_id' => $r['lesson_id'],
                    'lesson_name' => $r['lesson_name'],
                    'average' => 0,
                    'average_sum' => 0,
                    'average_count' => 0,
                    'types' => null
                ];
            }

            if (!isset($grades[$r['group_id']]['lessons'][$r['lesson_id']]['months'][$r['month']])) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['months'][$r['month']] = [
                    'first' => false,
                    'average' => 0,
                    'average_sum' => 0,
                    'average_count' => 0
                ];
            }

            if (empty($r['assignment_type'])) {
                continue;
            }

            if (!isset($grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']])) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']] = [
                    'assignment_type' => $this->msg->t(
                        'assignment.types.' . $r['assignment_type']
                    ),
                    'average' => 0,
                    'average_sum' => 0,
                    'average_count' => 0,
                    'months' => null
                ];
            }

            if (empty($r['grade_id'])) {
                continue;
            }

            $grade = new Grade($r, true);

            if ($grade->isGradeNumeric() && $grade->grade) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['average_sum'] += $grade->grade;
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['average_count'] += 1;
                
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['months'][$r['month']]['average_sum'] += $grade->grade;
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['months'][$r['month']]['average_count'] += 1;

                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['average_sum'] += $grade->grade;
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['average_count'] += 1;
            }

            $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['months'][$r['month']][] = [
                'grade_color' => $grade->isGradeNumeric() ? 'blue' : 'gray',
                'grade_value' => $grade->grade_formatted,
                'assignment_path' => '/assignments/' . intval($r['assignment_id'])
            ];
        }

        return $grades;
    }
}
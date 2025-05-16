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
                        if (isset($vv['grades'][$index])) {
                            $r = $vv['grades'][$index];
                            $grade_numeric = $r['grade_numeric'];

                            if ($grade_numeric === '0') {
                                $grade_numeric = 'n\v';
                            }

                            $lesson_grades[] = [
                                'first' => $month['first'],
                                'grade_numeric' => $grade_numeric,
                                'grade_percent' => $r['grade_percent'],
                                'grade_included' => $r['grade_included'],
                            ];
                        } else {
                            $lesson_grades[] = [
                                'first' => $month['first'],
                                'grade_numeric' => null,
                                'grade_percent' => null,
                                'grade_included' => null
                            ];
                        }

                        foreach ($vv['types'] as $kkk => $vvv) {
                            $type_grades = null;
                            $type_average = 0;

                            if ($vvv['average_count']) {
                                $type_average = round($vvv['average_sum'] / $vvv['average_count'], 2);
                            }

                            foreach ($months as $index => $month) {
                                if (isset($vvv['grades'][$index])) {
                                    $r = $vvv['grades'][$index];
                                    $grade_numeric = $r['grade_numeric'];

                                    if ($grade_numeric === '0') {
                                        $grade_numeric = 'n\v';
                                    }

                                    $type_grades[] = [
                                        'first' => $month['first'],
                                        'grade_numeric' => $grade_numeric,
                                        'grade_percent' => $r['grade_percent'],
                                        'grade_included' => $r['grade_included'],
                                    ];
                                } else {
                                    $type_grades[] = [
                                        'first' => $month['first'],
                                        'grade_numeric' => null,
                                        'grade_percent' => null,
                                        'grade_included' => null
                                    ];
                                }
                            }

                            $grades[$k]['lessons'][$kk]['types'][$kkk]['average'] = $type_average;
                            $grades[$k]['lessons'][$kk]['types'][$kkk]['grades'] = $type_grades;
                        }
                    }

                    $grades[$k]['lessons'][$kk]['average'] = $lesson_average;
                    $grades[$k]['lessons'][$kk]['grades'] = $lesson_grades;
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
                'ifnull(g.grade_numeric,ifnull(g.grade_percent,g.grade_included)) as grade'
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

    // private function getGradeIncludedOptions(?int $grade_included): array
    // {
    //     $options = [[
    //         'name' => null,
    //         'value' => null,
    //         'selected' => false
    //     ], [
    //         'name' => 'i',
    //         'value' => '1',
    //         'selected' => '1' === strval($grade_included ?? '')
    //     ], [
    //         'name' => 'ni',
    //         'value' => '0',
    //         'selected' => '0' === strval($grade_included ?? '')
    //     ]];

    //     return $options;
    // }

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
                'a.assignment_type',
                'group_concat(gr.grade_numeric separator ", ") as grade_numeric',
                'group_concat(gr.grade_percent separator ", ") as grade_percent',
                'group_concat(gr.grade_included separator ", ") as grade_included'
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
            ->group('x.lesson_id, x.group_id, month(s.schedule_date)', 'a.assignment_type');

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
                    'average_sum' => 0,
                    'average_count' => 0,
                    'assignment_types' => null,
                    'grades' => null
                ];
            }

            if (!isset($grades[$r['group_id']]['lessons'][$r['lesson_id']]['grades'][$r['month']])) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['grades'][$r['month']] = [
                    'grade_numeric' => null,
                    'grade_percent' => null,
                    'grade_included' => null
                ];
            }

            if (empty($r['assignment_type'])) {
                continue;
            }

            if (!isset($grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']])) {
                $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']] = [
                    'assignment_type' => $r['assignment_type'],
                    'average_sum' => 0,
                    'average_count' => 0,
                    'grades' => null
                ];
            }

            if ($r['grade_numeric'] !== null) {
                $values = explode(', ', $r['grade_numeric']);

                foreach ($values as $value) {
                    $grades[$r['group_id']]['lessons'][$r['lesson_id']]['average_sum'] += $value;
                    $grades[$r['group_id']]['lessons'][$r['lesson_id']]['average_count'] += 1;

                    $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['average_sum'] += $value;
                    $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['average_count'] += 1;
                }
            }

            $grades[$r['group_id']]['lessons'][$r['lesson_id']]['types'][$r['assignment_type']]['grades'][$r['month']] = [
                'grade_numeric' => $r['grade_numeric'],
                'grade_percent' => $r['grade_percent'],
                'grade_included' => $r['grade_included']
            ];
        }

        return $grades;
    }
}
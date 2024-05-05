<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;

class TestController extends ApplicationController
{
    public function indexAction(): ?View
    {
        echo '<pre>';

        $data = [];
        $query = new DataQuery();

        $query
          ->select(
            'a.*',
            'u.user_firstname', 
            'u.user_lastname', 
            's.schedule_date',
            'lt.lesson_time_start_at',
            'lt.lesson_time_end_at',
            'l.lesson_name'
          )
          ->from('assignment as a')
          ->leftJoin('user as u on u.user_id = a.user_id')
          ->leftJoin('schedule as s on s.schedule_id = a.schedule_id')
          ->leftJoin('lesson_time as lt on lt.lesson_time_id = s.lesson_time_id')
          ->leftJoin('lesson as l on l.lesson_id = s.lesson_id');

        if ($this->request->get('lesson_id')) {
          $query->where('l.lesson_id = ?', $this->request->get('lesson_id'));
        }

        if ($this->request->get('assignment_description')) {
          $query->where('a.assignment_description = ?', $this->request->get('assignment_description'));
        }

        echo $query->get();

        $data = $query->fetchAll();

        echo $data;

        echo $this->request->get('user_type');
        echo $this->request->get('lesson_id');

        return null;

        $where = null;
        $variable = null;
        $db = DataStore::init();

        if ($this->request->get('lesson_id')) {
          $where[] = 'l.lesson_id = ?';
          $variable[] = $this->request->get('lesson_id');
        }

        if ($this->request->get('lesson_name')) {
          $where[] = 'l.lesson_name = ?';
          $variable[] = $this->request->get('lesson_name');
        }

        // if ($assignment_description) {
        //   $where[] = 'a.assignment_description = ?';
        //   $variable[] = assignment_description;
        // }

        // $where = [
        //   'l.lesson_id = ?',
        //   'a.assignment_description = ?'
        // ];
        // $variable = [
        //   $lesson_id,
        //   $assignment_description
        // ];

        $a =  '
          select 
              a.*,
              u.user_firstname, 
              u.user_lastname, 
              s.schedule_date,
              lt.lesson_time_start_at,
              lt.lesson_time_end_at,
              l.lesson_name
          from assignment as a
          left join user as u on u.user_id = a.user_id
          left join schedule as s on s.schedule_id = a.schedule_id
          left join lesson_time as lt on lt.lesson_time_id = s.lesson_time_id
          left join lesson as l on l.lesson_id = s.lesson_id'.($where ? ' where '.implode(' and ', $where) : '').'
        ';
        
        echo $a;

        $db->data($a, $variable);

        return null;    
    }

}
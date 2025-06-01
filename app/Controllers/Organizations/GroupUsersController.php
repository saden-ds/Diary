<?php

namespace App\Controllers\Organizations;

include_once(__ROOT__.'/vendor/autoload.php');

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Models\GroupUser;
use App\Models\Group;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GroupUsersController extends ApplicationController
{
    public function indexAction(): ?View 
    {
        if ($this->request->get('format') !== 'xlsx') {
            throw new NotFoundException();
        }

        $group = Group::find($this->request->get('group_id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if ($group->organization_id !== $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        $query = new DataQuery();

        $query
            ->select('gu.*')
            ->from('group_user as gu')
            ->where('gu.group_id = ?', $group->group_id);
        
        if (!$data = $query->fetchAll()) {
            return null;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $filename = $group->group_name . '_users.xlsx';

        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', $group->group_name);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);

        $row = 2;

        $sheet->setTitle($this->msg->t('group.users'));
        
        $style = $sheet->getStyle('A' . $row);

        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEBE7E2');

        $sheet->setCellValue('A' . $row, $this->msg->t('user.fullname'));

        $style = $sheet->getStyle('B' . $row);

        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEBE7E2');

        $sheet->setCellValue('B' . $row, $this->msg->t('user.email'));

        $row++;

        foreach ($data as $v) {
            $sheet->setCellValue('A' . $row, $v['group_user_name']);
            $sheet->setCellValue('B' . $row, $v['group_user_email']);

            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        return null;
    }

    public function newAction(): ?View 
    {
        $group = $this->findGroup();

        return View::init('tmpl/group_users/form.tmpl', [
            'path' => '/groups/' . $group->group_id . '/users/create'
        ]);
    }

    public function createAction(): ?View
    {
        $group_user = new GroupUser($this->request->permit([
            'group_id', 'group_user_name', 'group_user_email'
        ]));
        $view = new View();

        if ($group_user->create()) {
            $this->flash->notice('Lietotājs veiksmīgi pievienots grupai!');
            return $view->data([
                'group_user_id' => $group_user->group_user_id
            ]);
        } else {
            return $this->recordError($group_user);
        } 
    }

    public function deleteAction(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $group = Group::find($this->request->get('group_id'));
        $group_user = GroupUser::find($this->request->get('group_user_id'));

        if ($group->group_id != $group_user->group_id) {
            throw new ForbiddenException();
        }

        $group_user->delete();

        $this->flash->notice('Lietotājs veiksmīgi dzēsts no grupas!');

        return $this->redirect('/groups/' . $group->group_id);
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
}
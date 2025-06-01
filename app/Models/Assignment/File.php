<?php

namespace App\Models\Assignment;

use App\Base\DataQuery;
use App\Base\File as BaseFile;
use App\Models\Model as Model;
use App\Validators\Length as ValidatorLength;
use App\Validators\Presence as ValidatorPresence;

class File extends Model {

    static $attributes_mapping = [
        'assignment_file_id' => ['type' => 'integer'],
        'assignment_file_name' => ['type' => 'string'],
        'assignment_file_type' => ['type' => 'string'],
        'assignment_file_checksum' => ['type' => 'string'],
        'assignment_file_created_at' => ['type' => 'datetime'],
        'assignment_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'assignment_file';
    protected static ?string $primary_key = 'assignment_file_id';
    private ?BaseFile $file = null;
    private ?string $file_path = null;
    private ?string $tmp_file = null;


    public static function findByIdAndUserId($id, $user_id) {
        $query = new DataQuery();

        $query
            ->select('*')
            ->from('assignment_file')
            ->where('assignment_file_id = ?', $id)
            ->where('user_id = ?', $user_id);

        if (!$id || !$user_id || !$r = $query->first()) {
            return null;
        }

        return new self($r, true);
    }


    public function setTmpFile($value) {
        if (empty($value)) {
            return;
        }

        if (!is_array($value)) {
            $this->tmp_file = $value;
            $this->assignment_file_checksum = md5_file($value);

            return;
        }

        if ($value['tmp_name'] ?? null) {
            $this->tmp_file = $value['tmp_name'];
            $this->assignment_file_checksum = md5_file($value['tmp_name']);
        }

        if ($value['error'] ?? null) {
            $this->addError('base', $value['error']);
        }

        $this->assignment_file_name = $value['name'] ?? null;
        $this->assignment_file_type = $value['type'] ?? null;
    }

    public function getFile() {
        if ($this->file === null) {
            $this->file = new BaseFile($this->getFilePath());
        }

        return $this->file;
    }

    public function getFilePath() {
        if ($this->file_path) {
            return $this->file_path;
        }

        $dir = $this->config->get('data.assignment_file');

        $this->file_path = $dir . '/' . $this->assignment_file_id;

        return $this->file_path;
    }

    public function create($attributes = null): bool
    {
        if (!$this->validateAndCreateRecord($attributes)) {
            return false;
        }

        $this->createFile();

        return true;
    }

    public function update($attributes = null): bool
    {
        return $this->validateAndUpdateRecord($attributes);
    }

    public function delete(): bool
    {
        if (!$this->db->query('
            delete from assignment_file
            where assignment_file_id = ?
        ', $this->assignment_file_id)) {
            return false;
        }

        $file = $this->getFile();

        $file->unlink();

        return true;
    }


    protected function validate(): void
    {
        if (!$this->config->get('data.assignment_file')) {
            $this->addError('base', $this->msg->t('error.config'));
            return;
        }

        $presence = new ValidatorPresence([
            'assignment_file_name', 'assignment_id'
        ]);
        $presence->validate($this);

        $length = new ValidatorLength([
            'assignment_file_name', 'assignment_file_type'
        ], [
            'maximum' => 190, 'allow_empty' => true
        ]);
        $length->validate($this);

        $this->validateExists();
    }


    private function validateExists() {
        if (
            $this->assignment_file_id ||
            !$this->assignment_file_checksum ||
            !$this->assignment_id
        ) {
            return;
        }

        $query = new DataQuery();

        $query
            ->select('1 as one')
            ->from('assignment_file')
            ->where('assignment_id = ?', $this->assignment_id)
            ->where('assignment_file_checksum = ?', $this->assignment_file_checksum);

        if ($query->first()) {
            $this->addError('base', $this->msg->t('error.exists'));
        }
    }

    private function createFile() {
        if ($this->tmp_file) {
            $file = new BaseFile($this->tmp_file);

            $file->moveUploadedFile($this->getFilePath());
        }
    }
}
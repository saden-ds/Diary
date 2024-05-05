<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use DateTime;

class ConversationsController extends PrivateController
{
    public function indexAction(): ?View
    {
        return View::init('tmpl/conversations/index.tmpl')
            ->data([
                'conversations' => $this->getConversations()
            ]);
    }

    public function showAction(): ?View
    {

        // $db->data( -- return array
        // $db->row( -- return row
        // $db->query( -- to set, update... 

        $id = intval($this->request->get('id'));
        $db = DataStore::init();
        $name = null;
        $conversation = $db->row('
            select *
            from conversation
            where conversation_id = ?
        ', [
            $id
        ]);

        if ($conversation) {
            $name = $conversation['conversation_name'];
        }

        if ($this->request->get('message')) {
            $db->query('
                insert into message (message_text, conversation_id, user_id, message_datetime)
                values (?, ?, ?, now())
            ', [
                $this->request->get('message'),
                $id,
                $this->current_user->id
            ]);
        }

        return View::init('tmpl/conversations/show.tmpl')
            ->data([
                'avatar' => '/img/avatar.jpeg',
                'name' => $name,
                'conversations' => $this->getConversations($id),
                'messages' => $this->getMessages($id)
            ]);
    }

    public function newAction(): ?View
    {
        return View::init('tmpl/conversations/form.tmpl')
            ->data([
                'text' => null,
                'conversations' => $this->getConversations(),
                'path' => '/conversations/create'
            ]);
    }

    public function editAction(): ?View
    {
        $id = $this->request->get('id');

        return View::init('tmpl/conversations/form.tmpl')
            ->data([
                'error' => null,
                'text' => $id,
                'path' => '/conversations/' . $id . '/update'
            ]);
    }

    public function createAction(): ?View 
    {
        $error = null;
        $text = $this->request->get('text');

        if (!$text) {
            $error = 'Text is required';
        }

        if ($error) {
            return View::init('tmpl/conversations/form.tmpl')
                ->data([
                    'error' => $error,
                    'text' => $text,
                    'path' => '/conversations/create'
                ]);
        }



        $this->request->redirect('/conversations');

        return null;
    }


    private function getConversations(?int $id = null): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select x.*, m.message_text, m.message_datetime
            from (
                select c.*, max(m.message_id) as max_message_id
                from conversation as c
                left join message m on m.conversation_id = c.conversation_id
                group by c.conversation_id
            ) x
            left join message m on m.message_id = x.max_message_id
        ');

        if (!$data) {
            return null;
        }

        $conversations = null;

        foreach ($data as $r) {
            $datetime = new DateTime($r['message_datetime']);
            $conversations[] = [
                'avatar' => '/img/avatar.jpeg',
                'name' => $r['conversation_name'],
                'time' => $datetime ? $datetime->format('d.m.Y.') : null,
                'text' => $r['message_text'],
                'path' => '/conversations/' . $r['conversation_id'],
                'active' => $r['conversation_id'] == $id
            ];            
        }

        // error_log(print_r($conversations, true));

        return $conversations;
    }

    private function getMessages(int $conversation_id): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select m.message_text, m.message_datetime, m.user_id, u.user_firstname, u.user_lastname
            from message as m
            join user u on u.user_id = m.user_id
            where m.conversation_id = ?
            order by m.message_id
        ', [
            $conversation_id
        ]);

        if (!$data) {
            return null;
        }

        $messages = null;

        foreach ($data as $r) {
            $messages[] = [
                'username' => $r['user_firstname'].' '.$r['user_lastname'],
                'text' => $r['message_text'],
                'time' => $r['message_datetime'],
                'incoming' => $r['user_id'] !== 1
            ];            
        }

        return $messages;
    }
}
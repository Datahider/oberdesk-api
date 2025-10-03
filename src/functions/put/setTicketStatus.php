<?php

namespace losthost\OberdeskAPI\functions\put;

use losthost\OberdeskAPI\functions\AbstractFunctionImplementation;
use losthost\OberbotModel\data\ticket;

class setTicketStatus extends AbstractFunctionImplementation {
    
    protected ticket $ticket;
    protected int $status;


    public function checkParams(array $params): true {
        $this->checkParamTicketGroup($params);
        $this->checkParamStatus($params);
        return true;
    }

    public function run(array $params): array {
        try {
            $this->statusChange();
        } catch (\Exception $exc) {
            return [
                'ok' => false,
                'error' => $exc->getMessage()
            ];
        }
        return [
            'ok' => true
        ];
    }
    
    protected function statusChange() {
        error_log("status: $this->status; ticket: ({$this->ticket->id}){$this->ticket->title}");
        switch ($this->status) {
            case ticket::STATUS_CREATING:
                throw new \Exception('Нельзя установить статус STATUS_CREATING для существующего тикета');
            case ticket::STATUS_NEW:
                $this->ticket->accept();
                break;
            case ticket::STATUS_REOPEN:
                $this->ticket->reopen();
                break;
            case ticket::STATUS_IN_PROGRESS:
                throw new \Exception('STATUS_IN_PROGRESS устанавливается автоматически при запуске таймера агентом');
            case ticket::STATUS_AWAITING_USER:
                $this->ticket->awaitUser();
                break;
            case ticket::STATUS_USER_ANSWERED:
                $this->ticket->userAnswered();
                break;
            case ticket::STATUS_CLOSED:
                $this->ticket->close();
                break;
            case ticket::STATUS_ARCHIVED:
                $this->ticket->archive();
                break;
        }
    }
    
    protected function checkParamTicketGroup($params) {
        $this->ticket = new ticket(['id' => $params['ticket'], 'chat_id' => $params['group']]);
    }
    
    protected function checkParamStatus($params) {
        
        $const_name = $params['status'];
        
        if (!$const_name) {
            throw new \Exception('Не задан обязательный параметр status');
        }
        
        if (!defined(ticket::class. "::$const_name")) {
            throw new \Exception('Неизвестный статус '. $const_name);
        }
        
        $this->status = constant(ticket::class. "::$const_name");
    }
}

<?php
    require_once('../constants/Constant.php');

    class TaskException extends Exception {}

    class TaskModel
    {
        public $_id;
        public $_title;
        public $_description;
        public $_deadline;
        public $_completed;
        
        public function __construct($id, $title, $description, $deadline, $completed){
            $this->setId($id);
            $this->setTitle($title);
            $this->setDescription($description);
            $this->setDeadline($deadline);
            $this->setCompleted($completed);
        }
        
        public function getId(){
            return $this->_id;
        }

        public function getTitle(){
            return $this->_title;
        }

        public function getDescription(){
            return $this->_description;
        }
        
        public function getDeadline(){
            return $this->_deadline;
        }
        
        public function getCompleted(){
            return $this->_completed;
        }

        public function setId($id){
            if (($id !== null) && ((!is_numeric($id)) || ($id <= 0) || ($id > TaskConstant::MaxNumberID) || ($this->_id !== null))) {
                throw new TaskException(TaskConstant::ErrorMessageID);
            }
            
            $this->_id = $id;
        }
        
        public function setTitle($title){
            if (($title == null) || strlen($title) < 0 && strlen($title) > TaskConstant::MaxTitleLength) {
                throw new TaskException(TaskConstant::ErrorMessageTitle);
            }
                        
            $this->_title = $title;
        }

        public function setDescription($description){
            if ($description !== null && strlen($description) > TaskConstant::MaxDescriptionLength) {
                throw new TaskException(TaskConstant::ErrorMessageDescription);
            }
                                    
            $this->_description = $description;
        }
        
        public function setDeadline($deadline){
            if ($deadline !== null && date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') != $deadline) {
                throw new TaskException(TaskConstant::ErrorMessageDeadline);
            }
                        
            $this->_deadline = $deadline;
        }

        public function setCompleted($completed){
            if (strtoupper($completed) !== TaskConstant::YES && strtoupper($completed) !== TaskConstant::NO) {
                throw new TaskException(TaskConstant::ErrorMessageCompleted);
            }

            $this->_completed = $completed;
        }

        public function taskToArray(){
            $task = array();
            $task['id'] = $this->getId();
            $task['title'] = $this->getTitle();
            $task['description'] = $this->getDescription();
            $task['deadline'] = $this->getDeadline();
            $task['completed'] = $this->getCompleted();
            return $task;
        }
    }
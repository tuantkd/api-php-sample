<?php
    require_once('../constants/Constant.php');

    class ImageException extends Exception {}

    class ImageModel {
        private $_id;
        private $_title;
        private $_fileName;
        private $_mimeType;
        private $_taskId;
        private $_uploadFolderLocation;

        public function __construct($id, $title, $fileName, $mimeType, $taskId)
        {
            $this->setId($id);
            $this->setTitle($title);
            $this->setFileName($fileName);
            $this->setMimeType($mimeType);
            $this->setTaskId($taskId);
            $this->_uploadFolderLocation = "../../upload_images/";
        }

        public function getId() 
        { 
            return $this->_id;
        }

        public function getTitle()
        { 
            return $this->_title; 
        }

        public function getFileName()
        {
            return $this->_fileName; 
        }

        public function getFileExtension()
        {
            $fileNameParts = explode('.', $this->_fileName);
            $lastArrayElement = count($fileNameParts) - 1;
            $fileExtension = $fileNameParts[$lastArrayElement];
            return $fileExtension;
        }

        public function getMimeType()
        {
            return $this->_mimeType; 
        }

        public function getTaskId()
        {
            return $this->_taskId; 
        }

        public function getUploadFolderLocation()
        {
            return $this->_uploadFolderLocation; 
        }
        
        public function getImageUrl()
        {
            $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? ImageConstant::HTTPS : ImageConstant::HTTP);
            $host = $_SERVER['HTTP_HOST'];
            $url = ImageConstant::V1NameControl . $this->getTaskId() . ImageConstant::Images . $this->getId();

            return $httpOrHttps . $host . $url;
        }

        public function returnImageFile()
        {
            $filePath = $this->getUploadFolderLocation().$this->getTaskId().'/'.$this->getFileName();
            if (!file_exists($filePath)){
                throw new ImageException(ImageConstant::ImageFileNotFound);
            }

            header('Content-Type: '.$this->getMimeType());
            header('Content-Disposition: inline; filename="'.$this->getFileName().'"');

            if (!readfile($filePath)){
                http_response_code(404);
                exit;
            }
            
            exit;
        }

        public function setId($id)
        {
            if (($id !== null) && (!is_numeric($id)) || ($id < 0) || ($id >= ImageConstant::MaxNumberID) || $this->_id !== null) {
                throw new ImageException(ImageConstant::ImageIDError);
            }

            $this->_id = $id;
        }

        public function setTitle($title)
        {
            if (strlen($title) < 1 || strlen($title) > 255) {
                throw new ImageException(ImageConstant::ImageTitleError);
            }

            $this->_title = $title;
        }

        public function setFileName($filename)
        {
            if (strlen($filename) < 1 || strlen($filename) > 30 || preg_match("/^[a-zA-Z0-9_-]+(.jpg|.gif|.png)$/", $filename) !== 1) {
                throw new ImageException(ImageConstant::ImageFileNameError);
            }

            $this->_fileName = $filename;
        }

        public function setMimeType($mimetype)
        {
            if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {
                throw new ImageException(ImageConstant::ImageMimeTypeError);
            }

            $this->_mimeType = $mimetype;
        }

        public function setTaskId($taskid)
        {
            if (($taskid !== null) && (!is_numeric($taskid)) || ($taskid <= 0) || ($taskid >= ImageConstant::MaxNumberID) || $this->_taskId !== null) {
                throw new ImageException(ImageConstant::ImageIDError);
            }

            $this->_taskId = $taskid;
        }

        public function saveImageFile($tempFilename)
        {
            $uploadedFilePath = $this->getUploadFolderLocation().$this->getTaskId().'/'.$this->getFileName();
            if(!is_dir($this->getUploadFolderLocation().$this->getTaskId())){
                if(!mkdir($this->getUploadFolderLocation().$this->getTaskId())){
                    throw new ImageException(ImageConstant::ImageFailCreateFolder);
                }
            }

            if (!file_exists($tempFilename)){
                throw new ImageException(ImageConstant::FailedUploadImage);
            }

            if (!move_uploaded_file($tempFilename, $uploadedFilePath)) {
                throw new ImageException(ImageConstant::FailedUploadImage);
            }
        }

        public function renameImageFile($oldFileName, $newFileName)
        {
            $originalFilename = $this->getUploadFolderLocation().$this->getTaskId().'/'.$oldFileName;
            $renameFilename = $this->getUploadFolderLocation().$this->getTaskId().'/'.$newFileName;

            if (!file_exists($originalFilename)){
                throw new ImageException(ImageConstant::FailedRenameImage);
            }

            if (!rename($originalFilename, $renameFilename)){
                throw new ImageException(ImageConstant::FailedRenameImage);
            }
        }

        public function returnImagesAsArray() 
        {
            $images = array();
            $images['id'] = $this->getId();
            $images['title'] = $this->getTitle();
            $images['filename'] = $this->getFileName();
            $images['mimetype'] = $this->getMimeType();
            $images['taskid'] = $this->getTaskId();
            $images['taskid'] = $this->getTaskId();
            $images['imageurl'] = $this->getImageUrl();

            return $images;
        }
    }
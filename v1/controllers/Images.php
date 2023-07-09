<?php
    require_once('../constants/Constant.php');
    require_once('../configs/Database.php');
    require_once('../models/Response.php');
    require_once('../models/ImageModel.php');

    function sendResponse($statusCode, $success, $message = null, $tocache = false, $data = null)
    {
        $res = new Response();
        $res->setSuccess($success);
        $res->setHttpStatusCode($statusCode);

        if ($message !== null) {
            $res->addMessages($message);
        }

        if ($data !== null) {
            $res->setData($data);
        }

        $res->toCache($tocache);
        $res->send();
        exit;
    }

    function checkAuthStatusAndReturnUserID($write)
    {
        // Begin Auth Script
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
            $message = null;
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])){
                $message = SessionValidateConstant::AccessTokenMissHeader;
            } else {
                if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                    $message = SessionValidateConstant::AccessTokenBlank;
                }
            }
            sendResponse(401, false, $message);
        }

        $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

        try {
            $query = $write->prepare('SELECT user_id, access_token_expiry, user_active, user_attempts FROM tbl_sessions, tbl_users
                WHERE tbl_users.id = tbl_sessions.user_id AND tbl_sessions.access_token = :accesstoken');
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                sendResponse(401, false, SessionValidateConstant::InvalidAccessToken);
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returnedUserId = $row['user_id'];
            $returnedAccessTokenExpiry = $row['access_token_expiry'];
            $returnedUserActive = $row['user_active'];
            $returnedUserAttempts = $row['user_attempts'];

            if ($returnedUserActive !== SessionValidateConstant::AccountActive) {
                sendResponse(401, false, SessionValidateConstant::ErrorNotActive);
            }

            if ($returnedUserAttempts >= SessionValidateConstant::NumberOfLogins) {
                sendResponse(401, false, SessionValidateConstant::AccountLocked);
            }

            if (strtotime($returnedAccessTokenExpiry) < time()) {
                sendResponse(401, false, SessionValidateConstant::RefreshTokenExpried);
            }

            return $returnedUserId;

        } catch (PDOException $ex) {
            sendResponse(500, false, Constant::AuthenticatingIssue);
        }

        // End Auth Script
    }

    function uploadImageRoute($readDb, $writeDb, $taskId, $returnedUserId)
    {
        try {
            if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data; boundary=') === false) {
                sendResponse(400, false, ImageConstant::ContentTypeFormData);
            }

            $query = $readDb->prepare('SELECT id FROM tbl_task WHERE id = :taskid AND user_id = :userid');
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                sendResponse(404, false, TaskValidateConstant::NotFoundID);
            }

            if (!isset($_POST['attributes'])) {
                sendResponse(400, false, ImageConstant::AttributesBodyRequest);
            }

            $jsonImagesAttributes = json_decode($_POST['attributes']);
            if (!$jsonImagesAttributes) {
                sendResponse(400, false, ImageConstant::AttributesInvalidJson);
            }

            if (!isset($jsonImagesAttributes->title) || !isset($jsonImagesAttributes->filename) || $jsonImagesAttributes->title == '' || $jsonImagesAttributes->filename == '') {
                sendResponse(400, false, ImageConstant::TitleAndFilename);
            }

            if (strpos($jsonImagesAttributes->filename, '.') > 0) {
                sendResponse(400, false, ImageConstant::FileNameExtension);
            }

            if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) {
                sendResponse(500, false, ImageConstant::ImageFileUnSuccess);
            }

            $imageFileDetail = getimagesize($_FILES['imagefile']['tmp_name']);

            if (isset($_FILES['imagefile']) && $_FILES['imagefile']['size'] > ImageConstant::SizeImage5MB) {
                sendResponse(400, false, ImageConstant::SizeImageMessage);
            }

            $allowedImageFileTypes = ImageConstant::ImageFileTypes;

            if (!in_array($imageFileDetail['mime'], $allowedImageFileTypes)) {
                sendResponse(400, false, ImageConstant::FileTypeNotSupport);
            }

            $fileExtension = "";

            switch ($imageFileDetail['mime']) {
                case "image/jpeg":
                    $fileExtension = ".jpg";
                    break;
                case "image/png":
                    $fileExtension = ".png";
                    break;
                case "image/gif":
                    $fileExtension = ".gif";
                    break;
                default:
                    break;
            }

            if ($fileExtension === "") {
                sendResponse(400, false, ImageConstant::InvalidFileExtension);
            }

            $image = new ImageModel(null, $jsonImagesAttributes->title, $jsonImagesAttributes->filename.$fileExtension, $imageFileDetail['mime'], $taskId);
            $imageTitle = $image->getTitle();
            $imageNewFilename = $image->getFilename();
            $imageMimeType = $image->getMimeType();

            $query = $readDb->prepare('SELECT tbl_images.id FROM tbl_task, tbl_images WHERE tbl_images.task_id = tbl_task.id
            AND tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.filename = :filename');
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->bindParam(':filename', $imageNewFilename, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount !== 0) {
                sendResponse(409, false, ImageConstant::FileNameExistsError);
            }

            $writeDb->beginTransaction();
            $query = $writeDb->prepare('INSERT INTO tbl_images (title, filename, mimetype, task_id) VALUES (:title, :filename, :mimetype, :taskid)');
            $query->bindParam(':title', $imageTitle, PDO::PARAM_STR);
            $query->bindParam(':filename', $imageNewFilename, PDO::PARAM_STR);
            $query->bindParam(':mimetype', $imageMimeType, PDO::PARAM_STR);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                if ($writeDb->inTransaction()) {
                    $writeDb->rollback();
                }
                sendResponse(500, false, ImageConstant::FailedUploadImage);
            }

            $lastImageId = $writeDb->lastInsertId();
            $query = $writeDb->prepare("SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id");
            $query->bindParam(':imageid', $lastImageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                if ($writeDb->inTransaction()) {
                    $writeDb->rollback();
                }
                sendResponse(500, false, ImageConstant::FailedRetrieveImage);
            }

            $imagesArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageFromDB = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
                $imagesArray = $imageFromDB->returnImagesAsArray();
            }
            $imageFromDB->saveImageFile($_FILES['imagefile']['tmp_name']);
            $writeDb->commit();

            sendResponse(200, true, ImageConstant::UploadImageSuccess, false, $imagesArray);

        } catch (PDOException $ex) {
            error_log("Connection error - " . $ex, 0);
            if ($writeDb->inTransaction()) {
                $writeDb->rollback();
            }
            sendResponse(500, false, ImageConstant::FailedUploadImage);
        } catch (ImageException $ex) {
            if ($writeDb->inTransaction()) {
                $writeDb->rollback();
            }
            sendResponse(500, false, $ex->getMessage());
        }
    }

    function getImageAttributesRoute($readDb, $taskId, $imageId, $returnedUserId)
    {
        try {
            $query = $readDb->prepare('SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id');
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $imageArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
                $imageArray = $image->returnImagesAsArray();
            }
            sendResponse(200, true, null, true, $imageArray);

        } catch (PDOException $ex) {
            error_log("Connection error - " . $ex, 0);
            sendResponse(500, false, ImageConstant::FailedImageAttribute);
        } catch (ImageException $ex) {
            sendResponse(500, false, $ex->getMessage());
        }
    }

    function updateImageAttributesRoute($writeDb, $taskId, $imageId, $returnedUserId)
    {
        try {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                sendResponse(400, false, Constant::InvalidContentType);
            }

            $rawPatchData = file_get_contents('php://input');
            $jsonData = json_decode($rawPatchData);
            if (!$jsonData) {
                sendResponse(400, false, Constant::InvalidReqBody);
            }

            $titleUpdated = false;
            $fileNameUpdated = false;

            $queryFields = "";

            if (isset($jsonData->title)) {
                $titleUpdated = true;
                $queryFields .= "tbl_images.title = :title, ";
            }

            if (isset($jsonData->filename)) {
                if (strpos($jsonData->filename, '.') !== false) {
                    sendResponse(400, false, ImageConstant::FileNameDotExtension);
                }
                $fileNameUpdated = true;
                $queryFields .= "tbl_images.filename = :filename, ";
            }

            $queryFields = rtrim($queryFields, ", ");

            if ($titleUpdated === false && $fileNameUpdated === false) {
                sendResponse(400, false, ImageConstant::NoImageFields);
            }

            $writeDb->beginTransaction();
            $query = $writeDb->prepare("SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_images.task_id = :taskid AND tbl_images.task_id = tbl_task.id AND tbl_task.user_id = :userid");
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                if ($writeDb->inTransaction()) {
                    $writeDb->rollback();
                }
                sendResponse(404, false, ImageConstant::FailedNoImageUpdate);
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
            }

            $queryString = "UPDATE tbl_images INNER JOIN tbl_task
            ON tbl_images.task_id = tbl_task.id SET " . $queryFields . " WHERE tbl_images.id = :imageid AND tbl_images.task_id = tbl_task.id AND tbl_images.task_id = :taskid AND tbl_task.user_id = :userid";
            $query = $writeDb->prepare($queryString);

            if ($titleUpdated === true) {
                $image->setTitle($jsonData->title);
                $updateTitle = $image->getTitle();
                $query->bindParam(':title', $updateTitle);
            }

            if ($fileNameUpdated === true) {
                $originalFileName = $image->getFileName();
                $image->setFileName($jsonData->filename . "." .$image->getFileExtension());
                $updateFileName = $image->getFileName();
                $query->bindParam(':filename', $updateFileName);
            }

            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                if ($writeDb->inTransaction()) {
                    $writeDb->rollback();
                }
                sendResponse(400, false, ImageConstant::NoUpdateImageAttribute);
            }

            $query = $writeDb->prepare("SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_images.task_id = :taskid AND tbl_images.task_id = tbl_task.id AND tbl_task.user_id = :userid");
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                if ($writeDb->inTransaction()) {
                    $writeDb->rollback();
                }
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $imageArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
                $imageArray = $image->returnImagesAsArray();
            }

            if ($fileNameUpdated === true) {
                $image->renameImageFile($originalFileName, $updateFileName);
            }

            $writeDb->commit();

            sendResponse(200, true, ImageConstant::ImageUpdateSuccess, false, $imageArray);

        } catch (PDOException $ex) {
            error_log("Connection error - " . $ex, 0);
            if ($writeDb->inTransaction()) {
                $writeDb->rollback();
            }
            sendResponse(500, false, ImageConstant::FailedUpdateImageAttribute);
        } catch (ImageException $ex) {
            if ($writeDb->inTransaction()) {
                $writeDb->rollback();
            }
            sendResponse(500, false, $ex->getMessage());
        }
    }

    function getImageRoute($readDb, $taskId, $imageId, $returnedUserId)
    {
        try {
            $query = $readDb->prepare('SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id');
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $image = null;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
            }

            if ($image == null) {
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $image->returnImageFile();

        } catch (PDOException $ex) {
            error_log("Connection error - " . $ex, 0);
            sendResponse(500, false, ImageConstant::FailedGetImage);
        } catch (ImageException $ex) {
            sendResponse(500, false, $ex->getMessage());
        }
    }

    function deleteImageRoute($writeDb, $taskId, $imageId, $returnedUserId)
    {
        try {
            $writeDb->beginTransaction();
            $query = $writeDb->prepare('SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id');
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $image = null;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
            }

            if ($image == null) {
                $writeDb->rollback();
                sendResponse(500, false, ImageConstant::FailedGetImage);
            }

            $query = $writeDb->prepare('DELETE tbl_images FROM tbl_images, tbl_task
            WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_images.task_id = tbl_task.id AND tbl_task.user_id = :userid');
            $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
            $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
            $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                $writeDb->rollback();
                sendResponse(404, false, ImageConstant::ImageNotFound);
            }

            $image->deleteImageFile();

            $writeDb->commit();

            sendResponse(200, true, ImageConstant::ImageDeleteSuccess);

        } catch (PDOException $ex) {
            error_log("Connection error - " . $ex, 0);
            $writeDb->rollback();
            sendResponse(500, false, ImageConstant::FailedDeleteImage);
        } catch (ImageException $ex) {
            $writeDb->rollback();
            sendResponse(500, false, $ex->getMessage());
        }
    }

    try {
        $write = Database::connectWriteDB();
        $read = Database::connectReadDB();
    } catch (PDOException $ex) {
        error_log("Connection error - " . $ex, 0);
        sendResponse(500, false, Constant::ErrorConnectDB);
    }

    $returnedUserID = checkAuthStatusAndReturnUserID($write);

    if (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET) && array_key_exists("attributes", $_GET))
    {
        $taskId = $_GET["taskid"];
        $imageId = $_GET["imageid"];
        $attributes = $_GET["attributes"];

        if ($taskId == "" || !is_numeric($taskId) || $imageId == "" || !is_numeric($imageId)){
            sendResponse(400, false, ImageConstant::ImageIDTaskID);
        }

        if ($_SERVER["REQUEST_METHOD"] == "GET"){
            getImageAttributesRoute($read, $taskId, $imageId, $returnedUserID);
        } else if ($_SERVER["REQUEST_METHOD"] == "PATCH") {
            updateImageAttributesRoute($write, $taskId, $imageId, $returnedUserID);
        } else {
            sendResponse(405, false, Constant::ErrorReqMethod);
        }

    } elseif (array_key_exists("taskid", $_GET) && array_key_exists("imageid", $_GET)) {
        $taskId = $_GET["taskid"];
        $imageId = $_GET["imageid"];

        if ($taskId == "" || !is_numeric($taskId) || $imageId == "" || !is_numeric($imageId)){
            sendResponse(400, false, ImageConstant::ImageIDTaskID);
        }

        if ($_SERVER["REQUEST_METHOD"] == "GET"){
            getImageRoute($read, $taskId, $imageId, $returnedUserID);
        } else if ($_SERVER["REQUEST_METHOD"] == "DELETE"){
            deleteImageRoute($write, $taskId, $imageId, $returnedUserID);
        } else {
            sendResponse(405, false, Constant::ErrorReqMethod);
        }

    } else if (array_key_exists("taskid", $_GET)) {
        $taskId = $_GET["taskid"];

        if ($taskId == "" || !is_numeric($taskId)){
            sendResponse(400, false, ImageConstant::ImageIDTaskID);
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            uploadImageRoute($read, $write, $taskId, $returnedUserID);
        } else {
            sendResponse(405, false, Constant::ErrorReqMethod);
        }
    } else {
        sendResponse(404, false, Constant::InvalidReqType);
    }
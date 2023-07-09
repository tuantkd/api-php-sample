<?php
    require_once('../constants/Constant.php');
    require_once('../configs/Database.php');
    require_once('../models/Response.php');
    require_once('../models/TaskModel.php');
    require_once('../models/ImageModel.php');

    function retrieveImages($dbConn, $taskId, $returnedUserId)
    {
        $imageQuery = $dbConn->prepare('SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
        WHERE tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id');
        $imageQuery->bindParam(':taskid', $taskId, PDO::PARAM_INT);
        $imageQuery->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
        $imageQuery->execute();

        $imageArray = array();
        while ($row = $imageQuery->fetch(PDO::FETCH_ASSOC)) {
            $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
            $imageArray[] = $image->returnImagesAsArray();
        }

        return $imageArray;
    }

    try {
        $write = Database::connectWriteDB();
        $read = Database::connectReadDB();
    } catch (PDOException $ex) {
        error_log("Connection error - " . $ex, 0);
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(500);
        $res->addMessages(Constant::ErrorConnectDB);
        $res->send();
        exit;
    }

    // ================================================
    // Begin Auth Script
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(401);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $res->addMessages(SessionValidateConstant::AccessTokenMissHeader) : false);
        (isset($_SERVER['HTTP_AUTHORIZATION']) && (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) ? $res->addMessages(SessionValidateConstant::AccessTokenBlank) : false);
        $res->send();
        exit;
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    try {
        $query = $read->prepare('SELECT user_id, access_token_expiry, user_active, user_attempts FROM tbl_sessions, tbl_users
            WHERE tbl_users.id = tbl_sessions.user_id AND tbl_sessions.access_token = :accesstoken');
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount == 0) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(401);
            $res->addMessages(SessionValidateConstant::InvalidAccessToken);
            $res->send();
            exit;
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $returnedUserId = $row['user_id'];
        $returnedAccessTokenExpiry = $row['access_token_expiry'];
        $returnedUserActive = $row['user_active'];
        $returnedUserAttempts = $row['user_attempts'];

        if ($returnedUserActive !== SessionValidateConstant::AccountActive) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(401);
            $res->addMessages(SessionValidateConstant::ErrorNotActive);
            $res->send();
            exit;
        }

        if ($returnedUserAttempts >= SessionValidateConstant::NumberOfLogins) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(401);
            $res->addMessages(SessionValidateConstant::AccountLocked);
            $res->send();
            exit;
        }

        if (strtotime($returnedAccessTokenExpiry) < time()) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(401);
            $res->addMessages(SessionValidateConstant::RefreshTokenExpried);
            $res->send();
            exit;
        }
    } catch (PDOException $ex) {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(500);
        $res->addMessages(Constant::AuthenticatingIssue);
        $res->send();
        exit;
    }

    // End Auth Script
    // ================================================

    if (array_key_exists("id", $_GET)) {
        $taskId = $_GET["id"];

        if ($taskId == "" || !is_numeric($taskId)) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(400);
            $res->addMessages(TaskValidateConstant::ErrorID);
            $res->send();
            exit;
        }

        if ($_SERVER["REQUEST_METHOD"] == "GET") {
            try {
                $query = $read->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task
                    WHERE id = :taskId AND user_id = :userId');
                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();
    
                $rowCount = $query->rowCount();
                if ($rowCount == 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(404);
                    $res->addMessages(TaskValidateConstant::NotFoundID);
                    $res->send();
                    exit;
                }
    
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($read, $taskId, $returnedUserId);
                    $task = new TaskModel($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                    $taskArray[] = $task->taskToArray();
                }
    
                $returnData = array();
                $returnData["rows_returned"] = $rowCount;
                $returnData["data"] = $taskArray;
    
                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->setData($returnData);
                $res->send();
                exit;
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (ImageException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedGetData);
                $res->send();
                exit;
            }
    
        } elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
            try {
                $queryImage = $read->prepare('SELECT tbl_images.id, tbl_images.title, tbl_images.filename, tbl_images.mimetype, tbl_images.task_id FROM tbl_images, tbl_task
                WHERE tbl_task.id = :taskid AND tbl_task.user_id = :userid AND tbl_images.task_id = tbl_task.id');
                $queryImage->bindParam(':taskid', $taskId, PDO::PARAM_INT);
                $queryImage->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
                $queryImage->execute();

                while ($row = $queryImage->fetch(PDO::FETCH_ASSOC)) {
                    $write->beginTransaction();

                    $image = new ImageModel($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['task_id']);
                    $imageId = $image->getId();

                    $query = $write->prepare('DELETE tbl_images FROM tbl_images, tbl_task
                    WHERE tbl_images.id = :imageid AND tbl_task.id = :taskid AND tbl_images.task_id = tbl_task.id AND tbl_task.user_id = :userid');
                    $query->bindParam(':imageid', $imageId, PDO::PARAM_INT);
                    $query->bindParam(':taskid', $taskId, PDO::PARAM_INT);
                    $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
                    $query->execute();

                    $image->deleteImageFile();
                    $write->commit();
                }

                $query = $write->prepare('DELETE FROM tbl_task WHERE id = :taskId AND user_id = :userId');
                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();
        
                $rowCount = $query->rowCount();
                if ($rowCount == 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(404);
                    $res->addMessages(TaskValidateConstant::NotFoundID);
                    $res->send();
                    exit;
                }

                $imageFolder = "../../upload_images/" . $taskId;
                if (is_dir($imageFolder)) {
                    rmdir($imageFolder);
                }
        
                $res = new Response();
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->addMessages(Constant::DeleteSuccess);
                $res->send();
                exit;

            } catch (PDOException $ex) {
                error_log("Connection error - " . $ex, 0);
                if ($write->inTransaction()) {
                    $write->rollback();
                }
                $res = new Response();
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->addMessages(Constant::DeleteField);
                $res->send();
                exit;
            } catch (ImageException $ex) {
                if ($write->inTransaction()) {
                    $write->rollback();
                }
                $res = new Response();
                $res->setSuccess(true);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            }

        } elseif ($_SERVER["REQUEST_METHOD"] == "PATCH") {
            try {

                if ($_SERVER["CONTENT_TYPE"] !== "application/json"){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessages("Content Type header not set to JSON");
                    $response->send();
                    exit;
                }

                $rawPatchData = file_get_contents('php://input');
                $jsonData = json_decode($rawPatchData);

                if (!$jsonData) {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessages("Request body is not valid JSON");
                    $response->send();
                    exit;
                }

                $title_updated = false;
                $description_updated = false;
                $deadline_updated = false;
                $completed_updated = false;

                $queryFields = "";

                if (isset($jsonData->title)) {
                    $title_updated = true;
                    $queryFields .= "title = :title, ";
                }

                if (isset($jsonData->description)) {
                    $description_updated = true;
                    $queryFields .= "description = :description, ";
                }
                  
                if (isset($jsonData->deadline)) {
                    $deadline_updated = true;
                    $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
                }
                
                if(isset($jsonData->completed)) {
                    $completed_updated = true;
                    $queryFields .= "completed = :completed, ";
                }

                $queryFields = rtrim($queryFields, ", ");

                if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessages(Constant::InvalidNoDataFields);
                    $response->send();
                    exit;
                }

                $query = $write->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task
                WHERE id = :taskId AND user_id = :userId');
                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessages(Constant::InvalidNoDataUpdate);
                    $response->send();
                    exit;
                }

                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($write, $taskId, $returnedUserId);
                    $taskDB = new TaskModel($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                }

                $queryString = "UPDATE tbl_task SET ".$queryFields." WHERE id = :taskId AND user_id = :userId";
                $query = $write->prepare($queryString);
                
                if($title_updated === true) {
                    $taskDB->setTitle($jsonData->title);
                    $up_title = $taskDB->getTitle();
                    $query->bindParam(':title', $up_title, PDO::PARAM_STR);
                }

                if($description_updated === true) {
                    $taskDB->setDescription($jsonData->description);
                    $up_description = $taskDB->getDescription();
                    $query->bindParam(':description', $up_description, PDO::PARAM_STR);
                }

                if($deadline_updated === true) {
                    $taskDB->setDeadline($jsonData->deadline);
                    $up_deadline = $taskDB->getDeadline();
                    $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
                }

                if($completed_updated === true) {
                    $taskDB->setCompleted($jsonData->completed);
                    $up_completed= $taskDB->getCompleted();
                    $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
                }

                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
    	        $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessages(Constant::InvalidNotUpdate);
                    $response->send();
                    exit;
                }

                $query = $write->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task
                WHERE id = :taskId AND user_id = :userId');
                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessages(TaskValidateConstant::NotFoundID);
                    $response->send();
                    exit;
                }

                $taskArray = array();
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($write, $taskId, $returnedUserId);
                    $task = new TaskModel(
                        $row['id'], 
                        $row['title'], 
                        $row['description'], 
                        $row['deadline'], 
                        $row['completed'],
                        $imageArray
                    );
                    $taskArray[] = $task->taskToArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['data'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->addMessages(Constant::UpdateSuccess);
                $response->setData($returnData);
                $response->send();
                exit;

            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(400);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (ImageException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedUpdateData);
                $res->send();
                exit;
            }

        } else {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(405);
            $res->addMessages(Constant::ErrorReqMethod);
            $res->send();
            exit;
        }

    } elseif (array_key_exists("completed", $_GET)) {
        $completed = $_GET["completed"];
        if ($completed !== TaskConstant::YES && $completed !== TaskConstant::NO) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(400);
            $res->addMessages(TaskValidateConstant::Completed);
            $res->send();
            exit;
        }
        
        if ($_SERVER["REQUEST_METHOD"] == "GET") {
            try {
                $query = $read->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task
                    WHERE completed = :completed AND user_id = :userId');
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();
    
                $rowCount = $query->rowCount();
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($write, $taskId, $returnedUserId);
                    $task = new TaskModel($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                    $taskArray[] = $task->taskToArray();
                }
    
                $returnData = array();
                $returnData["rows_returned"] = $rowCount;
                $returnData["data"] = $taskArray ?? null;
    
                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->setData($returnData);
                $res->send();
                exit;
            } catch (ImageException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedGetData);
                $res->send();
                exit;
            }
        } else {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(405);
            $res->addMessages(Constant::ErrorReqMethod);
            $res->send();
            exit;
        }

    } elseif (array_key_exists("page", $_GET) && array_key_exists("per_page", $_GET)){
        if ($_SERVER["REQUEST_METHOD"] == "GET") {
            $page = $_GET["page"];
            $limitPerPage = $_GET["per_page"];

            if ($page == "" || !is_numeric($page)) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(400);
                $res->addMessages(Constant::ErrorReqMethod);
                $res->send();
                exit;
            }

            try {
                $query = $read->prepare('SELECT COUNT(id) AS totalCount FROM tbl_task WHERE user_id = :userId');
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();

                $row = $query->fetch(PDO::FETCH_ASSOC);
                $totalCount = intval($row['totalCount']);
                $numberOfPages = ceil($totalCount/$limitPerPage);

                if ($numberOfPages == 0) {
                    $numberOfPages = 1;
                }

                if ($page > $numberOfPages || $page == 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(404);
                    $res->addMessages(Constant::PageNotFound);
                    $res->send();
                    exit;
                }

                $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));

                $queryData = $read->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task 
                WHERE user_id = :userid ORDER BY deadline ASC LIMIT :limitperpage OFFSET :offset');
                $queryData->bindParam(':limitperpage', $limitPerPage, PDO::PARAM_INT);
                $queryData->bindParam(':offset', $offset, PDO::PARAM_INT);
                $queryData->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
                $queryData->execute();

                $rowCount = $queryData->rowCount();
                while ($row = $queryData->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($write, $taskId, $returnedUserId);
                    $task = new TaskModel($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                    $taskArray[] = $task->taskToArray();
                }

                $returnData = array();
                $returnData["rows_returned"] = $rowCount;
                $returnData["total_rows"] = $totalCount;
                $returnData["total_pages"] = $numberOfPages;
                ($page < $numberOfPages ? $returnData["has_next_page"] = true : $returnData["has_next_page"] = false);
                ($page > 1 ? $returnData["has_previous_page"] = true : $returnData["has_previous_page"] = false);
                $returnData["data"] = $taskArray ?? null;
    
                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->setData($returnData);
                $res->send();
                exit;
            } catch (ImageException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedGetData);
                $res->send();
                exit;
            }

        } else {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(405);
            $res->addMessages(Constant::ErrorReqMethod);
            $res->send();
            exit;
        }

    } elseif (empty($_GET)) {
        if ($_SERVER["REQUEST_METHOD"] == "GET") {
            try {
                $query = $read->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task WHERE user_id = :userId');
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();
    
                $rowCount = $query->rowCount();
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $imageArray = retrieveImages($write, $taskId, $returnedUserId);
                    $task = new TaskModel($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                    $taskArray[] = $task->taskToArray();
                }
    
                $returnData = array();
                $returnData["rows_returned"] = $rowCount;
                $returnData["data"] = $taskArray ?? null;
    
                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->setData($returnData);
                $res->send();
                exit;
            } catch (ImageException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedGetData);
                $res->send();
                exit;
            }
    
        } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
            try {
                
                if ($_SERVER["CONTENT_TYPE"] !== "application/json") {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(400);
                    $res->addMessages(Constant::InvalidContentType);
                    $res->send();
                    exit;
                }

                $rowPostData = file_get_contents('php://input');
                $jsonData = json_decode($rowPostData);
                if (!$jsonData) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(400);
                    $res->addMessages(Constant::InvalidReqBody);
                    $res->send();
                    exit;
                }

                if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(400);
                    (!isset($jsonData->title) ? $res->addMessages(TaskValidateConstant::InvalidTitle) : false);
                    (!isset($jsonData->completed) ? $res->addMessages(TaskValidateConstant::InvalidCompleted) : false);
                    $res->send();
                    exit;
                }

                $newData = new TaskModel(
                    null,
                    (isset($jsonData->completed) ? $jsonData->title : null),
                    (isset($jsonData->description)? $jsonData->description : null),
                    (isset($jsonData->deadline)? $jsonData->deadline : null),
                    (isset($jsonData->completed)? $jsonData->completed : null)
                );

                $title = $newData->getTitle();
                $description = $newData->getDescription();
                $deadline = $newData->getDeadline();
                $completed = $newData->getCompleted();

                $query = $write->prepare('INSERT INTO tbl_task (title, description, deadline, completed, user_id)
                    VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
                $query->bindParam(':title', $title, PDO::PARAM_STR);
                $query->bindParam(':description', $description, PDO::PARAM_STR);
                $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid', $returnedUserId, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if ($rowCount === 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(500);
                    $res->addMessages(Constant::FailedCreate);
                    $res->send();
                    exit;
                }

                $lastId = $write->lastInsertId();
                $query = $read->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_task
                    WHERE id = :lastid AND user_id = :userId');
                $query->bindParam(':lastid', $lastId, PDO::PARAM_INT);
                $query->bindParam(':userId', $returnedUserId, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if ($rowCount === 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(500);
                    $res->addMessages(Constant::FailedRetrieve);
                    $res->send();
                    exit;
                }

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new TaskModel(
                        $row['id'], $row['title'], 
                        $row['description'], 
                        $row['deadline'], 
                        $row['completed']
                    );
                    $taskArray[] = $task->taskToArray();
                }
    
                $returnData = array();
                $returnData["rows_returned"] = $rowCount;
                $returnData["data"] = $taskArray ?? null;
    
                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(201);
                $res->addMessages(Constant::CreateSuccess);
                $res->setData($returnData);
                $res->send();
                exit;

            } catch (TaskException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages($ex->getMessage());
                $res->send();
                exit;
            } catch (PDOException $ex) {
                error_log("Database query error - " . $ex, 0);
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::FailedInsertData);
                $res->send();
                exit;
            }
        } else {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(405);
            $res->addMessages(Constant::ErrorReqMethod);
            $res->send();
            exit;
        }
    } else {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(404);
        $res->addMessages(Constant::InvalidReqType);
        $res->send();
        exit;
    }
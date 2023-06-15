<?php
    require_once('../constants/Constant.php');
    require_once('../configs/Database.php');
    require_once('../models/Response.php');

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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(405);
        $res->addMessages(Constant::ErrorReqMethod);
        $res->send();
        exit;
    }

    if ($_SERVER["CONTENT_TYPE"] !== "application/json") {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(400);
        $res->addMessages(Constant::InvalidContentType);
        $res->send();
        exit;
    }

    $rowPostData = file_get_contents("php://input");
    $jsonData = json_decode($rowPostData);

    if (!$jsonData) {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(400);
        $res->addMessages(Constant::InvalidReqBody);
        $res->send();
        exit;
    }

    if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(400);
        (!isset($jsonData->fullname) ? $res->addMessages(UserValidateConstant::ErrorFullname) : false);
        (!isset($jsonData->username) ? $res->addMessages(UserValidateConstant::ErrorUsername) : false);
        (!isset($jsonData->password) ? $res->addMessages(UserValidateConstant::ErrorPassword) : false);
        $res->send();
        exit;
    }

    if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 ||
        strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
        strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255)
    {
        $res = new Response();
        $res->setSuccess(false);
        $res->setHttpStatusCode(400);
        (strlen($jsonData->fullname) < 1 ? $res->addMessages(UserValidateConstant::FullnameBlank) : false);
        (strlen($jsonData->fullname) > 255 ? $res->addMessages(UserValidateConstant::FullnameGreaterThanChar) : false);
        (strlen($jsonData->username) < 1 ? $res->addMessages(UserValidateConstant::UsernameBlank) : false);
        (strlen($jsonData->username) > 255 ? $res->addMessages(UserValidateConstant::UsernameGreaterThanChar) : false);
        (strlen($jsonData->password) < 1 ? $res->addMessages(UserValidateConstant::PasswordBlank) : false);
        (strlen($jsonData->password) > 255 ? $res->addMessages(UserValidateConstant::PasswordGreaterThanChar) : false);
        $res->send();
        exit;
    }

    $fullname = trim($jsonData->fullname);
    $username = trim($jsonData->username);
    $password = $jsonData->password;

    try {
        $query = $read->prepare('SELECT id FROM tbl_users WHERE username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount !== 0) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(409);
            $res->addMessages(UserValidateConstant::UsernameExist);
            $res->send();
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = $write->prepare('INSERT INTO tbl_users (fullname, username, password) VALUES (:fullname, :username, :password)');
        $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);
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
        $returnData = array();
        $returnData['id'] = $lastId;
        $returnData['fullname'] = $fullname;
        $returnData['username'] = $username;

        $res = new Response();
        $res->toCache(true);
        $res->setSuccess(true);
        $res->setHttpStatusCode(201);
        $res->addMessages(Constant::CreateSuccess);
        $res->setData($returnData);
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
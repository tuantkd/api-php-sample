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

    if (array_key_exists('session_id', $_GET)) {
        $session_id = $_GET['session_id'];

        if ($session_id === "" || !is_numeric($session_id)) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(400);
            ($session_id === "" ? $res->addMessages(SessionValidateConstant::InvalidSessionID) : false);
            (!is_numeric($session_id) ? $res->addMessages(SessionValidateConstant::SessionIDIsNumeric) : false);
            $res->send();
            exit;
        }

        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1){
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(401);
            (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $res->addMessages(SessionValidateConstant::AccessTokenMissHeader) : false);
            (isset($_SERVER['HTTP_AUTHORIZATION']) && (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) ? $res->addMessages(SessionValidateConstant::AccessTokenBlank) : false);
            $res->send();
            exit;
        }

        $access_token = $_SERVER['HTTP_AUTHORIZATION'];

        if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            try {
                $query = $read->prepare('DELETE FROM tbl_sessions WHERE id = :sessionid AND access_token = :accesstoken');
                $query->bindParam(':sessionid', $session_id, PDO::PARAM_INT);
                $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
                $query->execute();
                
                $rowCount = $query->rowCount();
                if ($rowCount === 0) {
                    $res = new Response();
                    $res->setSuccess(false);
                    $res->setHttpStatusCode(400);
                    $res->addMessages(SessionValidateConstant::FailedDeleteSessionLogout);
                    $res->send();
                    exit;
                }

                $returnData = array();
                $returnData['session_id'] = intval($session_id);

                $res = new Response();
                $res->toCache(true);
                $res->setSuccess(true);
                $res->setHttpStatusCode(200);
                $res->addMessages(Constant::LogoutMessage);
                $res->setData($returnData);
                $res->send();
                exit;
            } catch (PDOException $ex) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(500);
                $res->addMessages(Constant::LogoutIssue);
                $res->send();
                exit;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
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

            if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(400);
                (!isset($jsonData->refresh_token) ? $res->addMessages(SessionValidateConstant::RefreshTokenNotSupplied) : false);
                (strlen($jsonData->refresh_token) < 1 ? $res->addMessages(SessionValidateConstant::RefreshTokenBlank) : false);
                $res->send();
                exit;
            }

            $refreshToken = $jsonData->refresh_token;

            $query = $read->prepare('SELECT tbl_sessions.id AS sessionid, user_id, access_token, refresh_token, user_active, user_attempts, access_token_expiry, refresh_token_expiry
            FROM tbl_sessions, tbl_users WHERE tbl_users.id = tbl_sessions.user_id
            AND tbl_sessions.id = :sessionid
            AND tbl_sessions.access_token = :accesstoken
            AND tbl_sessions.refresh_token = :refreshtoken');
            $query->bindParam(':sessionid', $session_id, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $access_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::IncorrectToken);
                $res->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returned_session_id = $row['sessionid'];
            $returned_user_id = $row['user_id'];
            $returned_access_token = $row['access_token'];
            $returned_refresh_token = $row['refresh_token'];
            $returned_user_active = $row['user_active'];
            $returned_user_attempts = $row['user_attempts'];
            $returned_access_token_expiry = $row['access_token_expiry'];
            $refresh_refresh_token_expiry = $row['refresh_token_expiry'];

            if ($returned_user_active !== SessionValidateConstant::AccountActive) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::ErrorNotActive);
                $res->send();
                exit;
            }

            if ($returned_user_attempts >= SessionValidateConstant::NumberOfLogins) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::AccountLocked);
                $res->send();
                exit;
            }

            if (strtotime($refresh_refresh_token_expiry) < time()) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::RefreshTokenExpried);
                $res->send();
                exit;
            }

            $access_token_new = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $access_token_expiry_seconds = 1200;
            $refresh_token_new = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $refresh_token_expiry_seconds = 1209600;

            $query = $read->prepare('UPDATE tbl_sessions SET access_token = :accesstoken,
            access_token_expiry = DATE_ADD(NOW(), INTERVAL :accesstokenexpiry SECOND),
            refresh_token = :refreshtoken,
            refresh_token_expiry = DATE_ADD(NOW(), INTERVAL :refreshtokenexpiry SECOND)
            WHERE id = :sessionid AND user_id = :userid AND access_token = :accesstokenheader AND refresh_token = :refreshtokeninput');

            $query->bindParam(':accesstoken', $access_token_new, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiry', $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refresh_token_new, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry_seconds, PDO::PARAM_INT);

            $query->bindParam(':sessionid', $returned_session_id, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_user_id, PDO::PARAM_INT);
            $query->bindParam(':accesstokenheader', $access_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtokeninput', $refreshToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::AccessTokenNotRefresh);
                $res->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = intval($returned_session_id);
            $returnData['access_token'] = $access_token_new;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refresh_token_new;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            $res = new Response();
            $res->toCache(true);
            $res->setSuccess(true);
            $res->setHttpStatusCode(201);
            $res->addMessages(Constant::TokenRefresh);
            $res->setData($returnData);
            $res->send();
            exit;
        }

    } elseif (empty($_GET)) {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(405);
            $res->addMessages(Constant::ErrorReqMethod);
            $res->send();
            exit;
        }

        sleep(1);

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

        if (!isset($jsonData->username) || !isset($jsonData->password)) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(400);
            (!isset($jsonData->username) ? $res->addMessages(UserValidateConstant::ErrorUsername) : false);
            (!isset($jsonData->password) ? $res->addMessages(UserValidateConstant::ErrorPassword) : false);
            $res->send();
            exit;
        }

        if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 ||
            strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255)
        {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(400);
            (strlen($jsonData->username) < 1 ? $res->addMessages(UserValidateConstant::UsernameBlank) : false);
            (strlen($jsonData->username) > 255 ? $res->addMessages(UserValidateConstant::UsernameGreaterThanChar) : false);
            (strlen($jsonData->password) < 1 ? $res->addMessages(UserValidateConstant::PasswordBlank) : false);
            (strlen($jsonData->password) > 255 ? $res->addMessages(UserValidateConstant::PasswordGreaterThanChar) : false);
            $res->send();
            exit;
        }

        try {
            $username = trim($jsonData->username);
            $password = trim($jsonData->password);

            $query = $read->prepare('SELECT id, fullname, username, password, user_active, user_attempts FROM tbl_users WHERE username = :username');
            $query->bindParam(':username', $username, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount == 0) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::IncorrectLogin);
                $res->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returned_id = $row['id'];
            $returned_fullname = $row['fullname'];
            $returned_username = $row['username'];
            $returned_password = $row['password'];
            $returned_user_active = $row['user_active'];
            $returned_user_attempts = $row['user_attempts'];

            if ($returned_user_active !== SessionValidateConstant::AccountActive) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::ErrorNotActive);
                $res->send();
                exit;
            }

            if ($returned_user_attempts >= SessionValidateConstant::NumberOfLogins) {
                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::AccountLocked);
                $res->send();
                exit;
            }

            if (!password_verify($password, $returned_password)) {
                $query = $read->prepare('UPDATE tbl_users SET user_attempts = user_attempts+1 WHERE id = :id');
                $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
                $query->execute();

                $res = new Response();
                $res->setSuccess(false);
                $res->setHttpStatusCode(401);
                $res->addMessages(SessionValidateConstant::IncorrectLogin);
                $res->send();
                exit;
            }

            $access_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $access_token_expiry_seconds = 1200;

            $refresh_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $refresh_token_expiry_seconds = 1209600;
        
        } catch (PDOException $ex) {
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(500);
            $res->addMessages(Constant::LoginIssue);
            $res->send();
            exit;
        }

        try {
            $write->beginTransaction();
            $query = $write->prepare('UPDATE tbl_users SET user_attempts = 0 WHERE id = :id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            $query = $write->prepare('INSERT INTO tbl_sessions (user_id, access_token, access_token_expiry, refresh_token, refresh_token_expiry)
            VALUES (:user_id, :access_token, DATE_ADD(NOW(), INTERVAL :accesstokenexpiry SECOND), :refresh_token, DATE_ADD(NOW(), INTERVAL :refreshtokenexpiry SECOND))');
            $query->bindParam(':user_id', $returned_id, PDO::PARAM_INT);
            $query->bindParam(':access_token', $access_token, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiry', $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refresh_token', $refresh_token, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->execute();
            $lastSessionId = $write->lastInsertId();
            $write->commit();

            $returnData = array();
            $returnData['session_id'] = intval($lastSessionId);
            $returnData['access_token'] = $access_token;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refresh_token;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            $res = new Response();
            $res->toCache(true);
            $res->setSuccess(true);
            $res->setHttpStatusCode(201);
            $res->addMessages(Constant::CreateSuccess);
            $res->setData($returnData);
            $res->send();
            exit;

        } catch (PDOException $ex) {
            $write->rollback();
            $res = new Response();
            $res->setSuccess(false);
            $res->setHttpStatusCode(500);
            $res->addMessages(Constant::LoginIssue);
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
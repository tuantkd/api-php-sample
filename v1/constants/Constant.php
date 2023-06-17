<?php
    class Constant {
        const Error500 = "Response creation erro!";
        const ErrorConnectDB = "Connection database error!";
        const LoginIssue = "There was an issue logging in - please try again!";
        const AuthenticatingIssue = "There was an issue Authenticating - please try again!";
        const LogoutIssue = "There was an issue logging out - please try again!";
        const ErrorReqMethod = "Request method not allowed!";
        const FailedGetData = "Failed to get data!";
        const FailedUpdateData = "Failed to update data to database - check submitted data for error!";
        const FailedInsertData = "Failed to insert data to database - check submitted data for error!";
        const FailedCreate = "Failed to create data!";
        const FailedRetrieve = "Failed to retrieve data after creation!";
        const PageNotFound = "Page not found!";
        const LogoutMessage = "Logged out!";
        const DeleteSuccess = "Delete Successfully";
        const CreateSuccess = "Create Successfully";
        const TokenRefresh = "Token refreshed";
        const UpdateSuccess = "Update Successfully";
        const InvalidContentType = "Content type header is not set to JSON";
        const InvalidReqBody = "Request body is not valid JSON";
        const InvalidReqType = "Endpoint type not supported";
        const InvalidNoDataFields = "No data fields provided";
        const InvalidNoDataUpdate = "No data found to update";
        const InvalidNotUpdate = "Data not updated - given values may be the same as the stored values";
        const InvalidReqPage = "Page number cannot be blank and must be numeric!";
    }

    class TaskConstant {
        const YES = "Y";
        const NO = "N";
        const ErrorMessageID = "Task ID has exceeded the number limit";
        const ErrorMessageTitle = "Task title requires not null and text length less than 255 characters";
        const ErrorMessageDescription = "Task Description has exceeded the text limit";
        const ErrorMessageDeadline = "Task Deadline requires format date time (d/m/y H:i)";
        const ErrorMessageCompleted = "Task Completed must be Y or N";
        const MaxNumberID = 9223372036854775807;
        const MaxTitleLength = 255;
        const MaxDescriptionLength = 16777215;
    }

    class TaskValidateConstant {
        const ErrorID = "Task ID cannot be blank or must be numeric!";
        const NotFoundID = "Task ID not found!";
        const Completed = "Task completed filter must be Y or N!";
        const InvalidCompleted = "Completed field require and must be provided!";
        const InvalidTitle = "Title field require and must be provided!";
    }

    class UserValidateConstant {
        const UsernameExist = "User name already exists!";
        const ErrorFullname = "Full name not supplied!";
        const ErrorUsername = "User name not supplied!";
        const ErrorPassword = "Password not supplied!";
        const FullnameBlank = "Full name cannot be blank!";
        const FullnameGreaterThanChar = "Full name cannot be greater than 255 characters!";
        const UsernameBlank = "User name cannot be blank!";
        const UsernameGreaterThanChar = "User name cannot be greater than 255 characters!";
        const PasswordBlank = "Password cannot be blank!";
        const PasswordGreaterThanChar = "Password cannot be greater than 255 characters!";
    }

    class SessionValidateConstant {
        const AccessTokenMissHeader= "Access token is missing from the header!";
        const AccessTokenBlank= "Access token cannot be blank!";
        const InvalidSessionID = "Session ID cannot be blank!";
        const InvalidAccessToken = "Invalid Access Token!";
        const SessionIDIsNumeric = "Session ID must be numeric!";
        const IncorrectLogin = "Username or password incorrect!";
        const AccessTokenNotRefresh = "Access token could not be refreshed - please login again!";
        const IncorrectToken = "Access token or Refresh token is incorrect for session id!";
        const RefreshTokenExpried = "Refresh token has expried - please login again!";
        const ErrorNotActive = "Username account not active!";
        const NumberOfLogins = 3;
        const AccountActive = "Y";
        const AccountLocked = "Account is currently locked out!";
        const FailedDeleteSessionLogout = "Failed to log out of this sessions using access token provided!";
        const RefreshTokenNotSupplied = "Refresh token not supplied!";
        const RefreshTokenBlank = "Refresh token cannot be blank!";
    }
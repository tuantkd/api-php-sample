<?php
    class Constant {
        const Error500 = "Response creation erro!";
        const ErrorConnectDB = "Connection database error!";
        const ErrorReqMethod = "Request method not allowed!";
        const FailedGetData = "Failed to get data!";
        const FailedInsertData = "Failed to insert data to database - check submitted data for error!";
        const FailedCreate = "Failed to create data!";
        const FailedRetrieve = "Failed to retrieve data after creation!";
        const PageNotFound = "Page not found!";
        const DeleteSuccess = "Delete Successfully";
        const CreateSuccess = "Create Successfully";
        const InvalidContentType = "Content type header is not set to JSON";
        const InvalidReqBody = "Request body is not valid JSON";
        const InvalidReqType = "Endpoint type not supported";
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
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

    class ImageConstant {
        const MaxNumberID = 9223372036854775807;
        const ImageNotFound = "Image not found";
        const ImageFileNotFound = "Image File not found";
        const ImageIDError = "Image ID Error";
        const ImageFailCreateFolder = "Failed to create image upload folder";
        const ImageTitleError = "Image Title Error";
        const ImageFileNameError = "Image Filename Error - must be between 1 and 30 characters and only be (.jpg|.png|.gif)";
        const HTTPS = "https://";
        const HTTP = "http://";
        const V1NameControl = "/v1/tasks/";
        const Images = "/images/";
        const ImageMimeTypeError = "Image MimeType Error";
        const ImageIDTaskID = "Image ID or Task ID cannot be blank and must be numerical";
        const FailedUploadImage = "Failed to Upload the Image";
        const FailedUpdateRenameImage = "Failed to update the file name";
        const FailedImageAttribute = "Failed to get Image attributes";
        const FailedUpdateImageAttribute = "Failed to update Image attributes - check your data for errors";
        const FailedNoImageUpdate = "Failed no Image found to updated";
        const NoUpdateImageAttribute = "Image attributes not updated - the given value may be the same as the stored values";
        const UpdateImageAttribute = "Image attributes update";
        const FailedGetImage = "Failed getting Image";
        const UploadImageSuccess = "Image uploaded successfully";
        const FailedRetrieveImage = "Failed to retrieve image attributes after upload - please try uploading image again";
        const ContentTypeFormData = "Content type header not set to multipart/form-data with a boundary";
        const AttributesBodyRequest = "Attributes missing from body of the request"; 
        const AttributesInvalidJson = "Attributes field is not valid JSON"; 
        const TitleAndFilename = "Title and Filename fields are required"; 
        const FileNameExtension = "Filename must not contain the extension";
        const FileNameDotExtension = "Filename cannot contain any dots or file extensions";
        const NoImageFields = "No Image fields provided";
        const ImageFileUnSuccess = "Image file upload unsuccessful - make sure you selected a file"; 
        const SizeImage5MB = 5242880; 
        const SizeImageMessage = "File must be under 5MB";  
        const ImageFileTypes = array("image/png", "image/jpeg", "image/gif");  
        const FileTypeNotSupport = "File type not supported";  
        const InvalidFileExtension = "No valid file extension found for mimetype";  
        const FileNameExistsError = "A file with that filename already exists - try a different filename";  
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
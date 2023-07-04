<?php
    require_once('ImageModel.php');

    try {
        $image = new ImageModel(1, "Image Title New", "image.docx", "image/jpeg", 3);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($image->returnImagesAsArray());
    } catch (ImageException $ex) {
        echo "Error: " . $ex->getMessage();
    }
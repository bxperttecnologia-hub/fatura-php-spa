<?php
session_start();

if ($_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
    $newName = uniqid('profile_') . '.' . $ext;
    $uploadPath = '../../assets/img/profiles/' . $newName;

    if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadPath)) {
        $_SESSION['user']['image'] = $newName;
        echo json_encode(["success" => true, "imagePath" => $newName]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao mover arquivo!"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Erro no upload!"]);
}

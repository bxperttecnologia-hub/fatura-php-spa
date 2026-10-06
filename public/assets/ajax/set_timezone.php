<?php 

session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["timezone"])) {
    $_SESSION["timezone"] = $_POST["timezone"];
    echo json_encode(["status" => "success", "timezone" => $_SESSION["timezone"]]);
} else {
    echo json_encode(["status" => "error", "message" => "Fuso horário não recebido"]);
}

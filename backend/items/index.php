<?php

session_start();

require_once __DIR__ . "/../db.php";

$retval = new \stdClass();
$retval->ok = false;

function get(&$retval) {
    global $db;

    header("Access-Control-Allow-Origin: *");

    $retval->items = [];

    $sql = <<<SQL
    SELECT
        *
    FROM
        entries
    ORDER BY
        createdAt DESC
SQL;

    $stmt = $db->prepare($sql);
    if($stmt === false) {
        throw new \Exception($db->errorInfo()[2]);
    }
    if($stmt->execute() === false) {
        throw new \Exception($stmt->errorInfo()[2]);
    }
    while($o = $stmt->fetchObject()) {
        $retval->items[] = $o;
    }
    $stmt->closeCursor();

    $retval->ok = true;
}

function post(&$retval) {
    global $db;

    if(!isset($_SESSION["csrfToken"]) || ($_SESSION["csrfToken"] != $_SERVER["HTTP_X_CSRF_TOKEN"])) {
        throw new \Exception("CSRF validation failed; reload the site.");
    }

    $body = json_decode(file_get_contents("php://input"));

    $sql = <<<SQL
    INSERT INTO entries
        (title, description, row, col)
    VALUES
        (:title, :description, :row, :col)
SQL;

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":title", $body->data->title);
    $stmt->bindParam(":description", $body->data->description);
    $stmt->bindParam(":row", $body->data->row);
    $stmt->bindParam(":col", $body->data->col);
    if($stmt === false) {
        throw new \Exception($db->errorInfo()[2]);
    }
    if($stmt->execute() === false) {
        throw new \Exception($stmt->errorInfo()[2]);
    }

    $retval->ok = true;
}

try {
    switch($_SERVER["REQUEST_METHOD"]) {
    case "GET":
        get($retval);
        break;
    case "POST":
        post($retval);
        break;
    default:
        throw new \Exception("unhandled verb");
    }
} catch(\Exception $e) {
    $retval->ok = false;
    $retval->msg = $e->getMessage();
}

header("Content-Type: application/json");
echo json_encode($retval, JSON_PRETTY_PRINT);
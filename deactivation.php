<?php

include_once 'database.php';

$id_array = [];
foreach ( $_POST['deactivate'] as $id ) {
    $id_array[] = (int)$id;
}

$ids = implode(', ', $id_array);

if ( mysqli_query($connection, "UPDATE cr_keywords SET active = 0 WHERE id IN ($ids)") ) {
    $_SESSION['searcher_alert'] = "success|Zapisi <strong>uspešno</strong> deaktivirani!";
    header("Location: status.php");
} else {
    $_SESSION['searcher_alert'] = "danger|Zapisi <strong>neuspešno</strong> deaktivirani!";
    header("Location: status.php");
}
<?php

include_once 'database.php';

$id_array = [];

foreach ( $_POST['activate'] as $id ) {
    $id_array[] = (int)$id;
}

$ids = implode(', ', $id_array);

if ( mysqli_query($connection, "UPDATE cr_keywords SET active = 1 WHERE id IN ($ids)") ) {
    $_SESSION['searcher_alert'] = "success|Zapisi <strong>uspešno</strong> aktivirani!";
    header("Location: status.php");
} else {
    $_SESSION['searcher_alert'] = "danger|Zapisi <strong>neuspešno</strong> aktivirani!";
    header("Location: status.php");
}
<?php

require 'Str.php';

$connection = mysqli_connect('localhost', 'root', '', 'searcher');
mysqli_query($connection, "SET NAMES UTF8;");

$typo       = $_POST['typo'];
$identifier = $_POST['identifier'];

$result = mysqli_query($connection, "SELECT * FROM cr_search WHERE id = " . $identifier);
$idData = mysqli_fetch_assoc($result);

if ( $_POST['type'] == 'existing' ) {
    $column = ( strlen($idData['column']) > 0 ) ? "'" . $idData['column'] . "'" : 'NULL';
    $query  =
        "UPDATE cr_keywords SET cr_table = '{$idData['cr_table']}', cr_id = {$idData['cr_id']}, `column` = $column WHERE id = " . $typo;
    if ( mysqli_query($connection, $query) ) {
        header('Location: connector.php');
    } else {
        echo "Napaka pri povezovanju";
    }
} else {
    $column = ( strlen($idData['column']) > 0 ) ? "'" . $idData['column'] . "'" : 'NULL';
    $typo   = ( strpos($typo, " ") !== false || strlen($typo) < 3 ) ? '"' . $typo . '"' : $typo;
    if ( mysqli_query($connection, "INSERT INTO cr_keywords (text, cr_id, cr_table, `column`) VALUES ('{$typo}', {$idData['cr_id']}, '{$idData['cr_table']}', $column);") ) {
        header('Location: connector.php');
    } else {
        echo "Napaka pri ustvarjanju nove povezave";
    }
}
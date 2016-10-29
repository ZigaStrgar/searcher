<?php

require 'Str.php';
include_once 'database.php';

function searchify($string)
{
    $ascii = Str::ascii($string);
    $str   = new Str();

    return $str->lower($ascii);
}

$typo       = $_POST['typo'];
$identifier = $_POST['identifier'];

$result = mysqli_query($connection, "SELECT * FROM cr_search WHERE id = " . $identifier);
$idData = mysqli_fetch_assoc($result);

if ( $_POST['type'] == 'existing' ) {
    $id_array = [];
    foreach ( $typo as $id ) {
        $id_array[] = (int)$id;
    }
    $ids    = implode(', ', $id_array);
    $column = ( strlen($idData['column']) > 0 ) ? "'" . $idData['column'] . "'" : 'NULL';
    $query  =
        "UPDATE cr_keywords SET cr_table = '{$idData['cr_table']}', cr_id = {$idData['cr_id']}, `column` = $column WHERE id IN ($ids)";
    if ( mysqli_query($connection, $query) ) {
        $_SESSION['searcher_alert'] = "success|Povezava <strong>uspešno</strong> spremenjena!";
        header('Location: index.php');
    } else {
        $_SESSION['searcher_alert'] = "danger|Povezava <strong>neuspešno</strong> spremenjena!";
        header('Location: index.php');
    }
} else {
    $column = ( strlen($idData['column']) > 0 ) ? "'" . $idData['column'] . "'" : 'NULL';
    $typo   = searchify(trim($typo));
    $typo   = ( strpos($typo, " ") !== false || strlen($typo) < 3 ) ? '"' . $typo . '"' : $typo;
    if ( mysqli_query($connection, "INSERT INTO cr_keywords (text, cr_id, cr_table, `column`) VALUES ('{$typo}', {$idData['cr_id']}, '{$idData['cr_table']}', $column);") ) {
        $_SESSION['searcher_alert'] = "success|Zapis <strong>uspešno</strong> dodan!";
        header('Location: index.php');
    } else {
        $_SESSION['searcher_alert'] =
            "danger|Zapis <strong>neuspešno</strong> dodan! Po vsej verjetnosti že <strong>obstaja</strong>!";
        header('Location: index.php');
    }
}
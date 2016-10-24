<?php

require 'Str.php';

$connection = mysqli_connect('localhost', 'root', '', 'searcher');
mysqli_query($connection, "SET NAMES UTF8;");
mysqli_query($connection, "TRUNCATE cr_keywords;");

function searchify($string)
{
    $ascii = Str::ascii($string);
    $str   = new Str();

    return $str->lower($ascii);
}

$result = mysqli_query($connection, "SELECT * FROM cr_search WHERE text LIKE '% _%'");

while ( $row = mysqli_fetch_assoc($result) ) {
    $column = ( strlen($row['column']) > 0 ) ? "'" . $row['column'] . "'" : "NULL";
    $text   = trim(searchify(str_replace("Občina ", "", $row['text'])));
    if ( strpos($text, " ") !== false ) {
        $query =
            "INSERT INTO cr_keywords (text, cr_id, cr_table, `column`) VALUES ('\"$text\"', {$row['cr_id']}, '{$row['cr_table']}', $column)";
        if ( !mysqli_query($connection, $query) ) {
            echo mysqli_errno($connection) . " " . $query . "<br />";
        }
    }
}
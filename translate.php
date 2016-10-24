<?php

require 'Str.php';

$connection = mysqli_connect('localhost', 'root', '', 'searcher');
mysqli_query($connection, "SET NAMES UTF8;");
mysqli_query($connection, "TRUNCATE cr_search;");

mysqli_query($connection, "UPDATE cr_criteria SET text = 'offer_type' WHERE id = 1;");
mysqli_query($connection, "UPDATE cr_criteria SET text = 'property_type' WHERE id = 2;");
mysqli_query($connection, "UPDATE cr_criteria SET text = 'property_subtype' WHERE id = 4;");
mysqli_query($connection, "UPDATE cr_criteria_opt SET criteria = 2 WHERE id BETWEEN 5 AND 10");

$config = [
    'cr_region'       => [
        'where' => [
            'visible' => 1
        ]
    ],
    'cr_city'         => [
        'where'      => [
            'visible' => 1
        ],
        'additional' => [
            'region'
        ]
    ],
    'cr_criteria_opt' => [
        'where'      => [
            'active' => 1
        ],
        'additional' => [
            'parent'
        ]
    ],
    'cr_district'     => [
        'where'      => [
            'deleted' => 0
        ],
        'additional' => [
            'city',
            'region'
        ]
    ],
    'cr_zip_postal'   => [
        'id' => 'code'
    ]
];

function searchify($string)
{
    $ascii = Str::ascii($string);
    $str   = new Str();

    return $str->lower($ascii);
}

foreach ( $config as $table => $properties ) {
    $where = [];
    $query = "SELECT * FROM {$table}";
    if ( isset( $properties['where'] ) ) {
        foreach ( $properties['where'] as $column => $value ) {
            $where[] = "{$column} = {$value}";
        }
        $whereSql = "WHERE " . implode(" AND ", $where);
        $query .= " $whereSql";
    }

    $result = mysqli_query($connection, $query);

    $insert = [];
    while ( $row = mysqli_fetch_assoc($result) ) {
        $text   = str_replace("obcina ", "", searchify($row['text']));
        $insert = [
            'cr_table' => $table,
            'cr_id'    => $row[( isset( $properties['id'] ) ) ? $properties['id'] : "id"],
            'text'     => $row['text'],
            'search'   => $text
        ];

        if ( isset( $properties['additional'] ) ) {
            foreach ( $properties['additional'] as $property ) {
                $insert[$property] = $row[$property];
            }
        }

        $insert_keys   = implode("`, `", array_keys($insert));
        $insert_values = implode(", ", array_map(function($string) {
            return ( is_numeric($string) ) ? $string : "'" . trim($string) . "'";
        }, array_values($insert)));

        $insertSql = "INSERT INTO cr_search (`{$insert_keys}`) VALUES ({$insert_values});";

        mysqli_query($connection, $insertSql);
    }
}

$query = "SELECT * FROM cr_criteria_opt WHERE active = 1";

$result = mysqli_query($connection, $query);

while ( $row = mysqli_fetch_assoc($result) ) {
    $sql     = "SELECT * FROM cr_criteria WHERE id = " . $row['criteria'] . " LIMIT 1";
    $result2 = mysqli_query($connection, $sql);
    $row2    = mysqli_fetch_assoc($result2);
    $sql     =
        "UPDATE cr_search SET `column` = '{$row2["text"]}' WHERE cr_table = 'cr_criteria_opt' AND cr_id = " . $row['id'];
    mysqli_query($connection, $sql);
}

echo "All good chief, proceed with your work!";
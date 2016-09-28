<?php

require_once 'Searcher.php';

$init1  = time();
$query  =
    ( isset( $_GET['query'] ) ) ? $_GET['query'] : "oddaja 3 sobno stanovanje v ljubljani mjejsto od 640 - 660€ Bežigrad od 70 - 80 kvadratov";
$search = new Searcher($query);

echo "<pre>";
echo $search->getSql() . "<br />";
print_r($search->getResults(true));
echo "</pre>";

$total = time() - $init1;

echo "$total ms";

?>
<form action="" method="GET">
    <input type="text" name="query" width="500">
    <input type="submit">
</form>

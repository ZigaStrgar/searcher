<?php

require_once 'Searcher.php';
$init1 = time();

$search = new Searcher("3 sobno stanovanje v ljubljani od 640 - 660€ Bežigrad do 80 kvadratov");

echo "<pre>";
echo $search->getSql()."<br />";
print_r($search->getResults());
echo "</pre>";

$total = time() - $init1;

echo "$total ms";
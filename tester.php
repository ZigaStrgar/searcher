<?php

require_once 'Searcher.php';

$init1 = time();
$query =
    (isset($_GET['query'])) ? $_GET['query'] : 'toplarna oddaja 3 sobno stanovanje v ljubljani od 640 - 660€ Bežigrad balkon';
$search = new Searcher($query);
?>
    <form action="" method="GET">
        <input type="text" name="query" value="<?php echo $_GET['query'] ?>" size="200">
        <input type="submit">
    </form>
<?php

echo '<pre>';
echo $search->getSql().'<br />';
print_r($search->getResults(true));
echo '</pre>';

$total = time() - $init1;

echo "$total ms";

?>
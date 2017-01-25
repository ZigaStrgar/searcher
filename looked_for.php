<?php

include_once 'database.php';

$hits =
    mysqli_query($connection, "SELECT sm.text AS searched_for, cs.text AS found_text, sm.attribute, sm.cr_table FROM searcher_matches sm LEFT JOIN cr_search cs ON cs.cr_id = sm.cr_id AND cs.cr_table = sm.cr_table ORDER BY sm.id DESC;");

include_once 'header.php'; ?>
<h2>Našel sem</h2>
<table class="table table-responsive table-bordered">
    <tr>
        <th>Vpisana beseda</th>
        <th>Šifrant</th>
        <th>Tabela</th>
        <th>Atribut</th>
    </tr>
    <?php
    while ( $hit = mysqli_fetch_assoc($hits) ) {
        ?>
        <tr>
            <td><?= $hit['searched_for'] ?></td>
            <td><?= $hit['found_text'] ?></td>
            <td><?= $hit['cr_table'] ?></td>
            <td><?= $hit['attribute'] ?></td>
        </tr>
        <?php
    }
    ?>
</table>
<?php include_once 'footer.php'; ?>

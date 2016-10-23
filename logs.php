<?php include_once 'header.php' ?>
    <h2 class="page-header">Pregled zgodovine iskanj</h2>
<?php if ( !isset( $_GET['advanced'] ) ) { ?>
    <a href="?advanced" class="btn btn-primary">Razširjen pogled</a>
    <br>
    <br>
<?php } ?>
    <table class="table table-bordered table-responsive col-xs-12">
        <tr>
            <th>Iskalni niz</th>
            <th>Agencija</th>
            <?php if ( isset( $_GET['advanced'] ) ) { ?>
                <th>Naprava</th>
                <th>IP</th>
            <?php } ?>
        </tr>
        <?php
        $result = mysqli_query($connection, "SELECT * FROM searcher_logs;");
        while ( $log = mysqli_fetch_assoc($result) ) {
            ?>
            <tr>
                <td><?= $log['query'] ?></td>
                <td><?= $log['agency_id'] ?></td>
                <?php if ( isset( $_GET['advanced'] ) ) { ?>
                    <td><?= $log['client_agent'] ?></td>
                    <td><?= $log['client_ip'] ?></td>
                <?php } ?>
            </tr>
            <?php
        }
        ?>
    </table>
<?php include_once 'footer.php' ?>
<?php

include_once 'database.php';

$connected =
    ( isset( $_GET['connected'] ) && $_GET['connected'] == 'true' ) ? "cr_table IS NOT NULL AND cr_id IS NOT NULL" : "cr_table IS NULL AND cr_id IS NULL";

$active   = mysqli_query($connection, "SELECT * FROM cr_keywords WHERE active = 1 AND $connected ORDER BY id DESC");
$inactive = mysqli_query($connection, "SELECT * FROM cr_keywords WHERE active = 0 ORDER BY id DESC");

include_once 'header.php'; ?>
<?php if ( isset( $_GET['connected'] ) && $_GET['connected'] == 'true' ) { ?>
    <a href="status.php" class="btn btn-primary">Skrij povezane zapise</a>
<?php } else { ?>
    <a href="status.php?connected=true" class="btn btn-primary">Prikaži povezane zapise</a>
<?php } ?>
    <br>
    <h2>Deaktivacija besed</h2>
    <p class="help-block">Izbereš lahko več zapisov na enkrat!</p>
    <form action="deactivation.php" method="POST">
        <div class="col-xs-12">
            <div class="form-group">
                <label for="">Besede</label>
                <select class="form-control identifiers" name="deactivate[]" multiple="multiple">
                    <?php
                    while ( $word = mysqli_fetch_assoc($active) ) {
                        ?>
                        <option value="<?= $word['id']; ?>"><?= $word['text'] ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="clearfix"></div>
        <input type="hidden" name="type" value="existing">
        <div class="form-group">
            <button type="submit" class="btn btn-success">Deaktivacija</button>
        </div>
    </form>
    <hr>
    <h2>Aktivacija besed</h2>
    <p class="help-block">Izbereš lahko več zapisov na enkrat! Neaktivirani zapisi so vedno prikazani VSI!</p>
    <form action="activation.php" method="POST">
        <div class="col-xs-12">
            <div class="form-group">
                <label for="">Besede</label>
                <select class="form-control identifiers" name="activate[]" multiple="multiple">
                    <?php
                    while ( $word = mysqli_fetch_assoc($inactive) ) {
                        ?>
                        <option value="<?= $word['id']; ?>"><?= $word['text'] ?> - <?= $word['cr_table'] ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
        <input type="hidden" name="type" value="new">
        <div class="clearfix"></div>
        <div class="form-group">
            <button type="submit" class="btn btn-success">Aktivacija</button>
        </div>
    </form>
<?php include_once 'footer.php'; ?>
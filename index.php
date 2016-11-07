<?php

include_once 'database.php';

$misses =
    mysqli_query($connection, 'SELECT * FROM cr_keywords WHERE cr_table IS NULL AND cr_id IS NULL AND active = 1 ORDER BY id DESC');
$words = mysqli_query($connection, 'SELECT * FROM cr_search');
$words2 = mysqli_query($connection, 'SELECT * FROM cr_search');

include_once 'header.php'; ?>
<h2>Povezava za iskanje</h2>
<form action="add_connection.php" method="POST">
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Šment, teh pa ni našlo!</label>
            <select class="form-control identifiers" name="typo[]" multiple="multiple">
                <?php
                while ($miss = mysqli_fetch_assoc($misses)) {
                    ?>
                    <option value="<?= $miss['id']; ?>"><?= $miss['text'] ?></option>
                    <?php

                }
                ?>
            </select>
        </div>
    </div>
    <div class="xol-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Šifranti</label>
            <select class="form-control identifiers" name="identifier">
                <option disabled selected></option>
                <?php
                while ($word = mysqli_fetch_assoc($words)) {
                    ?>
                    <option value="<?= $word['id']; ?>"><?= $word['text'] ?> - <?= $word['cr_table'] ?></option>
                    <?php

                }
                ?>
            </select>
        </div>
    </div>
    <div class="clearfix"></div>
    <input type="hidden" name="type" value="existing">
    <div class="form-group">
        <button type="submit" class="btn btn-success">Poveži</button>
    </div>
</form>
<hr>
<h2>Ustvarjanje novih povezav</h2>
<form action="add_connection.php" method="POST">
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Nov niz</label>
            <input type="text" name="typo" class="form-control">
            <span class="help-block">Niz lahko vsebuje presledke, šumnike in vse ostalo :)</span>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Šifranti</label>
            <select class="form-control identifiers" name="identifier">
                <option disabled selected></option>
                <?php
                while ($word = mysqli_fetch_assoc($words2)) {
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
        <button type="submit" class="btn btn-success">Ustvari povezavo</button>
    </div>
</form>
<?php include_once 'footer.php'; ?>

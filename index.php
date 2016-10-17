<?php

require 'Str.php';

$connection = mysqli_connect('localhost', 'root', '', 'searcher');
mysqli_query($connection, "SET NAMES UTF8;");

$misses =
    mysqli_query($connection, "SELECT * FROM cr_keywords WHERE cr_table IS NULL AND cr_id IS NULL ORDER BY id DESC");
$words  = mysqli_query($connection, "SELECT * FROM cr_search");
$words2 = mysqli_query($connection, "SELECT * FROM cr_search");

?>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <link rel="stylesheet" href="css/select2.min.css">
    <title>Povezovanje besed za iskanje</title>
</head>
<body class="container">
<h2>Povezava za iskanje</h2>
<form action="add_connection.php" method="POST">
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Zgrešene besede (zadnjih 10)</label>
            <select class="form-control" name="typo" id="">
                <?php
                while ( $miss = mysqli_fetch_assoc($misses) ) {
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
            <label for="">Naše besede</label>
            <select class="form-control identifiers" name="identifier">
                <option disabled selected></option>
                <?php
                while ( $word = mysqli_fetch_assoc($words) ) {
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
        <button type="submit" class="btn btn-success">Dodaj povezavo</button>
    </div>
</form>
<hr>
<h2>Ustvarjanje novih povezav</h2>
<form action="add_connection.php" method="POST">
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Zgrešena beseda</label>
            <input type="text" name="typo" class="form-control">
        </div>
    </div>
    <div class="col-xs-12 col-sm-6">
        <div class="form-group">
            <label for="">Naše besede</label>
            <select class="form-control identifiers" name="identifier">
                <option disabled selected></option>
                <?php
                while ( $word = mysqli_fetch_assoc($words2) ) {
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
        <button type="submit" class="btn btn-success">Dodaj povezavo</button>
    </div>
</form>
<script src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script src="js/select2.min.js"></script>
<script>
    $(function () {
        $(".identifiers").select2();
    });
</script>
</html>

<?php

if ( isset( $_SESSION['searcher_alert'] ) ) {
    list( $type, $message ) = explode("|", $_SESSION['searcher_alert']);
    ?>
    <div class="alert alert-<?= $type ?>">
        <p><?= $message ?></p>
    </div>
    <?php
    unset( $_SESSION['searcher_alert'] );
}
<?php
session_start();
session_destroy();
// Uloskirjautuessa ohjataan etusivulle
header('Location: ./index.php');
?>
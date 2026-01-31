<?php
session_start();    // riprende la sessione corrente
session_unset();    // svuota $_SESSION
session_destroy();  // distrugge sessione lato server
header("Location: index.php"); // redirect
exit;

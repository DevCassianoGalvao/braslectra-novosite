<?php
require __DIR__ . '/inc/bootstrap.php';
start_session();
if (is_post()) {
    csrf_check();
    logout_user();
}
redirect('login.php');

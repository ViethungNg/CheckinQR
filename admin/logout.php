<?php
require_once __DIR__ . '/../config/bootstrap.php';

logoutAdmin();
redirect(url('/admin/login.php'));

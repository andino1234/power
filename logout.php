<?php
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/helpers.php';

Auth::logout();
flash_set('success', 'Sesión cerrada correctamente.');
redirect('login.php');

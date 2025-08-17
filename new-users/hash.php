<?php
require 'vendor/autoload.php';
use Zend\Crypt\Password\Bcrypt;

$bcrypt = new Bcrypt();
echo $bcrypt->create('12345678');
?>
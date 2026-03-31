<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Easy!Appointments';
$config['protocol'] = 'smtp';
$config['mailtype'] = 'html';
$config['smtp_debug'] = '0';
$config['smtp_auth'] = TRUE;
$config['smtp_host'] = 'smtp.gmail.com';
$config['smtp_user'] = 'hola@honeywhale.com.mx';
$config['smtp_pass'] = 'vdmppehfsvmikbqm'; // App password without spaces
$config['smtp_crypto'] = 'tls';
$config['smtp_port'] = 587;
$config['from_name'] = 'Honey Whale';
$config['from_address'] = 'hola@honeywhale.com.mx';
$config['reply_to'] = 'hola@honeywhale.com.mx';
$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";

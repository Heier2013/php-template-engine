<?php 
include 'Template.php';

$tpl = new Template();

$tpl->setConfig('suffix_cache', 'php');

// echo '<pre>';
// var_dump($tpl->getConfig('suffix'));
// echo '</pre>';

$tpl->show('member');
$tpl->clean();
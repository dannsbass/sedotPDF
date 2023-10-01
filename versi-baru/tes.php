<?php
require 'config.php';
SedotPdf('https://archive.org/details/chorouhjawahara/', file_get_contents('msg_str.txt'));

function tes($v){
  return $v;
}
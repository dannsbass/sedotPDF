<?php

require __DIR__ . '/config.php';

// echo Bot::setWebhook(WEBHOOK); exit;

Bot::start(MSG, OPT);

Bot::channel_post(function () {
});

Bot::document(function () {

  $msg = Bot::message();

  if ($msg['from']['id'] != ADMIN_ID) return;

  //TXT
  if ($msg['document']['mime_type'] == 'text/plain') {

    my_background_exec('prosesText', [$msg], 'require "constants.php"; require "functions.php";', 10000);
  }

  //PDF
  if ($msg['document']['mime_type'] == 'application/pdf') {

    $caption = $msg['caption'];
    $file_id = $msg['document']['file_id'];
    preg_match('/https\:\/\/archive\.org.*\.pdf/', $caption, $cek);
    if (empty($cek[0])) return Bot::sendMessage('URL dari situs archive.org tidak ditemukan', ['reply' => true]);
    $url = $cek[0];
    $title = trim(substr($caption, 0, strpos($caption, $url)));
    my_background_exec('daftarkanPdf', array($url, $file_id, $title), 'require "config.php";', 1000);
    return Bot::sendDocument($file_id, ['caption' => $caption]);
  }
});

Bot::cmd('/unduh', function ($text) {

  $message = Bot::message();

  if ($message['from']['id'] != ADMIN_ID) return;

  if (empty($text)) return Bot::sendMessage("usage: /unduh [patern]\n\nexample: /unduh *php");

  $files = glob($text);
  $dir = "tmp";
  if (!is_dir($dir)) mkdir($dir);
  foreach ($files as $file) copy($file, "$dir/$file");
  $filename = 'files.zip';
  Bot::zipDir("./$dir", "./$filename");
  Bot::sendDocument($filename);
  unlink($filename);
  system("rm -rf $dir");
});

Bot::text(function ($text) {

  $message = Bot::message();
  $msg_str = json_encode($message);
  file_put_contents('msg_str.txt', $msg_str);

  if (strpos($text, 'https://archive.org/details') !== 0) return Bot::sendMessage('Maaf, link tidak valid. ' . MSG, OPT);

  $text = check_url($text) ? $text : stop_proses('Link tidak valid. ' . MSG);

  define('PESAN_SEMENTARA', Bot::sendMessage("Mohon tunggu sebentar, sedang diproses...", ['reply' => true]));

  my_background_exec('SedotPdf', [$text, $msg_str], 'require "config.php";', 10000);

  Bot::sendChatAction('upload_document');

  Bot::sendMessage($text, ['chat_id' => CHANNEL_ID]); //laporan

  $del = Bot::deleteMessage(PESAN_SEMENTARA);

  Bot::debug($del);
});

Bot::all(function () {
  Bot::sendMessage(MSG, OPT);
});

Bot::run();

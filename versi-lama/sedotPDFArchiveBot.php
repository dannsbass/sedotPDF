<?php

/**
 * Bot untuk menyedot file-file PDF dari situs archive.org
 * Intalasi:
 * Buka terminal, ketik: git clone https://github.com/dannsbass/bot && git clone https://github.com/dannsbass/dom
 */

define('TOKEN_BOT', '6307533534:AAEywzNZOoQK_B0Wp3w5Go2q48WnkJCkZHY');
define('NAMA_BOT', 'SedotPDFBot');

require 'bot/Bot.php';
$bot = new Bot(TOKEN_BOT, NAMA_BOT);

define('MSG', "Silahkan kirim link dari situs archive.org\n\nContoh link yang valid: <code>https://archive.org/details/chorouhjawahara/</code>✅\n\nContoh link yang tidak valid:\n\n<code>https://archive.org/details/chorouhjawahara/awnmorid/</code> (<b>awnmorid/</b>❌)\n\n<code>https://archive.org/download/chorouhjawahara/</code> (<b>download</b>❌)");

define('OPT', [
  'parse_mode' => 'html',
  'disable_web_page_preview' => true
]);

$bot->start(MSG, OPT);

$bot->text(function ($text) {
  if (strpos($text, 'https://archive.org/details') === 0) {
    define('PESAN_SEMENTARA', Bot::sendMessage("Mohon tunggu sebentar, sedang diproses...", ['reply' => true]));
    sedotPdf(trim($text));
    Bot::deleteMessage(PESAN_SEMENTARA);
  } else {
    Bot::sendMessage('Maaf, link tidak valid');
  }
});

$bot->all(function () {
  Bot::sendMessage(MSG, OPT);
});

$bot->run();

#fungsi
function check_url($details)
{
  $archive = 'https://archive.org/details';
  if (strpos($details, $archive) === false) return false;
  $details = trim($details);
  if (substr($details, -1) == '/') {
    $details = substr($details, 0, strlen($details) - 1);
    return check_url($details);
  } else {
    if (preg_match("#^$archive/([^\/]+)$#is", $details)) return filter_var($details, FILTER_VALIDATE_URL);
    else return false;
  }
}

function stop_proses(
  $pesan,
  $opt = [
    'reply' => true,
    'parse_mode' => 'html',
    'disable_web_page_preview' => true,
  ]
) {
  Bot::sendMessage($pesan, $opt);
  Bot::deleteMessage(PESAN_SEMENTARA);
  exit;
}

function sedotPdf($details)
{
  // $details = "https://archive.org/details/chorouhjawahara";
  $details = check_url($details) ? $details : stop_proses('Link tidak valid');

  $html = file_get_contents($details);
  if (false === $html) stop_proses("Maaf, gagal memuat konten $details. Silakan coba lagi");

  require __DIR__ . '/dom/simple_html_dom.php';
  $dom = new simple_html_dom($html);

  // find title
  $title = $dom->find('span[class="breaker-breaker"]', 0)->innertext;

  // find description
  $desc = $dom->find('div[id=descript]', 0)->innertext;
  $desc = str_replace('</div>', "\n", $desc);
  $desc = strip_tags($desc, '<a>');

  // get download page html
  $download = str_replace("https://archive.org/details", "https://archive.org/download", $details);
  $html = file_get_contents($download);
  if (false === $html) stop_proses("Maaf, gagal memuat konten $download. Silakan coba lagi.");

  // get all pdf links
  $array_link_pdf = [];
  foreach ($dom->find('a') as $a) {
    $href = $a->href;
    // get all xxx.pdf string from <a href="xxx.pdf">
    if (substr($href, -4, 4) !== '.pdf' || substr($href, -9, 9) === '_text.pdf') continue;
    $array_link_pdf[] = $href;
    // echo $href . PHP_EOL;
  }

  // send message
  $message = "<b>$title</b>\n$details\n$desc";
  $message = str_split($message, 4096)[0];
  $url = Bot::sendMessage($message, ['reply' => true, 'parse_mode' => 'html', 'disable_web_page_preview' => true]);
  $res = json_decode($url);
  if (!$res->ok) stop_proses("Gagal mengirim pesan. Silakan hubungi admin");
  // laporan
  Bot::sendMessage($message, ['chat_id' => -1001834833525, 'parse_mode' => 'html', 'disable_web_page_preview' => true]);

  // send pdf files to Telegram
  $no = 1;
  foreach ($array_link_pdf as $link_pdf) {
    if (strpos($link_pdf, '_text.pdf') !== false) continue;
    $document_url = "https://archive.org" . $link_pdf;
    $json = Bot::sendDocument($document_url, ['reply' => true, 'caption' => $title . $no . "\n" . $document_url]);
    $res = json_decode($json);
    if (!$res->ok) {
      //testing
      $konten_file = file_get_contents($document_url);
      $nama_file = end(explode('/', $link_pdf));
      file_put_contents($nama_file, $konten_file);
      $res_baru = Bot::sendDocument($nama_file, ['reply' => true, 'caption' => $title . $no . "\n" . $document_url]);
      //laporan
      Bot::sendDocument($nama_file, [
        'reply' => true, 'caption' => $title . $no . "\n" . $document_url,
        'chat_id' => -1001834833525
      ]);

      unlink($nama_file);

      //Bot::sendMessage("Gagal mengirim dokumen $no $document_url", ['reply'=>true]);
      //laporan
      //Bot::sendMessage("Gagal mengirim dokumen $no $document_url", ['chat_id'=> -1001834833525, 'parse_mode'=>'html', 'disable_web_page_preview'=>true]);
    }
    $no++;
    // laporan
    Bot::sendDocument($document_url, ['chat_id' => -1001834833525, 'caption' => $title . $no . "\n" . $document_url, 'parse_mode' => 'html', 'disable_web_page_preview' => true]);
  }
}

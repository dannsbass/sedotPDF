<?php

########################################
function daftarkanPdf($url, $file_id, $title)
{
  if (!Dannsheet::findRowByValue($url, NAMA_SHEET . '!A1:A')) Dannsheet::appendRow([[$url, $file_id, $title]], NAMA_SHEET);
}

########################################
class Test
{

  //NAMA_SHEET
  const TG = "https://api.telegram.org/bot" . TOKEN_BOT;

  public static function sendMessage($message, array $opt = [])
  {
    return self::send($message, array_merge(['method' => 'sendMessage'], $opt));
  }

  public static function sendDocument($document, array $opt = [])
  {
    return self::send($document, array_merge(['method' => 'sendDocument'], $opt));
  }

  public static function send($obj, $opt = null)
  {

    if ($opt['method'] == 'sendDocument') {

      if (!filter_var($obj, FILTER_VALIDATE_URL)) {

        if (file_exists($obj)) {

          $obj = curl_file_create($obj);
        }
      }

      $param = 'document';
    }

    if ($opt['method'] == 'sendMessage') {
      $param = 'text';
    }

    $ch = curl_init();

    $options = [
      CURLOPT_URL => self::TG . "/" . $opt['method'],
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => array_merge([$param => $obj], $opt),
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false
    ];

    if ($param == 'document') {
      $options[CURLOPT_HTTPHEADER] = ['Content-Type: multipart/form-data'];
    }

    curl_setopt_array($ch, $options);

    $result = curl_exec($ch);

    // file_put_contents('log', $result . PHP_EOL, FILE_APPEND);

    return $result;
  }
}

########################################
function my_background_exec($function_name, $params, $str_requires, $timeout = 600)
{
  $map = array('"' => '\"', '$' => '\$', '`' => '\`', '\\' => '\\\\', '!' => '\!');
  $str_requires = strtr($str_requires, $map);
  $path_run = dirname($_SERVER['SCRIPT_FILENAME']);
  $my_target_exec = "php -r \"chdir('{$path_run}'); {$str_requires} \\\$params=json_decode(file_get_contents('php://stdin'), true); call_user_func_array('{$function_name}', \\\$params);\"";
  $my_target_exec = strtr(strtr($my_target_exec, $map), $map);
  $my_background_exec = "(php -r \"chdir('{$path_run}'); {$str_requires} my_timeout_exec(\\\"{$my_target_exec}\\\", file_get_contents('php://stdin'), {$timeout});\" <&3 &) 3<&0"; //php by default use "sh", and "sh" don't support "<&0"
  my_timeout_exec($my_background_exec, json_encode($params), 2);
}

########################################
function my_timeout_exec($cmd, $stdin = '', $timeout = 0)
{
  $start = time();
  $stdout = '';
  $stderr = '';
  //file_put_contents('debug.txt', time().':cmd:'.$cmd."\n", FILE_APPEND);
  //file_put_contents('debug.txt', time().':stdin:'.$stdin."\n", FILE_APPEND);

  $process = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
  if (!is_resource($process)) {
    return array('return' => '1', 'stdout' => $stdout, 'stderr' => $stderr);
  }
  $status = proc_get_status($process);
  posix_setpgid($status['pid'], $status['pid']);    //seperate pgid(process group id) from parent's pgid

  stream_set_blocking($pipes[0], 0);
  stream_set_blocking($pipes[1], 0);
  stream_set_blocking($pipes[2], 0);
  fwrite($pipes[0], $stdin);
  fclose($pipes[0]);

  while (1) {
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);

    if (time() - $start > $timeout) {
      //proc_terminate($process, 9);    //only terminate subprocess, won't terminate sub-subprocess
      posix_kill(-$status['pid'], 9);    //sends SIGKILL to all processes inside group(negative means GPID, all subprocesses share the top process group, except nested my_timeout_exec)
      //file_put_contents('debug.txt', time().":kill group {$status['pid']}\n", FILE_APPEND);
      return array('return' => '1', 'stdout' => $stdout, 'stderr' => $stderr);
    }

    $status = proc_get_status($process);
    //file_put_contents('debug.txt', time().':status:'.var_export($status, true)."\n";
    if (!$status['running']) {
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($process);
      return $status['exitcode'];
    }

    usleep(100000);
  }
}

########################################
function check_url($details)
{
  $archive = 'https://archive.org/details';
  if (strpos($details, $archive) !== 0) return false;
  $details = trim($details);
  if (substr($details, -1) == '/') {
    $details = substr($details, 0, strlen($details) - 1);
    return check_url($details);
  } else {
    if (preg_match("#^$archive/([^\/]+)$#is", $details)) return filter_var($details, FILTER_VALIDATE_URL);
    else return false;
  }
}

########################################
function stop_proses($pesan, $opt = ['reply' => true, 'parse_mode' => 'html', 'disable_web_page_preview' => true])
{
  Bot::sendMessage($pesan, $opt);
  Bot::deleteMessage(PESAN_SEMENTARA);
  exit;
}

########################################
function SedotPdf(string $details, string $msg, array $saved_links_and_ids = [], bool $debug = false)
{

  // $details = "https://archive.org/details/chorouhjawahara";
  $msg = json_decode($msg, true);
  $from_id = $msg['from']['id'];
  $message_id = $msg['message_id'];

  $html = file_get_contents($details);
  if (false === $html) return Test::sendMessage("Maaf, gagal memuat konten $details. Silakan coba lagi.", ['chat_id' => $from_id]);

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
  if (false === $html) return Test::sendMessage("Maaf, gagal memuat konten $download. Silakan coba lagi.", ['chat_id' => $from_id]);

  // get all pdf links
  $array_link_pdf = [];
  foreach ($dom->find('a') as $a) {
    $href = $a->href;
    // get all *.pdf string from <a href="*.pdf">
    if (substr($href, -4, 4) !== '.pdf' || substr($href, -9, 9) === '_text.pdf') continue;
    $array_link_pdf[] = $href;
  }

  if (count($array_link_pdf) < 1) return Test::sendMessage("Maaf, tidak ditemukan file PDF dalam link ini. Silakan kirim link yang ada PDF-nya.", ['chat_id' => $from_id, 'reply_to_message_id' => $message_id]);

  $array_link_pdf = array_unique($array_link_pdf);

  ###################### DEBUG STARTS ######################
  #                                                        #
  $json_debug = json_encode([
    'details' => $details,
    'links' => $array_link_pdf,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  print_r($json_debug);

  if ($debug) return Test::sendMessage($json_debug, ['chat_id' => $msg['from']['id']]);
  #                                                        #
  ###################### DEBUG ENDS ########################

  // send message
  $message = "<b>$title</b>\n$details\n$desc";
  $message = str_split($message, 4096)[0];

  if (count($saved_links_and_ids) == 2) {
    $saved_links = $saved_links_and_ids['saved_links'];
    $saved_file_id = $saved_links_and_ids['saved_file_id'];
  } else {
    $saved_links_and_ids = getSavedLinks();
    $saved_links = $saved_links_and_ids['saved_links'];
    $saved_file_id = $saved_links_and_ids['saved_file_id'];
  }

  $url = Test::sendMessage($message, ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'disable_web_page_preview' => true]);

  $res = json_decode($url);

  if (is_null($res) || !$res->ok) Test::sendMessage("Gagal mengirim pesan. Silakan hubungi admin", ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'disable_web_page_preview' => true]);

  // send pdf files to Telegram
  $no = 1;
  foreach ($array_link_pdf as $link_pdf) {

    if (strpos($link_pdf, '_text.pdf') !== false) continue;

    $document_url = "https://archive.org" . $link_pdf;

    if (in_array($document_url, $saved_links)) {

      $index = array_search($document_url, $saved_links);
      $file_id = $saved_file_id[$index];

      // 1. TRY SENDING FILE FROM FILE ID
      $json = Test::sendDocument($file_id, ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'caption' => $title . $no . "\n" . $document_url]);
    } else {

      // 2. TRY SENDING FILE FROM URL
      $json = Test::sendDocument($document_url, ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'caption' => $title . $no . "\n" . $document_url]);

      file_put_contents('log', "line: " .  __LINE__ . " " . $json . PHP_EOL, FILE_APPEND);


      $res = json_decode($json);

      if ($res) {

        if ($res->ok) {

          masukkanDataBaru($json, $document_url);

          Test::sendDocument($document_url, ['chat_id' => -1001229830612/*Channel Gratis Kitab*/, 'parse_mode' => 'html', 'caption' => $title . $no . "\n" . $document_url]);
        }
      }
    }

    $res = json_decode($json);

    // 3. TRY SENDING FILE FROM FILE
    if (is_null($res) || !$res->ok) {


      $konten_file = file_get_contents($document_url);

      if (!$konten_file) {

        Test::sendMessage("Maaf, gagal mengirim file $document_url.", ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'disable_web_page_preview' => true]);
      }

      $array_from_url = explode('/', $link_pdf);

      $nama_file = end($array_from_url);

      $tes = file_put_contents($nama_file, $konten_file);

      $json = Test::sendDocument($nama_file, ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'caption' => $title . $no . "\n" . $document_url]);

      file_put_contents('log', "line: " .  __LINE__ . " " . $json . PHP_EOL, FILE_APPEND);

      $res = json_decode($json);

      if ($res) {
        if (!$res->ok) {
          if ($res->description == 'Request Entity Too Large') {

            Test::sendMessage("Maaf, gagal mengirim file $document_url. Mungkin ukuran file terlalu besar.", ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'disable_web_page_preview' => true]);
          }
        }
      }

      if (!$res || is_null($res)) {

        Test::sendMessage("Maaf, gagal mengirim file $document_url. Mungkin ukuran file terlalu besar.", ['chat_id' => $msg['from']['id'], 'reply_to_message_id' => $msg['message_id'], 'parse_mode' => 'html', 'disable_web_page_preview' => true]);

        file_put_contents('log', "line: " .  __LINE__ . " " . $json . PHP_EOL, FILE_APPEND);
      }

      if ($res->ok) {

        masukkanDataBaru($json, $document_url);

        if ($res->result->document) {

          $file_id = $res->result->document->file_id;
          $caption = $res->result->caption;
          $channel_gratis_kitab_id = -1001229830612;/*Channel Gratis Kitab*/
          Test::sendDocument($file_id, ['parse_mode' => 'html', 'caption' => $caption, 'chat_id' => $channel_gratis_kitab_id]);
        }

        unlink($nama_file);
      } else {

        // Test::sendMessage("Maaf, gagal mengirim file $nama_file. Silakan hubungi coba lagi.", ['chat_id'=>$msg['from']['id'], 'reply_to_message_id'=>$msg['message_id'], 'parse_mode'=>'html', 'disable_web_page_preview'=>true]);

        file_put_contents('log', $json . PHP_EOL, FILE_APPEND);
      }
    }

    $no++;
  }
}

########################################
function masukkanDataBaru($json, $document_url)
{

  // require_once 'dannsheet_config.php';

  $res = json_decode($json);

  if ($res->result->document->mime_type == 'application/pdf') {

    $file_name = $res->result->document->file_name;
    $file_id = $res->result->document->file_id;
    $caption = $res->result->caption;

    $title = trim(substr($caption, 0, strpos($caption, $document_url)));

    Dannsheet::appendRow([[$document_url, $file_id, $title]], NAMA_SHEET);
  }
}

########################################
function prosesText($msg)
{

  $file_id = $msg['document']['file_id'];

  $json = file_get_contents("https://api.telegram.org/bot" . TOKEN_BOT . "/getFile?file_id=$file_id");

  $obj = json_decode($json);

  if ($obj->ok) {

    $file_path = $obj->result->file_path;

    $konten = file_get_contents("https://api.telegram.org/file/bot" . TOKEN_BOT . "/$file_path");

    $array_of_links = explode("\n", $konten);

    $saved_links_and_ids = getSavedLinks();

    foreach ($array_of_links as $link) {

      SedotPdf($link, json_encode($msg), $saved_links_and_ids);
    }
  }
}

########################################
function getSavedLinks()
{

  require_once 'dannsheet_config.php';

  if ($A_values = Dannsheet::getValues(NAMA_SHEET . '!A1:B')) {

    $saved_links = [];
    $saved_file_id = [];

    foreach ($A_values as $array) {

      $saved_links[] = $array[0];
      $saved_file_id[] = $array[1];
    }
  }

  return ['saved_links' => $saved_links, 'saved_file_id' => $saved_file_id];
}

function saveLog($message = '')
{
  file_put_contents('log', "line: " .  __LINE__ . " " . $message . PHP_EOL, FILE_APPEND);
}

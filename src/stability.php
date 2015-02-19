<?php

require_once(dirname(__FILE__) . '/../../fund/src/catalog.php');
require_once(dirname(__FILE__) . '/../../fund/src/history.php');

date_default_timezone_set('UTC');
ini_set('memory_limit', '1G');

define('DAY', 24 * 60 * 60);
define('TMPFILE', tempnam('/tmp', 'fund'));

class Stability {
  static public function GetDirectoryName($fund_id) {
    $fund_id = substr($fund_id, -8);
    $fund_prefix = substr($fund_id, 0, 2);
    return dirname(__FILE__) . "/../data/$fund_prefix/$fund_id";
  }

  static public function GetFileName($fund_id) {
    return self::GetDirectoryName($fund_id) . '/stability.json';
  }

  static public function Get($fund_id) {
    if (!is_readable(self::GetFileName($fund_id))) {
      fwrite(STDERR, 'No such file: ' . self::GetFileName($fund_id) . "\n");
      exit(1);
    }
    return json_decode(file_get_contents(self::GetFileName($fund_id)), TRUE);
  }

  static public function Run($history) {
    fwrite(STDERR, "Calculating stability...\n");
    $data = ['stability' => []];
    $prices = $history['prices'];
    ksort($prices);
    $distributions = $history['distributions'];
    ksort($distributions);
    $dates = array_keys($prices);
    $start_date = $dates[0];
    $end_date = $dates[count($dates) - 1];
    $cache = 0;
    for ($date = $start_date; $date <= $end_date;
         $date = date('Y-m-d', strtotime($date) + DAY)) {
      if (isset($distributions[$date])) {
        $cache += $distributions[$date];
      }
      if (isset($prices[$date])) {
        $price = $prices[$date];
      }
      $prices[$date] = $price + $cache;
    }
    ksort($prices);
    $dates = array_keys($prices);
    $start_date = NULL;
    for ($i = 52 * 7 + 7; $i < count($dates); $i++) {
      if (date('l', strtotime($dates[$i])) == 'Sunday') {
        $start_date = $dates[$i];
        break;
      }
    }
    if (is_null($start_date)) {
      return [];
    }
    for ($date = $start_date; $date <= $end_date;
         $date = date('Y-m-d', strtotime($date) + 7 * DAY)) {
      $time = strtotime($date);
      foreach ([4, 13, 26, 52] as $week) {
        $price_diffs = [];
        for ($days_ago = 1; $days_ago <= $week * 7; $days_ago++) {
          $base_date = date('Y-m-d', $time - $days_ago * DAY);
          $past_date = date('Y-m-d', $time - ($days_ago + 7) * DAY);
          if (!isset($prices[$base_date]) || !isset($prices[$past_date])) {
            fwrite(STDERR, "Error: start_date=$start_date, " .
                           "past_date=$past_date\n");
            exit(1);
          }
          $price_diffs[] = intval(round(
              log($prices[$base_date] / $prices[$past_date]) * 10000));
        }
        file_put_contents(TMPFILE,
                          "$week\n\n" . implode("\n", $price_diffs) . "\n");
        $output = [];
        exec('./bin/threshold < ' . TMPFILE, $output);
        $data['stability'][$date]['period'][$week] =
            floatval(trim(implode("\n", $output)));
      }
      $data['stability'][$date]['price'] = $prices[$date];
    }
    return $data;
  }

  static public function Update($sbi_fund_id) {
    fwrite(STDERR, "Updateing $sbi_fund_id...\n");
    $fund_id = substr($sbi_fund_id, -8);
    $history = History::Get($fund_id);
    if (!is_array($history) || !isset($history['modified'])) {
      fwrite(STDERR, "Info data is broken.");
      return;
    }
    if (is_readable(self::GetFileName($fund_id))) {
      $data = self::Get($fund_id);
      if ($data['modified'] > $history['modified']) {
        fwrite(STDERR, "Cache is still alive.\n");
        return;
      }
    }
    if (!is_dir(self::GetDirectoryName($fund_id))) {
      mkdir(self::GetDirectoryName($fund_id), 0777, TRUE);
    }
    $data = self::Run($history);
    $data['modified'] = time();
    $data['modified_str'] = gmdate('Y-m-d H:i:s', $data['modified']);
    ksort($data);
    file_put_contents(
        self::GetFileName($fund_id),
        json_encode($data, JSON_PRETTY_PRINT |
                           JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "Finished.\n");
  }

  static public function Main($argv) {
    if (count($argv) > 1) {
      array_shift($argv);
      $sbi_fund_ids = $argv;
    } else {
      $catalog = Catalog::Get();
      $sbi_fund_ids = $catalog['funds'];
      shuffle($sbi_fund_ids);
    }
    foreach ($sbi_fund_ids as $sbi_fund_id) {
      self::Update($sbi_fund_id);
    }
  }
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
  Stability::Main($argv);
}

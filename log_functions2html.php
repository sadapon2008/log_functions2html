<?php

/*
* basic usage:
*
* $ export PGUSER=postgres
* $ export PGDATABASE=mydb
* $ php log_functions2html.php my_func /var/lib/pgsql/data/pg_log/postgresql-Fri.log my_func.csv >my_func.html
*
*/
function array_last(array $array)
{
    return end($array);
}

if(count($argv) < 4) {
  echo "usage\n";
  echo "  " . $argv[0] . " function_name log_filename csv_filename\n";
  exit(1);
}

// ターゲット関数名
$target = mb_strtolower($argv[1]);
// pg_log下のログファイル名
$logfilename = $argv[2];
// 一時ファイル名
$csvfilename = $argv[3];

// pg_logのログファイルから直近のターゲット関数の実行ログをとりだす
$cmd = 'echo "LOG:  log_functions, BEGIN, ' . $target . '" >' . $csvfilename;
system($cmd);
$cmd = 'grep "^LOG:\s*log_functions," ' . $logfilename . ' | tac | ' . "perl -e '" . '$flag=0;while(<>){$l=$_;if($l =~ /^LOG:  log_functions, BEGIN, ' . $target . '\s*$/){$flag=1;}if($flag==0){print $l;}}' . "' |tac >>" . $csvfilename;
system($cmd);

// 関数名と行番号を取り出す
$stat = array();
$func = array();
$fh = fopen($csvfilename, 'r');
if($fh === false) {
  echo "error: temporary file could not be opened" . "\n";
  exit(1);
}
while(!feof($fh)) {
  $l = fgets($fh);
  //echo $l;
  if(preg_match('/^LOG:  log_functions, BEGIN, (.*)$/u', $l, $matches)) {
    array_push($func, $matches[1]);
  } elseif(preg_match('/^LOG:  log_functions, END/u', $l)) {
    array_pop($func);
  } elseif(preg_match('/LOG:  log_functions, STMT START, line (\d+)/u', $l, $matches)) {
    if(empty($stat[array_last($func)])) {
      $stat[array_last($func)] = array();
    }
    if(empty($stat[array_last($func)][(int)$matches[1]])) {
      $stat[array_last($func)][(int)$matches[1]] = 0;
    }
    $stat[array_last($func)][(int)$matches[1]] += 1;
  }
}
fclose($fh);

// CSV出力
$fh = fopen($csvfilename, 'w');
foreach($stat as $func => $func_stat) {
  foreach($func_stat as $lineno => $count) {
    fputcsv($fh, array($func,$lineno,$count));
  }
}
fclose($fh);

// 関数のソースの行ごとに実行回数を関連付ける
$result = array();
foreach($stat as $func => $func_stat) {
  $result[$func] = array();
  $cmd = "psql -c \"copy (select prosrc from pg_proc where proname = '" . $func . "') to stdout with (format csv, FORCE_QUOTE *, encoding 'UTF-8');\" | sed -e 's/^\"//g'";
  $output = array();
  exec($cmd, $output);
  foreach($output as $idx => $content) {
    $count = 0;
    $lineno = $idx+1;
    if(array_key_exists($lineno, $func_stat)) {
      $count = $func_stat[$lineno];
    }
    $result[$func][] = array($lineno, $count, $content);
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title><?php echo $target; ?></title>
<style type="text/css">
table, td, th{
  border: 1px #000000 solid;
}
table {
  border-collapse: collapse;
}
td.line{
  text-align: right;
  width: 50px;
}
td.count{
  text-align: right;
  width: 50px;
}
td.code {
  border-top: 0;
  border-bottom: 0;
  min-width: 600px;
}
pre {
  margin: 0;
}
th {
  background-color: #0000ff;
}
tr.hit {
  background-color: #ffff00;
}
</style>
</head>
<body>
<h1>target: <?php echo $target; ?></h1>
<hr>
<h1>code coverage:</h1>
<?php foreach($result as $func => $lines): ?>
<h2>function: <?php echo $func; ?></h2>
<table>
  <thead>
    <tr>
      <td>Line</td>
      <td>Count</td>
      <td>Code</td>
    </tr>
  </thead>
  <tbody>
<?php foreach($lines as $line): ?>
<?php   $class = ''; ?>
<?php   if($line[1] > 0): ?>
<?php     $class = 'hit'; ?>
<?php   endif; ?>
    <tr class="<?php echo $class; ?>">
      <td class="line"><?php echo $line[0]; ?></td>
      <td class="count"><?php echo $line[1]; ?></td>
      <td class="code"><pre><?php echo rtrim($line[2], "\n"); ?></pre></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>
</body>
</html>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>あからさまに危険なPHPコード</title>
</head>
<body>
<h1>ファイル読み込み</h1>
<form action="index.php" method="post">
    <input type="text" name="name">
    <input type="submit" value="表示">
</form>
<pre>
<?php
$name = filter_input(INPUT_POST, "name");
echo("name: " . $name . "\n");
if($name != "") {
    $result = file_get_contents($name);
    if($result != FALSE) {
        echo($result);
    } else {
        echo("ファイル読み込み失敗: " . $name);
    }
}
?>
</pre>
<h1>環境変数一覧</h1>

<ul>
<?php
    foreach(getenv() as $k => $v) {
?>
    <li> <?= $k ?> => <?= $v ?></li>
<?php
    }
?>
</ul>
</body>
</html>

# シークレット

`シークレット`(`Secret`)は、コンフィグマップと同様にKey-Value形式でデータを渡せるリソースですが、大きな違いとして以下のものが挙げられます。

- 機密性が高いとされるデータを保持する目的で使います
- SSHの公開鍵やTLS/SSLの証明書を保持する目的で使います

たとえばデータベースの管理権限のパスワードや、AWSなどで使うアクセスキー等が機密性の求められるものと思います。

## 情報格納方法の違い

基本的なマニフェストの書式は`configMap`と変わりませんが、情報は事前にBase64形式に変換しておく必要があります。
問題はどうやってそんな処理をするかですが、LinuxやmacOSの場合、`base64`コマンドで行えます。

```{code-block} bash
:caption: Linux(bash)での実行例

 $ echo -n "hogehoge" | base64
 aG9nZWhvZ2U=
```

Windowsの場合、PowerShellで`System.Text.Encoding`と`Convert`クラスの組み合わせで対応するというのがあります([参考](https://win.just4fun.biz/?PowerShell/PowerShell%E3%81%A7Base64%E3%81%AE%E3%82%A8%E3%83%B3%E3%82%B3%E3%83%BC%E3%83%89%E3%81%A8%E3%83%87%E3%82%B3%E3%83%BC%E3%83%89))。

ですが、正直面倒というのもあるので、vscodeのBase64変換拡張を使ってみるのが良いでしょう。

- [vscode-base64](https://marketplace.visualstudio.com/items?itemName=adamhartford.vscode-base64)

変換したい文字列をマニフェスト上でデータとして記入してから変換をかけることになります。

例えばこのようなマニフェストがあるとしましょう。

```{literalinclude} codes/secret-example.yml
:caption: secret-example.yml(シークレットの例)
:emphasize-line: 8
:language: yaml
```

値の部分を選択し、"Base64 Encode"コマンドを発動すると書き換えられます。

```{figure} images/secret-base64.png
範囲指定してのコマンド呼び出し
```

```{figure} images/secret-base64-after.png
処理結果
```

もちろん同じ範囲を選んで"Base64 Decode"をすれば戻ります。

ファイルを組み込む場合も、`kubectl`コマンドを使うことで同様に行えます。

```{code-block} pwsh
:caption: kubectlを使ったファイルからのシークレット作成(一般データ)
PS> kubectl create secret generic data2 --from-file='secret-base64.png'
secret/data2 created
```

といった具合です。`kubectl get secret -o yaml`を使うことでマニフェストの形で結果を得ることもできます。

```{code-block} pwsh
# 先ほど作ったsecret/data2をYAML形式マニフェストとして出力
PS> kubectl get secret/data2 -o yaml
apiVersion: v1
data:
  secret-base64.png: iVBORw0KGgoAAAANSUhEUgAAA5oAAAKWCAYAAAAoQOZEAAAKq2lDQ1BJQ0MgUHJvZmlsZQAASImVlwdUU+kSgP9700NCSwgdQm/
...(中略)...
  XV7ERABERABERABERABERABERg2glIaE47cnUoAiIgAiIgAiIgAiIgAiIgAslN4P8D3Z6/gumLN6kAAAAASUVORK5CYII=
kind: Secret
metadata:
  creationTimestamp: "2022-11-05T04:59:47Z"
  name: data2
  namespace: default
  resourceVersion: "8366"
  uid: 572286e6-5c1d-430d-803c-80d948db4e39
type: Opaque
```

マニフェスト上にある `type` は、格納するデータの形式であり、一般的なデータは `Opaque`(『不透明』『光沢の無い』といった意味合い)という形式になります。

その他のものとしては、K8sのドキュメントを見た方が良いでしょう。

- [Secretの種類](https://kubernetes.io/ja/docs/concepts/configuration/secret/#secret-types)


## コンテナ側での利用方法

利用方法については、`ConfigMap` の時とほぼ同じで、参照先として `configMapKeyRef` から `secretKeyRef` を使うことになります。

```{literalinclude} codes/secret-pod-example.yml
:caption: secretの参照例(secret-pod-example.yml)
:emphasize-lines: 23-28,30-33
:language: yaml
```

## 実アプリケーションでのsecret参照例

たとえば、MariaDBのイメージ(ポッド)においては、**環境変数でrootのパスワードを渡す**などが行われていましたが、環境変数はWebアプリなどから参照して直接値を得られてしまう可能性があります。そのため実運用では使わない方がよかったりします。

別の方法として、このような記述がMariaDBのイメージの説明に記載されています。

- [mariadb](https://hub.docker.com/_/mariadb)

> As an alternative to passing sensitive information via environment variables, _FILE may be appended to the previously listed environment variables, causing the initialization script to load the values for those variables from files present in the container. In particular, this can be used to load passwords from Docker secrets stored in /run/secrets/<secret_name> files. For example:

```
$ docker run --name some-mysql -e MARIADB_ROOT_PASSWORD_FILE=/run/secrets/mysql-root -d mariadb:latest
```

この手順にそって作ってみましょう。

### secret

secretについては、以下の変数を定義することにします。

- `MARIADB_ROOT_PASSWORD`: `dbadmin`
- `MARIADB_DATABASE`: `sample`
- `MARIADB_USER`: `sampleuser`
- `MARIADB_PASSWORD`: `hogehoge`

これに基づく `secret/mariadb-sample` を以下のように設計しました。
もちろん値はBase64エンコードにしています。

```{literalinclude} codes/mariadb-sample.yml
:caption: パラメータをsecretで設定
:language: yaml
```

### pod

ポッドに関しては、podテンプレートを利用して以下のように定義しました。
ポイントは環境変数は`〜_FILE`の形式で定義するということです。
直接値が知られるよりはマシな状態になります。

```{literalinclude} codes/mariadb-sample-pod.yml
:caption: 環境変数は参照先ファイルのパスにしたものとなります
:language: yaml
```

## おまけ: ファイルは読まれないのか?

今回、MariaDBのイメージのおいて環境変数に値を直接置かずに、代わりにファイル名を渡す方式としましたが、これだけでセキュリティに寄与できるのでしょうか?
答えから言えば、これだけで完璧ということにはなりません。

たしかに直接管理者パスワードが読めなくなったのですが、読むべきファイルのパスが存在するため、PHPなど実行環境の命令を呼び出すことができれば、該当するファイルを開いて表示できるかもしれません。

そこで、サンプルのイメージとして「非常にセキュリティ的に問題のある」Dockerイメージを準備したので、置き換えたマニフェストで検証してみましょう。

```{literalinclude} codes/insecure-pod.yml
:caption: セキュリティ的にむちゃくちゃなPHPコードの入ったポッド
:language: yaml
```

このマニフェストを使ってポッドを起動してみます。
このポッドのコンテナは、現在の環境変数が見えます。
当然secretで渡しているMARIADB周りも見えてます。

では、`MARIADB_ROOT_PASSWORD_FILE`の値を引っ張ってきて、上部の『ファイル読み込み』のフォームにパスを渡してみてください。

```{figure} images/insecure-v1.png
変数の値に含まれるファイルが読めてしまう例
```

PHPの実行環境は、OSの中にあるファイルを(権限の範囲内で)アクセスできてしまうため、ファイル名を外部からインジェクションできるようなケースにおいては脆弱性となりえます。
対策はいくつかあります。

- ファイル名とおぼしき入力をみだりにファイル操作系関数で使わない
- どうしても使いたければフィルタリングする
  - 例えば`basename`でファイル名部分のみ抽出して、基底のディレクトリと結合して利用
- 指定のディレクトリ以下しか開けなくする
  - これが最強とは言えませんが

最後の『指定のディレクトリ以下』については、PHPの設定で`open_basedir`を使うというのがあります。
実際これを `/var/www/html` のみ可能という形で設定したのが `densukest/insecure-php:v2` です。
マニフェストのイメージ名をv2に差し替えて起動させるとエラーになります。




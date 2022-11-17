# 実例: Laravelアプリを構成してみよう

前期(AS構築Ⅰ)にて作ったメモアプリを実際に構成してみましょう。
ソース一式は以下のリンクに存在しますが、ここから作ったイメージを走らせるということにします。

- [kd-it/laravel-sample-remastered](https://github.com/kd-it/laravel-sample-remastered)

なお、下記のマニフェストは `docker-compose.yml` ファイルをベースに構築しています。

## バックエンド

データベース部分を考えます。データベースは普通にMySQL/MariaDBとなります。
まずは「とりあえず動かす」とします、セキュリティ部分はちょっと後回し。

### ストレージの確保

ストレージはPVCで確保しておきます、512MiBとでもしておきましょうか。

```{literalinclude} codes/laravel-manifests/db-store.yml
:caption: ストレージ部分
:language: yaml
```

### データベース部分

続いてデータベースですが、この時点ではまだsecretを使わない形にしておきます。

```{literalinclude} codes/laravel-manifests/backend.yml
:caption: バックエンド(データベース)
:language: yaml
```

ここまででいちど適用して、データベースが起動できるかをみておきます。

### サービス公開

今回はフロントエンド(Web/PHP)だけで見えれば良いので、ClusterIPでいいでしょう。

```{literalinclude} codes/laravel-manifests/svc-backend.yml
:caption: ClusterIPでdeploy/backendを公開
:language: yaml
```

アクセスできるかのテストに関しては、alpineのイメージでシェルを建てて確認するぐらいで良いかと思います。

```{code-block} pwsh
PS> kubectl run --rm --image=alpine -it alpine -- sh
/ # nslookup backend
Server:         10.96.0.10
Address:        10.96.0.10:53 # アドレスが取得できていればOK
... 以下略
/ # exit
```

## フロントエンド

フロントエンドはLaravelによるコードの入ったイメージを作っているので、それを呼び出しましょう。

```{literalinclude} codes/laravel-manifests/frontend.yml
:caption: フロントエンド側
:language: yaml
```

サービスも設定しましょう。
こちらはNodePortで設定し、外側とも接点を作っておきます。

```{literalinclude} codes/laravel-manifests/svc-frontend.yml
:caption: フロントエンド側サービス(NodePort)
:language: yaml
```

両者を適用後、`minikube service`でホスト側と接続してあげてみてください。

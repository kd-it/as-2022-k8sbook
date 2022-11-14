# カスタムイメージを使う

ここまでの基礎知識にて、実際に自分で作ったイメージをK8sのクラスタに載せて使うことができるようになります。
ただ、イメージをどのように持ち込むのかが少し悩みどころになると思います。

## インターネット上のリポジトリを使う方法

DockerHubなど、インターネット上で公開されているリポジトリを使うのがある意味一番困らないかと思います。

- [DockerHub](https://hub.docker.com/)
    - パブリックイメージは一応無制限
    - プライベートイメージは無料の範囲では1つだけ
- [Github Container Registry](https://github.com/features/packages)
    - オープンソース(パブリック)のものは無制限
    - プライベートなものは無料で500MB、Proで2GBまで可能

```{tip}
なおGitHubは、登録をすれば本学の学生は2年限定でProを無料で使うことができます(2年ごとに再度追加登録すればOK)。

- 参考資料: [【学割】GitHub Educationの申請とPrivateリポジトリを無料で使う方法](https://note.com/yotaszk/n/n8cf4d00dbf30)
  - ただし佐藤は現状でこれでいいかは未確認ですあしからず
```

## イメージの作成とポイント

### イメージの作成とポイント

イメージを作成するのは、Docker環境を使うことになるでしょう。
ということでまず簡単なカスタムイメージを作ってみたいと思います。

```{code-block} pwsh
:caption: 簡単なイメージ作成(準備)

PS> mkdir hello-web
PS> cd hello-web
PS> code .
```

カレントディレクトリでvscode開いて、ファイルを2つ作ります。

```{literalinclude} codes/hello-web/index.html
:caption: hello-web/index.html
```

```{tip}
`html:5`スニペットをベースにちょっと書き換えただけです。
```

```{literalinclude} codes/hello-web/Dockerfile
:caption: hello-web/Dockerfile
```

これをイメージ化しておきます。
DockerHubのユーザー名を`densukest`としておきます。

```{code-block} pwsh
PS> docker build -t densukest/hello-web:v0 .
```

読めるかどうかは当然ですがローカルのDocker環境でテストします。

```{code-block} pwsh
PS> docker run --rm -p 8080:80 densukest/hello-web:v0
```

これでブラウザ上で http://127.0.0.1:8080/ で表示できます。

```{figure} images/hw-web-browse.png
ブラウザで表示した例
```

```{tip}
以前表示したことあるページが出てしまった場合、キャッシュが載っている可能性があります。ブラウザ設定でキャッシュをクリアして再度読み込んでみると良いでしょう。
```

### イメージをK8s側で使ってみると…

では、作ったイメージをK8s側で読み込んでみましょう。
マニフェストを作っていきます。

まずはデプロイメントです。

```{literalinclude} codes/hello-web/manifests/deploy-hello-web.yml
:caption: hello-web/manifests/deploy-hello-web.yml
```

```{caution}
上記マニフェストをコピペする際は、必ずイメージ名を自分で作成したイメージ(+タグ)にすることを忘れずに。
```

準備できたらとりあえずapplyですね。

```{code-block} pwsh
:caption: デプロイメントのマニフェストを適用すると…
:emphasize-lines: 7

PS> kubectl apply -f deploy-hello-web.yml(のパス)
deployment.apps/hello-web created

# 適用後にポッド状態を見ると…
PS> kubectl get pods
NAME                         READY   STATUS         RESTARTS   AGE
hello-web-84785d998b-kx955   0/1     ErrImagePull   0          12s
```

**ErrImagePull** が発生しています。
このエラーは『イメージが取得できない』事を意味しています。
おかしいですね、自分の環境でイメージ作った以上ローカルのリポジトリには入っているはずです。

### なぜこうなるのか?

この理由は、[Docker環境を用いたminikube環境とサービスマッピング](./service.html#dockerminikube)で解説したことから導けます。

- minikube環境はDinD環境によって動いている
- よって、今使っていたDocker環境とは別の環境で動いている
- 当然イメージ置き場も別の場所となる

イメージはこんな感じです。

```{figure} images/docker-in-docker-imagecache.drawio.png
Docker環境が別である以上、イメージキャッシュも別の場所です
```

このような事情から、DinD上のminikube空間にはイメージが存在せずにエラーとなってしまうのです。

```{tip}
Docker DesktopのK8s機能を用いた環境の場合、イメージキャッシュを共有します。
よってこの部分は省略可能です。
```

### Docker環境の切り替えとビルド

前項から『DinD環境上でイメージを作れば良いのでは?』と導けると思いますが、どうやって行うのでしょう。
実はDockerも、Docker Engineのサーバーとローカル(`docker`コマンド)の間では、APIによる接続(+証明書による認証)でサーバー(Docker Engine)上で処理が行われます。
このサーバーのAPIエンドポイントと証明書を切り替えることで、DinD側にスイッチできます。

ためしに、 `minikube docker-env` とコマンド打ち込んでみてください。

```{code-block} pwsh
:caption: docker-envの出力
PS> minikube docker-env # bash/zsh
export DOCKER_TLS_VERIFY="1"
export DOCKER_HOST="tcp://127.0.0.1:50990"
export DOCKER_CERT_PATH="/Users/foo/.minikube/certs"
export MINIKUBE_ACTIVE_DOCKERD="minikube"

# To point your shell to minikube's docker-daemon, run:
# eval $(minikube -p minikube docker-env)

PS> minikube docker-env --shell powershell
$Env:DOCKER_TLS_VERIFY = "1"
$Env:DOCKER_HOST = "tcp://127.0.0.1:50990"
$Env:DOCKER_CERT_PATH = "/Users/foo/.minikube/certs"
$Env:MINIKUBE_ACTIVE_DOCKERD = "minikube"
# To point your shell to minikube's docker-daemon, run:
# & minikube -p minikube docker-env --shell powershell | Invoke-Expression
```

それぞれ末尾にコメントで書いているように、何らかの方法で現シェルに環境変数を適用させることで、切り替えができるようになっています。

```{code-block} bash
# bash/zsh
$ eval $(minikube -p minikube docker-env)
```

```{code-block} bash
# PowerShell
PS> & minikube -p minikube docker-env --shell powershell | Invoke-Expression
```

```{caution}
一度切り替えると戻す方法はほぼありません(一応定義している環境変数を削除すれば戻せるかとおもいます)。
ただし変更されるのは現在のセッションにすぎませんので、端末を閉じて別の端末を開けば元に戻りります。
```

切り替え後に`docker`コマンドを使うと、今までと少し違う出力になります。

```{code-block} pwsh
PS> docker images
REPOSITORY                                TAG       IMAGE ID       CREATED         SIZE
mariadb                                   10        825ad7e31db8   3 days ago      378MB
mariadb                                   10.9.4    825ad7e31db8   3 days ago      378MB
registry.k8s.io/kube-apiserver            v1.25.3   0346dbd74bcb   4 weeks ago     128MB
registry.k8s.io/kube-scheduler            v1.25.3   6d23ec0e8b87   4 weeks ago     50.6MB
registry.k8s.io/kube-controller-manager   v1.25.3   603999231275   4 weeks ago     117MB
registry.k8s.io/kube-proxy                v1.25.3   beaaf00edd38   4 weeks ago     61.7MB
densukest/hello-laravel                   latest    b4700d0041c3   3 months ago    568MB
registry.k8s.io/pause                     3.8       4873874c08ef   5 months ago    711kB
registry.k8s.io/etcd                      3.5.4-0   a8a176a5d5d6   5 months ago    300MB
registry.k8s.io/coredns/coredns           v1.9.3    5185b96f0bec   5 months ago    48.8MB
k8s.gcr.io/pause                          3.6       6270bb605e12   14 months ago   683kB
gcr.io/k8s-minikube/storage-provisioner   v5        6e38f40d628d   19 months ago   31.5MB
```

いままで見えていなかったK8sの関連イメージが出現しました。これが環境切り替えによる参照先の変更の効果です。
これでもう一度イメージのビルドを行うことで、この環境にイメージを持ち込むことになります。

```{code-block} pwsh
:emphasize-lines: 6
PS> docker build -t densukest/hello-web:v0 .
...
PS> docker images
docker images
REPOSITORY                                TAG            IMAGE ID       CREATED         SIZE
densukest/hello-web                       v0             b6933f501d82   4 seconds ago   55MB
httpd                                     2-alpine3.16   f5ad0727dee3   2 days ago      55MB
(以下略)
```

少しするとイメージが認識されて、稼働状態に切り替わると思います。

```{code-block} pwsh
PS> kubectl get pods
NAME                         READY   STATUS    RESTARTS   AGE
hello-web-84785d998b-kx955   1/1     Running   0          29m
```

### サービスの作成

デプロイメント経由でポッドが動いたとなれば、あとはサービスです。
`app: hello-web`のキーを使ってひっかけましょう。
外部接続を行いたいので`NodePort`を選択することになります。

```{literalinclude} codes/hello-web/manifests/svc-hello-web.yml
:caption: hello-web/manifests/svc-hello-web.yml
```

適用し、サービス状態を確認します。

```{code-block} pwsh
:emphasize-lines: 5

PS> kubectl apply -f 'svc-hello-web.yml'(のパス)
service/hello-web created
PS> kubectl get svc
NAME         TYPE        CLUSTER-IP     EXTERNAL-IP   PORT(S)        AGE
hello-web    NodePort    10.110.11.41   <none>        80:32446/TCP   5s
kubernetes   ClusterIP   10.96.0.1      <none>        443/TCP        125m
```

無事できてますね、minikubeに穴開けしてもらいましょう。

```{code-block} pwsh
PS> minikube service hello-web
|-----------|-----------|-------------|---------------------------|
| NAMESPACE |   NAME    | TARGET PORT |            URL            |
|-----------|-----------|-------------|---------------------------|
| default   | hello-web |          80 | http://192.168.49.2:32446 |
|-----------|-----------|-------------|---------------------------|
🏃  hello-web サービス用のトンネルを起動しています。
|-----------|-----------|-------------|------------------------|
| NAMESPACE |   NAME    | TARGET PORT |          URL           |
|-----------|-----------|-------------|------------------------|
| default   | hello-web |             | http://127.0.0.1:55713 |
|-----------|-----------|-------------|------------------------|
🎉  デフォルトブラウザーで default/hello-web サービスを開いています...
❗  Docker ドライバーを darwin 上で使用しているため、実行するにはターミナルを開く必要があります。
```

```{figure} images/hw-web-browse.png
k8s(minikube)で動いたhello-web
```

## リモートリポジトリを使う

この切り替えが面倒であれば、Docker Desktop上でイメージを作成し、リポジトリにpushすることで回り回ってイメージ取得も可能です。
pushの手間はありますけど…

```{figure} images/docker-in-docker-hub.drawio.png
Docker Hub経由での回り込み取得のイメージ
```


### まとめ

開発を行うときの注意点として、こんなことがありました。

- minikube使っている場合、Dockerの環境はDocker Desktopとは別物になっています
- 適宜環境を切り替えましょう(`minikube docker-env`)

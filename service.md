# サービス

ここまでで、ポッドに繋がるリソースが搭乗しました。

- Pod
- Deployment
- StatefulSet

これらと他のリソースの組み合わせで、プログラム自体をコンテナで走らせることができるようになりました。
しかしこのままだとそれぞれが個別に動いているだけのため、以下のことがまだできていません。

- あるポッドが同じクラスタ内の別のポッドに対しての接続がしにくい
    - 対象ポッドのIPがわかればどうにかなるけど不定なので難しい
- 外部に対する公開(可能)サービスとして認識できない

これらに対応するための方法として、**サービス**(`Service`)というリソースが存在します。
サービスを定義することで、ポッドに対して外部からアクセスできるようにするための準備をしてくれます。
具体的には、コントロールマスター上に存在するデータベース(etcdやcoredns)にサービスのマッピングを登録し、DNSの形で内部ネットワークに対して公開することができます。

minikubeによる開発環境の場合、本番環境のように完全に独自のIPアドレス、ホスト名での公開はほぼ行えません。実際に公開するときは、利用するクラウドプロバイダの方式に従う必要があるため、ここでは残念ながら(本当の意味での、グローバル空間への)完全な公開は行えません。

とはいえ、そこに至る基本ルートは確認できるので、試してみましょう。

## 基本の環境を作ってみる

まず2つのポッドをデプロイメントで作ってみます。

- Apache httpdを使ったWebサーバー(ただし `It's works.` のみ)
- Alpineを用いたシェルアクセス用ポッド

ではWebサーバーのデプロイメントを作ります。

```{literalinclude} codes/service-example/web.yml
:caption: Webサーバー側のデプロイメント(web.yml)
:language: yaml
```

特にひねりのない単なるWebサーバー(Apache)です。
リソース上の問題が無ければ普通にうごくものです。

続いてシェル側です。こちらも特にひねりはありません。

```{literalinclude} codes/service-example/shell.yml
:caption: シェルアクセス(Alpine)側のデプロイメント(shell.yml)
:language: yaml
```

両法を適用し、shell側にて `sh` を起動してweb側にアクセスを試みます。
`curl` は入っていないため、 `wget` で対応します。

```{code-block} pwsh
:caption: deploy/shellにてシェルを起動してwebに接続してみる

PS> kubectl exec -it deploy/shell -- sh
/ # wget -O- web
wget: bad address 'web'
/ # exit # 抜ける
```

webという名前で接続しようと思ってもわかりませんので、接続のしようがありません。

## 邪悪な方法で接続してみる

それぞれポッドとして存在はしているはずなので、存在するポッドの詳細を確認すると、一応内部のIPアドレスがわかります。

```{code-block} pwsh
:caption: Webサーバー側のポッドのIPアドレスを探索する例

# ポッド名を確認する
PS> kubectl get pods
NAME                            READY   STATUS    RESTARTS   AGE
apache-58c94dcbc4-gp5tv         1/1     Running   0          13m ← これ!
laravel-init-644688bbcd-4n5h8   1/1     Running   0          3h20m
shell-7d945c79f8-8zc4n          1/1     Running   0          12m

PS> kubectl describe pod/apache-58c94dcbc4-gp5tv # 上記ポッド名の調査
Name:             apache-58c94dcbc4-gp5tv
Namespace:        default
...(中略)...
Status:           Running
IP:               172.17.0.4
IPs:
  IP:
Controlled By:  ReplicaSet/apache-58c94dcbc4
Containers:
...(後略)...
```

これで172.17.0.4であると(今回の環境での話)わかりました。
"web"の代わりにこのIPアドレスを直接指定すれば接続はできます。

```{code-block} pwsh
:caption: IP直接指定でのアクセス(邪悪)

PS> kubectl exec -it deploy/shell -- sh
/ # wget -O- 172.17.0.4
Connecting to 172.17.0.4 (172.17.0.4:80)
writing to stdout
<html><body><h1>It works!</h1></body></html>
-                    100% |***********************|    45  0:00:00 ETA
written to stdout
/ # exit
```

と、確かにWebページ(It works)が得られました。

しかし、**ポッド自身で他のポッドのアドレスを調べる事はできません**。

## サービスを作ってみる

サービスを作ることで、ポッドに対して**外部からアクセスできる名前を割り付ける**ことになります。こういう『他の存在から見つけて萌えるようにする』という行為は**サービスディスカバリ(Service Discovery)**と呼ばれます。
K8sの環境では、etcdもしくはcorednsと呼ばれるデータベース(Key-Value型)が保持した内容をDNSの形で公開することになります。

ということで、Webサーバー側にサービスディスカバリを設定してみます。
vscodeの`service`スニペットをベースに書き換えてみます。

```{literalinclude} codes/service-example/service-web.yml
:caption: apacheのデプロイメントをひっかけてディスカバリできるサービス定義(service-web.yml)
:language: yaml
```

このマニフェストを適用させてみます。
```{code-block} pwsh
PS> kubectl apply -f service-web.yml(のパス)
content
```

この状態で、再度deploy/shell側にて`sh`コマンドによる端末接続を行ってページ取得を試みます。ただしIPアドレスでは無くサービスで定義した名前(web)を用いて行ってみます。


```{code-block} bash
# deploy/shellにてshで接続した状態で再テスト
/ # wget -O- web # IPではなくサービスで定義した"web"で
Connecting to web (10.102.171.198:80)
writing to stdout
<html><body><h1>It works!</h1></body></html> ← キマシタワー
-                    100%    45  0:00:00 ETA
written to stdout
```

## サービスディスカバリの『ディスカバリ』

ディスカバリ(Discovery)は『発見(する)』という言葉ですが、サービスリソースはどうやってポッドと紐付けを行うのでしょう。

ポッドを検出するためには、以下の条件が一般的に用いられます。

- ポッド側にラベル(A)が振られていること
- Serviceリソースのセレクタにてラベル(A)が指定されていること。

今回の例では、Webのデプロイメント側ではこのように定義されています。

```{literalinclude} codes/service-example/web.yml
:caption: web.yml上でポッドテンプレートに対するラベル付け
:language: yaml
:lines: 10-14
:emphasize-lines: 3,4
```

デプロイメント経由で生成されるポッドには、 `app: apache` というラベルが付与されるようになっています。

一方、サービス側はこれを見つけるためにセレクタで指定を行います。

```{literalinclude} codes/service-example/service-web.yml
:caption: セレクタでのポッド指定(service-web.yml)
:language: yaml
:lines: 5-8
:emphasize-lines: 2,3
```

このようにすることで、マッチするラベルを持つポッドを検出してサービスはetcd(coredns)に順次登録・更新していきます。

## サービスと複数マッチング

デプロイメントでweb側(deploy/apache)を設定しているため、レプリケーションにより、該当ラベルを持つポッドが複数現れることもありえます。

```{code-block} pwsh
:caption: スケーリングにより2つ動かす
:emphasize-lines: 1,3,5,10,11

PS> kubectl scale deploy/apache --replicas 2
deployment.apps/apache scaled
PS> kubectl get deploy,pod
NAME                           READY   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/apache         2/2     2            2           53m
deployment.apps/laravel-init   1/1     1            1           4h
deployment.apps/shell          1/1     1            1           52m

NAME                                READY   STATUS    RESTARTS   AGE
pod/apache-58c94dcbc4-gp5tv         1/1     Running   0          53m
pod/apache-58c94dcbc4-w85b2         1/1     Running   0          38s
pod/laravel-init-644688bbcd-4n5h8   1/1     Running   0          4h
pod/shell-7d945c79f8-8zc4n          1/1     Running   0          52m
```

このときservice/webはどうなるのでしょう、`describe`で確認します。

```{code-block} pwsh
:emphasize-lines: 1,14

PS> kubectl get svc/web
Name:              web
Namespace:         default
Labels:            <none>
Annotations:       <none>
Selector:          app=apache
Type:              ClusterIP
IP Family Policy:  SingleStack
IP Families:       IPv4
IP:                10.102.171.198
IPs:               10.102.171.198
Port:              <unset>  80/TCP
TargetPort:        80/TCP
Endpoints:         172.17.0.4:80,172.17.0.6:80
Session Affinity:  None
Events:            <none>
```

ちなみにスケーリング前はこうなっています。

```{code-block}
:caption: スケーリング前の状態(参照用)
:emphasize-lines: 14

PS> kubectl get svc/web
Name:              web
Namespace:         default
Labels:            <none>
Annotations:       <none>
Selector:          app=apache
Type:              ClusterIP
IP Family Policy:  SingleStack
IP Families:       IPv4
IP:                10.102.171.198
IPs:               10.102.171.198
Port:              <unset>  80/TCP
TargetPort:        80/TCP
Endpoints:         172.17.0.4:80
Session Affinity:  None
Events:            <none>
```

つまり、エンドポイント(実際に接続されるポッド)がスケールした分(同じラベルを持つので)捕捉された形になります。

## 複数マッチ時の扱いは?

こうなると、2つのエンドポイント(ポッド)のどちらに接続されるのでしょうか。
そこで、各ポッドに情報を仕込んで対応してみます。

```{code-block} pwsh
:caption: 2つのポッドに別の内容のファイルを配置する
:emphasize-lines: 1,3,4,9,10

PS> kubectl get pods
NAME                            READY   STATUS    RESTARTS   AGE
apache-58c94dcbc4-gp5tv         1/1     Running   0          68m
apache-58c94dcbc4-z944s         1/1     Running   0          6s
laravel-init-644688bbcd-4n5h8   1/1     Running   0          4h16m
shell-7d945c79f8-8zc4n          1/1     Running   0          67m
# 2つにスケーリングしてる状態です

PS> kubectl exec -t pod/apache-58c94dcbc4-gp5tv -- sh -c 'echo HostA | tee /usr/local/apache2/htdocs/host.txt'
HostA
PS> kubectl exec -t pod/apache-58c94dcbc4-z944s -- sh -c 'echo HostB | tee /usr/local/apache2/htdocs/host.txt'
HostB
```

では、shell側から繰り返しアクセスしてみましょうか。

```{warning}
タイミングに依るので数回、少し間を空けながら繰り返す必要があります。
以下の例は『たまたま一発でうまくいった』です。
```

```{code-block} pwsh
:caption: deploy/shellでシェル(sh)を起動して繰り返しアクセスしてみる
/ # wget -qO- web/host.txt
HostA
/ # wget -qO- web/host.txt
HostB
```

2つ以上のポッドが引っかかる場合、**どれか適当に**繋ぐという処理になります。
数をこなせばだいたい等分割になると思われます。

```{code-block} pwsh
:caption: 試しに1万回ぶんまわしてみる

# for i in `seq 10000`; do wget -qO- web/host.txt ;done | sort
| uniq -c
   5037 HostA
   4963 HostB
```

だいたい良い感じに分散してますね。
なんとなくロードバランサー的な感じになっています。

## サービスの種類

今回作ったサービスでは、`ClusterIP`というモードになっております。

### ClusterIP

```{code-block} pwsh
:caption: service/webをdescribeで覗くと
:emphasize-lines: 7

PS> kubectl describe service/web
Name:              web
Namespace:         default
Labels:            <none>
Annotations:       <none>
Selector:          app=apache
Type:              ClusterIP
IP Family Policy:  SingleStack
IP Families:       IPv4
IP:                10.102.171.198
IPs:               10.102.171.198
Port:              <unset>  80/TCP
TargetPort:        80/TCP
Endpoints:         172.17.0.4:80,172.17.0.6:80
Session Affinity:  None
Events:            <none>
```

`ClusterIP`は、K8sクラスタの中でのみ使えるIPアドレスを用意し、ポッドにポートを繋ぐ形で接続できるようにしています。
これがデフォルトとして扱われています。

```{figure} images/service-clusterip.drawio.png
ClusterIPの概念図
```

各ポッドはノードの中に存在しているのですが、ノードを跨いでもノードのネットワーク内でパケットがカプセル化されて他のノード内のポッドに接続できるようになっています。そのため、ノードをある意味無視しておくことも可能です。

```{tip}
ClusterIPに近いものとして、特定のノードのIPとポッドを結びつけるための`ExternalIP`というものもありますが、
そうそう使うものではないので省略します。これを使うなら次の`NodePort`使って下さい。
```

### NodePort

`NodePort`は、ノードに対してポートマッピングを行って接続できるようにします。
存在しているノードの各(仮想)NICのポート番号と該当するポッドの間の接続を可能とします。

- ClusterIP同様、対象サービス向けの仮想IPをクラスタ内で生成してetc/corednsによりマップします。Pod間ではこの部分で通信します。
- クラスタを構成しているネットワークのうち、**ノードのIPにて適当なポート番号にて待ち受けする**(だからNodePort)ように設定し、クラスタ外からの通信はこのポートからClusterIPに内部でマップして対応します。

この場合、マップされたクラスタ内ノードのIPと、ノードのIPがルーティン可能で有ることを前提として、**ノード側の待ち受けポート番号がわかれば外からアクセスできる**ことになります。

```{figure} images/service-nodeport.drawio.png
NodePortの概念図
```

では、ClusterIP側はそのまま残して、NodePortで繋ぐ設定のマニフェストを記述してみましょう。

```{literalinclude} codes/service-example/service-web-np.yml
:caption: NodePortでwebに繋げるマニフェスト(service-web-np.yml)
:language: yaml
:emphasize-lines: 8
```

といっても、元のマニフェストをコピーし、 `type: NodePort`を追加しただけです。
あとはClusterIP側と被らないように、サービス名を`web-np`にしているぐらいでしょう。

このマニフェストを適用後、サービス状態を出力してみます。

```{code-block} pwsh
PS> kubectl apply -f 'service-web-np.yml'(のパス)
service/web-np created
PS> kubectl get svc
NAME         TYPE        CLUSTER-IP      EXTERNAL-IP   PORT(S)        AGE
kubernetes   ClusterIP   10.96.0.1       <none>        443/TCP        9m29s
web          ClusterIP   10.109.21.105   <none>        80/TCP         8m28s # ← ClusterIP
web-np       NodePort    10.98.190.105   <none>        80:31848/TCP   8m18s # ← NodePort
```

実際に外(ブラウザ)から繋いでみましょう。ここでは `minikube` の力を借りることにします。

```{code-block} pwsh
:caption: minikubeを使ってブラウザでポッドのWebサーバーに繋ぐ

PS> minikube service web-np # NodePortのサービス名を渡して"minikube service"
|-----------|--------|-------------|---------------------------|
| NAMESPACE |  NAME  | TARGET PORT |            URL            |
|-----------|--------|-------------|---------------------------|
| default   | web-np |          80 | http://192.168.49.2:31848 |
|-----------|--------|-------------|---------------------------|
🏃  web-np サービス用のトンネルを起動しています。
|-----------|--------|-------------|------------------------|
| NAMESPACE |  NAME  | TARGET PORT |          URL           |
|-----------|--------|-------------|------------------------|
| default   | web-np |             | http://127.0.0.1:63214 |
|-----------|--------|-------------|------------------------|
🎉  デフォルトブラウザーで default/web-np サービスを開いています...
❗  Docker ドライバーを darwin 上で使用しているため、実行するにはターミナルを開く必要があります。```
```{figure} images/minikube-service-web-np.png
ブラウザでポッドにアクセス
```

ClusterIPはNodePort側にも与えられ、Pod間のIPとしてはこのアドレスが返されます。
その一方で、NodePort版(web-np)ではポート設定が 80:**31848** とついています。
この番号はその時によりランダムで設定されますが、これがクラスタ外から接続するときのポート番号です。

ポート番号がわかったものの、ノードのIPはどのように調べるのでしょう。
クラウドプロバイダであれば、もちろん提供されている方法で調べる事になります。
今回はminikubeなので、minikube自身がわかります。

```{code-block} pwsh
:caption: ノードのIPを調べる
PS> minikube ip
192.168.49.2 # ←検証の環境ではこう出ました
```

つまり、ノード外から接続するときは、 http://192.168.49.2:31848/ で接続できるということになります。

### Docker環境を用いたminikube環境とサービスマッピング

授業で使用しているminikube環境は、Docker DesktopによるDockerの環境で構築されています。
これがちょっとややこしくて、Docker内に構成されたminikubeのコンテナが内側に専用のDocker Engineを内包しています。
これを "**Docker in Docker**"(DinD) と呼んでいます。

```{figure} images/docker-in-docker.drawio.png
Docker in Docker 概念図
```

このとき、 `minikube ip` で取得したアドレスは、minikubeコンテナの中で構成したDocker Engine部分のIPアドレスになります。
このアドレスは、Docker Desktop環境の内側で構成されているため、他のコンテナ同様直接アクセスできません。

```{figure} images/docker-in-docker-ip.drawio.png
minikube ipの返していたIPアドレスはここだった
```

そこで、`minikube`は、`minikube service`の際に **ドライバーがDockerだったとき** に、自動的に追加のプログラムを起動し、
DinDのコンテナと繋ぐためのポートフォワードを行う設定を追加します。

```{figure} images/docker-in-docker-portforward.drawio.png
DinDに繋がるように、Dockerのポートフォワード機能を追加
```

実際、macOS上でHyperKitを使ったminikubeでは、`minikube ip`のアドレスとポート番号で素直にアクセスできますが、Dockerを使ったminikubeでは失敗します。
ポートフォワードが設定されたことは、先ほどの出力に含まれていました。

```{code-block} pwsh
:caption: minikubeを使ってブラウザでポッドのWebサーバーに繋ぐ(再掲)
:emphasize-lines: 7-12

PS> minikube service web-np # NodePortのサービス名を渡して"minikube service"
|-----------|--------|-------------|---------------------------|
| NAMESPACE |  NAME  | TARGET PORT |            URL            |
|-----------|--------|-------------|---------------------------|
| default   | web-np |          80 | http://192.168.49.2:31848 |
|-----------|--------|-------------|---------------------------|
🏃  web-np サービス用のトンネルを起動しています。
|-----------|--------|-------------|------------------------|
| NAMESPACE |  NAME  | TARGET PORT |          URL           |
|-----------|--------|-------------|------------------------|
| default   | web-np |             | http://127.0.0.1:63214 |
|-----------|--------|-------------|------------------------|
🎉  デフォルトブラウザーで default/web-np サービスを開いています...
❗  Docker ドライバーを darwin 上で使用しているため、実行するにはターミナルを開く必要があります。
```

この場合、ブラウザ上では http://127.0.0.1:63214 にアクセスすることで、DinD空間の http://192.168.49.2:31848 に転送され、そこからClusterIPを経由してのサーバー(ポッド)へと多段接続が行われます。この処理を行っている間は端末を止めることができないので注意して下さい(Ctrl-Cすると切断される)。

````{tip}
その他のドライバや環境だとどうなのでしょうか?
例えばDocker Desktopに内蔵されているKubernetes機能を有効にして、環境を切り替えた場合を見てみましょう。

```{code-block} pwsh
PS> kubectl config get-context # 登録済K8s環境をチェック
CURRENT   NAME             CLUSTER          AUTHINFO         NAMESPACE
          docker-desktop   docker-desktop   docker-desktop             # Docker Desktop
*         minikube         minikube         minikube         default   # minikube

PS> kubectl config use-context docker-desktop # minikubeに切り替え
Switched to context "docker-desktop".
PS> kubectl config get-contexts
CURRENT   NAME             CLUSTER          AUTHINFO         NAMESPACE
*         docker-desktop   docker-desktop   docker-desktop
          minikube         minikube         minikube         default
```

この状態で先ほど使っていたマニフェストを渡して様子を見ましょう。

```{code-block} pwsh
PS> kubectl apply -f 'web.yml' # Deployment
deployment.apps/apache created
PS> kubectl apply -f 'service-web-np.yml' # Service(NodePort)
service/web-np created
```

この場合、ポートフォワードはDocker Desktop上のDocker Engineによって管理されるため、NodePortのポートがそのままDocker Desktop環境でのアクセス先であるlocalhost(127.0.0.1)にマップされています。

```{code-block} pwsh
PS> kubectl get svc
NAME         TYPE        CLUSTER-IP      EXTERNAL-IP   PORT(S)        AGE
kubernetes   ClusterIP   10.96.0.1       <none>        443/TCP        15m
web-np       NodePort    10.102.199.29   <none>        80:30550/TCP   15s
```

よって、 http://127.0.0.1:30550/ でアクセスできてしまいます。

```{code-block} pwsh
PS> curl 127.0.0.1:30550
<html><body><h1>It works!</h1></body></html>
```

だったら最初からDocker DesktopのK8sを有効にすれば終わりだったのに…となるかもしれませんが、全員の環境がDocker Desktopで統一できない場合などを考慮してminikubeにしてます(実際ダッシュボードやアドオンの部分が地味に助かるので)。

```{warning}
`NodePort`使用時、ポート番号はここまでお任せでしたが、番号も指定可能です(spec.ports[n].nodePort)。
この場合、既に使用中のポートだと失敗してしまいます(よくある8080とかは奪い合いになるかも)。
```


````

### 上記以外のtypeについて

利用する可能性の高いものとして、 `LoadBalancer` があります。
外部と接続可能なロードバランサーを内部で起動させ、対象となるサービスに対するロードバランシングを提供します。
レプリケーションを用いて負荷分散をさせる際は、こちらが使われることが多くなるかもしれません。
LoadBalancerはクラウドプロバイダでは設定することで実際に外部に対して固定IPをひとつ割り付けてもらって参照可能となります。
アプリケーションプロキシとしては、Ingressサービスと連携させることで、L7のロードバランサーも出てくることがあります。




## まとめ

- ポッド間だけの通信が目的なら `ClusterIP` で設定して保護しましょう(デフォルトはこちら)
- 外部との疎通が必要なものは `NodePort` を使いましょう、その上で `minikube service サービス名` でドライバ固有設定を加えて繋ごう

## 参考文献

- [あなたの知らないKubernetesのServiceの仕組み(IIJ Engineers Blog)](https://eng-blog.iij.ad.jp/archives/9998)

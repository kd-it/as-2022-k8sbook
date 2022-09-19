# ジョブリソース

**ジョブ**は、いわゆる単発のお仕事です。
起動しっぱなしとかではなく、起動後処理が完了すれば消滅することになります。
ログ(出力)は残しておけるので、データの回収やバックアップ業務(単発)に使うことができます。

## 実際のリソース例

ジョブはその仕様としてポッドを必要とします。
ここではDockerが提供するhello-worldイメージを走らせるものを考えてみましょう。

なお、vscodeのjobスニペットでベースのコードは展開可能です。

```{literalinclude} codes/job-helloworld.yml
:caption: hello-worldを実行するジョブの例
```

このマニフェストを適用させると、ジョブのリソースが生成されます。
確認するなら`get jobs`となります。

```{code-block} ps1
PS> minikube kubectl -- apply -f job-helloworld.yml
job.batch/hello-world created
PS> minikube kubectl -- get jobs
NAME          COMPLETIONS   DURATION   AGE
hello-world   0/1           1s         1s
```

しばらくするとジョブが終了するので、Completionsが変化します。

```{code-block} ps1
PS> minikube kubectl -- get jobs
NAME          COMPLETIONS   DURATION   AGE
hello-world   1/1           7s         12s # ← 1/1で完了状態
```

## ジョブリソースの定義について

ジョブリソースについても、もちろんK8sに仕様書があります。

* (Job v1 batch)[https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.25/#job-v1-batch]
* kind: job
* apiversion: batch/v1

Podにおけるマニフェスト記述がある程度頭に入っていれば実はけっこう簡単な話です。
実はPodSpec部分がそのまま `template` 部分に記載されています。

```{literalinclude} codes/job-helloworld.yml
:caption: 起動するポッドの定義(Podのspecとほぼ同じ)
:lines: 8-12
```

ただ、以前のポッドのマニフェストでは出てこなかったものとして、 `restartPolicy` というものがあります。
これはポッドが(K8sの側が意図せず)終了した場合に再起動するかという設定で、[podSpec](https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.25/#podspec-v1-core)にて記載されています。[書くべきポリシー](https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#restart-policy)についても記載があります(`Always`/`OnFailure`/`Never`)。
ジョブとしては、その時に一度だけ動けば良いので、再起動は不要にしておくのが一般的です(`Never`)。

## ジョブの遺すもの

ジョブは実行することで何かのプロセスが走るのですが、実行結果はどうなるのでしょうか?
例えば何かのバックアップであれば、どこかのストレージに転送されているのでそこを見に行けば良いでしょう。

```{hint}
ローカル実行の場合は扱いにくい話になってしまいますが、実際のクラウドプロバイダの場合、オンラインストレージに一時的に接続してそこに配置されることが多いです。そのためオンラインストレージ側の内容を確認するとバックアップの結果が得られるということになります。
```

ジョブの実行結果のうち、標準出力・標準エラー出力に出されたものであれば、ジョブが残っている間は表示可能です。
例えば先ほど実行させたジョブ `hello-world` はジョブの実行結果が残っているため、`kubectl logs`で取得可能です。

```{code-block} ps1
:caption: ログの取得

# ジョブがあることを確認
PS> minikube kubectl -- get jobs
NAME          COMPLETIONS   DURATION   AGE
hello-world   1/1           8s         49s

# ジョブのログを取得(job/hello-world)
PS> minikube kubectl -- logs job/hello-world

Hello from Docker!
This message shows that your installation appears to be working correctly.

To generate this message, Docker took the following steps:
 1. The Docker client contacted the Docker daemon.
 2. The Docker daemon pulled the "hello-world" image from the Docker Hub.
    (amd64)
 3. The Docker daemon created a new container from that image which runs the
    executable that produces the output you are currently reading.
 4. The Docker daemon streamed that output to the Docker client, which sent it
    to your terminal.

To try something more ambitious, you can run an Ubuntu container with:
 $ docker run -it ubuntu bash

Share images, automate workflows, and more with a free Docker ID:
 https://hub.docker.com/

For more examples and ideas, visit:
 https://docs.docker.com/get-started/
```

ログ出力はジョブを削除するまでは残ります。
ずっと遺す必要の無いジョブであれば、**ジョブを遺す時間** というものが定義可能です。
実はコメントアウトしていた部分(`spec.ttlSecondsAfterFinished`)がそれで、頭の`#`を外して有効化すると、指定秒数後以降に適宜削除してくれます。長々と遺す必要が無いならこれも有効利用して良いでしょう。

````{hint}
試す場合、事前にジョブを一度削除してからにしてください。名前がぶつかって操作できない恐れがあります。

```{code-block} ps1
PS> minikube kubectl -- delete job hello-world      # 一度削除してから…
PS> minikube kubectl -- apply -f job-helloworld.yml # 再適用
```
````

## ジョブの使いみち

ジョブは概ね『単発で動く』ところがポイントになるため、Webサービス中心となるK8sの中では異端に思われますが、動いているサービスの状態を単発で確認したいとき(常時チェックは不要)というケースにおいては使えると思います。

たとえば例として、MariaDBのサービスの稼働状態を必要に応じてチェックするという挙動を考えて見ます。

```{literalinclude} codes/mariadb.yml
:caption: MariaDBを単純に動かすサービス
```

内容的にDeployment+Serviceになっていますが今はあまり気にしないで良いです。
そして、それをチェックするジョブとしてこんなのを用意したとします。

```{literalinclude} codes/job-checkmariadb.yml
:caption: MariaDBサービスの稼働(疎通)を確認するジョブ
```

これらを使ってみるとこんな感じになります。

```{code-block} ps1

```

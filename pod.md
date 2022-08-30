# Podリソース

Podは、K8sにおけるコンテナを示します。ただしコンテナそのものではなく概念なので、少し(?)異なります。

* Podは1つのPodの中に複数のコンテナを詰めることができます
    * 最低1つ定義されている必要があります
    * 2つ目以上のコンテナは常時起動していることもあれば、初期に起動するだけというものもあります
* 現時点(2022年8月)での仕様は、以下のようになっています
    * [Pod v1 core](https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.24/#pod-v1-core)
        * apiVersionとして"v1"、kindとして"Pod"を指定する
        * 必要なものは以下の4つ
            * `apiVersion`
            * `kind`
            * `metadata`
            * `spec`
                * 値の仕様は[PodSpec](https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.24/#podspec-v1-core)を読め、と
                * 正直これをマジメに読むのは辛いので止めておきましょう
                * 基本的には `containers` キー以下に作りたい[コンテナ情報を配列の値](https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.24/#container-v1-core)として記載していけば良い

## 実際に作ってみる

実際のマニフェストを作ってみましょう。vscodeのpodスニペット(テンプレート)ベースでかまいません。

```{literalinclude} codes/2nd.yml
---
caption: Ubuntuのイメージを使ったPodマニフェスト(2nd.yml)
language: yaml
---
```

```{code-block} ps1
---
caption: 2nd.ymlの適用とPod確認
---
PS> kubectl apply -f 2nd.yml
PS> kubectl get pods # 2ndpodがあればOK
```

## Pod内コンテナを追加してみる

`2nd.yml` に対し、コンテナを追加してみます。Podは最小単位的に扱われますが、その中にコンテナが1つである必要はなかったりします。

```{literalinclude} codes/2nd-add1.yml
---
caption: 2nd.ymlに別のコンテナを追加した例(2nd-add1.yml)
language: yaml
emphasize-lines: 18-
---
```

このマニフェストを適用する前に、いくつか確認しておきましょう。

### マニフェストファイルは別になってもかまいません

マニフェストファイルのファイル名が問題ではなく、中に書いてある内容で同一性を判断しています。

* `2nd.yml` の中ではコンテナ1つの `pod/2ndpod` が定義されており、その中ではコンテナが1つ(2ndpod)が定義されています
* `2nd-add1.yml` の中では同様に `pod/2ndpod` の定義となっていますが、コンテナが2つ定義されています

よって、このPodマニフェストを適用すると、既存のpod/2ndpodに対する変更となります。
このことを確認するため、実際に適用する前に `--dry-run` を使って確認してみましょう。

```{code-block} ps1
---
caption: 2nd-add1.yml適用前のPod状態
---
PS> kubectl get pods
NAME     READY   STATUS    RESTARTS        AGE
1stpod   1/1     Running   2 (5h51m ago)   21h
2ndpod   1/1     Running   0               17s
```

```{code-block} ps1
---
caption: 2nd-add1.yml適用前にdry-runしてみる
---
PS> kubectl apply --dry-run=client  -f 2nd-add1.yml
pod/2ndpod configured (dry run)
```

ファイル名は異なりますが、確かにpod/2ndpodが変更される(予定)ということがわかります。
実際に適用してみましょう。この場合、Podの中で動くコンテナが2になるため、READYフィールドの値が1→2となります。

```{code-block} ps1
---
caption: 2nd-add1.yml適用、即座にPod状態を確認(-w付き)
---
PS> kubectl delete -f 2nd-add1.yml;  kubectl apply -f 2nd-add1.yml; kubectl get pods -w
# "-w"付きのため、変更が生じる度に表示が増えます
pod "2ndpod" deleted
pod/2ndpod created
NAME     READY   STATUS    RESTARTS        AGE
1stpod   1/1     Running   2 (5h59m ago)   21h
2ndpod   0/2     Pending   0               0s
```

おや、`Pending` になってますね… これはPodの動くノードの性能上限にぶつかるため起動が抑制されてしまっていることで発生しています(今回のケース)。
よって、pod/1stpodを破棄すれば動くと思います。

```{code-block} ps1
---
caption: 別端末でpod/1stpodを破棄してみる
---
PS> kubectl delete pod/1stpod
pod "1stpod" deleted
```

すると、終了したところで2ndpodの起動準備が進みます。

```{code-block} ps1
---
caption: Pod状態の変更
---
NAME     READY   STATUS    RESTARTS        AGE
1stpod   1/1     Running   2 (5h59m ago)   21h
2ndpod   0/2     Pending   0               0s
1stpod   1/1     Terminating   2 (6h ago)      21h
1stpod   0/1     Terminating   2 (6h1m ago)    21h
1stpod   0/1     Terminating   2 (6h1m ago)    21h
1stpod   0/1     Terminating   2 (6h1m ago)    21h
2ndpod   0/2     Pending       0               71s
2ndpod   0/2     ContainerCreating   0               71s
2ndpod   2/2     Running             0               75s
```

READY数2なのは、Podの中のコンテナが2つだからです。2つのコンテナが共に利用可能状態になったことで "2/2" となるわけですね。

```{hint}
なお、Pod内のコンテナ(を指す名前)を知りたい場合は、ちょっと面倒ですが以下のコマンドで確認可能です。
ただし、適用したマニフェストを見た方が早いと思います。

```{code-block} ps1
PS> kubectl get pods 2ndpod  -o jsonpath="{.spec.containers[*].name}"
2ndpod 3rdpod
```

複数コンテナを投入できるということは、1つのポッドでWordpressの構成(Apache+PHP+MariaDB)といったことも可能です(あまりしませんが)。

各コンテナに接続するときは、 `kubectl exec`  の際にコンテナ名を`-c`で渡します。

```{code-block} ps1
---
caption: Pod内の各コンテナに接続して違いを見てみる
---
PS> # "-c 2ndpod"でubuntu側に接続、lsコマンドで/bin/lsの実体を見る
PS> kubectl exec -it -c 2ndpod 2ndpod -- sh
# ls -l /bin/ls
-rwxr-xr-x 1 root root 138208 Feb  7  2022 /bin/ls
# exit
PS> # "-c 3rdpod"でalpine側に接続、busyboxベースのため、lsの実体はbusybox
PS> kubectl exec -it -c 3rdpod 2ndpod -- sh
/ # ls -l /bin/ls
lrwxrwxrwx    1 root     root            12 Aug  9 08:47 /bin/ls -> /bin/busybox
/ # exit
```

## 後始末

終わったあとの後始末は、deleteさせれば良いだけでしたね。

```{code-block} ps1
PS> kubectl delete -f 2nd.yml
```

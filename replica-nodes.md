# レプリケーションとノード

スケーリングに関わる要素として、replicasetリソースというものがありました。
deploymentの配下として動き、実際にPodの数を調整していく立場にあるものでした。
ではreplicasetはどの程度までPodを増やしていけるのでしょうか…

## コマンドラインでのスケーリング

マニフェストに書くことで、適用すればその値になるようにとはたらきますが、一時的に変更したいというレベルであれば、コマンドライン上でもスケーリングの設定は可能です。

```{code-block} ps1
:caption: スケーリングのコマンドライン呼び出し例
# 初期状態でデプロイ(デプロイ済の方はそのまま使って結構です)
PS> kubectl apply -f deploy1.yml
# スケーリングのコマンドライン呼び出し
PS> kubectl scale deploy/frontend --replicas=4
```

`kubectl scale`コマンドにより、指定したリソースオブジェクトに対し、複製数を設定できるようになります。
マニフェストにおける `spec.replicas` を直接指定するようなものです。
この場合、作成したい複製を4を目標とさせることになります。
なおこの値は便宜上 **レプリカ数** と表現しておきます。

うまくできたかはデプロイ状態とポッド状態で確認できます。

```{code-block} ps1
:caption: スケーリングの状況を確認(レプリカ数4で設定)
PS> kubectl get deploy # デプロイメント
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   4/4     4            4           22h
PS> kubectl get pods # ポッド
NAME                        READY   STATUS    RESTARTS   AGE
frontend-675c86d757-fhwlg   1/1     Running   0          10m
frontend-675c86d757-thlcn   1/1     Running   0          21s
frontend-675c86d757-vjjgb   1/1     Running   0          12m
frontend-675c86d757-wjpnz   1/1     Running   0          21s
```

```{caution}
スケーリングの指示を出したあと、順次不足しているポッドの起動を行うため、すぐ反映されません。
ちまちまと上がっていくような感じなので、意識して少し間を置いてから確認するか、ちょこちょこ起動して眺めてください。
```

````{tip}
**スケールを0にする**ことも可能です。デプロイメントは存在しますが、起動するポッドの数が指示通り0になります。
あとでまた使うためデプロイメントを残しておきたいときに使うことがあります。

```{code-block} ps1
:caption: レプリカ数0の場合
PS> kubectl scale deploy/frontend --replicas 0
deployment.apps/frontend scaled
PS> kubectl get deploy
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   0/0     0            0           22h
```
````

## どこまで増やせる?

```{warning}
この部分は環境依存の部分が強いので、急いでやる必要はありません。
とはいえロードバランサーなどを意識するときには起動数が総定数と一致しないことがあるのでその時に改めて試してみると良いでしょう。
```


`--replica` に渡すレプリカ数は、いくつまでできるのでしょうか。
試しにレプリカ数を書き換えて試してみましょう。

ここでは8にしてみます。

```{code-block} ps1
:caption: レプリカ数8の場合
PS> kubectl scale deploy/frontend --replicas 8
deployment.apps/frontend scaled
PS> kubectl get deploy
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   4/8     8            4           22h
# 少し時間をおいてから再度確認
kubectl get deploy
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   6/8     8            6           22h
```

増えませんね… ポッド側も確認してみましょう。

```{code-block} ps1
:emphasize-lines: 5,11

# ポッド状態の確認
kubectl get pods
NAME                        READY   STATUS    RESTARTS   AGE
frontend-675c86d757-2dz7s   1/1     Running   0          2m20s
frontend-675c86d757-5m6fn   0/1     Pending   0          2m20s
frontend-675c86d757-mxsbj   1/1     Running   0          2m20s
frontend-675c86d757-p52nv   1/1     Running   0          2m20s
frontend-675c86d757-p565l   1/1     Running   0          2m20s
frontend-675c86d757-p9ff6   1/1     Running   0          2m20s
frontend-675c86d757-sl2g5   1/1     Running   0          2m20s
frontend-675c86d757-xkn9z   0/1     Pending   0          2m20s
```

見ての通り、 **Pending**という状態のものが出ています。起動を保留しているということです。

K8sのノード(実際にポッドが動くことになるホスト)は、それぞれの持つ性能というものをある程度把握しています。
実は今回のポッド達、テンプレート上で以下の設定が含まれています。

```{literalinclude} codes/deploy1.yml
:linenos:
:lines: 9-22
:emphasize-lines: 9-12
```

* CPUの利用は上限500m(500ミリ秒)使いたい
* メモリ利用は上限128MB(128メガバイト)使いたい

CPUの利用率はCPU1個分を「1秒当たりこれだけ必要である」という意味合いで使います、そのため500m(ミリ秒)とした場合、CPU1個の半分をリクエストすることになります。今回のノード用には4CPUで構成しているため、8つのポッドが動かせるように思えますが、
実際には6つのポッド(500ミリ秒×6ポッド=3000ミリ秒=CPU3個分)が上限となっています。
これはK8sの管理プロセス(コントロールマスター)が一緒に動いていることが原因(コントロールマスターのプログラムがCPUを使っているから)です。

試しにCPU2つに落とした環境で同じマニフェストを走らせてみます。
Dockerベースでminikubeをした場合、Docker Desktopで利用しているCPU数分実は使うため、minikubeで作るノードのCPU数と実際に見えているCPU数が異なるというややこしい状態になります。

ここではmacOSのため、hyperkitドライバによるVMを使って検証してみます。
Windowsの場合はHyper-V(hyperv)かVirtualBox(virtualbox)で行うことになります。

```{code-block} ps1
:caption: minikubeによるクラスタの再生成→再度レプリケーション
PS> minikube delete # クラスタの削除
PS> minikube start --driver=hyperkit --cpus=2
PS> kubectl apply -f deploy1.yml
PS> kubectl scale deploy/frontend --replicas 8
PS> kubectl get deploy # 30秒ぐらいおいたほうがいい
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   2/8     8            2           41s
```

コントロールマスターがCPU1つを奪っているからか、使えるのは2つになっているようです。

```{code-block} ps1
PS> kubectl get podskubectl get pods
NAME                        READY   STATUS    RESTARTS   AGE
frontend-675c86d757-4cwsc   0/1     Pending   0          38s
frontend-675c86d757-9rsf7   0/1     Pending   0          38s
frontend-675c86d757-d6t9j   1/1     Running   0          38s
frontend-675c86d757-dcfxc   0/1     Pending   0          38s
frontend-675c86d757-kfx9j   0/1     Pending   0          38s
frontend-675c86d757-rr8z6   1/1     Running   0          75s
frontend-675c86d757-sr72m   0/1     Pending   0          38s
frontend-675c86d757-x4dvq   0/1     Pending   0          38s
```

と、やはりPendingだらけです。

このままでは必要数だけ動かせません。どうすれば良いのでしょう。

- CPUの要求量をもっと小さくする(例えば100mとか)
- CPUを増やす

などが想像できると思います。

## CPU利用を減らす

リクエストするCPU利用率を調整してみましょう。例えば100ミリ秒にしてみます。

```{literalinclude} codes/deploy1-limitCPU.yml
:language: yaml
:lines: 17-20
:emphasize-lines: 4

CPU上限リクエストを100ミリ秒にした場合
```

こちらを適用させてみます。

``{code-block} PS1
PS> kubectl delete -f deploy1.yml # 一度削除 ※重要!
PS> kubectl apply -f deploy1.yml # 再適用
PS> kubectl scale deploy/frontend --replicas 8
# しばらく待つ
PS> kubectl get pods
NAME                        READY   STATUS    RESTARTS   AGE
frontend-699474d56f-28fj6   1/1     Running   0          3m19s
frontend-699474d56f-ds49s   1/1     Running   0          3m44s
frontend-699474d56f-grq2x   1/1     Running   0          3m19s
frontend-699474d56f-hhvq8   1/1     Running   0          3m19s
frontend-699474d56f-l5lxh   1/1     Running   0          3m19s
frontend-699474d56f-ndvm2   1/1     Running   0          3m19s
frontend-699474d56f-s5zrr   1/1     Running   0          3m19s
frontend-699474d56f-tq4xn   1/1     Running   0          3m19s
```

合計800ミリ秒となるため、CPU1つ分に収まるので大丈夫だったようですね。

一度マニフェストの削除(`kubektl delete -f ...`)をしておいて、その後500mにCPUを戻した状態で適用して元に戻しておきましょう。

## ノードを増やす

レプリカ数8にした場合、以下のようになりました。

``{code-block} PS1
PS> kubectl get pods
NAME                        READY   STATUS    RESTARTS   AGE
frontend-675c86d757-6ztxg   1/1     Running   0          4s
frontend-675c86d757-fxwlf   1/1     Running   0          11s
frontend-675c86d757-gzh4c   0/1     Pending   0          4s
frontend-675c86d757-mt57f   0/1     Pending   0          4s
frontend-675c86d757-qfjn8   0/1     Pending   0          4s
frontend-675c86d757-t2x8g   0/1     Pending   0          4s
frontend-675c86d757-wlgq9   0/1     Pending   0          4s
frontend-675c86d757-xvgt6   0/1     Pending   0          4s
``

K8sは**クラスタ**を構成します。複数のノードをコントロールマスターの配下とすることで、ノードを跨いでポッドを起動させることも可能になります。
minikubeは簡単にノードを増やせます。

```{code-block} PS1
:caption: ノードを増やして様子を見る

PS> minikube node add # ノードを1つ追加
😄  m02 ノードを minikube クラスターに追加します
❗  クラスターが CNI なしで作成されたため、ノードを追加するとネットワークが破損する可能性があります。
👍  minikube クラスター中の minikube-m02 ワーカーノードを起動しています
🔥  hyperkit VM (CPUs=2, Memory=2200MB, Disk=20000MB) を作成しています...
🐳  Docker 20.10.17 で Kubernetes v1.24.3 を準備しています...
🔎  Kubernetes コンポーネントを検証しています...
🏄  minikube への m02 追加に成功しました！
PS> kubectl get deploy # 起動後様子を見てみると…
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   5/8     8            5           5m46s
```

3つ増えました、4つにならないのはコントロールマスターとの通信プロセスが一部CPUを消費しているからだと思われます。
Podの状態については、オプションを足すことで「どこで動いているか」が見えます。

```{code-block} ps1
PS1> kubectl get pods -o wide
NAME                        READY   STATUS    RESTARTS   AGE     IP           NODE
frontend-675c86d757-6ztxg   1/1     Running   0          8m11s   172.17.0.4   minikube
frontend-675c86d757-fxwlf   1/1     Running   0          8m18s   172.17.0.3   minikube
frontend-675c86d757-gzh4c   1/1     Running   0          8m11s   172.17.0.4   minikube-m02
frontend-675c86d757-mt57f   1/1     Running   0          8m11s   172.17.0.3   minikube-m02
frontend-675c86d757-qfjn8   0/1     Pending   0          8m11s   <none>       <none>
frontend-675c86d757-t2x8g   0/1     Pending   0          8m11s   <none>       <none>
frontend-675c86d757-wlgq9   0/1     Pending   0          8m11s   <none>       <none>
frontend-675c86d757-xvgt6   1/1     Running   0          8m11s   172.17.0.2   minikube-m02
```

どうやらこの環境だと、1つノードを増やすと2CPUで3つ動かせるみたいなので、もうひとつ足したら全部動きそうな感じですね。



```{code-block} ps1
PS> minikube node add # ノードをもう1つ追加
PS> kubectl get deploy
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   8/8     8            8           11m
PS> kubectl get pods -o wide
NAME                        READY   STATUS    RESTARTS   AGE   IP           NODE
frontend-675c86d757-6ztxg   1/1     Running   0          12m   172.17.0.4   minikube
frontend-675c86d757-fxwlf   1/1     Running   0          12m   172.17.0.3   minikube
frontend-675c86d757-gzh4c   1/1     Running   0          12m   172.17.0.4   minikube-m02
frontend-675c86d757-mt57f   1/1     Running   0          12m   172.17.0.3   minikube-m02
frontend-675c86d757-qfjn8   1/1     Running   0          12m   172.17.0.2   minikube-m03
frontend-675c86d757-t2x8g   1/1     Running   0          12m   172.17.0.4   minikube-m03
frontend-675c86d757-wlgq9   1/1     Running   0          12m   172.17.0.3   minikube-m03
frontend-675c86d757-xvgt6   1/1     Running   0          12m   172.17.0.2   minikube-m02
```

1. 現ポッド構成ではリクエストに対応しきれないときなどに、ポッド数を増やす(スケールアウト)させてリクエストを分散するようにします
2. 本当に混雑した状態の場合だと、ノードの性能上限にぶつかって、増やしても動いてくれないことがあります
3. ノードを追加することで起動できていなかったポッドが動くようになり、更に分散が実現します


## ノードの削除と後片付け

(ワーカー)ノードは稼働中であっても削除できます。
あぶれた(巻き添えで消滅した)ポッドは、残りのノード内で再起動を試みます。
たとえばノード2番(minikube-m02)を削除してみます。

```{code-block} ps1
PS> minikube node delete m02 # m03残してm02を削除
PS> kubectl get deploy
NAME       READY   UP-TO-DATE   AVAILABLE   AGE
frontend   5/8     8            5           16m
```

このように、ノード数が減ると、その分動けるポッドが一時的に減ってしまいます、そのため残ったノードで可能なら起動を試みます。
でも残念ながら今回は動かせません。

ノードは動的に増減できるので、例えば…

1. サービス開始時は物珍しさでアクセスが増える → ノードを増やしてい対応
2. ある程度初期に試した人が抜けて性能が余ってきてる → ノードを削って必要な範囲にすればコストダウン

となります。

さて後始末として、もともとのクラスタ構成に戻します。

```{code-block} ps1
PS> minikube delete
PS> minikube start # 初期値で再度生成
```


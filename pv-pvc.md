# 永続ボリューム(PV,PVC)

`hostPath`や`emptyDir`を用いたボリュームの接続を行いましたが、このように利用したいストレージの種類を直接マニフェストに書いてしまうと、どのワークロードリソースが特定のクラウドプロバイダに依存する可能性が発生します。

そこで、クラウドプロバイダへの依存部分を外部へ切り出し、ストレージの利用を「要求」する形に書き換えることで、依存部分を独立させて可搬性の向上へと導く考え方が導入されています。

- 利用するストレージを構成するPV(PersistentVolume)
- ストレージの利用を要求するPVC(PersistentVolumeClaim)

ポッドやデプロイメントなどのワークロードにおいては、PVCを接続元にすることによって、ワークロードリソースマニフェストからクラウドプロバイダへの依存性をなるべく外した構成ができるようになります。

## PV(PersistentVolume)

PV(PersistentVolume; 永続ボリューム)は、コンテナとは別のデータを保つための領域として(一応)永続的にデータを保持しようとします。

```{caution}
実際にどこまで保持するかは、PVが利用するストレージの構造によります。`hostPath`や`emptyDir`を使えば、もちろん特定のポッドに依存しますし、
AWSのS3ストレージ等を用いれば、ストレージを明示的に破棄するまでは残るでしょう。
```

残念ながらminikubeでは標準でS3レベルのストレージがあるわけでもありませんので、ノード跨ぎなどの高度なものは用意できませんが、概念理解のツールですのでこれでいいでしょう。
ここでは、hostPathを用いたストレージをひとつつくって試してみましょう。

### PVの作成例

実際に作ってみます。

- hostPathを用いたシングルノード用のストレージ
- 同時に多数のポッドからのマウントを許容

という設定にしてみます。

PVは大雑把に、以下の内容を指定することになります。

- PVの名前
- 割り当てされるときの要求名(ストレージクラス)
- アクセスモード
- 割り当て元ストレージの宣言

実際に記述するとこんな感じです。

```{literalinclude} codes/pv-hostpath.yml
:caption: pv-hostpath.yml
```

このマニフェストを適用すると、PVが作成されます。

```{code-block} pwsh
PS> kubectl apply -f pv-hostpath.yml(のパス)
PS> kubectl get pv
NAME         CAPACITY  ACCESS MODES  RECLAIM POLICY  STATUS    CLAIM STORAGECLASS  REASON  AGE
pv-hostpath  512Mi     RWX           Delete          Available       pvhp                  3m1s
```

```{tip}
アクセスモード(ACCESS MODES)にある`RWX`はUNIXのアクセス権(Read-Write-eXecute)ではなく、ReadWrite**Many**によるものです。なんでXなんでしょうね… ReadWrite**Once**だと`RWO`です。
```

## PVC(PersistentVolumeClaim)

PVCは、定義済のPVに対し、条件に見合うものを検索し、利用できるように**要求**(Claim)する存在です。
ポッド達が敢えて直接ストレージを選択せずにClaimを挟むことにより、実際のボリュームを利用するポッドに対して抽象化できます(同一ストレージクラスのPVがあれば、クラウドプロバイダを跨いでも最小限の変更で使えるようになります)。

先ほど定義した `pv/pv-hostpath` を実際に利用するPVCを作成してみましょう。

```{literalinclude} codes/pvc-hostpath.yml
:caption: pvc-hostpath.yml
```

PVC側では、利用するストレージを選定するために、いくつかの情報を提示しています。

- ストレージとして必要としているサイズ(`spec.resources.requests.storage`)
- ストレージに対応するアクセスモード(RWXで宣言してたのでこちらもRWXで)
- 利用したいストレージクラス名

つまり、ストレージクラスが一致していても、PV側の持つ(保証ともいう)容量が不足していたり、アクセスモードが対応できない状況であれば選択できない可能性もあります。

とりあえず前述のPVCマニフェストを適用して様子を見てみましょう。

```{code-block} pwsh
PS> kubectl apply -f pvc-hostpath.yml(のパス)
PS> kubectl get pvc
NAME           STATUS   VOLUME        CAPACITY   ACCESS MODES   STORAGECLASS   AGE
pvc-hostpath   Bound    pv-hostpath   512Mi      RWX            pvhp           3s
```

実際に割り当てられたストレージは、512Miになっているのがわかります。

### 条件に合わなかったら?

では条件に合わないものを要求したらどうなるのでしょう。
たとえば先ほどのマニフェストにおいて、容量要求を1Giにしたとします。

```{literalinclude} codes/pvc-hostpath-1Gi.yml
:diff: codes/pvc-hostpath.yml
:caption: capacityを1Giに引き上げてみたもの
```

先ほどのPVCを削除後、このマニフェストを適用して様子を見ると、割り当てができません。

```{code-block} pwsh
PS> kubectl delete pvc/pvc-hostpath # 現PVC削除
PS> kubectl apply -f pvc-hostpath-1Gi.yml # 1Gi版を適用
PS> kebectl get pvc
NAME           STATUS    VOLUME   CAPACITY   ACCESS MODES   STORAGECLASS   AGE
pvc-hostpath   Pending                                      pvhp           5s
```

と、Pending状態になってしまいました。割り当てできるものが無いために『お待ちください』という感じです。

続いて、**容量は戻して**アクセスモードをReadWrite**Once**にしてみたらどうでしょう。
PV側はReadWriteManyのみのため、こちらも条件に該当しないと考えられます。

```{literalinclude} codes/pvc-hostpath-rwo.yml
:diff: codes/pvc-hostpath-1Gi.yml
:caption: 容量を戻してアクセスモードをRWOに変更
```

同様に現PVCを削除後適用して様子を見てみます。




```{code-block} pwsh
PS> kubectl delete pvc/pvc-hostpath # 現PVC削除
PS> kubectl apply -f pvc-hostpath-rwo.yml # RWO版を適用
PS> kebectl get pvc
NAME           STATUS    VOLUME   CAPACITY   ACCESS MODES   STORAGECLASS   AGE
pvc-hostpath   Pending                                      pvhp           56s
```

やはり割り当てができない状態でした。
このように、大雑把ですが、

- 容量
- アクセスモード
- PVの持つストレージクラス

の3点セットがPV選択の鍵となると考えておけば良いでしょう。

では、マニフェストを元に戻し、割り当て済の状態にしておきましょう。

## デプロイメントで使ってみましょう

では実際にワークロードで使ってみます。

```{literalinclude} codes/deploy-pvc.yml
:caption: 実際にPVCをマウントする例(deploy-pvc.yml)
:emphasize-lines: 16-18
```

これまでは`volumes`指定で`hostPath`や`empthDir`を出しましたが、抽象化して外に追い出したので、要求名で指定する必要があります。`PersistentVolumeClaim`で宣言し、対象となるクレーム名を設定します。

これを適用すると、`deploy/pvc`が生成されます。

```{code-block} pwsh
PS> kubectl apply -f deploy-pvc.yml(のパス)
deployment.apps/pvc created
# 少し間を置いてから
kubectl get deploy
NAME   READY   UP-TO-DATE   AVAILABLE   AGE
pvc    1/1     1            1           65s
```

````{warning}
前提となるPVC/PVが稼働していないといつまで経ってもデプロイメントが有効になりません。ポッドを確認するとPendingになってしまいます。

```{code-block} pwsh
kubectl get pods
NAME                  READY   STATUS    RESTARTS   AGE
pvc-b79f4cf4c-d2k2m   0/1     Pending   0          31s
```

PVC/PVの状態を確認して、消えていないかをチェックしておきましょう。
後付けで足りないPVCを足すことで遅れて検出されてポッドも動き始めます。
````

### ポッドのスケーリング

なお、今回のマニフェストではPVC込みで行っておりますが、RWXでPVを宣言しているため、スケーリングで複数のポッドが参照(同一ノード内での話)することになっても、共有した状態でマウントを継続していきます。

```{code-block} pwsh
# レプリケーション → 2つにする
PS> kubectl scale deploy/pvc --replicas 2
PS> kubectl get deploy
NAME   READY   UP-TO-DATE   AVAILABLE   AGE
pvc    2/2     2            2           5m54s
PS> kubectl get pods # 各ポッド名は次で使います
NAME                  READY   STATUS    RESTARTS   AGE
pvc-b79f4cf4c-d2k2m   1/1     Running   0          8m51s
pvc-b79f4cf4c-zh6t5   1/1     Running   0          3m6s
```

2つになったところで、片方のポッド上で日時情報を書き込み、他方で読み出してみます。

```{code-block} pwsh
:caption: 各ポッドを指定しての書き込み → 読み出し
PS> kubectl exec pod/pvc-b79f4cf4c-d2k2m -- sh -c 'date > /data/now'
PS> kubectl exec pod/pvc-b79f4cf4c-zh6t5 -- sh -c 'cat /data/now'
Mon Oct 17 20:56:07 UTC 2022
```

ただし、 **各ポッドが同じボリュームをマウントできるかは、提供するストレージの性質と、同一ノードか別ノードかなどの状況によって変化します**。利用するクラウドプロバイダの性質をよく確認する必要があります。

- [ボリューム |Kubernetes](https://kubernetes.io/ja/docs/concepts/storage/volumes/)


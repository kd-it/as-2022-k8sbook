# ステートフルセット

```{attention}
ステートフルセットは、少し使いどころが難しいと思いますので今無理しなくても大丈夫です。
```


**ステートフルセット**(`statefulset`略称`sts`)は、デプロイメントと似た概念ですが、**状態を持つ**という部分が異なります。
状態を保持するために永続ストレージ(PVC)がポッドのテンプレートに追加された形になっており、ポッドが再起動する際なども、同じストレージがマウントされるように調整されています。

用途として考えられるのは以下のようなものとなります。

- 安定したネットワーク識別子(一意)が必要なとき
- 安定した永続ストレージが必要なとき
- 安全なデプロイとスケーリングを行いたいとき
- ローリングアップデートを行いたいとき

デプロイメントと似ていますが、ストレージもまとめて管理されることに留意してください。
そのため、ポッドの再生成の際に、ストレージの存在するノード上で再生成が行われます(原則論)。

```{tip}
たとえばマルチノード構成のクラスタにおいて、以前動いていたノードが喪失(無くなる)というケースにおいては仕方ないので別ノードで起動しようとします。
```

## ステートフルセットの記述

状態を持つという意味では、やはりデータベースが一番わかりやすいです。
おさらい+αということで作成してみます。

```{literalinclude} codes/sts-mariadb.yml
:caption: ステートフルセット構成の例(MariaDB)
:linenos:
:emphasize-lines: 29-36
```

比較的デプロイメントに近く、レプリカ数の設定も可能な所もポイントですが、目をひくのは29行からの`volumeClaimTempletes`というキーです。
コンテナはもともと状態を保持しない(コンテナ消滅時に内包するデータは全て消滅する)ので、付随するストレージを用意して、そこに保存するようにする(起動時に再接続する)ことで、終了前の状態を再現できるように、というアプローチで構成されていることがわかると思います。

それ以外に現状ではよくわからない項目(例えば10行目の`serviceName`)もありますが、その辺りは今は気にしないで良いでしょう…

## 実際に使ってみると

上記マニフェストを適用して、ポッド達の状態を見てみると、デプロイメントからの流れと違ったものが見えます。

```{code-block} pwsh
:caption: 適用後のsts/pods/pvcリソースをチェック

PS> kubectl get sts,pods,pvc
NAME                           READY   AGE
statefulset.apps/sts-mariadb   1/1     5m6s

NAME                READY   STATUS    RESTARTS   AGE
pod/sts-mariadb-0   1/1     Running   0          5m6s

NAME                                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/storage-sts-mariadb-0   Bound    pvc-6dbe5870-6c5c-4761-b3e1-354437823255   256Mi      RWX            standard       5m6s
```

テンプレートに従ってボリュームが要求され、自動的に生成されています(今回はダイナミックプロビジョニングになっている)。
それに対応する形でポッドが生成されてマウントも行われています。

スケーリングさせると、自動的にストレージ(PVC)も追加されていきます。

```{code-block} pwsh
PS> kubectl scale sts/sts-mariadb --replicas 2 # 2つにする
PS> kubectl get sts,pods,pvc # 少し待ってからチェックする
NAME                           READY   AGE
statefulset.apps/sts-mariadb   2/2     9m32s

NAME                READY   STATUS    RESTARTS   AGE
pod/sts-mariadb-0   1/1     Running   0          9m32s
pod/sts-mariadb-1   1/1     Running   0          18s # 連番で増加

NAME                                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/storage-sts-mariadb-0   Bound    pvc-6dbe5870-6c5c-4761-b3e1-354437823255   256Mi      RWX            standard       9m32s
persistentvolumeclaim/storage-sts-mariadb-1   Bound    pvc-f17b7703-3c6a-4700-bc0b-57243931c27d   256Mi      RWX            standard       18s
↑PVCも一緒に作成
```

と、連動が自動で行われます。このとき、縮退運転が発生するとポッドは消滅しますが、ボリュームは残ります。

```{code-block} pwsh
:caption: 縮退運転を行ってみる
PS> kubectl scale sts/sts-mariadb --replicas 1 # 2→1
PS> kubectl get sts,pods,pvc
NAME                           READY   AGE
statefulset.apps/sts-mariadb   1/1     11m

NAME                READY   STATUS    RESTARTS   AGE
pod/sts-mariadb-0   1/1     Running   0          11m

NAME                                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/storage-sts-mariadb-0   Bound    pvc-6dbe5870-6c5c-4761-b3e1-354437823255   256Mi      RWX            standard       11m
persistentvolumeclaim/storage-sts-mariadb-1   Bound    pvc-f17b7703-3c6a-4700-bc0b-57243931c27d   256Mi      RWX            standard       117s
```

ポッド数は1になりましたが、ボリューム1は残ったままです。
そのため、次にスケーリングで2になったときにそのボリュームがそのままあてがわれます。

2に戻してもPVCは増えません、追加されたポッドが"1"になるため、既存の"1"の番号付きストレージがあてがわれます。

```{code-block} pwsh
:caption: 復帰させてみる
PS> kubectl scale sts/sts-mariadb --replicas 2 # 1→2
PS> kubectl get sts,pods,pvc
NAME                           READY   AGE
statefulset.apps/sts-mariadb   2/2     15m

NAME                READY   STATUS    RESTARTS   AGE
pod/sts-mariadb-0   1/1     Running   0          15m
pod/sts-mariadb-1   1/1     Running   0          82s

NAME                                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/storage-sts-mariadb-0   Bound    pvc-6dbe5870-6c5c-4761-b3e1-354437823255   256Mi      RWX            standard       15m
persistentvolumeclaim/storage-sts-mariadb-1   Bound    pvc-f17b7703-3c6a-4700-bc0b-57243931c27d   256Mi      RWX            standard       6m40s
```

このように、ストレージを保持しつつ、レプリケーションやアップデートが可能となるものがステートフルセットです。

## おまけ

なお、リソースを削除してもPVCは残ります。

```{code-block} pwsh
PS> kubectl get sts,pods,pvc
# sts/podsは消えているので出てこない、PVCのみ出力される
NAME                                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/storage-sts-mariadb-0   Bound    pvc-6dbe5870-6c5c-4761-b3e1-354437823255   256Mi      RWX            standard       19m
persistentvolumeclaim/storage-sts-mariadb-1   Bound    pvc-f17b7703-3c6a-4700-bc0b-57243931c27d   256Mi      RWX            standard       9m55s
```

## 永続ボリュームの要求

`hostPath` を使うと、パス名をキーとした永続ボリュームが用意できるわけですが、特定のパスをキーとするが故に可搬性に問題が出る可能性があります。
そこで、実際に運用するときは、その部分を抽象化するための考え方として**永続ボリュームの要求**という行為(リソース)を挟むようにしています。
このためのリソースが **PersistentVolumeClaim** です。略名は **pvc** となります。

```{literalinclude} codes/pvc-data.yml
:caption: 永続ボリューム要求の例(pvc/data)
:language: yaml
```

このマニフェストも、vscodeにおいて `persistentvolumeclaim` と入れることによって得られるスニペットをベースに記述しています。
適用すると、pvcに1つ、dataというリソースが準備されます。

```
PS> kubectl get pvc
NAME   STATUS   VOLUME         CAPACITY   ACCESS MODES   STORAGECLASS   AGE
data   Bound    pvc-5d10c5...  128Mi      RWO            standard       6s
```

* STATUS: 割り当て状態、Boundであれば割り当て済です(リクエストしたストレージの準備ができている、ということ)
* VOLUME: 内部的なボリューム名、Deploymentの時みたいに適当なIDがついた名前になっています
* CAPACITY: マニフェストで指定したサイズです
* ACCESS MODE: ボリュームに対する読み書きの設定、マニフェスト上では `ReadWriteOnce` (一カ所で読み書き可能)としているので、頭文字で`RWO`でそれを示しています
* STORAGECLASS: ボリュームの割り当て元となっているストレージの名称、minikube環境ではバックエンドのOS(K8sクラスタの稼働環境)にあわせたものが自動で設定されており、standardという名称で準備されています

このボリュームをPodで使うためには、`volumes`内の定義でPVCの利用を宣言すればよくなります。

```{literalinclude} codes/alpine-withpvc.yml
:caption: pvc/dataを/dataにマウントするポッドの例(alpineベース)
:emphasize-lines: 19-25
:linenos:
```

23〜25行目のボリューム定義において、ボリュームとして`emptyDir`などと同様に`PersistentVolumeClaim`というキーとその値(辞書)によりdataボリュームが要求されていることがわかると思います。
こうすることで、ボリュームというホスト環境に依存する部分を抽象化しており、実際のボリュームはより低い位置(pvc以下)に押し出すことができます。

````{tip}
なお、ポッドが利用したいボリュームが存在しない状態(pvc/dataを適用する前にポッド側を適用した場合)は、該当pvcができるまで起動が待たされてしまいます。この現象は、ストレージ自体が不足しているとき(1GiB欲しいと要求しているけど残りストレージが512MiBの時など)でもやはり発生します。

```{code-block} ps1
PS> kubectl delete pvc/data # PVCを削除
PS> kubectl apply -f alpine-withpvc.yml # pod/alpine-with-pvc作成
pod/alpine-with-pvc created # 作成自体は成功する、が…
PS> kubectl get pods
NAME                    READY   STATUS    RESTARTS   AGE
alpine-with-pvc         0/1     Pending   0          22s # 作れない
PS> kubectl describe pod/alpine-with-pvc
...
Events:
  Type     Reason            Age   From               Message
  ----     ------            ----  ----               -------
  Warning  FailedScheduling  78s   default-scheduler  0/3 nodes are available:
    3 persistentvolumeclaim "data" not found. preemption: 0/3 nodes are available:
    3 Preemption is not helpful for scheduling.
```

このように、ポッドの生成が止まってしまいます。
この状態でもpvcを追加すればそのうち気づいて実際にポッドが稼働しだします。

```{code-block} ps1
PS> kubectl apply -f pvc-data.yml
PS> kubectl get pods # 少し待ってからチェック
NAME                    READY   STATUS    RESTARTS   AGE
alpine-with-pvc         1/1     Running   0          3m30s
```

````


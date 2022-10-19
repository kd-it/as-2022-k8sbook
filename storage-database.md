# ストレージの実際: データベースの構築

では、`hostPath`を用いる例として、データベースを構築してみます。
ただしこのやり方が**今後も使う正しいものというわけでもありません**ので、そこは注意しておいてください。

## とりあえず従来の方法で

まずは、とりあえず従来の「ポッドが消えたらデータが消える」かたちで構築してみます。
これを書き換えていく・流用する形で進めていきます。

```{literalinclude} codes/mariadb-plain.yml
:language: yaml
:caption: MariaDBを動かすポッド(従来のもの)
```

```{warning}
このマニフェストを適用して実験しても良いですが、あとでボリュームを追加したときの適用の前に必ず`delete`するようにしてください。
```

## ストレージを導入する

ここにストレージを導入します。
`hostPath`方式のストレージ`dbstore`を用意します、ノード上のデータ置き場は `/data/mariadb` としておきます。

```{literalinclude} codes/mariadb-volume.yml
:diff: codes/mariadb-plain.yml
:caption: ストレージ`dbstore`の追加(差分)
```

そして、コンテナ側もストレージをマウントするように`volumeMounts`を追加します…

```{literalinclude} codes/mariadb-volume-mount.yml
:diff: codes/mariadb-volume.yml
:caption: マウント指定の追加(差分)
```

このマニフェストを使うと、ストレージを確保した上でコンテナから切り離されたストレージにデータベースの情報が記録されるようになります。よってポッドを破棄・再生成してもデータベースが維持されます。

```{warning}
もし初期状態のポッドマニフェストを適用している場合は、一度`delete`しておいてください。
再適用(`apply`)した場合はエラーになります(差分適用するレベルを超えてしまうため)。
```

## 試してみる

実際に試してみます。最終的に作ったファイルの名前を `mariadb-volume-mount.yml` としています。

```{code-block} pwsh
PS> kubectl apply -f mariadb-volume-mount.yml(のパス)
PS> kubectl get pods
kubectl get pods
NAME              READY   STATUS    RESTARTS      AGE
mariadb-storage   1/1     Running   0             1m11s
```

MariaDBデータベースに接続して、実際にデータベースを作成します。

```{code-block} pwsh
:caption: データベースの作成
:emphasize-lines: 1,2,13,16,28

PS> kubectl exec -it pod/mariadb-storage -- mysql --password=dbadmin
MariaDB [(none)]> show databases;
+--------------------+
| Database           |
+--------------------+
| information_schema |
| mysql              |
| performance_schema |
| sys                |
+--------------------+
4 rows in set (0.001 sec)

MariaDB [(none)]> create database k8ssample; -- k8ssampleデータベースの作成
Query OK, 1 row affected (0.001 sec)

MariaDB [(none)]> show databases; -- 再表示して確認
+--------------------+
| Database           |
+--------------------+
| information_schema |
| k8ssample          | <- 作成された!
| mysql              |
| performance_schema |
| sys                |
+--------------------+
5 rows in set (0.002 sec)

MariaDB [(none)]> quit; -- 一旦終了
Bye
```

データベースが作成されました。ここでポッドを一度破棄し、再度作り直してみます。
従来ならポッド消滅時にポッド内のストレージだったデータベース領域は削除されてましたが…

```{code-block} pwsh
:caption: 一度破棄してからの再生成(ポッド)

PS> kubectl delete pod/mariadb-storage
PS> kubectl apply -f mariadb-volume-mount.yml(のパス)
```

起動後接続し直すと、先ほど作った `k8ssample` データベースが残っていることがわかります。

```{code-block} pwsh
:caption: 再接続しての確認
:emphasize-lines: 1,3,15

PS> kubectl exec -it pod/mariadb-storage -- mysql --password=dbadmin

MariaDB [(none)]> show databases;
+--------------------+
| Database           |
+--------------------+
| information_schema |
| k8sample           | <- ちゃんとある!
| mysql              |
| performance_schema |
| sys                |
+--------------------+
5 rows in set (0.001 sec)

MariaDB [(none)]> quit;
Bye
```

## おまけ: ノードに直接乗り込む

`hostPath`によりノード上のディレクトリを割り当てるということをここでは行ってましたが、実際どのように見えているのでしょう。
`minikube`には`ssh`サブコマンドが用意されており、ノードにssh経由で入り込むことができます。

```{code-block} pwsh
:caption: ノードに接続してみる
:emphasize-lines: 1,2,5

PS> minikube ssh
$ ls /data
db  mariadb  mariadb-data  pv0001
# マニフェストを確認するとわかりますが、hostPathは/data/mariadbです
$ ls /data/mariadb
aria_log.00000001  ib_buffer_pool  ibtmp1             mysql               sys
aria_log_control   ib_logfile0     k8sample           mysql_upgrade_info
ddl_recovery.log   ibdata1         multi-master.info  performance_schema
$ exit
```

なので、ポッドが利用しているディレクトリを削除すれば、初期状態に戻ることも予想できると思います。

```{code-block} pwsh
:caption: ノードのストレージを破壊してみると…
:emphasize-lines: 4

PS> kubectl delete pod/mariadb-storage
pod "mariadb-storage" deleted
PS> minikube ssh
$ sudo rm -fr /data/mariadb # 破壊!
$ exit
PS> kubectl apply -f mariadb-volume-mount.yml(のパス) # 再生成
pod/mariadb-storage created
PS> kubectl exec -it pod/mariadb-storage -- mysql --password=dbadmin
MariaDB [(none)]> show databases;
+--------------------+
| Database           |
+--------------------+
| information_schema |
| mysql              |
| performance_schema |
| sys                |
+--------------------+
4 rows in set (0.001 sec)
MariaDB [(none)]> quit;
Bye
```

```{tip}
消すのが面倒となったら、最悪minikubeですので、`minikube delete`で環境自体を破壊してやりなすのも一考です。
マニフェストがあれば再現はできるはずですから!
```

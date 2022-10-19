# よかれあしかれhostPath

`hostPath`を使うとストレージが確保できるので一見便利そうですが、必ずしもそれがいいというわけでもありません。
前項のMariaDBのポッドをデプロイメントに拡張して考えてみます。

## デプロイメントに書き換えてみる

実際に書き直したものがこちらです、ベースはvscodeのスニペットからです。
強調したのはボリュームの設定についての部分です。

```{literalinclude} codes/mariadb-deploy.yml
:caption: MariaDB(ストレージ付き)をデプロイメントに書き換えた例
:emphasize-lines: 14-17,27-32
```

こちらも起動後、同様にチェックしてアクセスを確認しておきましょう。
マニフェストファイルは `mariadb-deploy.yml` としています。

```{code-block} pwsh
:caption: 適用してチェック

PS> kubectl apply -f mariadb-deploy.yml(のパス)
deployment.apps/mariadb created
# ↓接続対象が deploy/mariadb です、履歴使う場合は要書き換え
PS> kubectl exec -it deploy/mariadb -- mysql --password=dbadmin
MariaDB [(none)]> quit; -- 接続できたのを確認したら終了
Bye
```

今回は中身が問題ではないので、データベースの確認などは不要です。

## よくないこと

このマニフェストはデプロイメントの指示です。
デプロイメントということは、レプリケーション(スケーリング)が出現します。
Webサーバーはスケーリングさせることがありますが、このデプロイメントをスケーリングさせたら何が起きるでしょうか。
実際にスケーリングさせますが、その前にポッド状態の遷移を見たいので、端末をひとつ追加してウォッチしておきましょう。

```{code-block} pwsh
PS> kubectl get pods -w
NAME                      READY   STATUS    RESTARTS   AGE
mariadb-6cf7b6685-t88sb   1/1     Running   0          7m51s
```

これでよし、では別の端末を使って一時的なレプリケーション設定を加えます。

```{code-block} pwsh
PS> kubectl scale --replicas 2 deployment/mariadb
deployment.apps/mariadb scaled
```


すると、一見成功しているように見えます。

```{code-block}
PS> kubectl get pods -w
NAME                      READY   STATUS    RESTARTS   AGE
mariadb-6cf7b6685-t88sb   1/1     Running   0          7m51s
mariadb-6cf7b6685-2f44l   0/1     Pending   0          0s
mariadb-6cf7b6685-2f44l   0/1     Pending   0          0s
mariadb-6cf7b6685-2f44l   0/1     ContainerCreating   0          0s
mariadb-6cf7b6685-2f44l   1/1     Running             0          4s
```

ところがしばらくするとエラーが発生します。

```{code-block}
pythonmariadb-6cf7b6685-2f44l   0/1     ContainerCreating   0          0s
mariadb-6cf7b6685-2f44l   0/1     Pending   0          0s
mariadb-6cf7b6685-2f44l   0/1     ContainerCreating   0          0s
mariadb-6cf7b6685-2f44l   1/1     Running             0          4s
mariadb-6cf7b6685-2f44l   0/1     Error               0          35s
mariadb-6cf7b6685-2f44l   1/1     Running             1 (3s ago)   38s
```

もう少し続けてみていると、何度も落ちたためかバックオフが発生します…

```{code-block}
mariadb-6cf7b6685-2f44l   1/1     Running             1 (3s ago)   38s
mariadb-6cf7b6685-2f44l   0/1     Error               1 (35s ago)   70s
mariadb-6cf7b6685-2f44l   0/1     CrashLoopBackOff    1 (14s ago)   83s
mariadb-6cf7b6685-2f44l   1/1     Running             2 (17s ago)   86s
mariadb-6cf7b6685-2f44l   0/1     Error               2 (48s ago)   117s
mariadb-6cf7b6685-2f44l   0/1     CrashLoopBackOff    2 (11s ago)   2m8s
mariadb-6cf7b6685-2f44l   1/1     Running             3 (25s ago)   2m22s
```

ずっと繰り返されてると気持ち悪いので、スケーリングを戻しておきましょう。

```{code-block} pwsh
PS> kubectl scale --replicas 1 deployment/mariadb
deployment.apps/mariadb scaled
```

これで、ポッドのウォッチ状態は解除しておいても大丈夫です。

## どうして?

どうしてこんなことになるのでしょうか?
実は今の状態を図に起こすとこんな感じです。

```{figure} images/hpshare.drawio.png
デプロイメントの状態
```

この状態でスケーリングを実施し、ポッド数を2つにしたらどうなるでしょう。

```{figure} images/hpshare-r2.drawio.png
デプロイメントの状態(2つにスケーリング)
```

この場合、`hostPath` が同じパスを指すため、2つのポッドは同一のパスでノードのボリュームを共有することになります。
その結果、後で起動したMariaDBは、既存のデータベース構成を認識して初期構成を無視して起動しようとします(なのでRunningになる)。
でもMariaDBはひとつの構成を複数のサーバーでシェアすることによる(同時書き込みなどでの)破損を回避するため、データベースのディレクトリが使用中かを確認できるようにしています。
そのため起動しても使用中であることに気づき、エラーで終了してしまいます。
でもレプリカセット部分で『2つ動かさないと』となるから再度ポッドを起動してエラーが繰り返されてしまいます(なのでバックオフが発生)。

こういったことから、レプリカセットをそのまま使うと逆に動かせなくなりかねないケースもあるということを覚えてきましょう。

```{tip}
なお、複数ノード構成になっている場合、たまたま別ノードで動き出し、そちらで成功することもあるので見かけ上成功しているように見えることもあります。とはいえこのような不安定感は嫌ですよね。
```


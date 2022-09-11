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

## マニフェストでのスケーリング

デプロイメントのマニフェストであれば、レプリカ数を変更することで恒常的に変更が可能です。
たとえば以下のように修正してみたとします。

```{literalinclude} codes/deploy2.yml
:diff: codes/deploy1.yml
:caption: レプリカ数を変更する
```

このマニフェストを適用することでレプリカ数を2にすることになります。
実際に適用して状況を確認してみましょう。

```{code-block} ps1
PS> kubectl get pods
PS> kubectl apply -f deploy1.yml
PS> kubectl get pods # 少し様子を見ながら繰り返し確認する
```

やがて2つになると思います。スケーリングを最初から想定している場合は、レプリカ数をマニフェストに書いておけば忘れずに対応が入ることになります。




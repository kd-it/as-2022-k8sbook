# ダイナミックプロビジョニング

PVCはPVを用意し、PVに設定した`StorageClass`を用いて割り当てを受けます。
しかし、いちいち事前にPVを設定していくのも管理者にはちょっと面倒です。
第一容量に見合ったものが本当に最適解なのかもわかりません。

そこで、PVCに応じて自動的に指定容量のPVを作ってくれる仕組みが必要となりました。
この仕組みを**ダイナミックプロビジョニング**と呼びます。

## ダイナミックプロビジョニングを使うには

そもそもPVCがどうやってPVの割り当てを受けていたのでしょうか?

- 指定のStorageClassの中から
- 指定されたアクセスモードを持つ
- 指定した以上のストレージ容量を持つもの

が登録されたPVの中で選定され、あてがわれていました。そこでダイナミックプロビジョニングでは、StorageClassの中に**リクエストに応じたアクセスモード・容量を持つPVを作る機能**を追加し、カスタムメイドのPVをその場で作るという方法を採っています。
当然そのようなストレージサービス(StorageClass)が必要なわけですが、クラウドプロバイダ提供のものはそういう仕組みを組み込んだものを用意しています。

minikubeにおいては、hostPathによるストレージクラス(`k8s.io/minikube-hostpath`)が提供されています。

```{code-block} pwsh
PS> kubectl get sc # minikube環境下でチェック sc = StorageClass
```

```{figure} images/kubectl-get-sc.png
標準提供されるストレージクラス(ダイナミックプロビジョニング)
```

このStorageClassには、事前に `standard` という名前での割り付けが行われているので、PVC記述の際に用いることでダイナミックプロビジョニングが利用できるようになります。
実際に使ってみましょう。

```{literalinclude} codes/pvc-dp.yml
:caption: pvc-dp.yml ダイナミックプロビジョニングの例
```

minikubeにおいて、デフォルトのStorageClassがstandardになっているので書かなくても良かったりするのですが、今回は明示しています。

このマニフェストを適用すると、PVが自動生成され、PVCに割り当て(bound)されます。

```{code-block} pwsh
PS> kubectl apply -f pvc-dp.yml(のパス)
PS> kubectl get pv,pvc
NAME                       STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
persistentvolumeclaim/dp   Bound    pvc-917aa51b-31d6-45ab-8e4c-74e86b23c6ce   128Mi      RWO            standard       7s

NAME                                                        CAPACITY   ACCESS MODES   RECLAIM POLICY   STATUS   CLAIM        STORAGECLASS   REASON   AGE
persistentvolume/pvc-917aa51b-31d6-45ab-8e4c-74e86b23c6ce   128Mi      RWO            Delete           Bound    default/dp   standard                7s
```

このストレージクラスでは、割り当て解除後のポリシー(ReclaimPolicy)が削除(Delete)で設定されているため、解除後に自動的に破棄されます。

```{code-block} pwsh
PS> kubectl delete -f '/Users/densuke/Documents/book/mynewbook/codes/pvc-dp.yml'
persistentvolumeclaim "dp" deleted
PS> kubectl get pv,pvc
No resources found
```

事前に管理者がこのようなStorageClassを設定してもらえていればいちいちボリューム設計をしなくていい(実際の所PVも管理者が事前に作るタスクになりますから)ので、かなり楽になります。
ただし管理者の側で容量監視をしておかないとあとあとすごい課金状況になる恐れもあるので、必要な容量の検討はきちんとしておきましょう…


# Deploymentリソース

デプロイメント(deployment)リソースは、Podを配下に従えて、Podの管理や更新・ロールバックなどをサポートします。
このリソースを考えるときには、直下に存在することになるレプリカセット(replicaset/rs)についてもおさえておく必要があります。

とりあえずdeploymentを作成して、そこからrsに下って学んでいくと良いでしょう。

## とりあえず作ってみる

vscodeのK8s拡張により、スニペットが用意されます。こちらを使ってみると良いでしょう。
なお、適用(`kubectl apply`)の前に、前の節で使っているPodリソース(1st/2nd)は消しておく方が良いでしょう。

```{figure} images/vscode-snippet-deploy.png
スニペット "deployment" の呼び出し
```

```{figure} images/vscode-snippet-deploy-result.png
呼び出し結果
```

呼び出されたスニペットをベースに、内容を書き換えていってください。

```{literalinclude} codes/deploy1.yml
:linenos:
:caption: スニペットをベースとしたnginxデプロイメント(強調部が変更しているところ)
:emphasize-lines: 4,8,12,15,16,22
```

まずはデプロイしてみましょう。様子をGUIで見たい方は、別途ダッシュボードを起動しておくと良いでしょう。

```{code-block} ps1
:caption: deploy1.ymlを用いたデプロイ

PS> kubectl apply -f deploy1.yml
deployment.apps/frontend created
```

ダッシュボード上で見た場合、1つのマニフェストですが、3つのワークロードが設定されています。

```{figure} images/dashboard-deploy1.png
ダッシュボード上の表示、3つのワークロードが出てきた
```

出てきたものは3つあります。

- デプロイメント(deployment)
- ポッド(pod)
- **レプリカセット**(replicaset)

このうちPodは前節で解説しているので、つ次にデプロイメントとレプリカセットを見ていきましょう。


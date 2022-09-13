# マニフェストと `kubectl`

マニフェストは、K8sを使う上で必須となるテキストで、 **あるべき状態** を提示するために使います。
マニフェストをクラスタに適用することで、指定された状態になるように、各種の機能に指示を出していくことになります。

## マニフェストの制御対象

マニフェストでは様々なリソース(資源の種類)に対する指定が可能であり、実際に生成されるものが(リソース)オブジェクトとなります。
各リソースに対する指定の方法は書式がK8sによって定義されており、YAML(人間が書くときに主に使用)およびJSON(プログラム内で生成して使用)で記述することが一般的です。

以下に記すのは主なものです、その他いろいろありますが気にしないでください。
また、以下に記されていても授業では登場しないものもあります。

* ワークロード: コンテナなどの実働部隊
    * コンテナなどの実際に動くプログラム(ポッド) ※
    * コンテナの起動数の保持(レプリカセット)
    * コンテナの状態管理(デプロイメント) ※
* サービス: コンテナを他のコンテナや外部ネットワークと繋ぐ
    * サービス ※
    * イングレス(環境上用意しにくいので概略説明のみ)
* (永続)記憶: 何らかの情報を記憶・保持する
    * コンテナに対する設定を保持する(コンフィグマップ) ※少しだけ扱います
    * 機密情報を保持する(シークレット)  ※少しだけ扱います
    * 記憶域の提供(ストレージ・ストレージ要求) ※PVC中心、PVは時間次第

## マニフェストの書き方

マニフェストを手動で(人間の手で)記述するときは、ほとんどYAMLを用いています。
docker composeでYAMLには触れていると思いますので、書法そのものは認識していると思います。
しかし、K8sのマニフェストでは制御対象(kind)と仕様のバージョンなどの問題もあり、完全に手動でスクラッチで書くのは非常につらいです。
そのため、ひな形を用意して、エディタの補完機能を用いながら書くのが普通です。

ここでは、VisualStudio Code(vscode)の拡張機能であるKubernetes拡張を利用していきます。ということでvscodeを起動してください。

1. 拡張機能のビューに切り替えます
2. 検索ボックスに `kubernetes` と入れます
3. Microsoftが公開する拡張が出てくるので、入っていないなら入れておきましょう


```{figure} images/vscode-k8s-ext.png
---
:width: 75%
:caption: K8sの拡張を検索(vscode上)
```


入れてしまえばそれほど難しいことはありません。
実際に書いて試してみると良いでしょう。

1. 作業用にディレクトリを作ってください
2. その中でファイル `1st.yml` を作成します
3. `pod` と入れると拡張機能が反応し、"Kubernetes Pod"が候補として上がってくると思いますので、選んでください

```{figure} images/vscode-call-pod.png
---
width: 75%
---

podと記入したところ
```

ポッド(Pod)のスニペットが展開されると、以下のように基本コードが挿入されます。


```{figure} images/vscode-snippet-pod.png
---
width: 75%
---

Podスニペット展開
```


* `myapp` の部分がマルチセレクト状態になっていますので、とりあえず `1stpod` としておいてください
    * うっかりマルチセレクト状態を解除してしまったときは、該当箇所(三カ所)を全て手作業で書き換えておいてください
* 書き換え後TABを押すと `<Image>` にカーソルが移動すると思います、こちらには `alpine` を入れておいてください
* このままTABでports以下を書き換えることになるのですが、今回は使いませんので、ports以下は削除してください
    * もともとインターネット上へのサービス公開が主用途なので、スニペットでは「よく使いそうだから」ということで入ってるだけです、不要ならカットできます
* 代わりに `ports` だったところに `command` キーと値を入れておきます(後述のコード参照)

以上を構成すると、以下のコードになります。 `command`キーの所は内容とインデントに注意して記入してください。

```{literalinclude} codes/1st.yml
:language: yaml
```

## kubectlのインストール

マニフェストを実際にK8sクラスタに反映させるためには、 `kubectl` というコマンドを用います。
[単独でインストール](https://kubernetes.io/ja/docs/tasks/tools/install-kubectl/)するのが最良ですが、minikubeの力を借りて導入することもできます。

```{code-block} ps1
PS> minikube kubectl
```

```{image} images/minikube-kubectl-install.png
```

```{hint}
minikubeにkubectlを入れさせた場合、今後の呼び出しが `minikube kubectl ...` となってしまうので注意してください。
面倒に思う方は、エイリアスや関数を用いることで通常の `kubectl` に見せかけるか、単独でのインストールを行うことをお勧めします。
```

### kubectlの呼び出し方調整

`minikube kubectl` は記述がかなり面倒だと思います。
そこで、WindowsでもmacOSでも、呼び出し方を調整した方が良いと思います。ヒントになりそうな事をいくつか出しておきます。

#### `kubectl` を入れる

[単独でインストール](https://kubernetes.io/ja/docs/tasks/tools/install-kubectl/)すればOKです。

#### Windows10/11のPowerShell functionを使う

PowerShellの関数を用意することで `minikube` を省略できます。

```{code-block} ps1
PS> function kubectl { minikube kubectl -- $args }
```

こちらをプロファイルに書けば次のPowerShellセッションから有効となります。

```{code-block} ps1
# メモ帳でプロファイルを開く(or 新規編集)、内容は前述のfunctionの部分
PS> notepad ${PROFILE}
```

また、プロファイルの読み込みは初期状態で拒否される可能性があるので、[管理者権限で実行制限を緩めて](https://docs.microsoft.com/ja-jp/powershell/module/microsoft.powershell.core/about/about_execution_policies?view=powershell-7.2)(`RemoteSigned`)おいてください。

```{code-block} ps1
# プロファイル読み込みを有効にするため、スクリプトファイルの実行制限を "RemoteSigned" にする
(管理者)PS> Set-ExecutionPolicy RemoteSigned
```

プロファイルを設定した場合、有効になるのは作成・変更した後のセッションのみ(新規に起動したPowerShell)となります。

### macOS/Linuxのシェルエイリアスを利用する

エディタで `~/.bashrc` もしくは `~/.zshrc` に追記してください。

```{code-block} bash
# append to ~/.bashrc or ~/.zshrc
alias kubectl='minikube kubectl --'
```

次のセッションから有効になります。

## マニフェストの適用と削除

マニフェストは **適用** することで、記述した内容に基づいてクラスターの各部に設定が飛びます。
また、適用済マニフェストを使うことで(同じマニフェストファイルで) **削除** することもできます。

実際に適用してみましょう。

```{code-block} ps1
PS> kubectl apply -f 1st.yml
```

```{hint}
エイリアスなどの設定をしていない場合は、呼び出し方が以下のようになります。
以降、適宜読み替えてください。

```{code-block} ps1
PS> minikube kubectl -- apply -f 1st.yml
※ `kubectl` のあとに入れている `--` が地味に重要です(`-f` が `minikube` ではなく `kubectl` 側に渡すために必要)
```

逆に適用済のマニフェストを削除するときも、同じように適用(ただし`apply`ではなく`delete`)します。

```{code-block} ps1
PS> kubectl delete -f 1st.yml
```

マニフェストを削除すると、そのマニフェストによって適用された状態が適宜閉じられていきます。

改めて適用(`apply`)してみてから、状態を確認していきます。
ここまではダッシュボードを使っていましたが、ダッシュボードから離れてコマンドラインで見ていくようにしましょう。

```{code-block} ps1
# 1st.ymlはカレントディレクトリにあると想定しています
# 別の場所にある場合は、適宜パスを渡してください(以下略)
PS> kubectl apply -f 1st.yml
pod/1stpod created

# ポッドの一覧を出す
PS> kubectl get pods # 複数形
NAME     READY   STATUS    RESTARTS   AGE
1stpod   1/1     Running   0          56s
```

また、`kubectl`での`delete`サブコマンドにより、リソースオブジェクト単位での削除も可能です。
今作っていたポッドオブジェクト1stpodを削除してみましょう。

```{code-block} ps1
:captioin: kubectl deleteによるオブジェクト削除の例(pod/1stpod)

PS> minikube kubectl -- delete pod 1stpod # 種類 名前
pod "1stpod" deleted
(ここで1分ぐらい待たされることもあります)
PS> minikube kubectl -- get pods
No resources found in default namespace.
```

なお、該当リソースが既に存在しない状況でリソースを含むマニフェストで削除しようとすると、「既にそのリソースありませんけど」状態になりますが、わかってやってるので問題はありません。

```{code-block} ps1
:caption: 削除済リソースを含むマニフェストで二重に削除

PS> minikube kubectl -- delete -f 1st.yml
Error from server (NotFound): error when deleting "1st.yml": pods "1stpod" not found
# (API)サーバーからのエラー: 1st.ymlの削除中、pod 1stpodがありませんでした。
```


ポッドがなんなのかは次の話にて説明します。
`kubectl` では、 `get` サブコマンドにより現在見ているクラスターにおける各種リソースの内容を出すことができます。

今回はpods(ポッド)の一覧を出しました。今回渡したマニフェストでは、ポッドリソース(のオブジェクト)を生成させています。

```{literalinclude} codes/1st.yml
:language: yaml
:caption: 初めてのマニフェスト(再掲)
:linenos:
```

基本構造はどんなリソースでも概ね同じなので、ここで少しおさえておきましょう。

* 1,2行目
    * このマニフェストが処理対象としているリソースの種類(kind)と、定義しているAPIのバージョンです
    * 同一リソースに対する宣言でも、バージョンごとに記述する内容に違いがあるかもしれないので、そのためにバージョンを明示します
    * 過去のバージョンのものでも、サポートしているのであればその時の仕様に基づいた処理を行うようになっています
    * 2022/9現在(Ver.1.25)ではこうなっています
        * [Pod v1 core](https://kubernetes.io/docs/reference/generated/kubernetes-api/v1.25/#pod-v1-core)
        * 冒頭に警告がアナウンスされているとおりで、本来直接Podは使うものではありません、あくまで学習用です
* 3〜6行目
    * このマニフェストに対するメタデータです
        * `name` キーにより、生成するオブジェクトに対する名称が決められます(必須)
        * `label` キーは、このオブジェクトを外部(他のオブジェクト)から参照する場合に「絞り込み」として使うKey/Valueを設定します
            * vscodeでのマニフェストスニペットでは、`name`キーを使用していますが、別の場合もあります
* 7行目以降
    * リソースのための仕様です
    * podリソースに関しては、詳細は後ほど登場しますが、コンテナ(プロセス)を内包するという挙動を取ります

今回のポッドリソースでは、内容からもざっくり読み取れるように、alpineイメージを用いたコンテナであり、起動時に`sleep infinity`が動いていそうと言うことがわかります。
そのため、alpineのイメージに基づくコンテナが(停止させるまで)起動しっぱなしとなります。

このマニフェストが有効である限り、このクラスターでは常にこのコンテナは「1つ動いている」ことが状態として求められることになります。
よって、クラスターが終了してコンテナが消滅したとしても、次のクラスター起動時にマニフェストを再度適用し、勝手にコンテナを復帰(生成)させます。

```{code-block} ps1
:caption: クラスターの停止→(再)起動→ポッド状態確認

PS> minikube stop # deleteするとクラスターが消滅するのでさすがにNG
✋  「minikube」ノードを停止しています...
🛑  SSH 経由で「minikube」の電源をオフにしています...
🛑  1 台のノードが停止しました。
PS> minikube start # 再度クラスターを構成
😄  Darwin 12.5 上の minikube v1.26.1
✨  既存のプロファイルを元に、docker ドライバーを使用します
👍  minikube クラスター中のコントロールプレーンの minikube ノードを起動しています
🚜  ベースイメージを取得しています...
🔄  「minikube」のために既存の docker container を再起動しています...
🐳  Docker 20.10.17 で Kubernetes v1.24.3 を準備しています...
🔎  Kubernetes コンポーネントを検証しています...
    ▪ gcr.io/k8s-minikube/storage-provisioner:v5 イメージを使用しています
    ▪ kubernetesui/dashboard:v2.6.0 イメージを使用しています
    ▪ kubernetesui/metrics-scraper:v1.0.8 イメージを使用しています
🌟  有効なアドオン: default-storageclass, storage-provisioner, dashboard
🏄  終了しました！kubectl がデフォルトで「minikube」クラスターと「default」ネームスペースを使用するよう設定されました
PS> kubectl get pods
minikube stop; minikube start; kubectl get pods
NAME     READY   STATUS    RESTARTS      AGE
1stpod   1/1     Running   1 (58s ago)   15m
```


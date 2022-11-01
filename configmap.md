# コンフィグマップ

**コンフィグマップ**(`configMap`、略称`cm`)は、Key-Value型のデータやファイルをマニフェストから送り込むためのリソースです。

## 実際に使ってみる
上のように書いてもわかりにくいと思いますので、実際に作成してポッドから使ってみましょう。

```{literalinclude} codes/cm-example1.yml
:language: yaml
:caption: コンフィグマップの例、ユーザー名一覧的なもの
```

適用後に確認するとリソースが追加されたことがわかります。

```{code-block} pwsh
PS> kubectl apply -f cm-example1.yml(のパス)
PS> kubectl get cm
NAME               DATA   AGE
kube-root-ca.crt   1      29m # 最初から作られているもの
usernames          2      11s
```

定義内容はここでは出ませんが、`describe`するとわかります。

```{code-block} pwsh
:emphasize-lines: 14-24

PS> kubectl get cm
NAME               DATA   AGE
kube-root-ca.crt   1      29m
usernames          2      11s
PS> kubectl get cm/usernames
NAME        DATA   AGE
usernames   2      15s
❯ kubectl describe cm/usernames
Name:         usernames
Namespace:    default
Labels:       <none>
Annotations:  <none>

Data
====
densuke:
----
SATO Daisuke
hoge:
----
FUGA hoge

BinaryData
====

Events:  <none>
```

## どのように使うか(その1)

今までポッドの中で書いていた固有値を外部に追い出すというのが真っ先に出てくる話です。
たとえばMariaDBなど、環境変数の値を固定的に書いていた部分を追い出すという目的がわかりやすいでしょう。

```{literalinclude} codes/pod-username.yml
:caption: 環境変数設定時にcmを参照する例
:emphasize-lines: 19-24
```

このマニフェスト適用後、マニフェスト内で定義した変数`DATA`を参照すると、出力されます。

```{code-block} pwsh
PS> ubectl apply -f pod-username.yml(のパス)
# ポッド起動まで待つ
PS> kubectl exec -it pod/name -- sh
/ # echo $DATA # マニフェスト内で指定した変数を展開
FUGA hoge      # cm定義の値が出る
/ # exit
```

この仕組みを知るだけで、マニフェストに記載していた環境固有値を追い出せる可能性があることがわかると思います…

## どのように使うか(その2)

事前に登録することで、ファイルやディレクトリ(の中身)をまるまる持ち込むことも可能です。
ボリュームマウントの際のプロバイダとして指定することになります。

1. ディレクトリ `ex` を作成します
2. ファイル `ex/file1.txt` および `ex/file2.txt` を作成します。

```{literalinclude} codes/ex/file1.txt
:caption: ex/file1.txt
```

```{literalinclude} codes/ex/file2.txt
:caption: ex/file2.txt
```

これらをkubectlを使って登録します。

```{code-block} pwsh
# 単独ファイル
PS> kubectl create configmap file1 --from=file=ex/file1.txt
# ディレクトリを指定すると再帰的に改修される
PS> kubectl create configmap config --from=file=ex
```

```{warning}
`--from-file`に渡しているのは例では相対パスになっていますが、別に絶対パスでもかまいません。
vscodeであればエクスプローラー部などからドロップしてあげればいいでしょう。
```

こうやって作ったファイルを、ボリューム指定時に渡すことができます。

```{literalinclude} codes/pod-mountcm.yml
:caption: ボリュームとしてマウントする例
:emphasize-lines: 18-29
```

ボリュームプロバイダーとして、`configMap`を使うことで、登録した名前で指定できます。
ファイルとを渡したコンフィグマップからは該当するファイルだけが、ディレクトリを渡したときは、中にあるディレクトリ構造が再現されます。

```{warning}
マウントする際、全てroot所有でマウントしてしまいます。
アプリケーションによっては、特定ユーザーの所有物にするなどの対応が必要かもしれませんので、起動時の初期化処理などで対応する必要があるかもしれません。
```

## どう記憶されているの?
コンフィグマップのデータは`kubectl`から直接作成されていますが、マニフェスト的にはどうなのでしょう?
作成したリソースからマニフェストを取得してみましょう。

```{code-block} pwsh
:caption: マニフェストをリソースから生成(YAML形式)
:emphasize-line: 3-5

PS> kubectl get cm file1 -o yaml
apiVersion: v1
data:
  file1.txt: |
    Hello, World!
kind: ConfigMap
metadata:
  creationTimestamp: "2022-10-31T20:56:22Z"
  name: file1
  namespace: default
  resourceVersion: "2439"
  uid: 2e6015d8-e363-4ede-a2d7-5f32496f7658
```

`data`セクションにファイル名と"|"演算子を使った複数行テキスト私が使われているだけです。
複数ファイルを格納した場合だと、こうなっています。

```{code-block} pwsh
:caption: 複数ファイルを喰わせたとき
:emphasize-lines: 3-7

kubectl get cm config -o yaml
apiVersion: v1
data:
  file1.txt: |
    Hello, World!
  file2.txt: |
    Good morning!
kind: ConfigMap
metadata:
  creationTimestamp: "2022-10-31T21:04:28Z"
  name: config
  namespace: default
  resourceVersion: "2791"
  uid: 1630d92c-145e-4086-9644-624a62503bc8
```

つまり、`data`セクションにあるキーをファイル名、値を内容としてボリューム化しているだけだったりします。

````{tip}
なお、サブディレクトリの構造までは取り込まないようなので注意してください。

```
- ex
   + file1 # 取り込まれる
   + file2 # 取り込まれる
   + ex2
      + file3 # 取り込まれない
```
````

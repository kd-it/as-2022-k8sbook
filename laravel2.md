```{caution}
未チェックの項目のあるドラフト版です
```

# Laravel環境の設定を切り離そう

先ほど作ったLaravel環境のマニフェストは、このままだとセキュアな情報を内包したままで出しております。
この部分をできるだけ隠すことができないでしょうか。

## secretの利用でDBの情報を隠す

まず、データベース部分のマニフェストを見てみましょう。

```{literalinclude} codes/laravel-manifests/backend.yml
:caption: バックエンドのマニフェストより抜粋
:language: yaml
:lines: 19-34
:emphasize-lines: 5-13
```

この部分は、DB名やアカウント情報は秘匿しておいた方が良いでしょう。
対応するsecretを作成してみましょうか。

### secretリソースの作成

ということで早速作ってみます。

```{literalinclude} codes/laravel-manifests2/db-secret.yml
:caption: バックエンド向けのsecret例
:language: yaml
```

各変数の値は**Base64エンコードで保持しておく**ことを忘れないでください(マニフェストが通らなくなります)。


### マウントしての利用

これらに対応するように、参照側をざっと書き改めてみます。
環境変数に値がでないように、ファイルに出す形式にしてみましょう。

```{literalinclude} codes/laravel-manifests2/backend.yml
:caption: バックエンドのsecret対応版
:language: yaml
:emphasize-lines: 23-31,35-36,53-55
```

ポイントは3カ所ありました。

- secretもボリュームとして設定でき、キー名のファイルが作られます
- ボリュームとして宣言したものを適当な場所にマウントさせます
- (mariadb)各環境変数名に`_FILE`を付与し、参照ファイルを指定します

これにより、パスワードなどの情報を秘匿した状態で利用可能となります。

## .envファイルの問題

Laravelでは、データベース接続の設定は、プロジェクトディレクトリ上にある `.env` ファイルによって処理されます。

- [laravel-sample-remastered/laravel/sample/.env](https://github.com/kd-it/laravel-sample-remastered/blob/main/laravel/sample/.env)

しかし、githubなど公開されうる場所でパスワード情報の入っているようなファイルを置くべきではありません。

この問題にどう対応するかはいろいろ考えられます。

- パスワード以外の情報による `.env` を再構成し、秘匿情報はsecretを用いて環境変数で渡す
- `.env`をconfigMapもしくはsecretで渡す

前者はMariaDB側で対応しているのに…感はあるのですが、実はsecret自体は存在しているので、`secretKeyRef`を用いることで同じ値を使い回せます(変更忘れを防げる)。
変数が見えても良いのであれば選択肢としては考慮にたると思います(この場合はもちろんmariadb側も`_FILE`使わないとなることでしょう)。

そこで今回は、後者からsecretで渡すことを考えてみたいと思います。

secretから渡す方法も、雑に2つ考えられます。

- `.env` ファイルをsecretから作られた疑似ファイルに対してシンボリックリンクを貼って対応する
- `.env` ファイルをsecretから作られた疑似ファイルをinitContainers内でコピーして対応する

後者はイメージを書き換えずに対応できるかと思うのですが、initContainersとcontainersによるコンテナが同じディレクトリを共有状態にしていないと効果を持たないため難しいと思われます(チャレンジしがいはありそうですが)。

今回は前者の方式でイメージを書き換えてから使ってみましょう。



### forkして準備する

各自で作業してもらいたいので、まずは元ソースをforkしていきましょう。

- [kd-it/laravel-sample-remastered](https://github.com/kd-it/laravel-sample-remastered)

そしてforkを呼び出します。

```{figure} images/laravel-sample-fork.png
フォークの呼び出し
```

フォーク後の名前は適当に選べますが、ここでは変えずにそのまま使うことにします(重複してなければOK)。

```{figure} images/laravel-sample-newfork.png
forkした後の名前設定
```

これで実行させれば、自分のリポジトリにforkを生成できるので、こちらから取得して書き換えていきます。

ということで、`git clone`をしてから次に進みましょう。

```{code-block} pwsh
:caption: コードの取得(USERNAMEは自分のアカウント名)

PS> git clone https://github.com/USERNAME/laravel-sample-remastered.git
```

### ファイルのコピーと削除

`.env`は、クローンしたディレクトリの`laravel/sample`にあります。LinuxやmacOS環境ではドットファイルのため隠れて見えません。

```{tip}
もちろん `ls -a` で確認できますよ。
```

ここでは、`dotenv`という名前で作ることにします。

```{code-block} pwsh
PS> kubectl create secret generic dotenv --from-file ./.env
secret/dotenv created
```

これで `.env` ファイルを取り込んだので削除可能ですが、マニフェストは消してしまうことが十分考えられます。
そのため、別の場所にコピーして削除し、リポジトリからファイルを削除しておきます。

```{warning}
パスワードの含まれるようなファイルのですので、取り扱いには十分注意しておきましょう。
```

削除したら、git側に認識させ、コミットして送信しておきましょう。

コマンドライン的にgitに認識させるとこのような感じになります。

```{code-block} pwsh
:caption: コマンドラインでのgitによる削除処理

PS> git status
On branch main
Your branch is up to date with 'origin/main'.

Changes not staged for commit:
  (use "git add/rm <file>..." to update what will be committed)
  (use "git restore <file>..." to discard changes in working directory)
	deleted:    .env

no changes added to commit (use "git add" and/or "git commit -a")

PS> git rm .env # 削除をリポジトリ内でも処理
rm 'laravel/sample/.env'

PS> git status
On branch main
Your branch is up to date with 'origin/main'.

Changes to be committed:
  (use "git restore --staged <file>..." to unstage)
	deleted:    .env

PS> git commit -m "[del] 環境変数ファイルの削除"
PS> git push
```

ところが実際に運用する際、 `.env` ファイルが無いと意味がありません、
さらにマウントするときに、secretから`.env`ファイルだけ切り出してここに置くのも難しいです。
そこで、 `secret`のマウントは別の場所(`/config`)にでも行うようにして、シンボリックリンクでごまかすことにしてみましょう。
このことを考慮すると、`Dockerfile`に若干手を入れることにもなります。

```{code-block} Dockerfile
:caption: Dockerfile内、シンボリックリンクを作る
:emphasize-lines: 10,11

FROM php
RUN --mount=type=tmpfs,destination=/var/cache/apt \
    --mount=type=tmpfs,destination=/var/lib/apt \
    apt-get update; \
    apt-get install -y git unzip
COPY --from=composer/composer /usr/bin/composer /usr/local/bin/composer
RUN docker-php-ext-install pdo_mysql pdo
WORKDIR /var/www/html
COPY sample /var/www/html
# secretをマウントして、そこにある.envをsymlinkで見せる
RUN ln -sf /config/.env /var/www/html/.env
RUN composer install
```

### イメージの再生成

あとはイメージを再生成して、Docker Hubの自分のリポジトリに上げておけば使えるでしょう。

```{code-block} pwsh
PS> cd ..
# イメージ名は自分でアクセスできれば特に問いませんが、
# タグをきちんと設定した方が良いでしょう(ここではv1)
PS> docker build -t USERNAME/larabel-sample-remastered:v1 .
PS> docker push USERNAME/larabel-sample-remastered:v1
```

### secretをマウントしよう

あとはマニフェストの側で、イメージが利用する場所にsecretをマウントするだけです。

```{literalinclude} codes/laravel-manifests2/frontend.yml
:caption: フロントエンド側
:emphasize-lines: 15-18,34-36,44-46
```

```{caution}
イメージ名(2カ所)をきちんと差し替え版イメージのリポジトリ名にしてください。
```

このマニフェストはbackendを参照するので、呼び出す前に必ずserviceリソース(svc-backend)も適用させてください。

```{code-block} pwsh
PS> kubectl apply -f svc-backend.yml  # service/backend(ClusterIP)
PS> kubectl apply -f frontend.yml
PS> kubectl apply -f svc-frontend.yml # service/frontend(NodePort)
...
PS> minikube service frontend # open browser
```


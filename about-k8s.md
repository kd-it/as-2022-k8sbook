# Kubernetesとは?

では、いよいよKubernetes(K8s)について少しだけ学んでおきましょう。

## K8sは、コンテナ環境の管理・運用を行う基盤です

* 前期(AS構築Ⅰ)ではDockerによるコンテナの利用・イメージの作成について学びました
    * 適宜作ること、起動して走らせることはできるはずです
    * 複数のコンテナを連携させるための手段として、docker composeも体験しています
* 実際の運用環境では、dockerコマンドを使ってコンテナを起動させることはほとんど行いません(管理目的はともかく)
* コンテナは「落ちる」可能性があります
    * プログラム自体のバグ混入
    * 想定外の入力による停止
    * リソース不足に伴う異常終了
    * その他諸々
* 運用中にコンテナが落ちるとサービス提供ができなくなります
* 幸いなことに、コンテナは破棄して作り直すと(イメージが同じなら)基本的に最初の状態に戻って同様に動きます
    * 再度バグがつつかれたら同様に落ちることになりますが…
* なので、どのイメージでどういうコンテナを生成し、そのコンテナの死活状態を監視し、落ちてたらその分を復旧(再生成)することで運用の継続を狙う戦略を採ります

## K8sは、コンテナ周辺のリソースの管理を行います

* コンテナが動く環境は単一である保証はありません
    * 複数のノード(≒コンテナエンジンおよびそれが動くホスト)で構成されることもう普通にあります
* ノード間を突き抜けるようなネットワークを用意し、各コンテナがノードを跨いでも連携できるようにする必要もあるかもしれません
* コンテナが状態を保持するためにストレージを必要とするかもしれません
    * ノード内外のストレージに対する経路を用意し、マウントさせる必要もあるでしょう
* セキュアな情報(DBログイン情報や証明書など)を持たないといけないこともあります
    * 各コンテナが同じように取得・利用できないといけません

こういったことを管理するためにK8sのシステムがクラスターと構成するノード群の情報を常に保有し、分配・利用していく処理を行います。

## K8sは疎結合なネットワークで構成されています

* 各種の処理リクエストとしてのapiserver
* 各機能の窓口として存在するcontroller

これらが内部的にはWeb API(httpリクエスト)により連携する仕組みとなっており、その実装はあくまでAPIが仕様通りに機能すればどんなものでもかまいません。

この辺りをもう少しがっつり見ておきたい方は、オープンソースカンファレンスで定期的に説明のある動画を眺めておくと良いかもしれません。

* [いまさら聞けない人のためのK8s超入門](https://youtu.be/kr6WdgZK5yY)

<iframe width="560" height="315" src="https://www.youtube.com/embed/kr6WdgZK5yY" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

このような仕組みから、各メーカー(プロバイダー)がK8sとして使える製品をリリースしています(開発用も含めて)。

* Docker DesktopのK8s機能(利用状況により有料)
* minikube(minikube自体は無料)
* microk8s(無料)
* k3s(k3os、無料)
* rancher desktop(k3sを走らせる仮想マシン環境をGUIベースで構築できる、無料)
* kind(K8s in Docker、無料)
* kubeadmで自力構成(無料))
* AWS EKS(有料)
* Google GKE(有料)
* Azure AKS(有料)

※ 外部に公開できるサービスを構成するときは、自前で構成するよりもEKS/GKE/AKSを使う方が手間を考慮した場合割安になると思われます。

K8sの仕様はオープンなかたちで策定されています。

* [Kubernetes](https://kubernetes.io/)
* [CNCF](https://cncf.io)
    * クラウドでのサービス構築に関する議論を行うところ、K8sは[ここで育って独立している](https://www.cncf.io/projects/)
    * K8sのバックエンドで使われることもあるetcdやCoreDNSもここから卒業しています

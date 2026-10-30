---
name: modelop
description: "ローカルのmodelop互換ラッパー、またはSD-Linker CLIでモデルファイルのシンボリックリンクを同期・解除する。"
---

# modelop

Stable Diffusion のモデルファイルをコピーせず、シンボリックリンクとして登録・解除する作業を支援する。実行前に、指定された設定全体が対象になることを確認する。

## 使用するコマンドと互換性

ローカルの互換ラッパーの基本形:

```bash
modelop sync "$HOME/ckptconf/config/anima.yaml"
modelop clear "$HOME/ckptconf/config/anima.yaml"
```

ラッパーの構文は `modelop {sync|clear|edit} CONFIG_FILE`。現在のラッパーは設定ファイルを標準入力で渡して `php /home/iigau/linker/bin/sync` または `clear` を起動する。第3引数は `edit` 用のエディターに使われ、`sync` / `clear` には追加引数を転送しない。そのため、ラッパー経由で `--help`、`--dry-run`、`--category` を使えるとは限らない。

SD-Linker の CLI オプションを使う場合は、ラッパーを介さずプログラムを直接呼び出す。例:

```bash
php "$HOME/linker/bin/sync" --help
php "$HOME/linker/bin/sync" --dry-run --category loras < "$HOME/ckptconf/config/anima.yaml"
php "$HOME/linker/bin/clear" --category loras --category vae < "$HOME/ckptconf/config/anima.yaml"
```

実際のインストール先が異なる場合はそのパスを使う。利用可能なオプションは `php bin/sync --help` または `php bin/clear --help` で確認する。設定ファイルはコマンドの標準入力に渡す。

## 設定の読み方

`lookupKeys` が処理するカテゴリを選ぶ。各カテゴリの `destination` がリンク先ディレクトリ、`source` の各グループの `baseDirectory` と `weightsList` がリンク元を指定する。パスとファイル名は YAML のリテラル値であり、`~`、環境変数、glob は展開されない。リンク名は設定に書いたファイル名と同じで、別名指定には対応しない。

`lookupKeys` は必須で、空でないカテゴリ一覧にする。各選択カテゴリは `source` と `destination` の両方に必要。Symfony YAML は標準的なインデントなしのシーケンスも読める。`destination` と `baseDirectory` は絶対パスにする。`weightsList` と `ignoreList` は、それぞれリンク元・宛先配下の相対リテラルパスで、`..` を含む値は拒否される。

有効なグループの例:

```yaml
- meta:
    enabled: true
  baseDirectory: /absolute/path/to/models
  weightsList:
    - example-step00000600.safetensors
  ignoreList: []
```

- 有効グループの `weightsList` は `sync` の作成・維持対象。
- 有効グループの `ignoreList` と無効グループの `weightsList` は `sync` の解除対象。
- `clear` は有効・無効を問わず、選択カテゴリに設定された `weightsList` と `ignoreList` を調べる。
- `ignoreList` は省略可能で、省略時は空として扱われる。
- glob、リンクの別名、設定から消えたリンクの自動 prune はない。恒久的に無効化する場合は、対象を削除候補にする設定を保ったまま `sync` する。設定から行を消すだけでは、そのリンクは解除対象として検出されない。
- `--category` は `lookupKeys` のカテゴリをさらに絞る指定で、繰り返し指定できる。範囲外のカテゴリ名はエラー。

## 安全な実行手順

1. 使用設定と `modelop` / linker CLI の所在を確認する。依頼が設定更新を含む場合は、対話型の `modelop edit` を起動せず、エージェントのファイル編集機能で指定されたモデルだけ編集する。既存の並び・コメント・無関係なモデルを保ち、YAML と差分を確認する。
2. 設定の全選択対象について、元ファイル、宛先、作成・解除されるパスを確認する。カテゴリ指定がなければ `lookupKeys` 全体が対象。複数のリンク元が同じ宛先になる設定は、実行前に解消する。
3. 宛先を `lstat` / `test -L` などで確認する。通常ファイルやディレクトリがあれば実行を止めて報告する。既存シンボリックリンクは `readlink` で調べる。
4. 明示された操作を実行する。実行前に慣習的に `clear` を挟まない。プレビューが必要なら CLI を直接 `--dry-run` 付きで起動する。互換ラッパーが追加引数を転送すると思い込まない。既に依頼されたリンク作成・解除に、重ねて承認を求めない。sandbox 外のリンク先や管理 DB への書き込みは所定の昇格実行を使い、制限を手動の `ln` / `rm` で回避しない。
5. 終了コードと結果報告を確認し、実リンクを検査する。`sync` では有効項目が設定先を指し、解除対象が消えたことを確認する。`clear` では通常の `file_exists()` だけでなく、`is_link()` / `test -L` で dangling link も確認する。元モデルが残っていることも確認する。
6. 失敗や未実行項目があれば成功扱いせず、完了済み・失敗・未実行の対象と現在のリンク状態を報告する。

## SD-Linker の管理・エラー仕様

- 管理 DB は linker リポジトリの `data/links.sqlite`。通常の初回操作で必要に応じて作成される。DB を直接編集しない。
- 通常ファイル・ディレクトリは変更・削除しない。
- DB 未登録のシンボリックリンクは、リンクの参照先が `sync` で設定された期待元と一致する場合にだけ管理対象として登録する。不一致なら衝突として拒否する。`clear` は未管理リンクを削除しない。
- 管理リンクの実際のリンク先が DB 記録と異なる場合は、設定と一致していても手動変更として拒否する。リンクが記録どおりで設定の元だけ変わった場合は、`sync` が設定先へ付け替える。
- dangling link も通常の欠落パスと区別して検査する。管理 DB と設定から管理対象と確認できる場合だけ修復・解除する。
- 選択操作を事前検証し、事前検証エラーではリンク・管理 DB のどちらも変更しない。dry-run もリンク・DB を変更しない。
- 実行中に失敗したら後続処理を止め、完了済み・失敗・未実行を報告して非ゼロで終了する。複数操作全体の巻き戻しは保証されない。再実行前に実体と DB 状態を確認する。

## 結果の確認

出力だけで判断せず、対象リンクのリンク先と元ファイルの保持を確認する。通常ファイルが消えた、dangling link が残った、完了・未実行の報告と実体が一致しない、または同じ失敗が繰り返される場合は追加操作をせず、影響対象と状態を報告する。

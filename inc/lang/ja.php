<?php
/**
 * freedns.my - 日本語の用語集（第一弾・主要キーのみ）。
 *
 * 未訳のキーは自動的に英語へフォールバックします（詳細は 多言語-拡張方案.md を参照）。
 * 用語の目安：ドメイン／メール／サーバー／レジストリ／アカウント／サポート。
 */

return [
    'request.heading' => 'ドメインの申請',
    'request.submit'  => '申請を送信',
    'request.name'    => '希望するドメイン',
    'request.policy_link' => '登録ポリシー',
    'status.heading' => '申請状況の確認',
    'status.pending_text' => '申請は人手による審査待ちです。通常は数日で処理され、あなたの操作は不要です。',
    'nav.home'            => 'ホーム',
    'nav.request'         => 'ドメイン申請',
    'nav.status'          => '申請状況',
    'nav.manage'          => 'レコード管理',
    'nav.domains'         => 'ドメイン一覧',
    'nav.doc'             => 'ポリシーとドキュメント',
    'nav.admin'           => '管理画面',
    'nav.lang_en'         => 'English',
    'nav.lang_zh'         => '中文',
    'nav.lang_title'      => '言語',
    'nav.signin'          => 'ログイン',
    'nav.register'        => '新規登録',
    'nav.signout'         => 'ログアウト',
    'nav.console'         => 'コンソール（%s）',
    'nav.theme_title'     => 'テーマ',
    'nav.theme_light'     => 'ライト',
    'nav.theme_dark'      => 'ダーク',
    'nav.theme_auto'      => 'システムに合わせる',
    'nav.whois'           => 'WHOIS 照会',
    'nav.market'          => 'ドメイン取引',
    'nav.shop'            => 'クォータショップ',
    'nav.chat'            => 'オンライン相談',
    'nav.changelog'       => '更新履歴',
    'footer.tagline'      => '%s &mdash; 無料ドメインレジストリ。',
    'footer.contact'      => '連絡先：',
    'footer.generated'    => 'ページ生成時刻 %s（%s）。',
    'error.list_title'    => '次の問題を修正してください：',
    'error.token_title'   => 'フォームの有効期限が切れています',
    'error.token_body'    => 'フォームの有効期限が切れたか、セキュリティトークンが一致しません。戻ってページを'
        . '再読み込みし、もう一度送信してください。',
    'status.active'       => '有効',
    'status.pending'      => '審査待ち',
    'status.rejected'     => '却下',
    'status.suspended'    => '停止中',
    'status.deleted'      => '削除済み',
    'common.optional'     => '（任意）',
    'common.none'         => '-',
    'index.heading'        => '無料ドメイン',
    'index.intro1'         => '<strong>%s</strong> へようこそ：<code>%s</code> のドメインを無料で提供しています。'
        . '登録は完全に無料で、更新料もかかりません。ページに広告を挿入することもありません。',
    'index.intro_records'  => '当レジストリがこれらのドメインの DNS を管理します：承認されたドメインには、当方が'
        . '保持する <code>A</code>、<code>AAAA</code>、<code>CNAME</code>、<code>TXT</code> レコードが公開されます。',
    'index.intro_manage'   => 'ドメインが有効になったら、%s ページでレコードの変更や <code>www.myname</code> の'
        . 'ようなサブドメインの追加ができます。変更は管理者の承認後に公開されます。',
    'index.intro_psl'      => '上流が認めているサフィックス（%s に掲載されている us.ci、de5.net など）では、'
        . 'コンソールからご自身のネームサーバーを指定できます —— 即時反映、審査不要です。',
    'index.announcement'   => 'お知らせ',
    'index.group_heading'  => 'コミュニティに参加する',
    'index.group_text'     => '質問や提案、ほかの利用者との情報交換は、Telegram グループ %s へどうぞ。',
    'index.zones'          => '申請できるサフィックス',
    'index.no_zone'        => 'サフィックスがまだ設定されていません。管理者が <em>管理画面 → Zones</em> で'
        . '追加する必要があります。',
    'index.col_zone'       => 'サフィックス',
    'index.col_requests'   => '申請',
    'index.col_active'     => '有効',
    'index.col_notes'      => '説明',
    'index.open'           => '受付中',
    'index.closed'         => '受付停止',
    'index.status_h'       => 'レジストリの状況',
    'index.form'           => '申請フォーム',
    'index.form_open'      => '<span class="ok">受付中</span> &mdash; 申請を受け付けています',
    'index.form_closed'    => '<span class="err">受付停止</span> &mdash; 現在は申請を受け付けていません',
    'index.active_names'   => '有効なドメイン',
    'index.awaiting'       => '審査待ちの申請',
    'index.review_time'    => '審査時間',
    'index.review_time_v'  => '通常は数日以内、人手による審査',
    'index.price'          => '費用',
    'index.price_v'        => '無料、更新も無料',
    'index.howto'          => 'ドメインの申請方法',
    'index.step1'          => 'どのサフィックスを使うか、そのドメインをどのアドレスやホスト名に向けるかを決めます。',
    'index.step2'          => '%s を入力します：サフィックスを選び、ドメイン名を記入し、少なくとも 1 件の'
        . 'レコード（<code>A</code>、<code>AAAA</code>、<code>CNAME</code> のいずれか）を入力します。',
    'index.step3'          => '送信後に表示される申請番号を控えてください。進捗の確認とレコード管理に必要な'
        . '唯一の手がかりです。',
    'index.step4'          => '人手で審査します。承認されるとレコードが該当 zone に登録され、その後はサブドメインや'
        . 'TXT レコードも追加できます。',
    'index.step5'          => 'その後は %s でアドレスの変更やサブドメインの追加、%s で現在の状況の確認ができます。',
    'index.documents'      => 'ドキュメント',
    'index.documents_text' => '申請の前に %s をお読みください。ポリシーに合わない申請は却下されます。',
    'index.contact'        => '連絡先',
    'index.contact_q'      => '個別の申請についての質問：%s。メールには申請番号を明記してください。',
    'index.contact_none'   => '申請フォームまたは照会ページをご利用ください。電話サポートは提供していません。',
    'index.small_print'    => '本サービスはボランティアが可能な範囲で運営しており、サービスレベルの保証は'
        . 'ありません。迷惑行為、スパム、フィッシングに使われたドメインは回収されます。',
    'index.docs_link'      => '登録ポリシーとドキュメント',
    'index.manage_link'    => 'レコード管理',
    'index.psl_link'       => 'Public Suffix List（公共サフィックスリスト）',
    'request.intro'          => '下のフォームでドメインを申請してください。<strong>*</strong> は必須です。'
        . '申請は人手で審査されます。先に %s をお読みください。',
    'request.closed'         => '申請フォームは現在閉じています。',
    'request.no_zone'        => 'サフィックスがまだ設定されていません。管理者が <em>管理画面 → Zones</em> で'
        . '追加するまで申請を受け付けられません。',
    'request.s1'             => '1. サフィックスの選択',
    'request.s2'             => '2. 希望するドメイン',
    'request.s3'             => '3. あなたについて',
    'request.s4'             => '4. レコード',
    'request.s5'             => '5. 用途',
    'request.s6'             => '6. ポリシー',
    'request.zone_closed'    => '（現在は新規申請を受け付けていません）',
    'request.label'          => 'ドメイン *',
    'request.label_hint'     => 'ラベル部分だけを入力します（例：<code>myname</code>）。完全なドメインは '
        . '<code>myname.%s</code> になります。<code>www.myname</code> のようなサブドメインは、ドメインが'
        . '有効になってから追加できます。',
    'request.reserved_toggle' => 'これは予約ドメインの申請です',
    'request.reserved_hint'  => 'レジストリがそのドメインをあなたのために確保している場合のみチェックしてください。'
        . '予約ドメインは 1 文字でもかまいません。ドメインがレジストリの予約リストに入っている必要があり、'
        . 'チェックすると第 5 節の用途が必須になります。',
    'request.proxied_toggle' => 'Cloudflare プロキシ経由で公開する（オレンジ雲）',
    'request.proxied_hint'   => 'チェックしない場合は DNS only（名前解決のみ）で、これが通常の運用です。'
        . 'チェックすると A、AAAA、CNAME の通信が Cloudflare を経由します（TXT は常に非プロキシ）。'
        . '実際のアドレスを隠せますが、一部の国・地域では遅くなることがあります。送信後この設定は'
        . 'レジストリのみが変更できます。',
    'request.check_heading'  => 'このドメインはまだ申請できますか？',
    'request.check_hint'     => 'レジストリは申請を受け付ける前に、%s の DNS ゾーンでその名前が使われていないか'
        . '確認します。下のボタンで結果をすぐに確認できます。この操作では申請は送信されません。',
    'request.check_submit'   => '先に DNS レコードを確認',
    'request.precheck_ok'    => 'DNS サービス側に %s のレコードはまだありません。申請できます。',
    'request.name_hint'      => '<code>@</code> は申請するドメインそのものを表します。ここでサブドメインは'
        . '申請できません。値は IPv4 アドレス、IPv6 アドレス、またはホスト名です。',
    'request.col_name'       => '名前',
    'request.col_type'       => '種類',
    'request.col_value'      => '値',
    'request.name_placeholder'  => '@',
    'request.value_placeholder' => 'IPv4 アドレス、IPv6 アドレス、またはホスト名',
    'request.purpose'        => '用途の概要（任意）',
    'request.purpose_hint'   => '1〜2 文で結構です。審査の参考にしますが公開されません。',
    'request.purpose_reserved_hint' => '予約ドメインの申請では必須です。',
    'request.policy_accept'  => '%s を読み、同意します *',
    'request.done_title'     => '申請を受け付けました。',
    'request.done_keep'      => '下の申請番号を控えてください。進捗の確認とレコード管理に必要な唯一の手がかりです。',
    'request.done_code'      => '申請番号：',
    'request.done_domain'    => 'ドメイン：',
    'request.done_zone'      => 'サフィックス：',
    'request.done_status'    => '状態：',
    'request.done_submitted' => '送信時刻：',
    'request.done_records'   => '今回申請したレコード &mdash; ドメインは次のとおり解決されます：',
    'request.done_review'    => '管理者が人手で審査します。決定すると状態が <code>ACTIVE</code>（有効）または '
        . '<code>REJECTED</code>（却下）になり、上記のレコードが <code>%s</code> zone に登録されます。',
    'request.done_mail'      => 'メールが届く場合は %s にも通知します。',
    'request.done_manage'    => '承認後は、この申請番号とメールアドレスで %s ページからレコードの変更、'
        . 'サブドメインや <code>TXT</code> レコードの追加ができます。',
    'request.done_check'     => 'いつでも %s で現在の状態を確認できます。',
    'request.back_home'      => 'ホームへ戻る',
    'status.intro'           => '送信後に表示された申請番号と、そのとき入力したメールアドレスを入力してください。'
        . '現在の状態と保存されているレコードを表示します。',
    'status.code'            => '申請番号 *',
    'status.code_hint'       => '例：<code>FD-25-1A2B3C4D</code>',
    'status.email'           => '申請時に使ったメールアドレス *',
    'status.show'            => '状況を確認',
    'status.status'          => '状態',
    'status.zone'            => 'サフィックス',
    'status.applicant'       => '申請者',
    'status.org'             => '組織',
    'status.submitted'       => '送信時刻',
    'status.updated'         => '最終更新',
    'status.decided'         => '審査時刻',
    'status.records'         => 'レコード数',
    'status.published'       => 'このドメインでは次のレコードが公開されています：',
    'status.kept'            => 'この申請では次のレコードが保存されています：',
    'status.no_record'       => '；レコードはありません',
    'status.change_title'    => '審査待ちの変更があります',
    'status.change_text'     => '%s でレコード変更を送信しましたが、まだ管理者の審査待ちです。承認されるまで、'
        . '上記のレコードが有効です。',
    'status.proposed'        => '提案されたレコード：',
    'status.note'            => 'レジストリからのメモ',
    'status.active_text'     => '上記のレコードは <code>%s</code> zone で有効です。サービスを送信したアドレス'
        . 'またはホスト名に向けてください。%s でレコードの変更やサブドメインの追加ができます。変更は審査後に'
        . '公開されます。上流が認めているサフィックスでは、コンソールの「ネームサーバー」でご自身の'
        . 'ネームサーバーに切り替えられます（即時反映・審査不要）。',
    'status.rejected_text'   => '申請は承認されませんでした。修正して同じドメインを再申請できます。先に %s を'
        . 'お読みください。',
    'manage.heading'         => 'レコード管理',
    'manage.notice_heading'  => '重要なお知らせ',
    'manage.closed_title'    => 'このページは現在閉じています',
    'manage.closed_text'     => '現在、レコード変更の送信はログイン済みアカウントのみ受け付けています。申請時に'
        . '使ったメールアドレスで（または新規登録して）コンソールにログインしてください。ログイン後はこのページを'
        . '通常どおり利用できます。',
    'manage.closed_login'    => 'コンソールにログイン',
    'manage.closed_register' => 'アカウント登録',
    'manage.intro'           => '申請番号と申請時のメールアドレスを入力すると、そのドメインのレコード変更や '
        . '<code>www.myname</code> のようなサブドメインの追加ができます。審査待ちの申請では変更はすぐ保存されます'
        . '（この時点ではまだ何も公開されていません）。ドメインが<strong>有効</strong>になったあとは、変更のたびに'
        . '審査キューに入り、管理者の承認後に公開されます。',
    'manage.code'            => '申請番号 *',
    'manage.code_hint'       => 'ドメイン申請時に届いた番号です（例：<code>FD-25-1A2B3C4D</code>）。番号を忘れた'
        . '場合はフッターのメールアドレスまでご連絡ください。',
    'manage.email'           => '申請時に使ったメールアドレス *',
    'manage.open'            => 'レコード管理を開く',
    'manage.can_do'          => 'ここでできること',
    'manage.can_1'           => '審査待ちの申請のレコードを修正する（すぐ保存）；',
    'manage.can_2'           => '有効なドメインのレコードを変更する（審査キューに入ります）；',
    'manage.can_3'           => 'サブドメインを追加する（例：<code>www.myname</code>、<code>mail.myname</code>、'
        . '<code>shop.myname</code>）；',
    'manage.can_4'           => 'CNAME と TXT レコードを追加する；',
    'manage.can_5'           => '不要になったレコードを削除する；',
    'manage.can_6'           => '審査待ちの変更を撤回する。',
    'manage.hint_review'     => '有効なドメインの変更は人手の審査後に公開されます。',
    'manage.hint_disabled'   => ' レジストリは現在、セルフサービスでの変更を停止しています。',
    'manage.submitted_title' => '変更を送信しました。審査待ちです。',
    'manage.name_label'      => 'ドメイン：',
    'manage.code_label'      => '申請番号：',
    'manage.submitted_label' => '送信時刻：',
    'manage.proposed'        => '提案されたレコード：',
    'manage.what_changes'    => '変更内容',
    'manage.col_change'      => '種類',
    'manage.col_records'     => 'レコード',
    'manage.added'           => '追加',
    'manage.removed'         => '削除',
    'manage.kept'            => '変更なし',
    'manage.kept_n'          => '%d 件のレコードは変更なし',
    'manage.stay_live'       => '変更が承認されるまで、現在有効なレコードはそのままです。待っている間にご自身で'
        . '撤回できます：',
    'manage.withdraw'        => 'この変更を撤回',
    'manage.withdraw_queued' => 'キューの変更を撤回',
    'manage.check_status'    => '状況を確認',
    'manage.manage_again'    => 'もう一度レコードを管理',
    'manage.queued_notice'   => '%s で送信した変更はまだ審査待ちです。下のフォームはその提案を起点にしています。'
        . 'もう一度送信するとキューの変更が置き換わります。',
    'manage.records_live'    => '現在有効なレコード',
    'manage.records_on_file' => '保存されているレコード',
    'manage.disabled'        => 'レジストリはセルフサービスでのレコード変更を停止しています。フッターの'
        . 'メールアドレスに申請番号を添えてご連絡ください。',
    'manage.not_editable'    => 'レコードを変更できるのは、審査待ちの申請または <code>ACTIVE</code>（有効）な'
        . 'ドメインだけです。現在の状態：%s。',
    'manage.pending_notice'  => '申請はまだ審査待ちで、これらのレコードは公開されていません。ここでの変更はすぐ'
        . '保存され、審査時に確認されます。ドメインが有効になったあとは、変更のたびに審査キューに入ります。',
    'manage.active_notice'   => '送信するとレコード一式が審査キューに入ります。管理者が承認するまで何も公開されず、'
        . '現在有効なレコードはそれまで変わりません。',
    'manage.records_of'      => '%s のレコード',
    'manage.rows_hint'       => '頂点は <code>@</code>、サブドメインはラベル 1 つ（<code>www</code>、'
        . '<code>mail</code>、<code>shop</code> など）を入力します。<em>削除</em> にチェックするとそのレコードを'
        . '消せます。頂点には少なくとも 1 件の <code>A</code>、<code>AAAA</code>、<code>CNAME</code> レコードが'
        . '必要です。',
    'manage.col_name'        => '名前',
    'manage.col_type'        => '種類',
    'manage.col_value'       => '値',
    'manage.col_remove'      => '削除',
    'manage.col_ttl'         => 'TTL',
    'manage.col_proxy'       => 'プロキシ',
    'manage.apex'            => '@',
    'manage.add_records'     => 'レコードを追加',
    'manage.name_placeholder'   => '@ または www',
    'manage.value_placeholder'  => 'IPv4/IPv6 アドレス、ホスト名、またはテキスト',
    'manage.limits'          => '使用できる種類：<code>A</code>、<code>AAAA</code>（公開 IPv4/IPv6 アドレス、'
        . '同じ名前で最大 4 件）、<code>CNAME</code>（ホスト名で、その名前で唯一のレコードでなければなりません）、'
        . '<code>TXT</code>（最大 255 文字）。1 ドメインあたり最大 %d 件。',
    'manage.note_label'      => '審査担当者へのメモ（任意）',
    'manage.submit'          => '審査に送信',
    'rec.row_empty'        => '%d 行目：%s レコードの値が空です。このレコードを削除する場合は「削除」に'
        . 'チェックしてください。',
    'rec.new_row_empty'    => '%d 行目を追加：%s レコードの値を入力してください。',
    'rec.name_invalid'     => '名前部分 "%s" が正しくありません：頂点は <code>@</code>、サブドメインは英数字と'
        . 'ハイフンだけのラベルを入力してください。',
    'rec.type_unsupported' => 'レコード種別 "%s" には対応していません：A、AAAA、CNAME、TXT、MX、SRV、CAA、PTR '
        . 'が使えます。',
    'rec.ttl_range'        => '%s 行目：TTL は 1（自動）または 60〜86400 秒でなければなりません。',
    'rec.ttl_hint'         => '単位は秒、60〜86400。1 は Cloudflare の自動 TTL（Auto）です。表示されている数値の'
        . 'ままにするとレジストリ既定値を使います。',
    'rec.ttl_auto'         => '自動',
    'val.srv_need_key'     => 'API キーとインスタンス ID の両方を入力してください。',
    'val.srv_bad_id'       => 'インスタンス ID が正しくないようです（英数字、ハイフン、アンダースコアのみ）。',
    'val.srv_taken'        => 'このインスタンスはすでに登録されています。',
    'val.srv_need_approval'=> 'サーバーを登録するには先に承認が必要です。下のフォームから申請してください。',
    'val.srv_key_refused'  => 'RainYun がこの API キーを受け付けませんでした：',
    'rec.type_not_here'    => 'ここでは %s レコードは申請できません。申請時に使える種類：%s。',
    'rec.txt_later'        => '申請時には TXT レコードを入力できません。ドメインが有効になってから追加できます。',
    'rec.subnames_later'   => '申請時にはサブドメイン %s を入力できません。ドメインが有効になってから管理ページで'
        . '追加してください。',
    'rec.a_invalid'        => 'A レコードの値は IPv4 アドレスでなければなりません（受信値 "%s"）。',
    'rec.aaaa_invalid'     => 'AAAA レコードの値は IPv6 アドレスでなければなりません（受信値 "%s"）。',
    'rec.not_public'       => 'アドレス %s は公開ルーティング可能なアドレスではありません。',
    'rec.cname_target'     => 'CNAME のターゲットはホスト名でなければなりません（例：example.net、受信値 "%s"）。',
    'rec.cname_self'       => 'CNAME を自分自身に向けることはできません（%s）。',
    'rec.txt_length'       => 'TXT の値は最大 255 文字です。',
    'rec.txt_control'      => 'TXT の値に制御文字や改行は使えません。',
    'rec.mx_format'   => 'MX レコードの書式は「10 mail.example.com」：優先度 + メールサーバーのアドレス（"%s"）。',
    'rec.mx_target'   => '"%s" は有効なメールサーバーのホスト名ではありません。',
    'rec.mx_self'     => 'メールサーバーをドメイン自身に向けることはできません（"%s"）。',
    'rec.srv_format'  => 'SRV レコードの書式は「10 5 443 target.example.com」：優先度、重み、ポート、'
        . 'ターゲット（"%s"）。',
    'rec.srv_numbers' => 'SRV レコードの最初の 3 つの値は 0〜65535 の数値でなければなりません。',
    'rec.srv_target'  => '"%s" は有効なターゲットのホスト名ではありません。',
    'rec.srv_self'    => 'SRV のターゲットをドメイン自身に向けることはできません（"%s"）。',
    'rec.caa_format'  => 'CAA レコードの書式は「0 issue "letsencrypt.org"」：flag、タグ（issue / issuewild / '
        . 'iodef）、値（"%s"）。',
    'rec.caa_flags'   => 'CAA レコードの flag は 0〜255 でなければなりません。',
    'rec.caa_target'  => '"%s" は有効な認証局のドメインではありません。',
    'rec.caa_iodef'   => 'iodef の値は http(s) アドレスまたは mailto: アドレスでなければなりません（"%s"）。',
    'rec.ptr_bad'     => '"%s" は有効なホスト名ではありません。',
    'rec.ptr_same'    => 'PTR レコードをドメイン自身に向けることはできません（"%s"）。',
    'rec.duplicate'        => 'レコード %s %s %s が重複しています。',
    'rec.cname_one_only'   => '名前 %s に CNAME レコードが %d 件になります。1 件だけ許可されます。',
    'rec.cname_exclusive'  => '名前 %s にはすでに CNAME レコードがあるため、他の種類のレコードは置けません。',
    'rec.need_apex_record' => '頂点には少なくとも 1 件の A、AAAA、CNAME レコードが必要です。',
    'rec.need_apex_cname'  => '頂点には CNAME レコードが必要です。',
    'rec.subnames_zone_off' => 'サフィックス %s ではサブドメインを提供していません。%s 自体のレコードだけが'
        . '使えます。',
    'rec.too_many'         => '1 ドメインあたり最大 %d 件のレコードです。現在の一式は %d 件です。',
    'change.withdrawn'        => '変更を撤回しました。',
    'change.nothing_pending'  => '現在、審査待ちの変更はありません。',
    'change.updated_pending'  => 'レコードを更新しました。申請はまだ審査待ちです。',
    'change.disabled'         => 'このページのレコード変更機能は停止しています。フッターのメールアドレスまで'
        . 'ご連絡ください。',
    'change.not_editable'     => 'レコードを変更できるのは、審査待ちの申請または <code>ACTIVE</code>（有効）な'
        . 'ドメインだけです。現在の状態：%s。',
    'change.save_error'       => 'サーバーエラーにより変更を保存できませんでした。数分後にもう一度お試しください。',
    'val.zone_choose'      => '下に表示されたサフィックスから 1 つ選んでください。',
    'val.zone_closed'      => 'サフィックス %s は現在新しい申請を受け付けていません。',
    'val.no_zone'          => 'サフィックスがまだ設定されておらず、申請を受け付けられません。',
    'val.form_closed'      => '申請フォームは現在閉じています。しばらくしてからお試しください。',
    'val.spam'             => '送信はスパムフィルタによりブロックされました。',
    'val.name_required'    => '登録したいドメインを入力してください。',
    'val.name_dots'        => 'ここではラベル 1 つだけ登録できます。"%s" のみ入力してください。'
        . '<code>www.%s</code> のようなサブドメインはドメインが有効になってから追加できます。',
    'val.name_length'      => 'ドメイン名は 2〜63 文字でなければなりません。',
    'val.name_length_reserved' => '予約ドメインは 1〜63 文字でかまいません。',
    'val.reserved_not_listed' => 'ドメイン "%s" はレジストリの予約リストにないため「予約ドメインの申請」は'
        . '使えません。チェックを外してください。',
    'val.reserved_use_channel' => 'ドメイン "%s" はレジストリが予約しています。申請するには「これは予約ドメイン'
        . 'です」（第 2 節）にチェックし、用途を記入してください。',
    'val.reserved_purpose' => '予約ドメインの申請には説明が必須です：第 5 節に用途を記入してください。',
    'val.reserved_no_table' => 'サーバーに予約ドメインのリストがまだありません。このオプションは使えません。'
        . 'レジストリにご連絡ください（データベースに upgrade-reserved.sql の導入が必要です）。',
    'val.cf_records_exist' => 'Cloudflare に "%s" のレコードがすでに存在します（%s 件）：%s。このドメインは'
        . '使用済みです。別の名前で申請してください。問題があると思われる場合はレジストリにご連絡ください。',
    'val.cf_check_failed'  => '現在 Cloudflare にこのドメインを照会できません（%s）。そのため今回の申請は'
        . '受け付けませんでした。数分後にもう一度お試しください。',
    'val.name_invalid'     => 'ドメイン名に使えるのは小文字 a-z、数字 0-9、ハイフンのみで、ハイフンで始まる・'
        . '終わることはできません。',
    'val.name_reserved'    => 'ドメイン "%s" はレジストリが予約しているため登録できません。',
    'val.applicant_required' => '氏名を入力してください。',
    'val.applicant_long'   => '氏名が長すぎます。',
    'val.email_required'   => 'メールアドレスを入力してください。',
    'val.email_invalid'    => 'メールアドレスの形式が正しくありません。',
    'val.org_long'         => '組織名が長すぎます。',
    'val.country_long'     => '国・地域の入力が長すぎます。',
    'val.purpose_long'     => '用途の説明が長すぎます（最大 2000 文字）。',
    'val.policy_required'  => '登録ポリシーへの同意が必要です。',
    'val.dup_domain'       => '%s は当レジストリですでに「%s」の状態です（申請番号 %s）。',
    'val.limit_email'      => 'このメールアドレスは %s で %d 件のドメイン（有効または審査待ち）を保有しており、'
        . '現在の上限に達しています。',
    'val.quota_exceeded'   => 'このメールアドレスは全サフィックス合計で最大 %s 件のドメインを保有できます'
        . '（現在 %s 件）。上限に達しました。却下または削除されたドメインは枠を解放します。さらに必要なら'
        . 'レジストリにご連絡ください。',
    'val.name_taken'       => 'ドメイン "%s" はこのサフィックスの DNS サービスで使用されているため発行できません。'
        . '別の名前をお試しください。',
    'val.limit_ip'         => 'この接続からの申請が本日は多すぎます。明日またお試しください。',
    'val.save_error'       => 'サーバーエラーにより申請を保存できませんでした。数分後にもう一度お試しください。',
    'val.lookup_missing'   => '申請番号とメールアドレスの両方を入力してください。',
    'val.lookup_none'      => '一致する申請がありません。申請番号とメールアドレスをご確認ください。番号は '
        . 'FD-25-1A2B3C4D の形式です。',
    'val.throttled'        => '試行回数が多すぎます。数分後にもう一度お試しください。',
    'val.totp_code'        => 'アプリに表示される 6 桁のコードを入力してください。',
    'val.srv_off'          => 'クラウドサーバー連携機能は現在停止しています。',
    'val.srv_no_crypto'    => '現在の PHP 環境では暗号化できません（AES-256-GCM がありません）。キーを安全に'
        . '保存できないため連携を拒否します。',
    'val.srv_need'         => 'API キーとインスタンス ID の両方を入力してください。',
    'val.srv_no_key'       => '保存されたキーを復号できません（別のサイトキーで暗号化された可能性があります）。'
        . 'このインスタンスを登録し直してください。',
    'val.srv_power_off'    => '起動 / 停止 / 再起動は現在停止しています。',
    'val.srv_reinstall_off' => 'この操作は停止しています：再インストール、バックアップの復元、バックアップの削除は'
        . 'データを失う可能性があるため、管理者が有効にする必要があります。',
    'val.srv_backup_off'   => 'バックアップ機能は現在停止しています。',
    'val.srv_fw_off'       => 'ファイアウォールの変更は現在停止しています。',
    'val.srv_vnc_off'      => 'VNC コンソールは現在停止しています。',
    'val.srv_vnc_none'     => 'サービス提供元がコンソールのアドレスを返しませんでした。',
    'val.mail_off'         => 'メール転送は現在停止しています。',
    'val.mail_zone'        => 'メール転送ができるのは Cloudflare が名前解決するサフィックスだけです'
        . '（DNSHE にはこの機能がありません）。',
    'val.mail_active'      => 'メールを受け取れるのは有効なドメインだけです。',
    'val.mail_delegated'   => 'このドメインは独自のネームサーバーを使っており、メールはそちらで処理されます。',
    'val.mail_local'       => '@ の前には英数字、ドット、ハイフン、アンダースコアのみ使えます。',
    'val.mail_loop'        => '同じドメインにメールを転送することはできません。',
    'val.mail_taken'       => 'このドメインにはすでにアドレス "%s" があります。',
    'val.mail_own'         => 'このドメインまたは転送はあなたのアカウントのものではありません。',
    'account.register_heading' => 'アカウント登録',
    'account.register_intro'  => 'アカウントを作成すると、名下のすべてのドメインが 1 か所にまとまります：状態の確認、'
        . 'レコードの変更、新しいドメインの申請ができ、申請番号を探し回る必要はありません。アカウントは登録'
        . 'メールアドレスでドメインと結び付き、同じメールアドレスで申請したドメインはすべてここに表示されます。',
    'account.register_submit' => 'アカウントを作成',
    'account.invite_code'     => '招待コード（任意）',
    'account.invite_code_hint' => '友人の招待で来た場合は、ここにその招待コードを入力できます。招待人数はその人の'
        . '実績に加算されます。入力せずに、その人の招待リンクから登録してもかまいません。',
    'account.username'        => 'ユーザー名',
    'account.username_hint'   => '3〜32 文字：英数字、アンダースコア、ハイフン。先頭は英字でなければなりません。'
        . 'admin、www などの予約名は使えません。',
    'account.email'           => 'メールアドレス',
    'account.email_hint'      => 'このメールアドレスで申請したドメインがコンソールに表示されます。確認リンクを'
        . 'このアドレスに送ります。',
    'account.password'        => 'パスワード',
    'account.password_hint'   => '8 文字以上。ユーザー名やメールアドレスと同じにしないでください。',
    'account.password_repeat' => 'パスワード（確認）',
    'account.closed'          => '現在、新規登録を停止しています。',
    'account.have_account'    => 'すでにアカウントを持っています',
    'account.no_account'      => '新しいアカウントを登録',
    'account.registered_title' => 'アカウントを作成しました',
    'account.registered_body' => '確認リンクを %s に送りました。メール内のリンクを開いてアカウントを有効化すると'
        . 'ログインできます。',
    'account.registered_next' => '有効化済みですか？こちらからログイン：%s。',
    'account.registered_flash' => 'アカウントを作成しました。まずメールアドレスの確認を完了してください。',
    'account.verify_mail_failed' => '確認メールの送信に失敗しました。ログインページの「確認メールを再送」を使うか、'
        . 'レジストリにご連絡ください。',
    'account.verify_heading'  => 'メールアドレスの確認',
    'account.verify_ok'       => 'メールアドレスの確認が完了しました',
    'account.verify_ok_text'  => 'これでコンソールを使えます：%s。まだログインしていない場合は先に%sしてください。',
    'account.verify_fail'     => 'この確認リンクは無効です',
    'account.verify_fail_text' => 'リンクの有効期限が切れたか、すでに使用されています。ログイン後に確認メールを'
        . '再送するか、「パスワードをお忘れの方」ページをご利用ください。',
    'account.verify_retry'    => 'このリンクをもう一度試す',
    'account.verify_resend'   => '確認メールを再送',
    'account.verify_resent'   => '新しい確認メールを送信しました。',
    'account.email_unverified' => 'メールアドレス %s はまだ確認されていません。受信トレイ（迷惑メールフォルダも）を'
        . 'ご確認いただくか、下から確認メールを再送してください。',
    'account.login_heading'   => 'ログイン',
    'account.login_intro'     => 'ユーザー名またはメールアドレスでログインすると、名下のすべてのドメインをまとめて'
        . '管理できます。',
    'account.login_id'        => 'ユーザー名またはメールアドレス',
    'account.login_id_hint'   => 'どちらでもかまいません。',
    'account.login_submit'    => 'ログイン',
    'account.welcome_back'    => 'ログインしました。',
    'account.pending_notice'  => 'アカウント %s はまだ有効化されていません。先にメールアドレスの確認を完了して'
        . 'ください。',
    'account.resend_submit'   => '確認メールを再送',
    'account.resend_done'     => '新しい確認メールを %s に送信しました。',
    'account.resend_failed'   => 'このメールアドレスに対応する未確認のアカウントが見つかりません。',
    'account.forgot_link'     => 'パスワードをお忘れですか？',
    'account.forgot_heading'  => 'パスワードの再設定',
    'account.forgot_intro'    => 'アカウントのメールアドレスを入力してください。新しいパスワードを設定する'
        . 'メールをお送りします。',
    'account.forgot_submit'   => '再設定リンクを送信',
    'account.forgot_sent_title' => 'このメールアドレスが登録済みであれば、メールを送信しました',
    'account.forgot_sent_text' => '%s がここで登録されていれば、すでにメールを送信しています。リンクの有効期限は'
        . '2 時間です。',
    'account.reset_heading'   => '新しいパスワードの設定',
    'account.reset_intro'     => 'アカウント %s の新しいパスワードを設定します。',
    'account.reset_submit'    => '新しいパスワードを保存',
    'account.reset_done_title' => 'パスワードを変更しました',
    'account.reset_done_text' => '新しいパスワードでログインできます：%s。',
    'account.reset_bad_title' => 'この再設定リンクは無効です',
    'account.reset_bad_text'  => 'リンクの有効期限が切れたか、すでに使用されています。もう一度申請してください：%s。',
    'account.console_heading' => 'コンソール',
    'account.console_intro'   => '現在ログイン中：%s。以下はすべて、あなたのメールアドレスで申請したドメインです。',
    'account.member_since'    => '登録日',
    'account.last_login'      => '前回のログイン',
    'account.counts'          => '統計',
    'account.counts_value'    => '合計 %d 件のドメイン：%d 件が有効、%d 件が審査待ち、%d 件のレコード変更が'
        . '審査待ち',
    'account.my_domains'      => '私のドメイン',
    'account.no_domains'      => '%s で申請したドメインはまだありません。「ドメイン申請」から申請すると、ここに'
        . '表示されます。',
    'account.col_change'      => '審査待ちの変更',
    'account.col_actions'     => '操作',
    'account.queued'          => '審査待ち',
    'account.reserved_mark'   => '予約ドメイン',
    'account.action_manage'   => 'レコード管理',
    'account.action_status'   => '状態を確認',
    'account.what_now'        => '次にできること',
    'account.link_new'        => 'もう 1 つドメインを申請：%s。',
    'account.link_profile'    => 'プロフィールやパスワードの変更：%s。',
    'account.link_export'     => '名下のすべてのドメインとレコードを書き出す：%s。',
    'account.link_old'        => 'アカウント登録なしでも：申請番号で %s を照会し、%s で個別のドメインを管理できます。',
    'account.manage_heading'  => 'レコード管理',
    'account.manage_not_found' => 'このドメインはあなたのアカウントのものではありません。',
    'account.records_now'     => '現在有効なレコード',
    'account.profile_heading' => 'アカウント情報',
    'account.profile_intro'   => '現在ログイン中：%s。以下の変更には現在のパスワードが必要です。',
    'account.current_password' => '現在のパスワード',
    'account.new_username'    => '新しいユーザー名',
    'account.change_password' => 'パスワードの変更',
    'account.new_password'    => '新しいパスワード',
    'account.change_email'    => 'メールアドレスの変更',
    'account.new_email'       => '新しいメールアドレス',
    'account.email_change_warning' => 'コンソールには現在 %s で申請した %d 件のドメインが表示されています。'
        . 'メールアドレスを変更すると、一覧は新しいメールアドレスに従います。古いメールアドレスで申請したドメインは'
        . '申請番号で管理できます。',
    'account.email_changed'   => 'メールアドレスを %s に変更しました。確認を完了してください。',
    'account.profile_saved'   => '保存しました。',
    'account.password_changed' => 'パスワードを変更しました。',
    'account.nothing_changed' => '変更はありません。',
    'account.save'            => '保存',
    'account.delete_heading'  => 'アカウントの削除',
    'account.delete_warning'  => 'アカウントは削除されます。下の %d 件のドメインは残り、申請番号で引き続き管理'
        . 'できます。この操作は取り消せません。',
    'account.delete_confirm'  => 'アカウントの削除を確認します',
    'account.delete_submit'   => 'アカウントを削除',
    'account.deleted'         => 'アカウントを削除しました。ドメインは残っています。',
    'account.signed_out'      => 'コンソールからログアウトしました。',
    'account.please_sign_in'  => '先にコンソールにログインしてください。',
    'account.export_heading'  => '私のデータを書き出す',
    'account.old_channel_heading' => '単一のドメイン管理だけなら登録は不要',
    'account.old_channel_text' => 'コンソールは便利なだけで必須ではありません：申請番号と申請時のメールアドレスで、'
        . 'これまでどおり %s で進捗を照会し、%s でレコードを変更できます。',
    'close.heading'    => '私のデータとアカウントの削除',
    'close.intro'      => 'すべてのデータを持ち出すことも、アカウントを完全に削除することもできます。ここで'
        . 'あなた自身が操作しない限り、何も削除されません。',
    'close.export_heading' => 'すべてのデータを書き出す',
    'close.export_hint' => 'テキストファイル 1 つで、アカウント、クォータ、各ドメインとそのレコード、チケット、'
        . 'チャット、マーケットとショップの項目を含みます。大切に保管してください —— これは読むためのもので、'
        . '復元用のバックアップ形式ではありません。',
    'close.export_link' => '私のデータをダウンロード',
    'close.export_title' => '%s —— 私のデータ',
    'close.export_when' => '書き出し時刻 %s',
    'close.export_account' => 'アカウント',
    'close.export_domains' => 'ドメインとレコード',
    'close.export_tickets' => '譲渡チケット',
    'close.export_chat' => 'チャット',
    'close.export_market' => '出品',
    'close.export_shop' => 'ショップへの申請',
    'close.close_heading' => 'アカウントの削除',
    'close.warning'    => 'この操作は取り消せません。名下に %s 件のドメインがあります：それらは停止され、公開中の'
        . 'レコードは DNS サービスから削除されますが、ドメイン自体が他人に渡ることはありません。',
    'close.what'       => '項目',
    'close.effect'     => 'どうなるか',
    'close.account'    => 'アカウント',
    'close.effect_account' => 'ユーザー名、メールアドレス、パスワードが削除されます。チャット、出品、ショップへの'
        . '申請、API キー、メール転送も同時に削除されます。',
    'close.names'      => 'あなたの %s 件のドメイン',
    'close.effect_names' => '停止状態になります：レコードは上流から削除され、ドメインは名前解決されなくなり、'
        . '保持期間中は再発行されません。',
    'close.other'      => '何が残るか',
    'close.effect_other' => '監査ログには操作そのものが残ります（パスワードは含みません）—— レジストリは何が'
        . '起きたかを説明できなければなりません。',
    'close.password'   => 'あなたのパスワード',
    'close.phrase_label' => '確認フレーズを入力',
    'close.phrase_hint' => '次のとおり正確に入力してください：DELETE MY ACCOUNT',
    'close.submit'     => 'アカウントの削除を確定する',
    'close.bad_phrase' => '確認フレーズを正確に入力してください。',
    'close.bad_password' => 'パスワードが正しくありません。',
    'close.bad_code'   => '認証アプリのコードが現在無効です。',
    'close.done'       => 'アカウントを削除し、名下のドメインを停止しました。これまでありがとうございました。',
    'notify.heading'   => '受け取るメール',
    'notify.intro'     => '既定ではすべて有効です。不要なものはオフにできます。「アカウントへの再ログイン」に'
        . '関するメールは常に送信されます。',
    'notify.kind'      => '種類',
    'notify.examples'  => '例',
    'notify.switch_off' => 'オフ',
    'notify.kind_chat' => 'チャットの返信',
    'notify.example_chat' => 'レジストリがオンライン相談で返信したとき',
    'notify.kind_transfer' => '譲渡',
    'notify.example_transfer' => '譲渡コードが届いたとき、または譲渡が進んだとき',
    'notify.kind_market' => 'マーケット',
    'notify.example_market' => '出品が承認・却下されたとき、購入希望が来たとき',
    'notify.kind_shop' => 'クォータショップ',
    'notify.example_shop' => 'プロモーションが承認・却下されたとき',
    'notify.kind_domain' => 'ドメイン関連',
    'notify.example_domain' => '申請の審査結果、または送信した変更の結果',
    'notify.kind_account' => 'アカウント関連',
    'notify.example_account' => 'パスワード再設定、メールアドレスの確認',
    'notify.always'    => 'アカウント関連のメールはオフにできません：再ログインできるかどうかに関わるためです。'
        . 'あなた自身の操作の確認メールも常に送信されます —— その操作の記録だからです。',
    'notify.save'      => '設定を保存',
    'notify.saved'     => '保存しました。',
    'notify.no_table'  => 'データベースにこのページに必要な項目がありません。upgrade-mailpref.sql を'
        . '導入してください（セルフチェックページから実行できます）。',
    'notify.scope'     => 'この設定はこのアカウントのメールアドレスにのみ適用されます。「アカウントを持たない人」'
        . '（申請者、ゲスト）へのメールには影響しません。',
    'totp.heading'      => '二段階認証',
    'totp.intro'        => '完全に任意で、あなた自身のアカウントにのみ作用します：認証アプリ'
        . '（Google Authenticator、Aegis、1Password など）と組み合わせれば、パスワードが漏れてもコンソールには'
        . '入れません。',
    'totp.open'         => '二段階認証を設定',
    'totp.state'        => '状態',
    'totp.state_on'     => '有効（%s から）',
    'totp.state_off'    => '無効（パスワードのみでログイン）',
    'totp.account'      => 'アカウント',
    'totp.turn_on'      => '有効にする',
    'totp.turn_on_hint' => '認証アプリでコードを 1 度確認したあとにだけ鍵が保存されます —— そのため「設定の途中で'
        . '締め出される」ことはありません。',
    'totp.begin'        => '鍵を生成',
    'totp.setup'        => '設定を完了',
    'totp.step_app'     => '認証アプリを開き、「手動入力」でアカウントを追加します（ここではカメラは不要です）。',
    'totp.step_secret'  => '次の鍵をアプリに入力します（英字 A-Z と数字 2-7）：',
    'totp.step_uri'     => 'アプリが対応していれば、次のアドレスを直接貼り付けてもかまいません：',
    'totp.code'         => 'アプリに表示される 6 桁のコード',
    'totp.confirm'      => '確認して有効にする',
    'totp.cancel'       => '設定を中止',
    'totp.turn_off'     => '無効にする',
    'totp.turn_off_hint' => '無効化には現在のコードが必要なので、盗んだパスワードだけでは解除できません。',
    'totp.disable'      => '二段階認証を無効にする',
    'totp.enabled'      => '二段階認証を有効にしました。次回のログインからコードが必要です。',
    'totp.disabled'     => '二段階認証を無効にしました。再びパスワードのみでログインできます。',
    'totp.bad_code'     => 'コードが正しくありません。スマートフォンの時刻が正確かを確認し、次のコードを'
        . 'お試しください。',
    'totp.no_setup'     => '設定の有効期限が切れました。最初からやり直してください。',
    'totp.not_on'       => '現在、二段階認証は有効ではありません。',
    'totp.no_table'     => 'データベースにこの機能に必要な項目がありません。upgrade-totp.sql を導入してください'
        . '（セルフチェックページから実行できます）。',
    'totp.recovery'     => '鍵は別に安全な場所へ保管してください：鍵を失い利用できるコードもない場合、'
        . 'レジストリの管理者による復旧しか手段がありません。このページからいつでも無効にできます。',
    'totp.ask'          => '二段階認証',
    'totp.ask_intro'    => 'このアカウントは認証アプリを有効にしています。ログインを完了するには現在の 6 桁の'
        . 'コードを入力してください。',
    'totp.ask_submit'   => 'ログインを完了',
    'totp.ask_bad'      => 'このコードは現在無効です。',
    'share.heading'    => '共有されたドメイン',
    'share.intro'      => '自分のドメインの 1 つを別のコンソールアカウントに公開して見せることができ、逆に他人から'
        . '共有されたドメインも見られます。共有は**読み取り専用**です：相手はドメインとレコードを見られるだけで、'
        . '変更・譲渡・削除は持有人だけが行えます。',
    'share.with_me'    => '自分に共有されたもの',
    'share.with_me_none' => 'まだ誰もあなたにドメインを共有していません。',
    'share.by_me'      => '自分が共有したもの',
    'share.by_me_hint' => 'あなたのドメインを閲覧できるアカウントです。彼らは何も変更できません。',
    'share.by_me_none' => 'まだドメインを共有していません。',
    'share.name'       => 'ドメイン',
    'share.zone'       => 'サフィックス',
    'share.state'      => '状態',
    'share.by'         => '共有者',
    'share.account'    => 'アカウント',
    'share.when'       => '共有時刻',
    'share.look'       => 'レコードを見る',
    'share.revoke'     => '取り消す',
    'share.invite'     => '自分のドメインを共有する',
    'share.invite_email' => '相手アカウントのメールアドレス',
    'share.invite_hint' => 'そのメールアドレスは本サイトのコンソールアカウントとして登録済みである必要があります。'
        . '相手にメールが届き、「自分に共有されたもの」にこのドメインが表示されます。',
    'share.invite_submit' => '共有する（読み取り専用）',
    'share.added'      => '共有しました。相手はレコードを閲覧できるだけです。',
    'share.revoked'    => '共有を取り消しました。',
    'share.readonly_note' => '読み取り専用：この画面ではレコードを変更できません。編集・譲渡・削除・API は'
        . 'ドメインの持有人だけが行えます。',
    'share.not_mine'   => 'このドメインはあなたのアカウントのものではありません。',
    'share.self'       => 'これはあなた自身のドメインです。',
    'share.already'    => 'アカウント「%s」はすでにこのドメインを閲覧できます。',
    'share.no_account' => 'メールアドレス %s を使うコンソールアカウントがありません。相手が先に登録する必要が'
        . 'あります。',
    'share.no_table'   => 'データベースに共有関連のテーブルがありません。upgrade-sharing.sql を導入してください'
        . '（セルフチェックページから実行できます）。',
    'share.rec_name'   => '名前',
    'share.rec_type'   => '種類',
    'share.rec_value'  => '値',
    'mail.share_subject' => '%s があなたに共有しました',
    'mail.share_body'  => "%s が %s によってあなたに共有されました（読み取り専用）。\n\nコンソールの「共有されたドメイン」でこのドメインとレコードを閲覧できますが、変更はできません：編集・譲渡・削除は持有人だけが行えます。",
    'mail.share_footer' => 'いつでもフォローをやめられます。持有人が共有を取り消すこともできます。',
    'ns.heading'         => 'ネームサーバー',
    'ns.intro'           => '一部のサフィックスでは、ドメインを自分のネームサーバー（Cloudflare の NS など）に'
        . '向けられます。そうするとレジストリはそのドメインに応答しなくなります：上流に委任レコードを公開し、'
        . '既存のレコードを外すだけで、名前解決はあなたの DNS サービスが担当します。',
    'ns.open'            => 'ドメインのネームサーバーを設定',
    'ns.name'            => 'ドメイン',
    'ns.zone'            => 'サフィックス',
    'ns.state'           => '状態',
    'ns.state_delegated' => 'あなたのネームサーバーに委任済み',
    'ns.state_here'      => '当レジストリが応答',
    'ns.servers'         => '現在のネームサーバー',
    'ns.server'          => 'ネームサーバー %s',
    'ns.glue_hint'       => 'Glue が必要なのはネームサーバーがそのドメイン内にある場合だけです'
        . '（例：ns1.あなたのドメイン.us.ci）：そのアドレスも入力してください。',
    'ns.glue_host'       => 'Glue ホスト名',
    'ns.glue_value'      => 'Glue アドレス（IPv4 または IPv6）',
    'ns.apply'           => 'これらのネームサーバーを使う',
    'ns.clear'           => '当レジストリが応答する設定に戻す',
    'ns.none'            => 'カスタム NS を設定できる有効なドメインがありません（対象サフィックスで機能が'
        . '有効になっている必要があります）。',
    'ns.no_table'        => 'データベースに委任関連の項目がありません。upgrade-ns.sql を導入してください'
        . '（セルフチェックページが SQL を示します）。',
    'ns.applied'         => '保存しました —— このドメインはあなたのネームサーバーに委任されました。',
    'ns.cleared'         => '委任を解除し、当レジストリが応答する設定に戻しました。レコードを入力し直してください。',
    'ns.note'            => '委任は即時反映で審査は不要です。委任中はここでレコードを変更できません。また、'
        . 'あなたの DNS サービスが正常に応答する場合にのみドメインが名前解決されます。',
    'ns.read_only'       => 'このドメインは自分のネームサーバーを使っているため、ここではレコードを変更できません。',
    'val.ns_own'         => 'このドメインはあなたのアカウントのものではありません。',
    'val.ns_zone_off'    => 'このサフィックスはカスタムネームサーバーに対応していません。',
    'val.ns_not_active'  => '委任できるのは有効なドメインだけです。',
    'val.ns_publish'     => 'このサフィックスは自動公開が設定されていないため、委任を上流へ送信できません。'
        . 'レジストリにご連絡ください。',
    'val.ns_host'        => '"%s" は有効なネームサーバーのホスト名ではありません。',
    'val.ns_ours'        => 'レジストリ自身のネームサーバーを委任に使うことはできません。',
    'val.ns_few'         => 'ネームサーバーを 2 つ以上入力してください。',
    'val.ns_many'        => 'ネームサーバーは最大 4 つです。',
    'val.ns_glue_pair'   => 'Glue はホスト名とアドレスの両方を入力する必要があります。',
    'val.ns_glue_inside' => 'Glue は %2$s 内にあるネームサーバーにのみ使えます（"%1$s" は該当しません）。',
    'val.ns_glue_listed' => 'Glue ホスト名 "%s" は入力したネームサーバーの一覧にありません。',
    'val.ns_glue_ip'     => '"%s" は有効な IPv4 でも IPv6 でもありません。',
    'val.ns_glue_needed' => 'ネームサーバー %s はこのドメイン内にあるため、Glue アドレスが必要です。',
    'account.github_heading'        => 'GitHub',
    'account.github_login_hint'     => 'GitHub アカウントでもログインできます。あなたの GitHub メールアドレスを'
        . '使うアカウントが本サイトにない場合は、自動で作成されます。',
    'account.github_signin'         => 'GitHub でログイン',
    'account.github_or'             => 'または',
    'account.github_signup'         => 'GitHub で直接登録',
    'account.github_signup_hint'    => '上のフォームより簡単です：GitHub のユーザー名と確認済みメールアドレスを'
        . 'そのまま使い、確認メールは不要です。あとでプロフィールからパスワードを設定できます。',
    'account.github_signed_in'      => 'GitHub でログインしました：%s。',
    'account.github_welcome'        => 'ようこそ、%s！GitHub 経由でアカウント「%s」を作成しました。いつでも'
        . 'プロフィールからパスワードを設定できます。',
    'account.github_linked'         => 'GitHub アカウント <strong>%s</strong> を連携しました（連携日時：%s）。',
    'account.github_linked_ok'      => 'GitHub を連携しました（%s）。以後は GitHub でワンクリックログイン'
        . 'できます。',
    'account.github_already_linked' => 'このアカウントはすでに %s を連携しています。',
    'account.github_not_linked'     => 'GitHub はまだ連携していません。連携するとワンクリックでログインでき、'
        . 'パスワードを忘れたときにも便利です。',
    'account.github_link'           => 'GitHub アカウントを連携',
    'account.github_unlink'         => '連携を解除',
    'account.github_unlinked'       => 'GitHub の連携を解除しました。',
    'account.github_no_password_hint' => 'このアカウントには独自のパスワードがありません：「パスワードの変更」で'
        . '先に設定してから GitHub の連携を解除してください。そうしないとログインできなくなります。',
    'val.github_off'                => '現在 GitHub ログインは提供していません。',
    'val.github_no_table'           => 'データベースに GitHub 関連の項目がありません。upgrade-github.sql を'
        . '導入してください（セルフチェックページが SQL を示します）。',
    'val.github_signin_first'       => '先にログインしてから GitHub アカウントを連携してください。',
    'val.github_bad_request'        => 'GitHub へのリクエストが正しくありません。',
    'val.github_state'              => 'GitHub のコールバックを今回のリクエストに対応付けられません'
        . '（セキュリティトークンが一致しないか期限切れです）。もう一度操作してください。',
    'val.github_cancelled'          => 'GitHub の認可が取り消されました。',
    'val.github_token'              => 'GitHub からプロフィールを取得できません：%s',
    'val.github_taken'              => 'この GitHub アカウントは本サイトの別のアカウントに連携済みです。',
    'val.github_no_email'           => 'GitHub が**確認済み**のメールアドレスを返さなかったため、アカウントの'
        . '作成・照合ができません。先に GitHub でメールアドレスを追加・確認するか、メールアドレスで本サイトに'
        . '登録してください。',
    'val.github_no_account'         => 'GitHub アカウントに一致する本サイトのアカウントがなく、「GitHub で登録」は'
        . '現在無効です。',
    'val.github_not_linked'         => 'このアカウントは GitHub を連携していません。',
    'val.github_unlink_needs_password' => '先にこのアカウントのパスワードを設定してから GitHub の連携を'
        . '解除してください。そうしないとログインできなくなります。',
    'api.heading'    => 'API キー',
    'api.intro'      => 'HTTP で自分のドメインを管理します：ドメインの一覧、レコードの取得、追加・変更・削除が'
        . 'できます。キーはあなたのアカウントに属し、名下のドメインにしかアクセスできません。',
    'api.open'       => 'API キーの管理',
    'api.new'        => 'キーを作成',
    'api.create'     => 'キーを作成',
    'api.label'      => '用途メモ（任意）',
    'api.label_hint' => '例：「自分のスクリプト」',
    'api.created'    => 'キーを作成しました —— すぐにコピーしてください。表示はこの一度だけです。',
    'api.token_once' => 'これが新しいキーです。すぐにコピーしてください：以後は表示されません。',
    'api.mine'       => '自分のキー',
    'api.none'       => 'キーはまだありません。',
    'api.hint'       => '末尾',
    'api.calls'      => '呼び出し回数',
    'api.last'       => '最終使用',
    'api.state'      => '状態',
    'api.active'     => '有効',
    'api.revoked_state' => '失効済み（%s）',
    'api.revoke'     => '失効させる',
    'api.revoked'    => 'キーを失効させました。',
    'api.not_yours'  => 'このキーはあなたのアカウントのものではありません。',
    'api.off'        => 'API は現在停止しています。',
    'api.no_table'   => 'データベースに API 関連のテーブルがありません。upgrade-api.sql を導入してください'
        . '（セルフチェックページから実行できます）。',
    'api.docs'       => '使い方',
    'api.docs_body'  => "キーは Authorization ヘッダーに入れます：\n\n  GET  %s?action=domains\n  GET  %s?action=records&domain=myname.us.ci\n  POST %s?action=record.add&domain=myname.us.ci&name=@&type=A&value=1.2.3.4\n  POST %s?action=record.update&domain=myname.us.ci&index=0&value=1.2.3.5\n  POST %s?action=record.delete&domain=myname.us.ci&index=1\n\n応答はすべて JSON です：成功は \"ok\": true、失敗は \"error\" コードと説明を返します。",
    'api.rules'      => '操作できるのは自分のアカウントに属し有効なドメインだけです。独自 NS に委任済みの'
        . 'ドメインや譲渡中のドメインは変更を拒否します（応答に理由が入ります）。変更はフロントと同じ検証を'
        . '通り、即時公開され、監査ログに記録されます。',
    'api.docs_auth'  => '呼び出しごとに "Authorization: Bearer あなたのキー" ヘッダーが必要です。curl の例：'
        . 'curl -H "Authorization: Bearer あなたのキー" "https://freedns.my/api.php?action=domains"；'
        . '書き込み系の 3 つは -X POST を付けます。',
    'ddns.heading'      => '動的更新（DDNS）',
    'ddns.intro'        => 'ドメインにキー付きの更新 URL を設定します：ルーター、NAS、Raspberry Pi、スクリプトが'
        . '自分のアドレスを送信すると、レコードが自動で追従します。',
    'ddns.mine'         => 'このアカウントのドメイン',
    'ddns.name'         => 'ドメイン',
    'ddns.state'        => '更新 URL',
    'ddns.state_on'     => '有効',
    'ddns.state_off'    => '未作成',
    'ddns.last'         => '最終更新',
    'ddns.open'         => '開く',
    'ddns.all'          => 'すべてのドメイン',
    'ddns.token'        => '更新キー',
    'ddns.url'          => '更新 URL',
    'ddns.create'       => '更新キーを生成',
    'ddns.reset'        => 'キーを再生成',
    'ddns.revoke'       => 'キーを削除',
    'ddns.created'      => 'キーを生成しました。更新 URL が使えます。',
    'ddns.reset_done'   => '新しいキーを生成しました。古い URL はすぐ無効になります。',
    'ddns.revoked_done' => 'キーを削除しました。更新 URL はもう使えません。',
    'ddns.no_token'     => 'このドメインにはまだ更新キーがありません。',
    'ddns.none'         => '有効なドメインがまだありません。',
    'ddns.off'          => '動的更新は現在停止しています。',
    'ddns.no_table'     => 'データベースにこの機能に必要な項目がありません。upgrade-ddns.sql を導入してください'
        . '（セルフチェックページから実行できます）。',
    'ddns.examples'     => '使い方',
    'ddns.example_curl' => 'curl "%s"',
    'ddns.example_router' => "ルーター / NAS（カスタム DDNS の設定例）：\n  ホスト名 : あなたのドメイン（例：myname.us.ci）\n  URL      : 上記の更新 URL（\"&ip=\" は残してください。クライアントが自分でアドレスを付けます）\n  ユーザー名・パスワード : 空欄。URL 内のキーが認証情報です",
    'ddns.rules'        => '更新されるのはドメイン自身（@）の A と AAAA レコードだけなので、先にレコード編集画面で '
        . '1 件追加してください。2 回の更新は 10 秒以上空けてください。アドレスが変わっていなければすぐに'
        . '応答します。キーは大切に保管してください —— 知っている人はこのドメインのアドレスを変更できます。'
        . '「再生成」を押すと古い URL はすぐ無効になります。',
    'ddns.list_hint'    => 'ドメインを開くと、キー、すぐ使える更新 URL、例が表示されます。',
    'ddns.status_good'  => '更新済み',
    'ddns.status_nochg' => 'アドレス変更なし',
    'ddns.status_nohost' => '更新できるレコードがない',
    'ddns.status_badip' => 'アドレスが不正',
    'ddns.status_badauth' => 'キーが違う',
    'ddns.status_911'   => '公開に失敗',
    'val.ddns_own'      => 'このドメインはあなたのアカウントのものではありません。',
    'account.bonus_heading'   => '追加クォータ：GitHub で Star',
    'account.bonus_intro'     => 'GitHub で %s に Star を付けると、このアカウントの登録クォータが増えます。'
        . '確認は連携済みの GitHub アカウントをリポジトリの Star 一覧と照合して行うため、その Star はあなた自身の '
        . 'GitHub アカウントからのものでなければなりません。',
    'account.bonus_repo'      => 'リポジトリ',
    'account.bonus_amount'    => 'アカウントごとの特典',
    'account.bonus_names'     => '個の追加ドメイン、1 回のみ',
    'account.bonus_yours'     => 'すでに %s 個を獲得',
    'account.bonus_status'    => 'あなたの特典',
    'account.bonus_taken'     => '%s に付与済みです。ありがとうございます！',
    'account.bonus_need_link' => '先に GitHub アカウントを連携してください：%s。特典は実際に Star を付けた '
        . 'GitHub アカウントにのみ付与されます。',
    'account.bonus_ready'     => '確認できます —— Star を付けたら下のボタンを押してください。',
    'account.bonus_step1'     => '%s を開き、右上の「Star」を押します。',
    'account.bonus_step2'     => '数秒待ちます（GitHub の Star 反映には少し時間がかかります）。',
    'account.bonus_step3'     => '下のボタンで確認します。通ればクォータがすぐにアカウントへ加算されます。',
    'account.bonus_submit'    => 'Star を付けました。今すぐ確認',
    'account.bonus_ok'        => 'ありがとうございます！このアカウントに %s 個のドメインクォータを追加しました。',
    'account.bonus_off'       => '現在 Star 特典は提供していません。',
    'account.bonus_count'     => '現在 %s 個のアカウントがこのリポジトリに Star を付けています（一覧の取得：%s）。',
    'account.bonus_truncated' => '一覧が本サイトの読み取り上限より長いです。見つからない場合は管理者に'
        . 'ご連絡ください。',
    'account.bonus_note'      => 'アカウントごとに 1 回だけです。あとで Star を外しても付与済みのクォータは'
        . '取り消されません。',
    'account.back_to_console' => 'コンソールに戻る',
    'val.star_off'            => '現在 Star 特典は提供していません。',
    'val.star_no_table'       => 'データベースに Star 特典関連の項目がありません。upgrade-star.sql を'
        . '導入してください（セルフチェックページが SQL を示します）。',
    'val.star_no_repo'        => 'レジストリはまだ GitHub リポジトリを設定していません。',
    'val.star_link_first'     => '先にプロフィールで GitHub アカウントを連携してください —— 特典は Star を付けた '
        . 'GitHub アカウントにのみ付与されます。',
    'val.star_already'        => 'このアカウントはすでにこの特典を受け取っています。',
    'val.star_lookup'         => 'GitHub から Star 一覧を取得できません：%s',
    'val.star_not_found'      => 'リポジトリの Star 一覧にあなたの GitHub アカウントが見つかりません。'
        . 'リポジトリのページで Star を付け、少し待ってからもう一度お試しください。',
    'abuse.heading'  => '迷惑行為の報告',
    'abuse.heading_short' => '迷惑行為の報告',
    'abuse.intro'    => '当レジストリはドメインを無料で発行し、迷惑行為には真剣に対応します。いずれかの'
        . 'サフィックスのドメインがフィッシング、スパム、マルウェア、商標侵害その他の違法な用途に使われている'
        . '場合は、ここでお知らせください —— アカウント登録は不要です。',
    'abuse.domain'   => '報告するドメイン',
    'abuse.reporter' => 'あなたのメールアドレス',
    'abuse.reporter_hint' => '詳細を確認するためにご連絡することがあります。公開されることも、ドメインの'
        . '持有人に伝えられることもありません。',
    'abuse.kind'     => 'どの種類ですか？',
    'abuse.kind_phishing'  => 'フィッシング / 詐欺',
    'abuse.kind_spam'      => 'スパム',
    'abuse.kind_malware'   => 'マルウェア',
    'abuse.kind_trademark' => '商標侵害',
    'abuse.kind_illegal'   => '違法コンテンツ',
    'abuse.kind_other'     => 'その他',
    'abuse.message'  => '何を見ましたか？',
    'abuse.message_hint' => 'できるだけ客観的に、完全なアドレス（具体的な URL など）を添えてください。'
        . '時刻やスクリーンショットがあるとなお助かります。報告内容は上流のサービス提供者に共有することが'
        . 'あります。',
    'abuse.submit'   => '報告を送信',
    'abuse.thanks'   => 'ご報告ありがとうございます。受け付けました。人手で確認します。ご連絡先の'
        . 'メールアドレスに連絡することがあります。',
    'abuse.no_table' => 'データベースに報告用テーブルがありません。upgrade-abuse.sql を導入してください'
        . '（セルフチェックページから実行できます）。',
    'abuse.policy'   => '迷惑行為に使われたドメインは停止または削除されます。報告が該当しない場合は理由を'
        . '示して却下し、どちらの結果も記録に残します。',
    'val.abuse_domain' => '完全なドメインを入力してください（例：something.us.ci）。',
    'val.abuse_short'  => '問題を数文で説明してください（20 文字以上）。',
    'mail.abuse_subject' => '%s に関する迷惑行為の報告',
    'legal.terms'    => '利用規約',
    'legal.privacy'  => 'プライバシー',
    'legal.intro'    => 'このレジストリのルールと、あなたのデータがどう扱われるかを平易な言葉で説明します。',
    'legal.footer'   => 'この文章は管理画面からいつでも変更できるため、常に現在稼働している版のサービスを'
        . '説明しています。',
    'whois.heading'           => 'WHOIS 照会',
    'whois.intro'             => 'ドメインを入力してください：当レジストリが発行したドメインは本站の登録'
        . 'データベースから直接回答し、それ以外は公共の WHOIS データを照会します（RDAP を優先し、必要なら'
        . '従来の 43 番ポートのサービスに切り替えます）。',
    'whois.label'             => 'ドメイン',
    'whois.submit'            => '照会',
    'whois.disabled'          => '現在 WHOIS 照会機能は停止しています。',
    'whois.invalid'           => '"%s" は有効なドメインではありません。myname.freedns.my や example.com の'
        . 'ような形式で入力してください。',
    'whois.not_registered'    => '見つかりません：%s は当レジストリに登録されていません（%s は本站の'
        . 'サフィックスの 1 つです）。審査待ちと却下された申請は表示されません。',
    'whois.external_off'      => '当レジストリは自分のサフィックス下のドメインにのみ回答します。外部ドメインの'
        . '照会は停止しています。',
    'whois.limit'             => 'このアドレスからは 1 時間に %s 件まで外部ドメインを照会できます。使い切り'
        . 'ました。しばらくしてから、またはそのドメインのレジストリで直接お試しください。',
    'whois.cached'            => '以下はキャッシュされた結果です（%s）。数分ごとに自動更新されます。',
    'whois.lookup_failed'     => '%s の照会に失敗しました：%s',
    'whois.internal_note'     => 'このドメインは本站で登録されています。ここでは個人情報を一切公開しません：'
        . 'レジストリは申請者の情報を保持し、状態とレコードのみを表示します（IP は公開リストと同じ方式で'
        . 'マスクします）。',
    'whois.external_note'     => 'この結果はドメインのレジストリの公共 WHOIS/RDAP サービスによるもので、'
        . '正確性はそのレジストリの責任です。',
    'whois.help_heading'      => '説明',
    'whois.help_1'            => '本站のサフィックス下のドメインは直接回答します（例：%s）—— データは本站の'
        . '登録データベースからです。',
    'whois.help_2'            => 'それ以外は外部照会になります：まず RDAP、次に従来の 43 番ポートの WHOIS。'
        . 'どちらもないサフィックスには回答できません。',
    'whois.help_3'            => '外部照会はアドレスごとに 1 時間 %s 件までで、数分間キャッシュします。'
        . 'そのため本ページを無料の WHOIS プロキシとして使うことはできません。',
    'block.heading'           => 'アクセスが制限されています',
    'block.message'           => 'アクセスが制限されています：本站があなたのこのアドレスから短時間に多数の'
        . 'リクエストを受け取りました。管理者にご連絡ください。',
    'block.remaining'         => '制限は約 %s 分後に自動で解除されます。',
    'block.contact'           => '管理者の連絡先：%s',
    'block.hint'              => '通常の閲覧だけをしている場合は、数分待ってからお試しください。アカウントには'
        . '何の変更もありません。',
    'health.footer'           => '対応が必要な項目がある場合、セルフチェックページからデータベース構造を'
        . '直接修復できます。',

    /* ============ 第 12 バッチ（前半）：ドメイン一覧と更新履歴 ============ */
    'list.heading'        => 'ドメイン一覧',
    'list.count_one'      => '%s は 1 件のドメインを登録しています%s。',
    'list.count_many'     => '%s は %s 件のドメインを登録しています%s。',
    'list.where_all'      => '当レジストリ',
    'list.where_zone'     => '<code>%s</code> 内',
    'list.filtered'       => '（絞り込み中）',
    'list.also'           => '審査待ち、却下、停止中の申請は表示されません。',
    'list.search'         => '名前で検索',
    'list.zone'           => 'サフィックス',
    'list.all_zones'      => 'すべてのサフィックス',
    'list.search_btn'     => '検索',
    'list.reset'          => 'リセット',
    'list.col_name'       => 'ドメイン',
    'list.col_zone'       => 'サフィックス',
    'list.col_records'    => 'レコード',
    'list.col_org'        => '組織',
    'list.col_registered' => '登録日時',
    'list.none'           => '条件に合うドメインがありません。',
    'list.more'           => '（ほか %d 件）',
    'changelog.heading'  => '更新内容',
    'changelog.intro'    => '知っておくべき変更をここに記録します：何を直し、何を追加したか。最新の更新が'
        . '一番上です。',
    'changelog.footer'   => '分からない点や、ここにない問題があれば「オンライン相談」でお知らせください。'
        . 'ここで、そしてメールで返信します。',

    /* ============ 第 13 バッチ：申請の残りとポリシー・ドキュメント ============ */
    'request.email'          => 'メールアドレス *',
    'request.email_hint'     => '本人確認、審査結果の連絡、今後のレコード管理に使います。公開リストには'
        . '表示されません。',
    'request.org'            => '組織（任意）',
    'request.country'        => '国・地域（任意）',
    'request.country_hint'   => '自由記入です（例：<code>CN</code>、<code>US</code>、<code>Singapore</code>）。',
    'request.records_hint'   => '少なくとも頂点（<code>@</code>）に 1 件のレコードが必要です。ここでは '
        . '<code>A</code>、<code>AAAA</code>、<code>CNAME</code> が使えます。<code>TXT</code> はドメインが'
        . '有効になってから追加してください。<code>CNAME</code> を使う場合、同じ名前には他のレコードを'
        . '置けません。',
    'request.records_psl'    => '既定では当レジストリが名前解決を担当します。上流が認めているサフィックス'
        . '（<em>Public Suffix List</em> に掲載の us.ci、de5.net など）では、ドメインが有効になったあと'
        . 'コンソールの「ネームサーバー」でご自身のネームサーバーに切り替えられます —— 即時反映・審査不要で、'
        . 'その際ここで入力したレコードは削除されます。',
    'doc.heading'          => 'ポリシーとドキュメント',
    'doc.intro'            => '以下の内容はレジストリが管理しており、すべてプレーンテキストです。このページ、'
        . 'トップページ、ドメイン一覧は Cookie を一切使用しません。Cookie を使うのは申請フォーム、照会ページ、'
        . '管理ページ、管理画面のみで、目的はクロスサイトリクエストフォージェリの防止だけです。',
    'doc.policy'           => '登録ポリシー',
    'doc.documentation'    => 'ドキュメント',
    'doc.quickref'         => '早見表',
    'doc.zones_offered'    => '申請できるサフィックス',
    'doc.none_configured'  => '未設定',
    'doc.closed'           => '（受付停止）',
    'doc.zone'             => 'サフィックス',
    'doc.records_required' => 'レコード要件',
    'doc.records_value'    => '頂点に少なくとも 1 件の <code>A</code>、<code>AAAA</code>、<code>CNAME</code> '
        . 'レコード。同じ名前で最大 4 アドレス',
    'doc.record_types'     => '管理できるレコード種別',
    'doc.record_types_v'   => '<code>A</code>、<code>AAAA</code>、<code>CNAME</code>、<code>TXT</code>'
        . ' &mdash; 1 ドメインあたり最大 %d 件',
    'doc.subnames'         => 'サブドメイン',
    'doc.subnames_v'       => 'ドメインの前にラベルを 1 つ付けます（例：<code>www.myname.%s</code>）。'
        . 'ドメインが有効になれば追加できます',
    'doc.changes'          => 'レコードの変更',
    'doc.changes_v'        => '申請者が %s ページから送信し、管理者の承認後に反映されます',
    'doc.nameservers'      => 'ネームサーバー',
    'doc.nameservers_v'    => '名前解決はレジストリが担当します。許可されたサフィックス（Public Suffix List に'
        . '掲載の us.ci、de5.net など）では、コンソールからご自身のネームサーバーを指定できます &mdash; '
        . '即時反映・審査不要',
    'doc.cost'             => '費用',
    'doc.cost_v'           => '無料、更新も無料',
    'doc.example'          => 'レコードの例',
    'doc.example_intro'    => 'たとえばドメイン <code>example.%s</code>：自分のアドレスが 1 つ、<code>www</code> '
        . 'は別のサーバー、メールはメール事業者に任せる場合、レジストリは次を公開します：',
    'doc.docrange'         => '<code>203.0.113.x</code> と <code>2001:db8::/32</code> はドキュメント用の'
        . '例示範囲で、ここでは書き方の説明にのみ使います。フォームはこれらを拒否します。実際にご自身の'
        . 'サービスが使うアドレスとホスト名を入力してください。',
    'doc.tell'             => '内容が変わったらレジストリにお知らせください。管理ページからご自身で変更する'
        . 'こともできます。レコードの変更は人手で審査され、通常は数日で処理されます。',
    'doc.why'              => 'いつ自前のネームサーバーを使えるか',
    'doc.why_1'            => 'サブドメインを配る一般的な方法は <code>NS</code> レコードで委任し、ゾーンを自分で'
        . '管理して任意のレコード種別（MX、TXT、SRV など）を設定する方法です。これにはそのサフィックスが '
        . '<em>Public Suffix List</em>（PSL）に掲載されている必要があります：そうでないと、ブラウザ、'
        . '証明書発行機関、リゾルバがこのサブドメインを独立した公共サフィックスとして扱わず、委任した名前で'
        . '予期しない動作が起きます。',
    'doc.why_2'            => 'サフィックスが PSL に収録されるまでは、レジストリが承認済みの各ドメインの'
        . '名前解決を担当し、保存されたレコードを公開します。そのため <code>A</code>、<code>AAAA</code>、'
        . '<code>CNAME</code>、<code>TXT</code> 以外の種別（<code>MX</code>、<code>SRV</code> など）は現在'
        . 'ご自身では設定できません。必要な場合はフッターのメールアドレスに申請番号を添えてご連絡ください。',

    /* ============ 第 14 バッチ（前半）：譲渡チケット ============ */
    'transfer.heading'        => 'ドメインの譲渡',
    'transfer.intro'          => 'DNSHE が名前解決するサフィックスのドメインは、DNSHE 側では削除できず、譲渡で'
        . 'しか受け渡せません：ここで申請し、レジストリが DNSHE で譲渡コードを用意し、受け取り手が DNSHE で'
        . '承諾します。レジストリは下のチケットで返信し、返信のたびにメールが届きます。',
    'transfer.ticket'         => 'チケット #%s',
    'transfer.status_heading' => '状態',
    'transfer.status_open'    => 'レジストリの対応待ち',
    'transfer.status_approved'=> '譲渡コードを発行済み、受け取り手の承諾待ち',
    'transfer.status_accepted'=> '完了',
    'transfer.status_rejected'=> '却下',
    'transfer.status_expired' => '期限切れ（時間内に承諾されませんでした）',
    'transfer.status_cancelled' => '取り下げ済み',
    'transfer.hours_left'     => 'あと %s 時間は承諾できます',
    'transfer.requester'      => '現在の持有人',
    'transfer.recipient'      => '受け取り手',
    'transfer.recipient_name' => '受け取り手の氏名',
    'transfer.recipient_email'=> '受け取り手のメールアドレス',
    'transfer.opened'         => '開始日時',
    'transfer.reason'         => '説明',
    'transfer.thread'         => 'やり取り',
    'transfer.no_messages'    => 'まだメッセージはありません。',
    'transfer.from_staff'     => 'レジストリ',
    'transfer.from_applicant' => '申請者（%s）',
    'transfer.write'          => 'レジストリへのメッセージ',
    'transfer.message'        => '内容',
    'transfer.send'           => '送信',
    'transfer.withdraw'       => '申請を取り下げる',
    'transfer.mine'           => '自分の譲渡チケット',
    'transfer.none'           => 'まだ譲渡チケットはありません。',
    'transfer.name'           => 'ドメイン',
    'transfer.role'           => '自分の立場',
    'transfer.role_holder'    => '譲渡する側',
    'transfer.role_recipient' => '受け取る側',
    'transfer.open'           => '開く',
    'transfer.back'           => '自分のチケットに戻る',

    /* ============ 第 15 バッチ：譲渡の残りとクラウドサーバー ============ */
    'transfer.new_heading'    => '譲渡を申請',
    'transfer.new_intro'      => '自分のドメインを 1 つ選び、受け取り手を入力します。レジストリが DNSHE で'
        . '譲渡を開始し、譲渡コードを受け取り手のメールアドレスに送ります。受け取り手は 72 時間以内に'
        . '承諾する必要があります。',
    'transfer.submit'         => '譲渡申請を送信',
    'transfer.off'            => '現在、譲渡は受け付けていません。',
    'transfer.nothing_to_transfer' => '譲渡できるドメインがありません（DNSHE サフィックス下の有効なドメインで'
        . 'ある必要があります）。',
    'transfer.hint'           => 'チケットが対応中・承諾待ちの間、そのドメインのレコードはロックされ変更できません。'
        . '受け取り手は、ここに入力したメールアドレスに対応するアカウントで承諾する必要があります。',
    'transfer.created'        => '譲渡申請を送信しました。レジストリはここで、そしてメールで返信します。',
    'transfer.message_saved'  => 'メッセージを保存しました。',
    'transfer.confirmed'      => 'ご確認ありがとうございます。ドメインはあなたのアカウントに移りました。',
    'transfer.cancelled'      => '申請を取り下げました。',
    'transfer.accept_heading' => '譲渡を承諾',
    'transfer.accept_text'    => 'あなたはこのドメインの受け取り手です。まず DNSHE のパネルで承諾し'
        . '（譲渡コードはメールで届いています）、次に下のボタンを押してください：ドメインはここでのあなたの'
        . 'アカウントに移ります。',
    'transfer.accept_submit'  => 'DNSHE で承諾しました —— ドメインを私に移してください',
    'transfer.locked'         => 'このドメインは譲渡手続き中で、しばらくレコードを変更できません。',
    'val.transfer_off'        => '現在、譲渡は受け付けていません。',
    'val.transfer_suffix'     => '譲渡できるのは DNSHE が名前解決するサフィックス下のドメインだけです。',
    'val.transfer_not_active' => '譲渡できるのは有効なドメインだけです。',
    'val.transfer_pending'    => 'このドメインはすでに譲渡手続き中です。',
    'val.transfer_recipient'  => '受け取り手の氏名と有効なメールアドレスを入力してください。',
    'val.transfer_self'       => '自分のメールアドレスにドメインを譲渡することはできません。',
    'val.transfer_own'        => 'このチケットまたはドメインはあなたのアカウントのものではありません。',
    'val.transfer_not_recipient' => 'この譲渡は別のメールアドレス宛てです。チケットに記載された'
        . 'メールアドレスのアカウントでログインしてください。',
    'val.transfer_expired'    => '72 時間が過ぎました。レジストリにご連絡のうえ譲渡コードを再発行してください。',
    'val.transfer_empty'      => '先に内容を入力してください。',
    'mail.transfer_new_subject' => '新しい譲渡申請：%s',
    'mail.transfer_new_body'  => "ユーザーが譲渡申請を送信しました。\n\nドメイン：%s\n現在の持有人：%s\n受け取り手：%s <%s>\n\n管理画面の Tickets ページで対応してください。",
    'mail.transfer_reply_subject' => '%s に関する譲渡チケット',
    'mail.transfer_code_subject' => '%s の譲渡コード',
    'mail.transfer_code_body' => "ドメインを譲り受けます：%s\n\n譲渡コード：%s\n有効期限：%s（72 時間）\n譲渡する側：%s\n\nDNSHE アカウントでこのコードを引き換え、その後コンソールに戻り、このメールアドレスのアカウントで譲渡を確定してください。",
    'mail.transfer_approved_subject' => '%s の譲渡を承認しました',
    'mail.transfer_approved_body' => "%s の譲渡を承認しました。譲渡コードは %s に送信済みです。相手が %s までに承諾すると譲渡が完了します。",
    'mail.transfer_rejected_subject' => '%s の譲渡は承認されませんでした',
    'mail.transfer_rejected_body' => "%s の譲渡は承認されませんでした。\n\n%s",
    'mail.transfer_done_subject' => '%s の譲渡が完了しました',
    'mail.transfer_done_body' => "%s はあなたのメールアドレス名義になりました。コンソールで管理できます。",
    'mail.transfer_done_holder_body' => "%s の %s への譲渡が完了しました。このドメインはあなたのアカウントから外れました。",
    'mail.transfer_footer'    => 'チケットはコンソールの「ドメインの譲渡」ページで追跡できます。',
    'srv.heading' => '私のクラウドサーバー',
    'srv.intro'   => 'RainYun で購入したサーバーをここに登録します：状態と有効期限の確認、期限前の通知、'
        . '起動・停止・再起動ができます。サーバーは常にあなた自身のアカウントにあります —— レジストリは'
        . '代理支払いも預かりもしません。',
    'srv.usage'   => '負荷と使用量',
    'srv.usage_hint' => 'たった今サービス提供元から取得しました。値がおかしい場合は、表の下に生のレスポンスが'
        . 'あります。',
    'srv.maintenance' => 'メンテナンス',
    'srv.no_maintenance' => 'メンテナンスのお知らせはありません。',
    'srv.vnc'     => 'VNC コンソールを開く',
    'srv.vnc_hint' => 'ワンタイムチケット：自動的に失効し、当方は保存しません。取得のたびに監査ログに'
        . '記録されます。',
    'srv.raw'     => '生のレスポンス',

    /* ============ 第 16 バッチ：ドメイン取引 ============ */
    'market.heading'          => '出品中のドメイン',
    'market.disclaimer_heading' => 'まずお読みください',
    'market.disclaimer'       => '本コーナーは情報掲示板にすぎません：レジストリは掲載を審査し双方を'
        . '引き合わせますが、**取引には関与しません** —— ドメインを保有せず、支払いを代理収受せず、ドメインの'
        . '真の帰属を確認せず、いかなる保証やエスクローも提供しません。価格・支払い方法・移転方法は当事者間で'
        . '決めてください。支払い前にご自身でドメインを確認してください（持有人、有効期限、移転ロック）。'
        . '生じた一切のリスクと紛争は売買双方の負担です。',
    'market.intro'            => 'ここには、コンソールアカウントが掲載した、他所で購入して不要になった'
        . 'ドメインが並びます。連絡は売買双方がメールで直接行います。',
    'market.off'              => '取引コーナーは現在閉じています。',
    'market.search'           => '検索（ドメイン / 説明 / 登録事業者）',
    'market.suffix'           => 'サフィックス（com、net…）',
    'market.max_price'        => '最高価格',
    'market.filter'           => '絞り込む',
    'market.reset'            => 'リセット',
    'market.empty'            => '条件に合う出品はありません。',
    'market.domain'           => 'ドメイン',
    'market.price'            => '売却価格',
    'market.transfer_price'   => '移転価格',
    'market.currency'         => '通貨',
    'market.negotiable'       => '応相談',
    'market.registrar'        => '登録事業者',
    'market.registered_on'    => '登録日',
    'market.expires_on'       => '有効期限',
    'market.status_heading'   => '状態',
    'market.status_pending'   => '審査待ち',
    'market.status_active'    => '出品中',
    'market.status_rejected'  => '否認',
    'market.status_sold'      => '売却済み',
    'market.status_withdrawn' => '取り下げ済み',
    'market.listed'           => '掲載日時',
    'market.description'      => '詳細',
    'market.pinned'           => '固定',
    'market.open'             => '見る',
    'market.report_link'      => 'この出品を報告する',
    'market.back'             => '取引コーナーに戻る',
    'market.sell_hint'        => '売りたいドメインがありますか？%s（コンソールアカウントが必要で、1 件ずつ'
        . '人手で審査します）。',
    'market.sell_link'        => 'ここで出品する',
    'market.buy_heading'      => '購入したい',
    'market.buy_intro'        => '購入申請を送ると、あなたと売り手が互いのメールアドレスを受け取り、以後は'
        . '直接メールでやり取りします。（%s）',
    'market.buy_intro_account' => '売り手はアカウントのメールアドレスを使用',
    'market.buy_intro_custom' => '売り手は別の連絡用メールアドレスを使用',
    'market.your_name'        => 'お名前',
    'market.your_email'       => 'メールアドレス',
    'market.email_hint'       => 'このメールアドレスは売り手に伝えられ、直接連絡できます。ご自身が使える'
        . 'アドレスを入力してください。',
    'market.message'          => '売り手へのメッセージ',
    'market.send'             => '購入申請を送信',
    'market.login_first'      => '購入申請の前にログインしてください。',
    'market.login_first_help' => '申請にはコンソールアカウントが必要です —— %s または %s の後にここへ'
        . '戻ってください。',
    'market.sent'             => '申請を送信しました：メールをご確認ください。売り手にもあなたの'
        . 'メールアドレスが届いています。',
    'market.sold_note'        => 'このドメインは売り手により売却済みとされています。',
    'market.own_offer'        => 'これはあなた自身の出品です。購入者の申請はあなたのコンソールに表示されます。',
    'market.mine_heading'     => '自分の出品',
    'market.mine_intro'       => 'あなたが出品したもの。新規作成やドメイン変更は人手の審査後に表示されます。'
        . 'いつでも取り下げるか売却済みにできます。',
    'market.new_heading'      => '出品する',
    'market.new_intro'        => '出品できるのは他所で購入した**一級ドメイン**だけです（当サイトが発行した'
        . '二級ドメインは出品できません）。レジストリは帰属を確認できないため、正直に記入してください —— '
        . '購入者はそれを判断材料にします。',
    'market.domain_hint'      => '例：example.com。当サイト自身のサフィックス下のドメインは受け付けません。',
    'market.contact_email'    => '購入者用の連絡メールアドレス',
    'market.contact_hint'     => '申請した購入者に表示されます。アカウントのメールアドレスでも、別のものでも'
        . 'かまいません。',
    'market.submit'           => '審査に送信',
    'market.save'             => '変更を保存',
    'market.created'          => '送信しました。人手の審査待ちで、承認されれば表示されます。',
    'market.updated'          => '保存しました。',
    'market.mark_sold'        => '売却済みにする',
    'market.withdraw'         => '取り下げる',
    'market.marked_sold'      => '売却済みにしました。',
    'market.withdrawn_ok'     => '取り下げました。',
    'market.reject_reason'    => 'レジストリからの理由',
    'market.review_hint'      => '出品は 1 件ずつ人手で審査し、承認・否認のいずれもメールで通知します。',
    'market.none'             => 'まだ出品していません。',
    'market.enquiries'        => '購入申請',
    'market.enquiries_count'  => '申請数',
    'market.all_enquiries'    => 'すべての購入申請',
    'market.no_enquiries'     => 'まだ申請はありません。',
    'market.when'             => '日時',
    'market.buyer'            => '購入者',
    'market.view_public'      => '取引コーナーで見る',
    'market.back_mine'        => '自分の出品に戻る',
    'val.market_off'          => '取引コーナーは現在閉じています。',
    'val.market_domain'       => '登録可能な一級ドメインを入力してください（例：example.com）。',
    'val.market_own_suffix'   => '当サイト自身のサフィックス下のドメインは出品できません（レジストリが'
        . '発行しています）。',
    'val.market_taken'        => 'このドメインはすでに出品されています。',
    'val.market_price'        => '売却価格を入力してください。',
    'val.market_own'          => 'この出品はあなたのアカウントのものではありません。',
    'val.market_closed'       => 'この出品は終了しています。',
    'val.market_self'         => '自分の出品に購入申請はできません。',

    /* ============ 第 17 バッチ：取引メールとオンライン相談 ============ */
    'mail.market_new_subject' => '取引コーナーに新規出品：%s',
    'mail.market_new_body'    => "ユーザーが出品を送信しました。\n\nドメイン：%s\n売り手：%s\n売却価格：%s\n\n管理画面の Market ページで審査してください。",
    'mail.market_approved_subject' => '%s の出品を公開しました',
    'mail.market_approved_body' => "%s の出品が審査を通過し、取引コーナーに表示されています。\n\n購入者はあなたが記入した連絡用メールアドレスに連絡します。申請は 1 件ずつコンソールにも記録されます。",
    'mail.market_rejected_subject' => '%s の出品は否認されました',
    'mail.market_rejected_body' => "%s の出品は審査を通過しませんでした。\n\n理由：%s\n\nコンソールで修正して再提出できます。",
    'mail.market_buyer_subject' => '%s への購入申請',
    'mail.market_buyer_body'  => "%s（%s）の購入を申請しました。\n\n売り手の連絡用メールアドレス：%s\n価格と移転方法はそのアドレスに直接ご連絡ください。レジストリは取引に関与しません。",
    'mail.market_seller_subject' => '%s を購入したい人がいます',
    'mail.market_seller_body' => "%s に関心を持つ購入者がいます。\n\nお名前：%s\nメールアドレス：%s\nメッセージ：%s\n\nそのアドレスに直接ご連絡ください。レジストリは取引に関与しません。",
    'chat.heading'       => 'レジストリとのオンライン相談',
    'chat.intro'         => '何でもここでご質問ください。このページで返信し、同時にメールもお送りします。ページは'
        . '自動で更新され、返信がそのまま表示されます。',
    'chat.refresh_note'  => '%s 秒ごとに自動更新します —— JavaScript は不要です。',
    'chat.leave_heading' => 'メッセージ',
    'chat.leave_intro'   => 'まだログインしていません。お名前とメールアドレスを入力してください。メールで'
        . '返信します。コンソールアカウント（%s）を使うと、やり取りはアカウントに保存されます。',
    'chat.your_name'     => 'お名前',
    'chat.your_email'    => 'メールアドレス',
    'chat.email_hint'    => '返信はこのメールアドレスに届きます。確実に確認するアドレスを入力してください。',
    'chat.your_message'  => 'メッセージ',
    'chat.send'          => '送信',
    'chat.sent'          => 'メッセージを受け付けました。ここで、そしてメールで返信します。',
    'chat.need_name'     => 'お名前を入力してください。',
    'chat.thread'        => 'やり取り #%s',
    'chat.state'         => '状態',
    'chat.status_open'   => '進行中',
    'chat.status_archived' => 'アーカイブ済み（1 週間メッセージなし）',
    'chat.addressed'     => '返信先',
    'chat.keep'          => 'メッセージの保存',
    'chat.keep_value'    => 'メッセージは削除できません。いつでも書き出せます',
    'chat.no_messages'   => 'まだメッセージはありません。',
    'chat.write'         => 'メッセージを書く',
    'chat.from_staff'    => 'レジストリ',
    'chat.from_me'       => 'あなた',
    'chat.from_user'     => 'ユーザー',
    'chat.from_guest'    => 'ゲスト',
    'chat.empty'         => '先に内容を入力してください。',
    'chat.too_long'      => 'メッセージが %s 文字を超えています。',
    'chat.too_fast'      => '2 通の間は数秒空けてください。',
    'chat.rate_note'     => '2 通の間隔は最低 %s 秒、1 通は最大 %s 文字です。',
    'chat.archived_note' => 'このやり取りは 1 週間メッセージがなかったためアーカイブされました。もう 1 通送れば'
        . '再開します。',
    'chat.export'        => '自分のメッセージを書き出す',
    'chat.export_title'  => 'やり取り #%s（相手：%s）',
    'chat.refresh'       => '今すぐ更新',
    'chat.refresh_manual_hint' => 'このページは自動更新しないため、入力中の内容が消えることはありません。'
        . '相手の返信を確認したいときは下のボタンを押してください。',
    'chat.no_table'      => 'データベースにチャット関連のテーブルがありません。upgrade-chat.sql を'
        . '導入してください（セルフチェックページが SQL を示します）。',
    'mail.chat_new_subject' => '新しいチャットメッセージ',
    'mail.chat_new_body' => "%s（%s）が書きました：\n\n%s\n\n管理画面の Chat ページで返信してください。",
    'mail.chat_reply_subject' => 'レジストリが返信しました',
    'mail.chat_reply_body' => "レジストリがあなたのメッセージに返信しました：\n\n%s\n\nチャットページで引き続きやり取りできます。",

    /* ============ 第 18 バッチ：クォータショップ ============ */
    'shop.heading'      => 'クォータショップ：登録クォータを増やす',
    'shop.intro'        => 'アカウントの登録クォータを増やす方法は 3 つあり、いずれも無料です。特典はすべて'
        . '元のクォータに加算されます。',
    'shop.current'      => 'あなたのクォータ',
    'shop.names'        => '合計 %s 件のドメイン',
    'shop.unlimited'    => '無制限',
    'shop.used'         => '使用済み %s 件',
    'shop.earned'       => 'うち %s 件はショップ特典',
    'shop.bonus'        => '特典',
    'shop.status'       => '状態',
    'shop.when'         => '日時',
    'shop.status_pending'  => '審査待ち',
    'shop.status_approved' => '承認済み',
    'shop.status_rejected' => '却下',
    'shop.no_table'     => 'データベースにショップ関連のテーブル/項目がありません。upgrade-shop.sql を'
        . '導入してください（セルフチェックページが SQL を示します）。',
    'shop.item_star'    => 'GitHub でプロジェクトに Star を付ける',
    'shop.star_intro'   => '当方の GitHub リポジトリに Star を付けると、アカウントに %s 件の追加クォータが'
        . '付与されます。その Star があなた本人のものか確認するため、先に GitHub アカウントを連携する必要が'
        . 'あります。',
    'shop.star_off'     => '現在 Star 特典は提供していません。',
    'shop.star_done'    => '受け取り済み（%s）。ご支援ありがとうございます！',
    'shop.star_need_link' => '先に GitHub アカウントを連携してください：%s。',
    'shop.star_submit'  => 'Star を付けました。すぐ確認する',
    'shop.star_ok'      => 'ご支援ありがとうございます！このアカウントに %s 件のドメインクォータを追加しました。',
    'shop.item_promo'   => 'プラットフォームで当方を紹介する',
    'shop.promo_intro'  => 'ブログ、フォーラム、グループ、動画で当方を紹介しましたか？リンクをお送りください：'
        . '審査に通るたびに %s 件のクォータが加算され、最大 %s 件まで貯まります。',
    'shop.promo_left'   => 'すでに %s 件を獲得、あと %s 件獲得できます。',
    'shop.promo_full'   => '紹介特典は上限に達しました。広めてくださりありがとうございます！',
    'shop.promo_url'    => '紹介リンク',
    'shop.promo_url_hint' => '当方に言及している公開ページ（https://…）。審査時に管理者が開いて確認します。',
    'shop.promo_description' => '何を書いて、どこに公開しましたか？',
    'shop.promo_submit' => '審査に送信',
    'shop.promo_sent'   => '送信しました。レジストリが審査し、メールで通知します。',
    'shop.item_invite'  => '専用招待コード',
    'shop.invite_intro' => 'これがあなた専用の招待コードと招待リンクです。あなたのリンクから登録した人は'
        . 'あなたの実績に数えられます：現在 %s 人、うち有効なドメイン %s 件。',
    'shop.invite_code'  => 'あなたの招待コード',
    'shop.invite_link'  => 'あなたの招待リンク',
    'shop.invite_people' => 'あなたが連れてきた人数',
    'shop.invite_active' => '彼らの有効なドメイン数',
    'shop.invite_pending' => '（ほか %s 件が審査待ち）',
    'shop.invite_hint'  => 'リンクをどこでも共有してください。リンクから登録したユーザーは、初めてコンソールに'
        . '入ったときにあなたの実績に記録されます。管理画面の Shop ページの数字もこれと完全に一致し、管理者は'
        . 'それを基に特典を付与できます。',
    'shop.people_short' => '人',
    'shop.active_short' => '件の有効ドメイン',
    'val.shop_off'      => '現在クォータショップは閉じています。',
    'val.shop_cap'      => '紹介特典は上限（%s）に達しました。',
    'val.shop_url'      => 'http:// または https:// で始まるリンクを入力してください。',
    'val.shop_description' => '公開した内容と場所を簡単に説明してください。',
    'mail.shop_new_subject' => '%s が紹介を送信しました',
    'mail.shop_new_body' => "%s（%s）が紹介を送信しました。\n\nリンク：%s\n説明：%s\n\n管理画面の Shop ページで審査してください。",
    'mail.shop_approved_subject' => 'あなたの紹介が承認されました',
    'mail.shop_approved_body' => "ご支援ありがとうございます！アカウントに %s 件のクォータが加算され、紹介特典はあと %s 件獲得できます。",
    'mail.shop_rejected_subject' => 'あなたの紹介は承認されませんでした',
    'mail.shop_rejected_body' => "あなたの紹介は審査を通過しませんでした。\n\n理由：%s\n\n紹介内容が明確に確認できる別のリンクで再提出できます。",

    /* ============ 第 19 バッチ（最終）：通知メール・転送・アカウント検証 ============ */
    'mail.request_subject'   => '[freedns] 新しい申請：%s',
    'mail.request_intro'     => '新しいドメイン申請を受け取りました。',
    'mail.change_subject'    => '[freedns] レコード変更：%s',
    'mail.reserved'          => '予約ドメインの申請：はい（予約ドメイン経由で送信）',
    'mail.change_decision_subject' => '[freedns] %s - レコード変更%s',
    'mail.approved_short'    => '承認',
    'mail.rejected_short'    => '却下',
    'mail.change_intro'      => '審査待ちのレコード変更があります。',
    'mail.decision_subject'  => '[freedns] %s - 申請 %s',
    'mail.dear'              => '%s 様',
    'mail.request_state'     => '申請されたドメイン %s の現在の状態は %s です。',
    'mail.change_state'      => '%s で送信された（%s）レコード変更は%s',
    'mail.approved'          => '承認され公開されました。',
    'mail.rejected'          => '却下されました。',
    'mail.records'           => '現在保存されているレコード：',
    'mail.records_now'       => '現在公開されているレコード：',
    'mail.records_proposed'  => 'そのとき提案されたレコード：',
    'mail.note_registry'     => 'レジストリからのメモ：',
    'mail.note_applicant'    => '申請者のメモ：',
    'mail.check_status'      => '状態はいつでもこちらで確認できます：%s',
    'mail.manage'            => '有効なドメインのレコードはこちらで管理します：%s',
    'mail.review_at'         => '審査画面：%s',
    'mail.domain'            => 'ドメイン  ：%s',
    'mail.zone'              => 'サフィックス：%s',
    'mail.code'              => '申請番号  ：%s',
    'mail.applicant'         => '申請者    ：%s <%s>',
    'mail.organization'      => '組織      ：%s',
    'mail.country'           => '国・地域  ：%s',
    'mail.records_label'     => 'レコード：',
    'mail.purpose'           => '用途の説明：',
    'mail.submitted'         => '送信時刻  ：%s',
    'mail.alert_subject'     => '公開チェック：%s 件の新しい問題',
    'mail.alert_intro'       => 'ドメインの上流レコードが保存内容と一致していません。このメールは問題が'
        . '**新たに発生または変化した**ときだけ送信されます。システムは何も自動修復していません：',
    'mail.alert_footer'      => '管理画面の「Published records」ページで個別に修復するか、そこで全件の '
        . 'reconcile を実行できます。',
    'mail.srv_expire_subject' => '%s の有効期限が近づいています',
    'mail.srv_expire_body' => "クラウドサーバー %s は %s に期限切れになります（残り %s 日）。\n\nレジストリは通知のみ行います：ご自身の RainYun アカウントで更新してください。代理支払いも預かりもしません。",
    'mail.srv_expire_footer' => 'コンソールの「私のクラウドサーバー」で、連携済みサーバーと有効期限を確認できます。',
    'mail.abuse_body' => "迷惑行為の報告を受け取りました。\n\nドメイン：%s\n種類：%s\n報告者：%s\n\n%s\n\n管理画面の Abuse ページで対応してください。",
    'mail.heading'      => 'メール転送',
    'mail.intro'        => '自分のドメインで受信し、実際のメールアドレスに転送します。Cloudflare が名前解決する'
        . 'サフィックスにのみ有効です（レジストリが Email Routing を有効化し、転送ルールを作成します）。',
    'mail.add_heading'  => '転送アドレスを追加',
    'mail.add_hint'     => 'ドメイン、@ の前の部分、受け取りたい実際のメールアドレスを選びます。',
    'mail.name'         => 'ドメイン',
    'mail.local'        => 'アドレス（@ の前）',
    'mail.forward_to'   => '転送先',
    'mail.confirm_hint' => '初回は Cloudflare がそのメールアドレスに確認リンクを送ります。確認すると転送が'
        . '有効になります。',
    'mail.add'          => '転送を作成',
    'mail.mine'         => '自分の転送アドレス',
    'mail.no_routes'    => 'まだ転送アドレスはありません。',
    'mail.address'      => 'アドレス',
    'mail.status'       => '状態',
    'mail.when'         => '作成日時',
    'mail.remove'       => '削除',
    'mail.added'        => '作成しました。Cloudflare から届いた確認メールで確認すると受信できるようになります。',
    'mail.removed'      => '転送を削除しました。',
    'mail.none'         => 'Cloudflare が名前解決する有効なドメインがないため、メール転送は使えません。',
    'mail.off'          => 'メール転送は現在停止しています。',
    'mail.no_table'     => 'データベースにこの機能に必要なテーブルがありません。upgrade-mailroute.sql を'
        . '導入してください（セルフチェックページから実行できます）。',
    'mail.rules'        => '転送されるのは入力したアドレスだけです。それ以外は Cloudflare が直接拒否します。'
        . '独自 NS に委任したドメインはこの機能を使えません（メールはご自身の DNS が処理します）。転送を'
        . '削除すると Cloudflare 上のルールも削除されます。レジストリはメール内容を読まず、保存もしません。',
    'mail.status_active'  => '有効',
    'mail.status_pending' => '保留中',
    'mail.status_error'   => 'Cloudflare に拒否されました',
    'val.password_mismatch'   => '入力したパスワードが一致しません。',
    'val.username_short'      => 'ユーザー名は 3 文字以上必要です。',
    'val.username_long'       => 'ユーザー名は最大 32 文字です。',
    'val.username_chars'      => 'ユーザー名は英字で始まり、英数字・アンダースコア・ハイフンのみ使用できます。',
    'val.username_reserved'   => 'このユーザー名は予約名です。別の名前をお試しください。',
    'val.username_taken'      => 'このユーザー名はすでに使われています。',
    'val.user_email_taken'    => 'このメールアドレスはすでに登録されています。',
    'val.password_short'      => 'パスワードは 8 文字以上必要です。',
    'val.password_long'       => 'パスワードが長すぎます（最大 200 文字）。',
    'val.password_weak'       => 'パスワードはユーザー名やメールアドレスと同じにできません。',
    'val.user_current_password' => '現在のパスワードが正しくありません。',
    'val.user_credentials'    => 'ユーザー名、メールアドレス、またはパスワードが正しくありません。',
    'val.user_pending'        => 'このアカウントはまだ有効化されていません。確認メールのリンクを開いてください。',
    'val.user_suspended'      => 'このアカウントは停止されています。レジストリにご連絡ください。',
    'val.user_token'          => 'リンクの有効期限が切れたか、すでに使用されています。',
    'val.user_policy'         => '先に登録ポリシーに同意してください。',
    'val.user_invite_code'    => '招待コードが存在しません。ない場合は空欄にしてください。',
    'val.user_closed'         => '現在、新規登録を停止しています。',
    'val.user_limit'          => '本日このアドレスからの登録が多すぎます。明日またお試しください。',
    'val.user_no_table'       => 'データベースにユーザーコンソール用のテーブルがありません。レジストリが '
        . 'upgrade-users.sql を導入する必要があります。',
    'val.user_delete_confirm' => '先に確認のチェックを入れてください。',
    'mail.user_verify_subject' => '[%s] メールアドレスを確認してください',
    'mail.user_verify_intro'  => 'ようこそ！次のリンクを開いて、アカウント「%s」が使うメールアドレスを'
        . '確認してください：',
    'mail.user_verify_expire' => 'このリンクの有効期限は 3 日です。',
    'mail.user_verify_ignore' => '心当たりがない場合は、このメールを無視してください。影響はありません。',
    'mail.user_reset_subject' => '[%s] 新しいパスワードの設定',
    'mail.user_reset_intro'   => 'あなたのアカウントでパスワード再設定が申請されました。次のリンクを開いて'
        . '新しいパスワードを設定してください：',
    'mail.user_reset_expire'  => 'このリンクの有効期限は 2 時間です。',
    'mail.user_reset_ignore'  => '心当たりがない場合は、このメールを無視してください。パスワードは変わりません。',
];

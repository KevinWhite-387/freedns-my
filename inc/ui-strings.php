<?php
declare(strict_types=1);

/**
 * freedns.my - wording for the pages added later (developer, vc.ci).
 *
 * Those pages started with an inline "is_zh() ? … : …" which only ever produced Chinese or
 * English. The registry ships six languages, so the strings live here instead, in one table:
 * adding a language means adding one column, and fixing a sentence means touching one line.
 *
 *   ui('developer.title')            the string in the visitor's language
 *   ui('developer.title', [], 'ja')  forced language
 *
 * A missing translation falls back to English, then to the key itself - never to an empty page.
 */

function ui_languages(): array
{
    return ['en', 'zh', 'zh-hant', 'ja', 'ko', 'ms'];
}

/** The language the visitor is reading right now. */
function ui_lang(): string
{
    $lang = function_exists('lang') ? strtolower(trim((string)lang())) : 'en';
    if (!in_array($lang, ui_languages(), true)) {
        $lang = 'en';
    }

    return $lang;
}

/** @return array<string,array<string,string>> */
function ui_table(): array
{
    static $table = null;
    if ($table !== null) {
        return $table;
    }

    $table = [
        'home.browser_notice' => [
            'en'      => 'This site supports Microsoft Internet Explorer 6.0 and later. We recommend Microsoft Edge, Google Chrome, Apple Safari or Mozilla Firefox.',
            'zh'      => '本站支持 Microsoft Internet Explorer 6.0 以上浏览器访问，建议使用 Microsoft Edge / Google Chrome / Apple Safari / Mozilla Firefox 浏览器访问。',
            'zh-hant' => '本站支援 Microsoft Internet Explorer 6.0 以上瀏覽器存取，建議使用 Microsoft Edge / Google Chrome / Apple Safari / Mozilla Firefox 瀏覽器存取。',
            'ja'      => '当サイトは Microsoft Internet Explorer 6.0 以降に対応しています。Microsoft Edge / Google Chrome / Apple Safari / Mozilla Firefox のご利用をおすすめします。',
            'ko'      => '이 사이트는 Microsoft Internet Explorer 6.0 이상을 지원합니다. Microsoft Edge / Google Chrome / Apple Safari / Mozilla Firefox 사용을 권장합니다.',
            'ms'      => 'Laman ini menyokong Microsoft Internet Explorer 6.0 dan ke atas. Kami mengesyorkan Microsoft Edge, Google Chrome, Apple Safari atau Mozilla Firefox.',
        ],
        /* ---------------------------------------------------------- developer */
        'developer.title' => [
            'en'      => 'Developer',
            'zh'      => '开发者',
            'zh-hant' => '開發者',
            'ja'      => '開発者',
            'ko'      => '개발자',
            'ms'      => 'Pembangun',
        ],
        'developer.status' => [
            'en'      => 'Status',
            'zh'      => '状态',
            'zh-hant' => '狀態',
            'ja'      => '状態',
            'ko'      => '상태',
            'ms'      => 'Status',
        ],
        'developer.account' => [
            'en'      => 'Account',
            'zh'      => '账号',
            'zh-hant' => '帳號',
            'ja'      => 'アカウント',
            'ko'      => '계정',
            'ms'      => 'Akaun',
        ],
        'developer.is_developer' => [
            'en'      => 'Developer',
            'zh'      => '开发者',
            'zh-hant' => '開發者',
            'ja'      => '開発者',
            'ko'      => '개발자',
            'ms'      => 'Pembangun',
        ],
        'developer.yes' => [
            'en' => 'yes', 'zh' => '是', 'zh-hant' => '是', 'ja' => 'はい', 'ko' => '예', 'ms' => 'ya',
        ],
        'developer.no' => [
            'en' => 'no', 'zh' => '否', 'zh-hant' => '否', 'ja' => 'いいえ', 'ko' => '아니요', 'ms' => 'tidak',
        ],
        'developer.waiting' => [
            'en'      => 'requested, waiting for review',
            'zh'      => '已申请，等待审核',
            'zh-hant' => '已申請，等待審核',
            'ja'      => '申請済み、審査待ち',
            'ko'      => '신청됨, 검토 대기 중',
            'ms'      => 'dimohon, menunggu semakan',
        ],
        'developer.open_types' => [
            'en'      => 'Open types',
            'zh'      => '公开类型',
            'zh-hant' => '公開類型',
            'ja'      => '公開タイプ',
            'ko'      => '공개 유형',
            'ms'      => 'Jenis terbuka',
        ],
        'developer.extra_types' => [
            'en'      => 'Extra types approved',
            'zh'      => '已批准的额外类型',
            'zh-hant' => '已批准的額外類型',
            'ja'      => '承認済みの追加タイプ',
            'ko'      => '승인된 추가 유형',
            'ms'      => 'Jenis tambahan diluluskan',
        ],
        'developer.apply_heading' => [
            'en'      => 'Apply for developer status',
            'zh'      => '申请开发者身份',
            'zh-hant' => '申請開發者身分',
            'ja'      => '開発者ステータスを申請',
            'ko'      => '개발자 자격 신청',
            'ms'      => 'Mohon status pembangun',
        ],
        'developer.apply_intro' => [
            'en'      => 'The open types above need no permission. Everything else needs a developer account, and the suffix provider must support it too.',
            'zh'      => '上面那些公开类型人人都能用。其余类型需要开发者身份，并且还要看该后缀的服务商是否支持。',
            'zh-hant' => '上面那些公開類型人人都能用。其餘類型需要開發者身分，並且還要看該後綴的服務商是否支援。',
            'ja'      => '上記の公開タイプは誰でも使えます。それ以外は開発者アカウントが必要で、さらにサフィックスのプロバイダが対応している必要があります。',
            'ko'      => '위의 공개 유형은 누구나 사용할 수 있습니다. 나머지는 개발자 계정이 필요하며, 해당 서픽스의 공급자가 지원해야 합니다.',
            'ms'      => 'Jenis terbuka di atas boleh digunakan oleh sesiapa. Yang lain memerlukan akaun pembangun, dan penyedia akhiran itu juga mesti menyokongnya.',
        ],
        'developer.reason' => [
            'en'      => 'What you need it for (required)',
            'zh'      => '用途说明（必填，写清楚你要做什么记录）',
            'zh-hant' => '用途說明（必填，寫清楚你要做什麼記錄）',
            'ja'      => '用途（必須・どのようなレコードが必要か）',
            'ko'      => '용도 (필수, 어떤 레코드가 필요한지)',
            'ms'      => 'Untuk apa (wajib, nyatakan rekod apa yang diperlukan)',
        ],
        'developer.submit' => [
            'en'      => 'Submit request',
            'zh'      => '提交申请',
            'zh-hant' => '提交申請',
            'ja'      => '申請する',
            'ko'      => '신청 제출',
            'ms'      => 'Hantar permohonan',
        ],
        'developer.record_heading' => [
            'en'      => 'Request one gated record',
            'zh'      => '申请写入一条非公开记录',
            'zh-hant' => '申請寫入一筆非公開記錄',
            'ja'      => '非公開レコードを 1 件申請',
            'ko'      => '비공개 레코드 1건 신청',
            'ms'      => 'Mohon satu rekod terhad',
        ],
        'developer.record_intro' => [
            'en'      => 'Once approved the record is written and published to the DNS provider. Types the provider does not accept are not offered.',
            'zh'      => '批准后会自动写入并发布到 DNS 服务商。服务商不支持的类型不会出现在下拉里。',
            'zh-hant' => '批准後會自動寫入並發佈到 DNS 服務商。服務商不支援的類型不會出現在下拉裡。',
            'ja'      => '承認されるとレコードが書き込まれ、DNS プロバイダへ公開されます。プロバイダが対応していないタイプは表示されません。',
            'ko'      => '승인되면 레코드가 기록되고 DNS 공급자에게 게시됩니다. 공급자가 지원하지 않는 유형은 표시되지 않습니다.',
            'ms'      => 'Setelah diluluskan, rekod ditulis dan diterbitkan ke penyedia DNS. Jenis yang tidak disokong penyedia tidak dipaparkan.',
        ],
        'developer.name' => [
            'en'      => 'Name',
            'zh'      => '域名',
            'zh-hant' => '網域',
            'ja'      => 'ドメイン名',
            'ko'      => '도메인',
            'ms'      => 'Nama domain',
        ],
        'developer.type' => [
            'en'      => 'Type',
            'zh'      => '类型',
            'zh-hant' => '類型',
            'ja'      => 'タイプ',
            'ko'      => '유형',
            'ms'      => 'Jenis',
        ],
        'developer.host' => [
            'en'      => 'Host (empty or @ for the apex)',
            'zh'      => '主机记录（根域留空或 @）',
            'zh-hant' => '主機記錄（根域留空或 @）',
            'ja'      => 'ホスト名（頂点は空または @）',
            'ko'      => '호스트 (apex는 비우거나 @)',
            'ms'      => 'Hos (kosong atau @ untuk apex)',
        ],
        'developer.value' => [
            'en'      => 'Value',
            'zh'      => '记录值',
            'zh-hant' => '記錄值',
            'ja'      => 'レコード値',
            'ko'      => '레코드 값',
            'ms'      => 'Nilai rekod',
        ],
        'developer.value_hint' => [
            'en'      => 'MX as "10 mx1.example.com"; TLSA/SVCB/HTTPS in the RFC field order',
            'zh'      => 'MX 写「10 mx1.example.com」；TLSA/SVCB/HTTPS 按 RFC 的字段顺序写',
            'zh-hant' => 'MX 寫「10 mx1.example.com」；TLSA/SVCB/HTTPS 依 RFC 的欄位順序寫',
            'ja'      => 'MX は「10 mx1.example.com」のように。TLSA/SVCB/HTTPS は RFC の項目順で。',
            'ko'      => 'MX는 "10 mx1.example.com" 형식. TLSA/SVCB/HTTPS는 RFC 필드 순서대로.',
            'ms'      => 'MX sebagai "10 mx1.example.com"; TLSA/SVCB/HTTPS ikut susunan medan RFC',
        ],
        'developer.reason_short' => [
            'en'      => 'Reason',
            'zh'      => '理由',
            'zh-hant' => '理由',
            'ja'      => '理由',
            'ko'      => '이유',
            'ms'      => 'Sebab',
        ],
        'developer.submit_record' => [
            'en'      => 'Submit record request',
            'zh'      => '提交记录申请',
            'zh-hant' => '提交記錄申請',
            'ja'      => 'レコード申請を送信',
            'ko'      => '레코드 신청 제출',
            'ms'      => 'Hantar permohonan rekod',
        ],
        'developer.mine' => [
            'en'      => 'My requests',
            'zh'      => '我的申请',
            'zh-hant' => '我的申請',
            'ja'      => '自分の申請',
            'ko'      => '내 신청',
            'ms'      => 'Permohonan saya',
        ],
        'developer.nothing' => [
            'en'      => 'Nothing yet.',
            'zh'      => '还没有申请。',
            'zh-hant' => '還沒有申請。',
            'ja'      => 'まだありません。',
            'ko'      => '아직 없습니다.',
            'ms'      => 'Belum ada.',
        ],
        'developer.col_status' => [
            'en' => 'Status', 'zh' => '状态', 'zh-hant' => '狀態', 'ja' => '状態', 'ko' => '상태', 'ms' => 'Status',
        ],
        'developer.col_sent' => [
            'en' => 'Sent', 'zh' => '提交', 'zh-hant' => '提交', 'ja' => '送信', 'ko' => '제출', 'ms' => 'Dihantar',
        ],
        'developer.col_note' => [
            'en' => 'Note', 'zh' => '备注', 'zh-hant' => '備註', 'ja' => 'メモ', 'ko' => '메모', 'ms' => 'Nota',
        ],
        'developer.st_pending' => [
            'en' => 'waiting', 'zh' => '等待中', 'zh-hant' => '等待中', 'ja' => '待機中', 'ko' => '대기 중', 'ms' => 'menunggu',
        ],
        'developer.st_approved' => [
            'en' => 'approved', 'zh' => '已批准', 'zh-hant' => '已批准', 'ja' => '承認済み', 'ko' => '승인됨', 'ms' => 'diluluskan',
        ],
        'developer.st_rejected' => [
            'en' => 'rejected', 'zh' => '已驳回', 'zh-hant' => '已駁回', 'ja' => '却下', 'ko' => '거부됨', 'ms' => 'ditolak',
        ],
        'developer.status_request' => [
            'en'      => 'developer status',
            'zh'      => '开发者身份',
            'zh-hant' => '開發者身分',
            'ja'      => '開発者ステータス',
            'ko'      => '개발자 자격',
            'ms'      => 'status pembangun',
        ],
        'developer.footnote' => [
            'en'      => 'The open types can be edited at any time without this page.',
            'zh'      => '公开类型可以随时直接改，不需要来这里。',
            'zh-hant' => '公開類型可以隨時直接改，不需要來這裡。',
            'ja'      => '公開タイプはいつでも直接編集できます。このページは不要です。',
            'ko'      => '공개 유형은 언제든 바로 수정할 수 있습니다.',
            'ms'      => 'Jenis terbuka boleh disunting bila-bila masa tanpa halaman ini.',
        ],
        'developer.no_table' => [
            'en'      => 'The developer_requests table is missing: import upgrade-record-types.sql first.',
            'zh'      => '数据库还缺表：请先导入 upgrade-record-types.sql。',
            'zh-hant' => '資料庫還缺表：請先匯入 upgrade-record-types.sql。',
            'ja'      => 'developer_requests テーブルがありません。upgrade-record-types.sql を先に取り込んでください。',
            'ko'      => 'developer_requests 테이블이 없습니다. upgrade-record-types.sql을 먼저 가져오세요.',
            'ms'      => 'Jadual developer_requests tiada: import upgrade-record-types.sql dahulu.',
        ],
        'developer.none_available' => [
            'en'      => '(none: configure a provider for the suffix first)',
            'zh'      => '（没有可用类型：先给后缀配置服务商）',
            'zh-hant' => '（沒有可用類型：先給後綴設定服務商）',
            'ja'      => '（利用可能なタイプがありません。先にサフィックスのプロバイダを設定してください）',
            'ko'      => '（사용 가능한 유형 없음: 먼저 서픽스 공급자를 설정하세요）',
            'ms'      => '(tiada: tetapkan penyedia untuk akhiran itu dahulu)',
        ],

        /* -------------------------------------------------------------- vc.ci */
        'vcci.title' => [
            'en'      => 'Sign in with vc.ci',
            'zh'      => '通过 vc.ci 登录',
            'zh-hant' => '透過 vc.ci 登入',
            'ja'      => 'vc.ci でサインイン',
            'ko'      => 'vc.ci로 로그인',
            'ms'      => 'Log masuk dengan vc.ci',
        ],
        'vcci.signin' => [
            'en'      => 'Sign in with vc.ci',
            'zh'      => '用 vc.ci 登录',
            'zh-hant' => '用 vc.ci 登入',
            'ja'      => 'vc.ci でサインイン',
            'ko'      => 'vc.ci로 로그인',
            'ms'      => 'Log masuk dengan vc.ci',
        ],
        'vcci.intro' => [
            'en'      => 'Use your vc.ci account to sign in here. The first sign-in creates a local account, and your vc.ci domains can be migrated automatically.',
            'zh'      => '用你的 vc.ci 账户登录本站。第一次登录会自动建本地账号，你的 vc.ci 域名也可以自动迁移过来。',
            'zh-hant' => '用你的 vc.ci 帳號登入本站。第一次登入會自動建立本機帳號，你的 vc.ci 網域也可以自動遷移過來。',
            'ja'      => 'vc.ci アカウントでここにサインインできます。初回はローカルアカウントが作成され、vc.ci のドメインも自動で移行できます。',
            'ko'      => 'vc.ci 계정으로 로그인할 수 있습니다. 처음 로그인하면 로컬 계정이 생성되고, vc.ci 도메인도 자동으로 이전됩니다.',
            'ms'      => 'Gunakan akaun vc.ci anda untuk log masuk. Log masuk pertama mencipta akaun tempatan, dan domain vc.ci boleh dipindahkan secara automatik.',
        ],
        'vcci.link_status' => [
            'en'      => 'Link status',
            'zh'      => '绑定状态',
            'zh-hant' => '綁定狀態',
            'ja'      => '連携状態',
            'ko'      => '연결 상태',
            'ms'      => 'Status pautan',
        ],
        'vcci.linked' => [
            'en'      => 'linked', 'zh'      => '已绑定', 'zh-hant' => '已綁定',
            'ja'      => '連携済み', 'ko'      => '연결됨', 'ms'      => 'dipautkan',
        ],
        'vcci.not_linked' => [
            'en'      => 'not linked', 'zh'      => '未绑定', 'zh-hant' => '未綁定',
            'ja'      => '未連携', 'ko'      => '연결 안 됨', 'ms'      => 'tidak dipautkan',
        ],
        'vcci.link' => [
            'en'      => 'Link a vc.ci account', 'zh'      => '绑定 vc.ci 账户',
            'zh-hant' => '綁定 vc.ci 帳號', 'ja'      => 'vc.ci アカウントを連携',
            'ko'      => 'vc.ci 계정 연결', 'ms'      => 'Pautkan akaun vc.ci',
        ],
        'vcci.unlink' => [
            'en' => 'Unlink', 'zh' => '解绑', 'zh-hant' => '解綁', 'ja' => '連携解除', 'ko' => '연결 해제', 'ms' => 'Nyahpaut',
        ],
        'vcci.last_migration' => [
            'en'      => 'Last migration', 'zh'      => '上次自动迁移', 'zh-hant' => '上次自動遷移',
            'ja'      => '前回の自動移行', 'ko'      => '마지막 자동 이전', 'ms'      => 'Pemindahan terakhir',
        ],
        'vcci.migrate_now' => [
            'en'      => 'Migrate my vc.ci domains now', 'zh'      => '立即迁移我在 vc.ci 的域名',
            'zh-hant' => '立即遷移我在 vc.ci 的網域', 'ja'      => 'vc.ci のドメインを今すぐ移行',
            'ko'      => '내 vc.ci 도메인 지금 이전', 'ms'      => 'Pindahkan domain vc.ci saya sekarang',
        ],
        'vcci.not_configured' => [
            'en'      => 'Not configured yet: fill in the vc.ci client_id / client_secret and import upgrade-vcci.sql first.',
            'zh'      => '还没配置好：请先在后台填写 vc.ci 的 client_id / client_secret，并导入 upgrade-vcci.sql。',
            'zh-hant' => '還沒設定好：請先在後台填寫 vc.ci 的 client_id / client_secret，並匯入 upgrade-vcci.sql。',
            'ja'      => '未設定です。vc.ci の client_id / client_secret を設定し、upgrade-vcci.sql を取り込んでください。',
            'ko'      => '아직 설정되지 않았습니다. vc.ci client_id / client_secret을 입력하고 upgrade-vcci.sql을 가져오세요.',
            'ms'      => 'Belum dikonfigurasikan: isi client_id / client_secret vc.ci dan import upgrade-vcci.sql dahulu.',
        ],
        'vcci.missing_columns' => [
            'en'      => 'The database is missing the vc.ci columns: import upgrade-vcci.sql first.',
            'zh'      => '数据库还缺列：请先导入 upgrade-vcci.sql。',
            'zh-hant' => '資料庫還缺欄位：請先匯入 upgrade-vcci.sql。',
            'ja'      => 'データベースに vc.ci の列がありません。upgrade-vcci.sql を取り込んでください。',
            'ko'      => '데이터베이스에 vc.ci 열이 없습니다. upgrade-vcci.sql을 먼저 가져오세요.',
            'ms'      => 'Pangkalan data tiada lajur vc.ci: import upgrade-vcci.sql dahulu.',
        ],
        'vcci.privacy' => [
            'en'      => 'Only what vc.ci grants is read here: account id, user name, e-mail and the domain list. Never a password.',
            'zh'      => '本站只会拿到 vc.ci 授权的资料：账号 id、用户名、邮箱、域名列表；不会拿到密码。',
            'zh-hant' => '本站只會取得 vc.ci 授權的資料：帳號 id、使用者名稱、電子郵件、網域清單；不會取得密碼。',
            'ja'      => 'ここで取得するのは vc.ci が許可した情報のみです（アカウント ID、ユーザー名、メール、ドメイン一覧）。パスワードは取得しません。',
            'ko'      => 'vc.ci가 허용한 정보만 가져옵니다: 계정 id, 사용자 이름, 이메일, 도메인 목록. 비밀번호는 가져오지 않습니다.',
            'ms'      => 'Hanya maklumat yang dibenarkan vc.ci dibaca: id akaun, nama pengguna, e-mel dan senarai domain. Bukan kata laluan.',
        ],
        'vcci.please_sign_in' => [
            'en'      => 'Please sign in first, then link the vc.ci account.',
            'zh'      => '请先登录，再绑定 vc.ci 账户。',
            'zh-hant' => '請先登入，再綁定 vc.ci 帳號。',
            'ja'      => '先にサインインしてから vc.ci アカウントを連携してください。',
            'ko'      => '먼저 로그인한 뒤 vc.ci 계정을 연결하세요.',
            'ms'      => 'Sila log masuk dahulu, kemudian pautkan akaun vc.ci.',
        ],
        'vcci.unlinked_ok' => [
            'en'      => 'The vc.ci account was unlinked.', 'zh'      => '已解绑 vc.ci 账户。',
            'zh-hant' => '已解綁 vc.ci 帳號。', 'ja'      => 'vc.ci アカウントの連携を解除しました。',
            'ko'      => 'vc.ci 계정 연결을 해제했습니다.', 'ms'      => 'Akaun vc.ci telah dinyahpaut.',
        ],
        'vcci.linked_ok' => [
            'en'      => 'The vc.ci account is now linked.', 'zh'      => 'vc.ci 账户已绑定 ✓',
            'zh-hant' => 'vc.ci 帳號已綁定 ✓', 'ja'      => 'vc.ci アカウントを連携しました ✓',
            'ko'      => 'vc.ci 계정이 연결되었습니다 ✓', 'ms'      => 'Akaun vc.ci kini dipautkan ✓',
        ],
        'vcci.signed_in' => [
            'en'      => 'Signed in with vc.ci.', 'zh'      => '已用 vc.ci 登录 ✓',
            'zh-hant' => '已用 vc.ci 登入 ✓', 'ja'      => 'vc.ci でサインインしました ✓',
            'ko'      => 'vc.ci로 로그인했습니다 ✓', 'ms'      => 'Log masuk dengan vc.ci ✓',
        ],
        'vcci.account_created' => [
            'en'      => 'Account created from vc.ci and signed in.', 'zh'      => '已用 vc.ci 创建账号并登录 ✓',
            'zh-hant' => '已用 vc.ci 建立帳號並登入 ✓', 'ja'      => 'vc.ci からアカウントを作成してサインインしました ✓',
            'ko'      => 'vc.ci에서 계정을 만들어 로그인했습니다 ✓', 'ms'      => 'Akaun dicipta daripada vc.ci dan log masuk ✓',
        ],
    ];

    return $table;
}

/**
 * @param array<int,string> $args printf-style replacements, in order of %s
 */
function ui(string $key, array $args = [], ?string $forceLang = null): string
{
    $table = ui_table();
    $lang  = $forceLang !== null && in_array($forceLang, ui_languages(), true) ? $forceLang : ui_lang();

    $row = $table[$key] ?? [];
    $text = (string)($row[$lang] ?? ($row['en'] ?? $key));

    if ($args !== []) {
        try {
            $text = vsprintf($text, $args);
        } catch (Throwable $e) {
            /* a broken placeholder must never blank a page */
        }
    }

    return $text;
}

<?php
/**
 * freedns.my - Bahasa Melayu (kelompok pertama, hanya kunci teras).
 *
 * Kunci yang belum diterjemah akan jatuh balik ke bahasa Inggeris secara automatik (lihat
 * 多语言-扩展方案.md). Istilah: domain / e-mel / pelayan / pendaftar / akaun / sokongan.
 */

return [

    /* freedns.my の翻訳。未訳の部分は英語で表示されます。 */
    'nav.home'      => 'Utama',
    'nav.request'   => 'Mohon domain',
    'nav.status'    => 'Semak permohonan',
    'nav.domains'   => 'Domain berdaftar',
    'nav.doc'       => 'Dokumentasi',
    'nav.whois'     => 'Carian WHOIS',
    'nav.market'    => 'Pasaran domain',
    'nav.shop'      => 'Kedai kuota',
    'nav.chat'      => 'Sembang langsung',
    'nav.changelog' => 'Log perubahan',
    'nav.console'   => 'Konsol',
    'index.heading' => 'Dapatkan domain anda secara percuma',
    'index.announcement' => 'Pengumuman',
    'index.zones'   => 'Akhiran yang boleh dimohon',
    'index.group_heading' => 'Sertai kumpulan',
    'request.heading' => 'Mohon domain',
    'request.submit'  => 'Hantar permohonan',
    'request.name'    => 'Domain yang dikehendaki',
    'request.policy_link' => 'Dasar pendaftaran',
    'status.heading' => 'Semak status permohonan',
    'status.pending_text' => 'Permohonan sedang menunggu semakan manusia; biasanya selesai dalam beberapa hari dan anda tidak perlu berbuat apa-apa.',
    'status.rejected_text' => 'Permohonan tidak diluluskan. Anda boleh perbaiki dan memohon semula domain yang sama.',

    /* ============ Kelompok 1: navigasi · pengaki · halaman utama · umum ============ */
    'nav.manage'          => 'Urus rekod',
    'nav.admin'           => 'Pentadbir',
    'nav.lang_en'         => 'English',
    'nav.lang_zh'         => '中文',
    'nav.lang_title'      => 'Bahasa',
    'nav.signin'          => 'Log masuk',
    'nav.register'        => 'Daftar akaun',
    'nav.signout'         => 'Log keluar',
    'nav.theme_title'     => 'Tema',
    'nav.theme_light'     => 'Cerah',
    'nav.theme_dark'      => 'Gelap',
    'nav.theme_auto'      => 'Ikut sistem',
    'footer.tagline'      => '%s &mdash; pendaftar domain percuma.',
    'footer.contact'      => 'Hubungi: ',
    'footer.generated'    => 'Halaman dijana pada %s(%s).',
    'error.list_title'    => 'Sila betulkan masalah berikut:',
    'error.token_title'   => 'Borang telah tamat tempoh',
    'error.token_body'    => 'Borang ini telah tamat tempoh atau token keselamatan tidak sepadan. Sila kembali, '
        . 'muat semula halaman dan hantar semula.',
    'status.active'       => 'Aktif',
    'status.pending'      => 'Menunggu semakan',
    'status.rejected'     => 'Ditolak',
    'status.suspended'    => 'Digantung',
    'status.deleted'      => 'Dipadam',
    'common.optional'     => '(pilihan)',
    'common.none'         => '-',
    'index.intro1'         => 'Selamat datang ke <strong>%s</strong>: kami memberikan domain di bawah '
        . '<code>%s</code> secara percuma. Pendaftaran seratus peratus percuma, pembaharuan juga percuma, dan tiada '
        . 'iklan pada halaman ini.',
    'index.intro_records'  => 'Pendaftar ini menguruskan DNS domain tersebut secara terus: domain yang diluluskan akan '
        . 'menerbitkan rekod <code>A</code>, <code>AAAA</code>, <code>CNAME</code> dan <code>TXT</code> yang kami '
        . 'simpan.',
    'index.intro_manage'   => 'Setelah domain diaktifkan, anda boleh mengubah rekod dan menambah subdomain seperti '
        . '<code>www.myname</code> pada halaman %s. Perubahan akan diterbitkan selepas kelulusan pentadbir.',
    'index.intro_psl'      => 'Bagi akhiran yang dibenarkan oleh pihak atasan (us.ci, de5.net dan lain-lain yang '
        . 'tersenarai dalam %s), anda boleh menetapkan pelayan nama sendiri dari konsol — berkuat kuasa serta-merta '
        . 'tanpa semakan.',
    'index.group_text'     => 'Soalan, pendapat dan pertukaran maklumat dengan pengguna lain dialu-alukan di kumpulan '
        . 'Telegram %s.',
    'index.no_zone'        => 'Belum ada akhiran yang dikonfigurasikan. Pentadbir perlu menambahnya dahulu di '
        . '<em>Pentadbir → Zones</em>.',
    'index.col_zone'       => 'Akhiran',
    'index.col_requests'   => 'Permohonan',
    'index.col_active'     => 'Aktif',
    'index.col_notes'      => 'Keterangan',
    'index.open'           => 'Dibuka',
    'index.closed'         => 'Ditutup',
    'index.status_h'       => 'Status pendaftar',
    'index.form'           => 'Borang permohonan',
    'index.form_open'      => '<span class="ok">Dibuka</span> &mdash; kami sedang menerima permohonan',
    'index.form_closed'    => '<span class="err">Ditutup</span> &mdash; kami tidak menerima permohonan sekarang',
    'index.active_names'   => 'Domain aktif',
    'index.awaiting'       => 'Permohonan menunggu semakan',
    'index.review_time'    => 'Masa semakan',
    'index.review_time_v'  => 'Biasanya dalam beberapa hari, disemak oleh manusia',
    'index.price'          => 'Kos',
    'index.price_v'        => 'Percuma, pembaharuan juga percuma',
    'index.howto'          => 'Cara memohon domain',
    'index.step1'          => 'Tentukan akhiran yang hendak digunakan dan alamat atau nama hos yang hendak dihubungkan '
        . 'dengan domain ini.',
    'index.step2'          => 'Isi %s: pilih akhiran, taip domain, dan masukkan sekurang-kurangnya satu rekod '
        . '(<code>A</code>, <code>AAAA</code> atau <code>CNAME</code>).',
    'index.step3'          => 'Simpan nombor permohonan yang dipaparkan selepas penghantaran. Itu satu-satunya kunci '
        . 'untuk menyemak kemajuan dan mengurus rekod kemudian.',
    'index.step4'          => 'Manusia akan menyemak permohonan anda. Setelah diluluskan, rekod akan diterbitkan dalam '
        . 'zon berkenaan dan anda boleh menambah subdomain serta rekod TXT.',
    'index.step5'          => 'Seterusnya anda boleh menukar alamat atau menambah subdomain di %s, dan menyemak status '
        . 'semasa di %s.',
    'index.documents'      => 'Dokumen',
    'index.documents_text' => 'Sila baca %s sebelum memohon. Permohonan yang tidak mematuhi dasar akan ditolak.',
    'index.contact'        => 'Hubungi',
    'index.contact_q'      => 'Soalan tentang permohonan tertentu: %s. Sila sertakan nombor permohonan dalam e-mel.',
    'index.contact_none'   => 'Sila gunakan borang permohonan atau halaman semakan. Kami tidak menyediakan sokongan '
        . 'telefon.',
    'index.small_print'    => 'Perkhidmatan ini dijalankan oleh sukarelawan mengikut kemampuan dan tidak menjamin tahap '
        . 'perkhidmatan. Domain yang disalahgunakan, digunakan untuk spam atau phishing akan ditarik balik.',
    'index.docs_link'      => 'Dasar pendaftaran dan dokumentasi',
    'index.manage_link'    => 'Urus rekod',
    'index.psl_link'       => 'Public Suffix List (senarai akhiran awam)',

    /* ============ Kelompok 2: borang permohonan ============ */
    'request.intro'          => 'Mohon domain melalui borang di bawah. <strong>*</strong> bermaksud wajib. Permohonan '
        . 'disemak oleh manusia, jadi sila baca %s terlebih dahulu.',
    'request.closed'         => 'Borang permohonan ditutup buat sementara.',
    'request.no_zone'        => 'Belum ada akhiran dikonfigurasikan. Pentadbir perlu menambahnya di '
        . '<em>Pentadbir → Zones</em> sebelum permohonan boleh diterima.',
    'request.s1'             => '1. Pilih akhiran',
    'request.s2'             => '2. Domain yang dikehendaki',
    'request.s3'             => '3. Tentang anda',
    'request.s4'             => '4. Rekod',
    'request.s5'             => '5. Kegunaan',
    'request.s6'             => '6. Dasar',
    'request.zone_closed'    => '(tidak menerima permohonan baharu buat masa ini)',
    'request.label'          => 'Domain *',
    'request.label_hint'     => 'Masukkan bahagian label sahaja (contoh: <code>myname</code>). Domain penuh akan '
        . 'menjadi <code>myname.%s</code>. Subdomain seperti <code>www.myname</code> boleh ditambah selepas domain '
        . 'diaktifkan.',
    'request.reserved_toggle' => 'Ini permohonan domain terpelihara',
    'request.reserved_hint'  => 'Tandakan hanya jika pendaftar menyimpan domain itu untuk anda. Domain terpelihara '
        . 'boleh terdiri daripada satu aksara, mesti ada dalam senarai simpanan pendaftar, dan apabila ditanda, '
        . 'kegunaan pada bahagian 5 menjadi wajib.',
    'request.proxied_toggle' => 'Terbitkan melalui proksi Cloudflare (awan oren)',
    'request.proxied_hint'   => 'Jika tidak ditanda, ia DNS only (penyelesaian nama sahaja) dan inilah cara biasa. '
        . 'Jika ditanda, trafik A, AAAA dan CNAME melalui Cloudflare (TXT sentiasa tanpa proksi). Anda boleh '
        . 'menyembunyikan alamat sebenar, tetapi sesetengah negara atau wilayah mungkin menjadi perlahan. Selepas '
        . 'dihantar, hanya pendaftar boleh mengubah tetapan ini.',
    'request.check_heading'  => 'Bolehkah domain ini masih dimohon?',
    'request.check_hint'     => 'Sebelum menerima permohonan, pendaftar menyemak sama ada nama itu sudah digunakan '
        . 'dalam zon DNS %s. Butang di bawah menunjukkan hasilnya dengan segera; langkah ini tidak menghantar '
        . 'permohonan.',
    'request.check_submit'   => 'Semak rekod DNS dahulu',
    'request.precheck_ok'    => 'Penyedia DNS belum mempunyai rekod untuk %s. Anda boleh memohon.',
    'request.name_hint'      => '<code>@</code> merujuk kepada domain yang dimohon itu sendiri. Subdomain tidak boleh '
        . 'dimohon di sini. Nilainya ialah alamat IPv4, alamat IPv6 atau nama hos.',
    'request.col_name'       => 'Nama',
    'request.col_type'       => 'Jenis',
    'request.col_value'      => 'Nilai',
    'request.name_placeholder'  => '@',
    'request.value_placeholder' => 'Alamat IPv4, alamat IPv6 atau nama hos',
    'request.purpose'        => 'Ringkasan kegunaan (pilihan)',
    'request.purpose_hint'   => 'Satu atau dua ayat sudah memadai. Ia membantu semakan dan tidak akan diterbitkan.',
    'request.purpose_reserved_hint' => 'Wajib untuk permohonan domain terpelihara.',
    'request.policy_accept'  => 'Saya telah membaca dan bersetuju dengan %s *',
    'request.done_title'     => 'Permohonan anda telah diterima.',
    'request.done_keep'      => 'Simpan nombor permohonan di bawah. Itu satu-satunya kunci untuk menyemak kemajuan dan '
        . 'mengurus rekod kemudian.',
    'request.done_code'      => 'Nombor permohonan:',
    'request.done_domain'    => 'Domain:',
    'request.done_zone'      => 'Akhiran:',
    'request.done_status'    => 'Status:',
    'request.done_submitted' => 'Masa hantar:',
    'request.done_records'   => 'Rekod bagi permohonan ini &mdash; domain akan diselesaikan seperti berikut:',
    'request.done_review'    => 'Pentadbir akan menyemak secara manual. Setelah diputuskan, status menjadi '
        . '<code>ACTIVE</code> (aktif) atau <code>REJECTED</code> (ditolak), dan rekod di atas akan diterbitkan dalam '
        . 'zon <code>%s</code>.',
    'request.done_mail'      => 'Jika e-mel boleh sampai, kami juga memberitahu %s.',
    'request.done_manage'    => 'Selepas diluluskan, gunakan nombor permohonan dan alamat e-mel ini untuk mengubah '
        . 'rekod, menambah subdomain dan rekod <code>TXT</code> pada halaman %s.',
    'request.done_check'     => 'Anda boleh menyemak status semasa di %s pada bila-bila masa.',
    'request.back_home'      => 'Kembali ke utama',

    /* ============ Kelompok 3: semakan status dan pengurusan rekod ============ */
    'status.intro'           => 'Masukkan nombor permohonan yang dipaparkan selepas penghantaran dan alamat e-mel yang '
        . 'anda gunakan ketika itu. Kami akan menunjukkan status semasa dan rekod yang disimpan.',
    'status.code'            => 'Nombor permohonan *',
    'status.code_hint'       => 'Contoh: <code>FD-25-1A2B3C4D</code>',
    'status.email'           => 'Alamat e-mel yang digunakan semasa memohon *',
    'status.show'            => 'Semak status',
    'status.status'          => 'Status',
    'status.zone'            => 'Akhiran',
    'status.applicant'       => 'Pemohon',
    'status.org'             => 'Organisasi',
    'status.submitted'       => 'Masa hantar',
    'status.updated'         => 'Kemas kini terakhir',
    'status.decided'         => 'Masa keputusan',
    'status.records'         => 'Bilangan rekod',
    'status.published'       => 'Rekod berikut diterbitkan untuk domain ini:',
    'status.kept'            => 'Rekod berikut disimpan untuk permohonan ini:',
    'status.no_record'       => '; tiada rekod',
    'status.change_title'    => 'Terdapat perubahan menunggu semakan',
    'status.change_text'     => 'Anda menghantar perubahan rekod di %s, tetapi ia masih menunggu semakan pentadbir. '
        . 'Rekod di atas kekal berkuat kuasa sehingga diluluskan.',
    'status.proposed'        => 'Rekod yang dicadangkan:',
    'status.note'            => 'Nota daripada pendaftar',
    'status.active_text'     => 'Rekod di atas berkuat kuasa dalam zon <code>%s</code>. Halakan perkhidmatan anda ke '
        . 'alamat atau nama hos yang dihantar. Anda boleh mengubah rekod atau menambah subdomain di %s; perubahan '
        . 'akan diterbitkan selepas semakan. Bagi akhiran yang dibenarkan pihak atasan, anda boleh bertukar kepada '
        . 'pelayan nama sendiri melalui 「Pelayan nama」 di konsol (berkuat kuasa serta-merta, tanpa semakan).',
    'manage.heading'         => 'Urus rekod',
    'manage.notice_heading'  => 'Notis penting',
    'manage.closed_title'    => 'Halaman ini ditutup buat sementara',
    'manage.closed_text'     => 'Buat masa ini hanya akaun yang telah log masuk boleh menghantar perubahan rekod. '
        . 'Sila log masuk ke konsol dengan alamat e-mel yang digunakan semasa memohon (atau daftar dahulu); selepas '
        . 'log masuk, halaman ini berfungsi seperti biasa.',
    'manage.closed_login'    => 'Log masuk konsol',
    'manage.closed_register' => 'Daftar akaun',
    'manage.intro'           => 'Masukkan nombor permohonan dan alamat e-mel yang digunakan semasa memohon untuk '
        . 'mengubah rekod domain itu dan menambah subdomain seperti <code>www.myname</code>. Bagi permohonan yang '
        . 'masih menunggu semakan, perubahan disimpan serta-merta (pada peringkat ini tiada apa-apa diterbitkan); '
        . 'selepas domain menjadi <strong>aktif</strong>, setiap perubahan akan masuk ke barisan semakan dan '
        . 'diterbitkan selepas kelulusan pentadbir.',
    'manage.code'            => 'Nombor permohonan *',
    'manage.code_hint'       => 'Nombor yang anda terima semasa memohon domain (contoh: <code>FD-25-1A2B3C4D</code>). '
        . 'Jika anda terlupa, hubungi alamat e-mel di pengaki.',
    'manage.email'           => 'Alamat e-mel yang digunakan semasa memohon *',
    'manage.open'            => 'Buka pengurusan rekod',
    'manage.can_do'          => 'Apa yang boleh dilakukan di sini',
    'manage.can_1'           => 'membetulkan rekod bagi permohonan yang menunggu semakan (disimpan serta-merta);',
    'manage.can_2'           => 'mengubah rekod domain aktif (masuk ke barisan semakan);',
    'manage.can_3'           => 'menambah subdomain (contoh: <code>www.myname</code>, <code>mail.myname</code>, '
        . '<code>shop.myname</code>);',
    'manage.can_4'           => 'menambah rekod CNAME dan TXT;',
    'manage.can_5'           => 'memadam rekod yang tidak diperlukan lagi;',
    'manage.can_6'           => 'membatalkan perubahan yang menunggu semakan.',
    'manage.hint_review'     => 'Perubahan pada domain aktif akan diterbitkan selepas semakan manusia.',
    'manage.hint_disabled'   => ' Pendaftar kini menutup ciri perubahan kendiri.',
    'manage.submitted_title' => 'Perubahan dihantar dan sedang menunggu semakan.',
    'manage.name_label'      => 'Domain:',
    'manage.code_label'      => 'Nombor permohonan:',
    'manage.submitted_label' => 'Masa hantar:',
    'manage.proposed'        => 'Rekod yang dicadangkan:',
    'manage.what_changes'    => 'Perubahan',
    'manage.added'           => 'Ditambah',
    'manage.removed'         => 'Dipadam',
    'manage.kept'            => 'Tiada perubahan',
    'manage.kept_n'          => '%d rekod tidak berubah',
    'manage.stay_live'       => 'Rekod yang berkuat kuasa sekarang kekal sehingga perubahan diluluskan. Anda boleh '
        . 'membatalkannya sendiri sementara menunggu:',
    'manage.withdraw'        => 'Batalkan perubahan ini',
    'manage.withdraw_queued' => 'Batalkan perubahan dalam barisan',
    'manage.check_status'    => 'Semak status',
    'manage.manage_again'    => 'Urus rekod semula',
    'manage.queued_notice'   => 'Perubahan yang dihantar di %s masih menunggu semakan. Borang di bawah bermula '
        . 'daripada cadangan itu; menghantar semula akan menggantikannya.',
    'manage.records_live'    => 'Rekod yang berkuat kuasa sekarang',
    'manage.records_on_file' => 'Rekod yang disimpan',
    'manage.disabled'        => 'Pendaftar menutup ciri perubahan rekod kendiri. Sila hubungi alamat e-mel di pengaki '
        . 'dengan menyertakan nombor permohonan.',
    'manage.not_editable'    => 'Rekod hanya boleh diubah bagi permohonan yang menunggu semakan atau domain '
        . '<code>ACTIVE</code> (aktif). Status semasa: %s.',
    'manage.pending_notice'  => 'Permohonan masih menunggu semakan, jadi rekod ini belum diterbitkan. Perubahan di sini '
        . 'disimpan serta-merta dan akan dinilai semasa semakan. Selepas domain diaktifkan, setiap perubahan masuk ke '
        . 'barisan semakan.',
    'manage.active_notice'   => 'Menghantar akan meletakkan keseluruhan set rekod ke dalam barisan semakan. Tiada '
        . 'apa-apa diterbitkan sehingga pentadbir meluluskannya, dan rekod yang berkuat kuasa kekal sehingga itu.',
    'manage.records_of'      => 'Rekod untuk %s',
    'manage.col_name'        => 'Nama',
    'manage.col_type'        => 'Jenis',
    'manage.col_value'       => 'Nilai',
    'manage.col_remove'      => 'Padam',
    'manage.col_ttl'         => 'TTL',
    'manage.col_proxy'       => 'Proksi',
    'manage.apex'            => '@',
    'manage.add_records'     => 'Tambah rekod',
    'manage.limits'          => 'Jenis yang tersedia: <code>A</code>, <code>AAAA</code> (alamat IPv4/IPv6 awam, '
        . 'maksimum 4 bagi nama yang sama), <code>CNAME</code> (nama hos dan mesti satu-satunya rekod bagi nama itu), '
        . '<code>TXT</code> (maksimum 255 aksara). Maksimum %d setiap domain.',
    'manage.note_label'      => 'Nota untuk penyemak (pilihan)',
    'manage.submit'          => 'Hantar untuk semakan',

    /* ============ Kelompok 4: pengesahan rekod dan hasil perubahan ============ */
    'rec.row_empty'        => 'Baris %d: nilai rekod %s kosong. Jika anda mahu memadam rekod ini, tandakan '
        . '「Padam」.',
    'rec.new_row_empty'    => 'Baris %d baharu: sila masukkan nilai rekod %s.',
    'rec.name_invalid'     => 'Bahagian nama "%s" tidak sah: apex ialah <code>@</code>, subdomain ialah satu label '
        . 'dengan huruf, angka dan sengkang sahaja.',
    'rec.type_unsupported' => 'Jenis rekod "%s" tidak disokong: A, AAAA, CNAME, TXT, MX, SRV, CAA dan PTR tersedia.',
    'rec.ttl_range'        => 'Baris %s: TTL mesti 1 (auto) atau 60 hingga 86400 saat.',
    'rec.ttl_hint'         => 'Unit ialah saat, 60 hingga 86400. 1 ialah TTL auto Cloudflare. Biarkan nombor yang '
        . 'dipaparkan untuk menggunakan nilai lalai pendaftar.',
    'rec.ttl_auto'         => 'Auto',
    'rec.type_not_here'    => 'Rekod %s tidak boleh dimohon di sini. Jenis yang dibenarkan semasa memohon: %s.',
    'rec.txt_later'        => 'Rekod TXT tidak boleh dimasukkan semasa memohon. Ia boleh ditambah selepas domain '
        . 'diaktifkan.',
    'rec.subnames_later'   => 'Subdomain %s tidak boleh dimasukkan semasa memohon. Tambahkannya di halaman pengurusan '
        . 'selepas domain diaktifkan.',
    'rec.a_invalid'        => 'Nilai rekod A mesti alamat IPv4 (nilai diterima "%s").',
    'rec.aaaa_invalid'     => 'Nilai rekod AAAA mesti alamat IPv6 (nilai diterima "%s").',
    'rec.not_public'       => 'Alamat %s bukan alamat yang boleh dihalakan secara awam.',
    'rec.cname_target'     => 'Sasaran CNAME mesti nama hos (contoh: example.net, nilai diterima "%s").',
    'rec.cname_self'       => 'CNAME tidak boleh menghala ke dirinya sendiri (%s).',
    'rec.txt_length'       => 'Nilai TXT maksimum 255 aksara.',
    'rec.txt_control'      => 'Nilai TXT tidak boleh mengandungi aksara kawalan atau baris baharu.',
    'rec.mx_format'   => 'Format rekod MX ialah 「10 mail.example.com」: keutamaan + alamat pelayan mel.',
    'rec.mx_target'   => '"%s" bukan nama hos pelayan mel yang sah.',
    'rec.mx_self'     => 'Pelayan mel tidak boleh dihalakan ke domain itu sendiri ("%s").',
    'rec.srv_format'  => 'Format rekod SRV ialah 「10 5 443 target.example.com」: keutamaan, pemberat, port dan '
        . 'sasaran ("%s").',
    'rec.srv_numbers' => 'Tiga nilai pertama rekod SRV mesti nombor antara 0 dan 65535.',
    'rec.srv_target'  => '"%s" bukan nama hos sasaran yang sah.',
    'rec.srv_self'    => 'Sasaran SRV tidak boleh dihalakan ke domain itu sendiri ("%s").',
    'rec.caa_format'  => 'Format rekod CAA ialah 「0 issue "letsencrypt.org"」: flag, tag (issue / issuewild / iodef) '
        . 'dan nilai ("%s").',
    'rec.caa_flags'   => 'Flag rekod CAA mesti antara 0 dan 255.',
    'rec.caa_target'  => '"%s" bukan domain pihak berkuasa sijil yang sah.',
    'rec.caa_iodef'   => 'Nilai iodef mesti alamat http(s) atau alamat mailto: ("%s").',
    'rec.ptr_bad'     => '"%s" bukan nama hos yang sah.',
    'rec.ptr_same'    => 'Rekod PTR tidak boleh dihalakan ke domain itu sendiri ("%s").',
    'rec.duplicate'        => 'Rekod %s %s %s berganda.',
    'rec.cname_one_only'   => 'Nama %s akan mempunyai %d rekod CNAME; hanya satu dibenarkan.',
    'rec.cname_exclusive'  => 'Nama %s sudah mempunyai rekod CNAME, jadi jenis lain tidak boleh diletakkan.',
    'rec.need_apex_record' => 'Apex memerlukan sekurang-kurangnya satu rekod A, AAAA atau CNAME.',
    'rec.need_apex_cname'  => 'Apex memerlukan rekod CNAME.',
    'rec.subnames_zone_off' => 'Akhiran %s tidak menyediakan subdomain; hanya rekod untuk %s sendiri tersedia.',
    'rec.too_many'         => 'Maksimum %d rekod setiap domain. Set sekarang mengandungi %d.',
    'change.withdrawn'        => 'Perubahan telah dibatalkan.',
    'change.nothing_pending'  => 'Tiada perubahan menunggu semakan sekarang.',
    'change.updated_pending'  => 'Rekod dikemas kini; permohonan masih menunggu semakan.',
    'change.disabled'         => 'Ciri perubahan rekod pada halaman ini ditutup. Sila hubungi alamat e-mel di pengaki.',
    'change.not_editable'     => 'Rekod hanya boleh diubah bagi permohonan yang menunggu semakan atau domain '
        . '<code>ACTIVE</code> (aktif). Status semasa: %s.',
    'change.save_error'       => 'Perubahan tidak dapat disimpan kerana ralat pelayan. Sila cuba lagi sebentar lagi.',

    /* ============ Kelompok 5: mesej pengesahan borang ============ */
    'val.zone_choose'      => 'Sila pilih salah satu akhiran yang disenaraikan di bawah.',
    'val.zone_closed'      => 'Akhiran %s tidak menerima permohonan baharu buat masa ini.',
    'val.no_zone'          => 'Belum ada akhiran dikonfigurasikan, jadi permohonan tidak boleh diterima.',
    'val.form_closed'      => 'Borang permohonan ditutup buat sementara. Sila cuba sebentar lagi.',
    'val.spam'             => 'Penghantaran disekat oleh penapis spam.',
    'val.name_required'    => 'Sila masukkan domain yang hendak didaftarkan.',
    'val.name_dots'        => 'Hanya satu label boleh didaftarkan di sini. Sila masukkan "%s" sahaja; subdomain seperti '
        . '<code>www.%s</code> boleh ditambah selepas domain diaktifkan.',
    'val.name_length'      => 'Nama domain mesti 2 hingga 63 aksara.',
    'val.name_length_reserved' => 'Domain terpelihara boleh 1 hingga 63 aksara.',
    'val.reserved_not_listed' => 'Domain "%s" tiada dalam senarai simpanan pendaftar, jadi 「permohonan domain '
        . 'terpelihara」 tidak boleh digunakan. Sila nyah tanda.',
    'val.reserved_use_channel' => 'Domain "%s" disimpan oleh pendaftar. Untuk memohon, tandakan 「Ini domain '
        . 'terpelihara」 (bahagian 2) dan isi kegunaannya.',
    'val.reserved_purpose' => 'Permohonan domain terpelihara memerlukan penjelasan: sila isi kegunaan pada bahagian 5.',
    'val.reserved_no_table' => 'Pelayan belum mempunyai senarai domain terpelihara, jadi pilihan ini tidak tersedia. Sila '
        . 'hubungi pendaftar (pangkalan data perlu menjalankan upgrade-reserved.sql).',
    'val.cf_records_exist' => 'Cloudflare sudah mempunyai rekod untuk "%s" (%s kesemuanya): %s. Domain ini sudah '
        . 'digunakan; sila mohon nama lain. Jika anda rasa ini silap, hubungi pendaftar.',
    'val.cf_check_failed'  => 'Cloudflare tidak dapat ditanya tentang domain ini sekarang (%s), jadi permohonan ini '
        . 'tidak diterima. Sila cuba lagi beberapa minit kemudian.',
    'val.name_invalid'     => 'Nama domain hanya boleh mengandungi huruf kecil a-z, angka 0-9 dan sengkang, serta tidak '
        . 'boleh bermula atau berakhir dengan sengkang.',
    'val.name_reserved'    => 'Domain "%s" disimpan oleh pendaftar dan tidak boleh didaftarkan.',
    'val.applicant_required' => 'Sila masukkan nama anda.',
    'val.applicant_long'   => 'Nama terlalu panjang.',
    'val.email_required'   => 'Sila masukkan alamat e-mel.',
    'val.email_invalid'    => 'Format alamat e-mel tidak sah.',
    'val.org_long'         => 'Nama organisasi terlalu panjang.',
    'val.country_long'     => 'Negara/wilayah terlalu panjang.',
    'val.purpose_long'     => 'Keterangan kegunaan terlalu panjang (maksimum 2000 aksara).',
    'val.policy_required'  => 'Anda mesti bersetuju dengan dasar pendaftaran.',
    'val.dup_domain'       => '%s sudah berstatus 「%s」 di pendaftar ini (nombor permohonan %s).',
    'val.limit_email'      => 'Alamat e-mel ini sudah memiliki %d domain (aktif atau menunggu semakan) di %s dan telah '
        . 'mencapai had semasa.',
    'val.quota_exceeded'   => 'Alamat e-mel ini boleh memiliki maksimum %s domain merentas semua akhiran (kini %s). Had '
        . 'telah dicapai; domain yang ditolak atau dipadam akan mengembalikan kuota. Jika perlu lebih, hubungi '
        . 'pendaftar.',
    'val.name_taken'       => 'Domain "%s" sedang digunakan dalam perkhidmatan DNS akhiran itu dan tidak boleh '
        . 'dikeluarkan. Sila cuba nama lain.',
    'val.limit_ip'         => 'Terlalu banyak permohonan dari sambungan ini hari ini. Sila cuba esok.',
    'val.save_error'       => 'Permohonan tidak dapat disimpan kerana ralat pelayan. Sila cuba lagi sebentar lagi.',
    'val.lookup_missing'   => 'Sila masukkan kedua-dua nombor permohonan dan alamat e-mel.',
    'val.lookup_none'      => 'Tiada permohonan sepadan. Sila semak nombor permohonan dan alamat e-mel; nombor '
        . 'berformat FD-25-1A2B3C4D.',
    'val.throttled'        => 'Terlalu banyak percubaan. Sila cuba lagi sebentar lagi.',

    /* ============ Kelompok 6: akaun ============ */
    'account.login_heading'   => 'Log masuk',
    'account.login_intro'     => 'Log masuk dengan nama pengguna atau alamat e-mel untuk menguruskan semua domain anda '
        . 'di satu tempat.',
    'account.login_id'        => 'Nama pengguna atau alamat e-mel',
    'account.login_id_hint'   => 'Kedua-duanya boleh.',
    'account.login_submit'    => 'Log masuk',
    'account.welcome_back'    => 'Anda telah log masuk.',
    'account.password'        => 'Kata laluan',
    'account.password_hint'   => 'Sekurang-kurangnya 8 aksara, dan jangan sama dengan nama pengguna atau alamat e-mel.',
    'account.password_repeat' => 'Ulang kata laluan',
    'account.username'        => 'Nama pengguna',
    'account.username_hint'   => '3 hingga 32 aksara: huruf, angka, garis bawah dan sengkang; mesti bermula dengan '
        . 'huruf. Nama simpanan seperti admin dan www tidak dibenarkan.',
    'account.email'           => 'Alamat e-mel',
    'account.email_hint'      => 'Domain yang dimohon dengan alamat ini akan dipaparkan dalam konsol, dan pautan '
        . 'pengesahan dihantar ke alamat ini.',
    'account.register_heading' => 'Daftar akaun',
    'account.register_submit' => 'Daftar akaun',
    'account.closed'          => 'Pendaftaran akaun baharu ditutup buat masa ini.',
    'account.have_account'    => 'Sudah mempunyai akaun',
    'account.no_account'      => 'Daftar akaun baharu',
    'account.verify_heading'  => 'Pengesahan alamat e-mel',
    'account.verify_ok'       => 'Alamat e-mel telah disahkan',
    'account.verify_ok_text'  => 'Anda kini boleh menggunakan konsol: %s. Jika belum log masuk, sila %s dahulu.',
    'account.verify_fail'     => 'Pautan pengesahan ini tidak sah',
    'account.verify_fail_text' => 'Pautan telah tamat tempoh atau sudah digunakan. Selepas log masuk, hantar semula '
        . 'e-mel pengesahan atau gunakan halaman 「Lupa kata laluan」.',
    'account.verify_retry'    => 'Cuba pautan ini lagi',
    'account.verify_resend'   => 'Hantar semula e-mel pengesahan',
    'account.verify_resent'   => 'E-mel pengesahan baharu telah dihantar.',
    'account.email_unverified' => 'Alamat e-mel %s belum disahkan. Sila semak peti masuk anda (termasuk folder spam) '
        . 'atau hantar semula e-mel pengesahan di bawah.',
    'account.pending_notice'  => 'Akaun %s belum diaktifkan. Sila selesaikan pengesahan alamat e-mel dahulu.',
    'account.resend_submit'   => 'Hantar semula e-mel pengesahan',
    'account.resend_done'     => 'E-mel pengesahan baharu dihantar ke %s.',
    'account.resend_failed'   => 'Tiada akaun belum disahkan yang sepadan dengan alamat e-mel itu.',
    'account.forgot_link'     => 'Lupa kata laluan?',
    'account.forgot_heading'  => 'Tetapkan semula kata laluan',
    'account.forgot_intro'    => 'Masukkan alamat e-mel akaun anda. Kami akan menghantar e-mel untuk menetapkan kata '
        . 'laluan baharu.',
    'account.forgot_submit'   => 'Hantar pautan tetapan semula',
    'account.forgot_sent_title' => 'Jika alamat itu berdaftar, e-mel telah dihantar',
    'account.forgot_sent_text' => 'Jika %s berdaftar di sini, e-mel sudah dihantar. Pautan sah selama 2 jam.',
    'account.reset_heading'   => 'Tetapkan kata laluan baharu',
    'account.reset_intro'     => 'Tetapkan kata laluan baharu untuk akaun %s.',
    'account.reset_submit'    => 'Simpan kata laluan baharu',
    'account.reset_done_title' => 'Kata laluan telah diubah',
    'account.reset_done_text' => 'Anda boleh log masuk dengan kata laluan baharu: %s.',
    'account.reset_bad_title' => 'Pautan tetapan semula ini tidak sah',
    'account.reset_bad_text'  => 'Pautan telah tamat tempoh atau sudah digunakan. Sila mohon semula: %s.',
    'account.console_heading' => 'Konsol',
    'account.console_intro'   => 'Sedang log masuk: %s. Semua domain yang dimohon dengan alamat e-mel ini dipaparkan '
        . 'di bawah.',
    'account.member_since'    => 'Ahli sejak',
    'account.last_login'      => 'Log masuk terakhir',

    /* ============ Kelompok 7: konsol, profil dan pengesahan akaun ============ */
    'account.counts'          => 'Statistik',
    'account.counts_value'    => 'Jumlah %d domain: %d aktif, %d menunggu semakan, %d perubahan rekod menunggu semakan',
    'account.my_domains'      => 'Domain saya',
    'account.no_domains'      => 'Belum ada domain yang dimohon dengan %s. Mohon melalui 「Mohon domain」 dan ia akan '
        . 'muncul di sini.',
    'account.col_change'      => 'Perubahan menunggu',
    'account.col_actions'     => 'Tindakan',
    'account.queued'          => 'Menunggu semakan',
    'account.reserved_mark'   => 'Domain terpelihara',
    'account.action_manage'   => 'Urus rekod',
    'account.action_status'   => 'Semak status',
    'account.what_now'        => 'Apa seterusnya',
    'account.link_new'        => 'Mohon satu lagi domain: %s.',
    'account.link_profile'    => 'Tukar profil atau kata laluan: %s.',
    'account.link_export'     => 'Eksport semua domain dan rekod anda: %s.',
    'account.link_old'        => 'Tanpa akaun pun: semak dengan nombor permohonan di %s, dan urus domain individu di %s.',
    'account.manage_heading'  => 'Urus rekod',
    'account.manage_not_found' => 'Domain ini bukan milik akaun anda.',
    'account.records_now'     => 'Rekod yang berkuat kuasa sekarang',
    'account.profile_heading' => 'Maklumat akaun',
    'account.profile_intro'   => 'Sedang log masuk: %s. Perubahan di bawah memerlukan kata laluan semasa.',
    'account.current_password' => 'Kata laluan semasa',
    'account.new_username'    => 'Nama pengguna baharu',
    'account.change_password' => 'Tukar kata laluan',
    'account.new_password'    => 'Kata laluan baharu',
    'account.change_email'    => 'Tukar alamat e-mel',
    'account.new_email'       => 'Alamat e-mel baharu',
    'account.email_changed'   => 'Alamat e-mel ditukar kepada %s. Sila selesaikan pengesahan.',
    'account.profile_saved'   => 'Disimpan.',
    'account.password_changed' => 'Kata laluan telah diubah.',
    'account.nothing_changed' => 'Tiada perubahan.',
    'account.save'            => 'Simpan',
    'account.delete_heading'  => 'Padam akaun',
    'account.delete_warning'  => 'Akaun akan dipadamkan. %d domain di bawah kekal dan boleh terus diurus dengan nombor '
        . 'permohonan. Tindakan ini tidak boleh dibatalkan.',
    'account.delete_confirm'  => 'Saya sahkan pemadaman akaun saya',
    'account.delete_submit'   => 'Padam akaun',
    'account.deleted'         => 'Akaun dipadamkan. Domain anda masih kekal.',
    'account.signed_out'      => 'Anda telah log keluar dari konsol.',
    'account.please_sign_in'  => 'Sila log masuk ke konsol dahulu.',
    'account.export_heading'  => 'Eksport data saya',
    'account.old_channel_heading' => 'Pendaftaran tidak perlu jika hanya mengurus satu domain',
    'account.old_channel_text' => 'Konsol hanya kemudahan, bukan keperluan: dengan nombor permohonan dan alamat e-mel '
        . 'asal, anda masih boleh menyemak kemajuan di %s dan mengubah rekod di %s.',
    'val.password_mismatch'   => 'Kata laluan yang dimasukkan tidak sepadan.',
    'val.username_short'      => 'Nama pengguna mesti sekurang-kurangnya 3 aksara.',
    'val.username_long'       => 'Nama pengguna maksimum 32 aksara.',
    'val.username_chars'      => 'Nama pengguna mesti bermula dengan huruf dan hanya boleh mengandungi huruf, angka, '
        . 'garis bawah dan sengkang.',
    'val.username_reserved'   => 'Nama pengguna ini disimpan. Sila guna nama lain.',
    'val.username_taken'      => 'Nama pengguna ini sudah digunakan.',
    'val.user_email_taken'    => 'Alamat e-mel ini sudah didaftarkan.',
    'val.password_short'      => 'Kata laluan mesti sekurang-kurangnya 8 aksara.',
    'val.password_long'       => 'Kata laluan terlalu panjang (maksimum 200 aksara).',
    'val.password_weak'       => 'Kata laluan tidak boleh sama dengan nama pengguna atau alamat e-mel.',
    'val.user_current_password' => 'Kata laluan semasa tidak betul.',
    'val.user_credentials'    => 'Nama pengguna, alamat e-mel atau kata laluan tidak betul.',
    'val.user_pending'        => 'Akaun ini belum diaktifkan. Sila buka pautan dalam e-mel pengesahan dahulu.',
    'val.user_suspended'      => 'Akaun ini digantung. Sila hubungi pendaftar.',
    'val.user_token'          => 'Pautan telah tamat tempoh atau sudah digunakan.',
    'val.user_policy'         => 'Sila bersetuju dengan dasar pendaftaran dahulu.',
    'val.user_invite_code'    => 'Kod jemputan tidak wujud. Biarkan kosong jika tiada.',
    'val.user_closed'         => 'Pendaftaran baharu ditutup buat masa ini.',
    'val.user_limit'          => 'Terlalu banyak pendaftaran dari alamat ini hari ini. Sila cuba esok.',
    'val.user_delete_confirm' => 'Sila tandakan kotak pengesahan dahulu.',
    'val.user_no_table'       => 'Pangkalan data tiada jadual untuk konsol pengguna. Pendaftar perlu menjalankan '
        . 'upgrade-users.sql.',

    /* ============ Kelompok 8: senarai domain, dasar dan log perubahan ============ */
    'list.heading'        => 'Senarai domain',
    'list.count_one'      => '%s mendaftarkan 1 domain%s.',
    'list.count_many'     => '%s mendaftarkan %s domain%s.',
    'list.where_all'      => 'Pendaftar ini',
    'list.where_zone'     => 'Di bawah <code>%s</code>',
    'list.filtered'       => '(ditapis)',
    'list.also'           => 'Permohonan yang menunggu semakan, ditolak atau digantung tidak dipaparkan.',
    'list.search'         => 'Cari mengikut nama',
    'list.zone'           => 'Akhiran',
    'list.all_zones'      => 'Semua akhiran',
    'list.search_btn'     => 'Cari',
    'list.reset'          => 'Set semula',
    'list.col_name'       => 'Domain',
    'list.col_zone'       => 'Akhiran',
    'list.col_records'    => 'Rekod',
    'list.col_org'        => 'Organisasi',
    'list.col_registered' => 'Masa pendaftaran',
    'list.none'           => 'Tiada domain sepadan.',
    'list.more'           => '(dan %d lagi)',
    'changelog.heading'  => 'Perubahan',
    'changelog.intro'    => 'Kami merekodkan perubahan yang perlu anda tahu di sini: apa yang dibaiki dan apa yang '
        . 'ditambah. Perubahan terbaharu di atas sekali.',
    'changelog.footer'   => 'Jika ada yang tidak jelas atau masalah yang tiada di sini, beritahu kami melalui '
        . '「Sembang langsung」. Kami akan membalas di sana dan melalui e-mel.',
    'doc.heading'          => 'Dasar dan dokumentasi',
    'doc.intro'            => 'Kandungan di bawah diuruskan oleh pendaftar dan semuanya teks biasa. Halaman ini, '
        . 'halaman utama dan senarai domain tidak menggunakan kuki sama sekali. Kuki hanya digunakan pada borang '
        . 'permohonan, halaman semakan, halaman pengurusan dan pentadbir, dan tujuannya hanya untuk mencegah pemalsuan '
        . 'permintaan merentas laman.',
    'doc.policy'           => 'Dasar pendaftaran',
    'doc.documentation'    => 'Dokumentasi',
    'doc.quickref'         => 'Rujukan pantas',
    'doc.zones_offered'    => 'Akhiran yang boleh dimohon',
    'doc.none_configured'  => 'Belum dikonfigurasikan',
    'doc.closed'           => '(ditutup)',
    'doc.zone'             => 'Akhiran',
    'doc.records_required' => 'Keperluan rekod',
    'doc.records_value'    => 'Sekurang-kurangnya satu rekod <code>A</code>, <code>AAAA</code> atau <code>CNAME</code> '
        . 'pada apex. Maksimum 4 alamat bagi nama yang sama',
    'doc.record_types'     => 'Jenis rekod yang boleh diurus',
    'doc.record_types_v'   => '<code>A</code>, <code>AAAA</code>, <code>CNAME</code>, <code>TXT</code>'
        . ' &mdash; maksimum %d setiap domain',
    'doc.subnames'         => 'Subdomain',
    'doc.subnames_v'       => 'Tambah satu label di hadapan domain (contoh: <code>www.myname.%s</code>). Boleh '
        . 'ditambah selepas domain diaktifkan',
    'doc.changes'          => 'Perubahan rekod',
    'doc.changes_v'        => 'Dihantar oleh pemohon di halaman %s dan diterbitkan selepas kelulusan pentadbir',
    'doc.nameservers'      => 'Pelayan nama',
    'doc.nameservers_v'    => 'Penyelesaian nama dikendalikan oleh pendaftar. Bagi akhiran yang dibenarkan (us.ci, '
        . 'de5.net dan lain-lain dalam Public Suffix List), anda boleh menetapkan pelayan nama sendiri dari konsol '
        . '&mdash; berkuat kuasa serta-merta tanpa semakan',
    'doc.cost'             => 'Kos',
    'doc.cost_v'           => 'Percuma, pembaharuan juga percuma',
    'doc.example'          => 'Contoh rekod',
    'doc.example_intro'    => 'Contohnya domain <code>example.%s</code>: satu alamat sendiri, <code>www</code> pada '
        . 'pelayan lain, dan e-mel diserahkan kepada pembekal mel, maka pendaftar akan menerbitkan:',
    'doc.docrange'         => '<code>203.0.113.x</code> dan <code>2001:db8::/32</code> ialah julat contoh untuk '
        . 'dokumentasi dan hanya digunakan di sini untuk menerangkan cara penulisan. Borang akan menolaknya. Sila '
        . 'masukkan alamat dan nama hos yang benar-benar digunakan perkhidmatan anda.',
    'doc.tell'             => 'Beritahu pendaftar jika kandungan berubah. Anda juga boleh mengubahnya sendiri di '
        . 'halaman pengurusan. Perubahan rekod disemak oleh manusia dan biasanya selesai dalam beberapa hari.',
    'doc.why'              => 'Bila anda boleh menggunakan pelayan nama sendiri',
    'doc.why_1'            => 'Cara biasa untuk mengagihkan subdomain ialah mewakilkan dengan rekod <code>NS</code>, '
        . 'menguruskan zon sendiri dan menetapkan sebarang jenis rekod (MX, TXT, SRV dan lain-lain). Ini memerlukan '
        . 'akhiran itu tersenarai dalam <em>Public Suffix List</em> (PSL): jika tidak, pelayar, pihak berkuasa sijil '
        . 'dan penyelesai tidak akan menganggap subdomain ini sebagai akhiran awam yang berdikari, dan nama yang '
        . 'diwakilkan akan berkelakuan tidak dijangka.',
    'doc.why_2'            => 'Sehingga akhiran tersenarai dalam PSL, pendaftar mengendalikan penyelesaian nama bagi '
        . 'setiap domain yang diluluskan dan menerbitkan rekod yang disimpan. Oleh itu jenis selain <code>A</code>, '
        . '<code>AAAA</code>, <code>CNAME</code> dan <code>TXT</code> (seperti <code>MX</code> dan <code>SRV</code>) '
        . 'tidak boleh ditetapkan sendiri sekarang. Jika perlu, hubungi alamat e-mel di pengaki dengan menyertakan '
        . 'nombor permohonan.',

    /* ============ Kelompok 9: penyalahgunaan, terma, WHOIS dan had akses ============ */
    'abuse.heading'  => 'Laporan penyalahgunaan',
    'abuse.heading_short' => 'Laporan penyalahgunaan',
    'abuse.intro'    => 'Pendaftar ini mengeluarkan domain secara percuma dan mengambil penyalahgunaan dengan serius. '
        . 'Jika domain di mana-mana akhiran kami digunakan untuk phishing, spam, perisian berniat jahat, pelanggaran '
        . 'tanda dagangan atau tujuan haram lain, beritahu kami di sini — akaun tidak diperlukan.',
    'abuse.domain'   => 'Domain yang dilaporkan',
    'abuse.reporter' => 'Alamat e-mel anda',
    'abuse.reporter_hint' => 'Kami mungkin menghubungi anda untuk butiran lanjut. Maklumat ini tidak diterbitkan dan '
        . 'tidak diberitahu kepada pemilik domain.',
    'abuse.kind'     => 'Jenis apa?',
    'abuse.kind_phishing'  => 'Phishing / penipuan',
    'abuse.kind_spam'      => 'Spam',
    'abuse.kind_malware'   => 'Perisian berniat jahat',
    'abuse.kind_trademark' => 'Pelanggaran tanda dagangan',
    'abuse.kind_illegal'   => 'Kandungan haram',
    'abuse.kind_other'     => 'Lain-lain',
    'abuse.message'  => 'Apa yang anda lihat?',
    'abuse.message_hint' => 'Sila nyatakan secara objektif dengan alamat penuh (URL khusus dan sebagainya). Masa dan '
        . 'tangkapan skrin amat membantu. Laporan anda mungkin dikongsi dengan pembekal perkhidmatan di peringkat '
        . 'atasan.',
    'abuse.submit'   => 'Hantar laporan',
    'abuse.thanks'   => 'Terima kasih atas laporan anda. Ia telah diterima dan akan disemak oleh manusia; kami mungkin '
        . 'menghubungi alamat e-mel yang anda berikan.',
    'abuse.no_table' => 'Pangkalan data tiada jadual laporan. Sila jalankan upgrade-abuse.sql (boleh dijalankan dari '
        . 'halaman semakan kendiri).',
    'abuse.policy'   => 'Domain yang disalahgunakan akan digantung atau dipadam. Jika laporan tidak berasas, kami '
        . 'menolaknya dengan menyatakan sebabnya, dan kedua-dua keputusan direkodkan.',
    'legal.terms'    => 'Terma perkhidmatan',
    'legal.privacy'  => 'Privasi',
    'legal.intro'    => 'Kami menerangkan peraturan pendaftar ini dan cara data anda dikendalikan dalam bahasa yang '
        . 'mudah.',
    'legal.footer'   => 'Teks ini boleh diubah dari pentadbir pada bila-bila masa, jadi ia sentiasa menerangkan versi '
        . 'perkhidmatan yang sedang berjalan.',
    'whois.heading'           => 'Carian WHOIS',
    'whois.intro'             => 'Masukkan domain: domain yang dikeluarkan oleh pendaftar ini dijawab terus daripada '
        . 'pangkalan data pendaftaran kami, manakala yang lain diambil daripada data WHOIS awam (RDAP diutamakan, dan '
        . 'jika perlu beralih kepada perkhidmatan tradisional port 43).',
    'whois.label'             => 'Domain',
    'whois.submit'            => 'Cari',
    'whois.disabled'          => 'Ciri carian WHOIS ditutup buat masa ini.',
    'whois.invalid'           => '"%s" bukan domain yang sah. Sila gunakan format seperti myname.freedns.my atau '
        . 'example.com.',
    'whois.not_registered'    => 'Tidak ditemui: %s tidak didaftarkan dengan pendaftar ini (%s ialah salah satu akhiran '
        . 'kami). Permohonan yang menunggu semakan dan yang ditolak tidak dipaparkan.',
    'whois.external_off'      => 'Pendaftar ini hanya menjawab domain di bawah akhiran kami sendiri. Carian domain '
        . 'luar ditutup.',
    'whois.limit'             => 'Dari alamat ini anda boleh mencari %s domain luar setiap jam. Kuota telah habis; '
        . 'sila cuba sebentar lagi atau cari terus di pendaftar domain berkenaan.',
    'whois.cached'            => 'Berikut ialah hasil yang diCache (%s). Ia dikemas kini secara automatik setiap '
        . 'beberapa minit.',
    'whois.lookup_failed'     => 'Carian %s gagal: %s',
    'whois.internal_note'     => 'Domain ini didaftarkan di sini. Tiada maklumat peribadi diterbitkan: pendaftar '
        . 'menyimpan maklumat pemohon dan hanya memaparkan status dan rekod (IP ditutup dengan cara yang sama seperti '
        . 'senarai awam).',
    'whois.external_note'     => 'Hasil ini datang daripada perkhidmatan WHOIS/RDAP awam pendaftar domain berkenaan, '
        . 'dan ketepatannya adalah tanggungjawab pendaftar itu.',
    'whois.help_heading'      => 'Penjelasan',
    'whois.help_1'            => 'Domain di bawah akhiran kami dijawab terus (contoh: %s) — datanya daripada pangkalan '
        . 'data pendaftaran kami.',
    'whois.help_2'            => 'Domain lain dikendalikan melalui carian luar: RDAP dahulu, kemudian WHOIS tradisional '
        . 'port 43. Akhiran yang tiada kedua-duanya tidak boleh dijawab.',
    'whois.help_3'            => 'Carian luar dihadkan kepada %s setiap jam bagi setiap alamat dan diCache beberapa '
        . 'minit, jadi halaman ini tidak boleh digunakan sebagai proksi WHOIS percuma.',
    'block.heading'           => 'Akses dihadkan',
    'block.message'           => 'Akses dihadkan: laman ini menerima terlalu banyak permintaan daripada alamat anda '
        . 'dalam masa yang singkat. Sila hubungi pentadbir.',
    'block.remaining'         => 'Had ini akan ditarik balik secara automatik dalam kira-kira %s minit.',
    'block.contact'           => 'Hubungi pentadbir: %s',
    'block.hint'              => 'Jika anda hanya melayari seperti biasa, sila cuba lagi beberapa minit kemudian. '
        . 'Tiada perubahan pada akaun anda.',
    'health.footer'           => 'Jika ada perkara yang perlu diambil tindakan, halaman semakan kendiri boleh '
        . 'membaiki struktur pangkalan data secara terus.',

    /* ============ Kelompok 10: kunci API dan kemas kini dinamik ============ */
    'api.heading'    => 'Kunci API',
    'api.intro'      => 'Urus domain anda melalui HTTP: senarai domain, baca rekod, serta tambah, ubah dan padam rekod. '
        . 'Kunci ini milik akaun anda dan hanya boleh mengakses domain anda.',
    'api.open'       => 'Urus kunci API',
    'api.new'        => 'Cipta kunci',
    'api.create'     => 'Cipta kunci',
    'api.label'      => 'Nota kegunaan (pilihan)',
    'api.label_hint' => 'Contoh: 「Skrip saya」',
    'api.created'    => 'Kunci dicipta — salin sekarang. Ia dipaparkan sekali sahaja.',
    'api.token_once' => 'Ini kunci baharu anda. Salin sekarang: ia tidak akan dipaparkan lagi.',
    'api.mine'       => 'Kunci saya',
    'api.none'       => 'Belum ada kunci.',
    'api.hint'       => 'Hujung',
    'api.calls'      => 'Bilangan panggilan',
    'api.last'       => 'Penggunaan terakhir',
    'api.state'      => 'Status',
    'api.active'     => 'Sah',
    'api.revoked_state' => 'Dibatalkan (%s)',
    'api.revoke'     => 'Batalkan',
    'api.revoked'    => 'Kunci telah dibatalkan.',
    'api.not_yours'  => 'Kunci ini bukan milik akaun anda.',
    'api.off'        => 'API ditutup buat masa ini.',
    'api.no_table'   => 'Pangkalan data tiada jadual berkaitan API. Sila jalankan upgrade-api.sql (boleh dijalankan '
        . 'dari halaman semakan kendiri).',
    'api.docs'       => 'Cara guna',
    'api.docs_body'  => "Kunci diletakkan dalam pengepala Authorization:\n\n  GET  %s?action=domains\n  GET  %s?action=records&domain=myname.us.ci\n  POST %s?action=record.add&domain=myname.us.ci&name=@&type=A&value=1.2.3.4\n  POST %s?action=record.update&domain=myname.us.ci&index=0&value=1.2.3.5\n  POST %s?action=record.delete&domain=myname.us.ci&index=1\n\nSemua respons ialah JSON: kejayaan mengembalikan \"ok\": true, kegagalan mengembalikan kod \"error\" dan penjelasan.",
    'api.rules'      => 'Anda hanya boleh mengendalikan domain milik akaun ini yang sudah aktif. Domain yang diwakilkan '
        . 'kepada pelayan nama sendiri atau sedang dipindahkan akan ditolak perubahannya (sebabnya ada dalam respons). '
        . 'Perubahan melalui pengesahan yang sama seperti antara muka, diterbitkan serta-merta dan direkodkan dalam log '
        . 'audit.',
    'api.docs_auth'  => 'Setiap panggilan memerlukan pengepala "Authorization: Bearer kunci-anda". Contoh curl: '
        . 'curl -H "Authorization: Bearer kunci-anda" "https://freedns.my/api.php?action=domains"; bagi tiga operasi '
        . 'tulis, tambah -X POST.',
    'ddns.heading'      => 'Kemas kini dinamik (DDNS)',
    'ddns.intro'        => 'Tetapkan URL kemas kini berkunci pada domain anda: penghala, NAS, Raspberry Pi atau skrip '
        . 'menghantar alamatnya sendiri dan rekod akan mengikut secara automatik.',
    'ddns.mine'         => 'Domain akaun ini',
    'ddns.name'         => 'Domain',
    'ddns.state'        => 'URL kemas kini',
    'ddns.state_on'     => 'Aktif',
    'ddns.state_off'    => 'Belum dicipta',
    'ddns.last'         => 'Kemas kini terakhir',
    'ddns.open'         => 'Buka',
    'ddns.all'          => 'Semua domain',
    'ddns.token'        => 'Kunci kemas kini',
    'ddns.url'          => 'URL kemas kini',
    'ddns.create'       => 'Jana kunci kemas kini',
    'ddns.reset'        => 'Jana semula kunci',
    'ddns.revoke'       => 'Padam kunci',
    'ddns.created'      => 'Kunci dijana. URL kemas kini boleh digunakan.',
    'ddns.reset_done'   => 'Kunci baharu dijana. URL lama tidak lagi sah serta-merta.',
    'ddns.revoked_done' => 'Kunci dipadamkan. URL kemas kini tidak lagi berfungsi.',
    'ddns.no_token'     => 'Domain ini belum mempunyai kunci kemas kini.',
    'ddns.none'         => 'Belum ada domain aktif.',
    'ddns.off'          => 'Kemas kini dinamik ditutup buat masa ini.',
    'ddns.no_table'     => 'Pangkalan data tiada item yang diperlukan ciri ini. Sila jalankan upgrade-ddns.sql (boleh '
        . 'dijalankan dari halaman semakan kendiri).',
    'ddns.examples'     => 'Cara guna',
    'ddns.example_curl' => 'curl "%s"',
    'ddns.example_router' => "Penghala / NAS (contoh tetapan DDNS tersuai):\n  Nama hos : domain anda (contoh: myname.us.ci)\n  URL      : URL kemas kini di atas (biarkan \"&ip=\"; klien akan menambah alamat sendiri)\n  Pengguna / kata laluan : biarkan kosong. Kunci dalam URL ialah bukti kelayakan",
    'ddns.rules'        => 'Hanya rekod A dan AAAA bagi domain itu sendiri (@) dikemas kini, jadi tambah satu di editor '
        . 'rekod dahulu. Beri jarak sekurang-kurangnya 10 saat antara dua kemas kini; jika alamat tidak berubah, ia '
        . 'akan dijawab serta-merta. Simpan kunci dengan baik — sesiapa yang mengetahuinya boleh menukar alamat domain '
        . 'ini. Menekan 「Jana semula」 akan menjadikan URL lama tidak sah serta-merta.',
    'ddns.list_hint'    => 'Buka domain untuk melihat kunci, URL kemas kini yang sedia digunakan dan contohnya.',
    'ddns.status_good'  => 'Dikemas kini',
    'ddns.status_nochg' => 'Tiada perubahan alamat',
    'ddns.status_nohost' => 'Tiada rekod untuk dikemas kini',
    'ddns.status_badip' => 'Alamat tidak sah',
    'ddns.status_badauth' => 'Kunci salah',
    'ddns.status_911'   => 'Gagal menerbitkan',
    'val.ddns_own'      => 'Domain ini bukan milik akaun anda.',

    /* ============ Kelompok 11: pelayan nama dan pengesahan wakilan ============ */
    'ns.heading'         => 'Pelayan nama',
    'ns.intro'           => 'Sesetengah akhiran membenarkan domain anda dihalakan kepada pelayan nama sendiri (contoh: '
        . 'NS Cloudflare). Dengan itu pendaftar tidak lagi menjawab domain tersebut: kami hanya menerbitkan rekod '
        . 'wakilan di peringkat atasan dan menarik balik rekod sedia ada, manakala penyelesaian nama dikendalikan oleh '
        . 'perkhidmatan DNS anda.',
    'ns.open'            => 'Tetapkan pelayan nama untuk domain tertentu',
    'ns.name'            => 'Domain',
    'ns.zone'            => 'Akhiran',
    'ns.state'           => 'Status',
    'ns.state_delegated' => 'Diwakilkan kepada pelayan nama anda',
    'ns.state_here'      => 'Dijawab oleh pendaftar ini',
    'ns.servers'         => 'Pelayan nama semasa',
    'ns.server'          => 'Pelayan nama %s',
    'ns.glue_hint'       => 'Glue hanya diperlukan apabila pelayan nama berada dalam domain itu sendiri (contoh: '
        . 'ns1.domainanda.us.ci): masukkan juga alamatnya.',
    'ns.glue_host'       => 'Nama hos Glue',
    'ns.glue_value'      => 'Alamat Glue (IPv4 atau IPv6)',
    'ns.apply'           => 'Guna pelayan nama ini',
    'ns.clear'           => 'Kembali kepada jawapan pendaftar ini',
    'ns.none'            => 'Tiada domain aktif yang boleh ditetapkan NS tersuai (ciri ini perlu dihidupkan bagi '
        . 'akhiran berkenaan).',
    'ns.no_table'        => 'Pangkalan data tiada item berkaitan wakilan. Sila jalankan upgrade-ns.sql (halaman semakan '
        . 'kendiri akan menunjukkan SQL).',
    'ns.applied'         => 'Disimpan — domain ini kini diwakilkan kepada pelayan nama anda.',
    'ns.cleared'         => 'Wakilan dibatalkan dan pendaftar ini menjawab semula. Sila masukkan semula rekod anda.',
    'ns.note'            => 'Wakilan berkuat kuasa serta-merta tanpa semakan. Semasa diwakilkan, rekod tidak boleh '
        . 'diubah di sini, dan domain hanya akan diselesaikan jika perkhidmatan DNS anda menjawab dengan betul.',
    'ns.read_only'       => 'Domain ini menggunakan pelayan nama sendiri, jadi rekod tidak boleh diubah di sini.',
    'val.ns_own'         => 'Domain ini bukan milik akaun anda.',
    'val.ns_zone_off'    => 'Akhiran ini tidak menyokong pelayan nama tersuai.',
    'val.ns_not_active'  => 'Hanya domain aktif boleh diwakilkan.',
    'val.ns_publish'     => 'Akhiran ini tidak mempunyai penerbitan automatik, jadi wakilan tidak boleh dihantar ke '
        . 'peringkat atasan. Sila hubungi pendaftar.',
    'val.ns_host'        => '"%s" bukan nama hos pelayan nama yang sah.',
    'val.ns_ours'        => 'Pelayan nama pendaftar sendiri tidak boleh digunakan untuk wakilan.',
    'val.ns_few'         => 'Sila masukkan sekurang-kurangnya dua pelayan nama.',
    'val.ns_many'        => 'Maksimum empat pelayan nama.',
    'val.ns_glue_pair'   => 'Glue memerlukan nama hos dan alamat.',
    'val.ns_glue_inside' => 'Glue hanya boleh digunakan untuk pelayan nama di dalam %2$s ("%1$s" tidak memenuhi syarat).',
    'val.ns_glue_listed' => 'Nama hos Glue "%s" tiada dalam senarai pelayan nama yang anda masukkan.',
    'val.ns_glue_ip'     => '"%s" bukan IPv4 atau IPv6 yang sah.',
    'val.ns_glue_needed' => 'Pelayan nama %s berada dalam domain ini, jadi alamat Glue diperlukan.',

    /* ============ Kelompok 12: pautan GitHub dan faedah Star ============ */
    'account.github_heading'        => 'GitHub',
    'account.github_login_hint'     => 'Anda juga boleh log masuk dengan akaun GitHub. Jika tiada akaun di sini '
        . 'menggunakan alamat e-mel GitHub anda, satu akan dicipta secara automatik.',
    'account.github_signin'         => 'Log masuk dengan GitHub',
    'account.github_or'             => 'atau',
    'account.github_signup'         => 'Daftar terus dengan GitHub',
    'account.github_signup_hint'    => 'Lebih mudah daripada borang di atas: guna nama pengguna dan alamat e-mel yang '
        . 'disahkan dari GitHub, tanpa e-mel pengesahan. Anda boleh menetapkan kata laluan kemudian di profil.',
    'account.github_signed_in'      => 'Anda log masuk dengan GitHub: %s.',
    'account.github_welcome'        => 'Selamat datang, %s! Akaun 「%s」 telah dicipta melalui GitHub. Anda boleh '
        . 'menetapkan kata laluan pada bila-bila masa di profil.',
    'account.github_linked'         => 'Akaun GitHub <strong>%s</strong> telah dipautkan (masa pautan: %s).',
    'account.github_linked_ok'      => 'GitHub telah dipautkan (%s). Mulai sekarang anda boleh log masuk dengan satu '
        . 'klik melalui GitHub.',
    'account.github_already_linked' => 'Akaun ini sudah memautkan %s.',
    'account.github_not_linked'     => 'GitHub belum dipautkan. Memautkannya membolehkan log masuk satu klik dan amat '
        . 'berguna jika anda lupa kata laluan.',
    'account.github_link'           => 'Pautkan akaun GitHub',
    'account.github_unlink'         => 'Nyahpaut',
    'account.github_unlinked'       => 'Pautan GitHub telah dinyahpaut.',
    'account.github_no_password_hint' => 'Akaun ini tiada kata laluan sendiri: tetapkan dahulu melalui 「Tukar kata '
        . 'laluan」 sebelum menyahpaut GitHub, jika tidak anda tidak akan dapat log masuk semula.',
    'val.github_off'                => 'Log masuk GitHub tidak disediakan buat masa ini.',
    'val.github_no_table'           => 'Pangkalan data tiada item berkaitan GitHub. Sila jalankan upgrade-github.sql '
        . '(halaman semakan kendiri akan menunjukkan SQL).',
    'val.github_signin_first'       => 'Sila log masuk dahulu sebelum memautkan akaun GitHub.',
    'val.github_bad_request'        => 'Permintaan ke GitHub tidak sah.',
    'val.github_state'              => 'Panggil balik GitHub tidak dapat dipadankan dengan permintaan ini (token '
        . 'keselamatan tidak sepadan atau telah tamat tempoh). Sila cuba lagi.',
    'val.github_cancelled'          => 'Kebenaran GitHub dibatalkan.',
    'val.github_token'              => 'Gagal mendapatkan profil daripada GitHub: %s',
    'val.github_taken'              => 'Akaun GitHub ini sudah dipautkan kepada akaun lain di laman ini.',
    'val.github_no_email'           => 'GitHub tidak mengembalikan alamat e-mel yang **disahkan**, jadi akaun tidak '
        . 'boleh dicipta atau dipadankan. Sila tambah dan sahkan alamat e-mel di GitHub dahulu, atau daftar di sini '
        . 'menggunakan alamat e-mel.',
    'val.github_no_account'         => 'Tiada akaun di laman ini sepadan dengan akaun GitHub anda, dan 「Daftar dengan '
        . 'GitHub」 ditutup buat masa ini.',
    'val.github_not_linked'         => 'Akaun ini tidak memautkan GitHub.',
    'val.github_unlink_needs_password' => 'Tetapkan kata laluan akaun ini dahulu sebelum menyahpaut GitHub, jika tidak '
        . 'anda tidak akan dapat log masuk semula.',
    'account.bonus_heading'   => 'Kuota tambahan: beri Star di GitHub',
    'account.bonus_intro'     => 'Beri Star kepada %s di GitHub dan kuota pendaftaran akaun ini akan bertambah. '
        . 'Pengesahan dilakukan dengan membandingkan akaun GitHub yang dipautkan dengan senarai Star repositori, jadi '
        . 'Star itu mesti datang daripada akaun GitHub anda sendiri.',
    'account.bonus_repo'      => 'Repositori',
    'account.bonus_amount'    => 'Faedah setiap akaun',
    'account.bonus_names'     => 'domain tambahan, sekali sahaja',
    'account.bonus_yours'     => 'Sudah menerima %s',
    'account.bonus_status'    => 'Faedah anda',
    'account.bonus_taken'     => 'Diberikan kepada %s. Terima kasih!',
    'account.bonus_need_link' => 'Pautkan akaun GitHub dahulu: %s. Faedah hanya diberikan kepada akaun GitHub yang '
        . 'benar-benar memberi Star.',
    'account.bonus_ready'     => 'Boleh disemak — beri Star kemudian tekan butang di bawah.',
    'account.bonus_step1'     => 'Buka %s dan tekan 「Star」 di penjuru kanan atas.',
    'account.bonus_step2'     => 'Tunggu beberapa saat (Star di GitHub mengambil sedikit masa untuk dikemas kini).',
    'account.bonus_step3'     => 'Semak dengan butang di bawah; jika lulus, kuota akan ditambah ke akaun serta-merta.',
    'account.bonus_submit'    => 'Saya sudah beri Star, semak sekarang',
    'account.bonus_ok'        => 'Terima kasih! %s kuota domain telah ditambah ke akaun ini.',
    'account.bonus_off'       => 'Faedah Star tidak disediakan buat masa ini.',
    'account.bonus_count'     => 'Kini %s akaun memberi Star kepada repositori ini (senarai diambil: %s).',
    'account.bonus_truncated' => 'Senarai melebihi had bacaan laman ini. Jika tidak dijumpai, sila hubungi pentadbir.',
    'account.bonus_note'      => 'Sekali sahaja bagi setiap akaun; menarik balik Star kemudian tidak akan menarik '
        . 'balik kuota yang telah diberikan.',
    'account.back_to_console' => 'Kembali ke konsol',
    'val.star_off'            => 'Faedah Star tidak disediakan buat masa ini.',
    'val.star_no_table'       => 'Pangkalan data tiada item berkaitan faedah Star. Sila jalankan upgrade-star.sql '
        . '(halaman semakan kendiri akan menunjukkan SQL).',
    'val.star_no_repo'        => 'Pendaftar belum menetapkan repositori GitHub.',
    'val.star_link_first'     => 'Pautkan akaun GitHub di profil dahulu — faedah hanya diberikan kepada akaun GitHub '
        . 'yang memberi Star.',
    'val.star_already'        => 'Akaun ini sudah menerima faedah ini.',
    'val.star_lookup'         => 'Gagal membaca senarai Star daripada GitHub: %s',
    'val.star_not_found'      => 'Akaun GitHub anda tiada dalam senarai Star repositori. Sila beri Star di halaman '
        . 'repositori dan cuba lagi sebentar kemudian.',

    /* ============ Kelompok 13: pengesahan dua langkah dan perkongsian domain ============ */
    'totp.heading'      => 'Pengesahan dua langkah',
    'totp.intro'        => 'Sepenuhnya pilihan dan hanya terpakai pada akaun anda sendiri: digabungkan dengan aplikasi '
        . 'pengesahan (Google Authenticator, Aegis, 1Password dan lain-lain), kata laluan yang bocor pun tidak '
        . 'membolehkan orang lain masuk ke konsol.',
    'totp.open'         => 'Tetapkan pengesahan dua langkah',
    'totp.state'        => 'Status',
    'totp.state_on'     => 'Aktif (sejak %s)',
    'totp.state_off'    => 'Tidak aktif (log masuk dengan kata laluan sahaja)',
    'totp.account'      => 'Akaun',
    'totp.turn_on'      => 'Hidupkan',
    'totp.turn_on_hint' => 'Kunci hanya disimpan selepas kod disahkan sekali dalam aplikasi pengesahan — jadi anda '
        . 'tidak akan 「terkunci semasa menetapkannya」.',
    'totp.begin'        => 'Jana kunci',
    'totp.setup'        => 'Selesaikan tetapan',
    'totp.step_app'     => 'Buka aplikasi pengesahan dan tambah akaun melalui 「Masuk manual」 (kamera tidak diperlukan '
        . 'di sini).',
    'totp.step_secret'  => 'Masukkan kunci berikut dalam aplikasi (huruf A-Z dan angka 2-7):',
    'totp.step_uri'     => 'Jika aplikasi anda menyokongnya, anda boleh tampal alamat berikut terus:',
    'totp.code'         => 'Kod 6 digit yang dipaparkan dalam aplikasi',
    'totp.confirm'      => 'Sahkan dan hidupkan',
    'totp.cancel'       => 'Batal tetapan',
    'totp.turn_off'     => 'Matikan',
    'totp.turn_off_hint' => 'Mematikannya memerlukan kod semasa, jadi kata laluan yang dicuri sahaja tidak cukup untuk '
        . 'menyahaktifkannya.',
    'totp.disable'      => 'Matikan pengesahan dua langkah',
    'totp.enabled'      => 'Pengesahan dua langkah dihidupkan. Kod diperlukan mulai log masuk seterusnya.',
    'totp.disabled'     => 'Pengesahan dua langkah dimatikan. Anda kembali log masuk dengan kata laluan sahaja.',
    'totp.bad_code'     => 'Kod tidak betul. Sila pastikan masa telefon anda tepat dan cuba kod seterusnya.',
    'totp.no_setup'     => 'Tetapan telah tamat tempoh. Sila mula semula dari awal.',
    'totp.not_on'       => 'Pengesahan dua langkah tidak dihidupkan sekarang.',
    'totp.no_table'     => 'Pangkalan data tiada item yang diperlukan ciri ini. Sila jalankan upgrade-totp.sql (boleh '
        . 'dijalankan dari halaman semakan kendiri).',
    'totp.recovery'     => 'Simpan kunci di tempat lain yang selamat: jika kunci hilang dan tiada kod yang boleh '
        . 'digunakan, hanya pentadbir pendaftar boleh memulihkannya. Anda boleh mematikannya pada bila-bila masa di '
        . 'halaman ini.',
    'totp.ask'          => 'Pengesahan dua langkah',
    'totp.ask_intro'    => 'Akaun ini menggunakan aplikasi pengesahan. Untuk melengkapkan log masuk, masukkan kod 6 '
        . 'digit semasa.',
    'totp.ask_submit'   => 'Lengkapkan log masuk',
    'totp.ask_bad'      => 'Kod ini tidak sah sekarang.',
    'val.totp_code'     => 'Sila masukkan kod 6 digit yang dipaparkan dalam aplikasi.',
    'share.heading'    => 'Domain yang dikongsi',
    'share.intro'      => 'Anda boleh membuka salah satu domain anda kepada akaun konsol lain untuk dilihat, dan '
        . 'sebaliknya melihat domain yang dikongsi orang lain. Perkongsian adalah **baca sahaja**: mereka hanya boleh '
        . 'melihat domain dan rekod, manakala ubah, pindah dan padam kekal milik pemilik sahaja.',
    'share.with_me'    => 'Dikongsi dengan saya',
    'share.with_me_none' => 'Belum ada sesiapa mengongsi domain dengan anda.',
    'share.by_me'      => 'Dikongsi oleh saya',
    'share.by_me_hint' => 'Akaun yang boleh melihat domain anda. Mereka tidak boleh mengubah apa-apa.',
    'share.by_me_none' => 'Anda belum mengongsi mana-mana domain.',
    'share.name'       => 'Domain',
    'share.zone'       => 'Akhiran',
    'share.state'      => 'Status',
    'share.by'         => 'Dikongsi oleh',
    'share.account'    => 'Akaun',
    'share.when'       => 'Masa perkongsian',
    'share.look'       => 'Lihat rekod',
    'share.revoke'     => 'Tarik balik',
    'share.invite'     => 'Kongsi salah satu domain saya',
    'share.invite_email' => 'Alamat e-mel akaun penerima',
    'share.invite_hint' => 'Alamat itu mesti sudah didaftarkan sebagai akaun konsol di laman ini. Penerima akan '
        . 'menerima e-mel dan domain ini akan muncul dalam 「Dikongsi dengan saya」.',
    'share.invite_submit' => 'Kongsi (baca sahaja)',
    'share.added'      => 'Dikongsi. Penerima hanya boleh melihat rekod.',
    'share.revoked'    => 'Perkongsian ditarik balik.',
    'share.readonly_note' => 'Baca sahaja: rekod tidak boleh diubah pada skrin ini. Sunting, pindah, padam dan API '
        . 'kekal milik pemilik domain sahaja.',
    'share.not_mine'   => 'Domain ini bukan milik akaun anda.',
    'share.self'       => 'Ini domain anda sendiri.',
    'share.already'    => 'Akaun 「%s」 sudah boleh melihat domain ini.',
    'share.no_account' => 'Tiada akaun konsol menggunakan alamat e-mel %s. Penerima perlu mendaftar dahulu.',
    'share.no_table'   => 'Pangkalan data tiada jadual berkaitan perkongsian. Sila jalankan upgrade-sharing.sql (boleh '
        . 'dijalankan dari halaman semakan kendiri).',
    'share.rec_name'   => 'Nama',
    'share.rec_type'   => 'Jenis',
    'share.rec_value'  => 'Nilai',
    'mail.share_subject' => '%s mengongsi dengan anda',
    'mail.share_body'  => "%s telah dikongsi dengan anda oleh %s (baca sahaja).\n\nAnda boleh melihat domain dan rekodnya di 「Domain yang dikongsi」 dalam konsol, tetapi tidak boleh mengubahnya: sunting, pindah dan padam hanya boleh dilakukan oleh pemilik.",
    'mail.share_footer' => 'Anda boleh berhenti mengikutinya pada bila-bila masa, dan pemilik boleh menarik balik '
        . 'perkongsian itu.',

    /* ============ Kelompok 14: tiket pemindahan domain dan pelayan awan ============ */
    'transfer.heading'        => 'Pemindahan domain',
    'transfer.intro'          => 'Domain di bawah akhiran yang diselesaikan oleh DNSHE tidak boleh dipadam di pihak '
        . 'DNSHE dan hanya boleh diserahkan melalui pemindahan: mohon di sini, pendaftar akan menyediakan kod '
        . 'pemindahan di DNSHE, dan penerima menerimanya di DNSHE. Pendaftar membalas dalam tiket di bawah dan setiap '
        . 'balasan dihantar melalui e-mel.',
    'transfer.ticket'         => 'Tiket #%s',
    'transfer.status_heading' => 'Status',
    'transfer.status_open'    => 'Menunggu tindakan pendaftar',
    'transfer.status_approved'=> 'Kod pemindahan dikeluarkan, menunggu penerimaan',
    'transfer.status_accepted'=> 'Selesai',
    'transfer.status_rejected'=> 'Ditolak',
    'transfer.status_expired' => 'Tamat tempoh (tidak diterima dalam masa)',
    'transfer.status_cancelled' => 'Dibatalkan',
    'transfer.hours_left'     => 'Boleh diterima selama %s jam lagi',
    'transfer.requester'      => 'Pemilik semasa',
    'transfer.recipient'      => 'Penerima',
    'transfer.recipient_name' => 'Nama penerima',
    'transfer.recipient_email'=> 'Alamat e-mel penerima',
    'transfer.opened'         => 'Masa mula',
    'transfer.reason'         => 'Keterangan',
    'transfer.thread'         => 'Perbualan',
    'transfer.no_messages'    => 'Belum ada mesej.',
    'transfer.from_staff'     => 'Pendaftar',
    'transfer.from_applicant' => 'Pemohon (%s)',
    'transfer.write'          => 'Mesej kepada pendaftar',
    'transfer.message'        => 'Kandungan',
    'transfer.send'           => 'Hantar',
    'transfer.withdraw'       => 'Batalkan permohonan',
    'transfer.mine'           => 'Tiket pemindahan saya',
    'transfer.none'           => 'Belum ada tiket pemindahan.',
    'transfer.name'           => 'Domain',
    'transfer.role'           => 'Peranan saya',
    'transfer.role_holder'    => 'Pihak yang menyerahkan',
    'transfer.role_recipient' => 'Pihak yang menerima',
    'transfer.open'           => 'Buka',
    'transfer.back'           => 'Kembali ke tiket saya',
    'srv.heading' => 'Pelayan awan saya',
    'srv.intro'   => 'Daftarkan pelayan yang anda beli di RainYun di sini: semak status dan tarikh luput, peringatan '
        . 'sebelum luput, serta mula, henti dan mulakan semula. Pelayan sentiasa berada dalam akaun anda sendiri — '
        . 'pendaftar tidak membayar atau menyimpannya untuk anda.',
    'srv.usage'   => 'Beban dan penggunaan',
    'srv.usage_hint' => 'Baru dibaca daripada pembekal perkhidmatan. Jika nilainya pelik, respons mentah ada di bawah '
        . 'jadual.',
    'srv.maintenance' => 'Penyelenggaraan',
    'srv.no_maintenance' => 'Tiada notis penyelenggaraan.',
    'srv.vnc'     => 'Buka konsol VNC',
    'srv.vnc_hint' => 'Tiket sekali guna: ia luput sendiri dan kami tidak menyimpannya. Setiap permintaan direkodkan '
        . 'dalam log audit.',
    'srv.raw'     => 'Respons mentah',

    /* ============ Kelompok 15: pemindahan (sambungan) dan pasaran domain ============ */
    'transfer.new_heading'    => 'Mohon pemindahan',
    'transfer.new_intro'      => 'Pilih satu domain anda dan masukkan penerima. Pendaftar akan memulakan pemindahan di '
        . 'DNSHE dan menghantar kod pemindahan ke alamat e-mel penerima. Penerima mesti menerimanya dalam masa 72 jam.',
    'transfer.submit'         => 'Hantar permohonan pemindahan',
    'transfer.off'            => 'Pemindahan ditutup buat masa ini.',
    'transfer.nothing_to_transfer' => 'Tiada domain yang boleh dipindahkan (perlu domain aktif di bawah akhiran DNSHE).',
    'transfer.hint'           => 'Semasa tiket sedang diproses atau menunggu penerimaan, rekod domain itu dikunci dan '
        . 'tidak boleh diubah. Penerima mesti menerimanya dengan akaun yang sepadan dengan alamat e-mel yang anda '
        . 'masukkan di sini.',
    'transfer.created'        => 'Permohonan pemindahan dihantar. Pendaftar akan membalas di sini dan melalui e-mel.',
    'transfer.message_saved'  => 'Mesej disimpan.',
    'transfer.confirmed'      => 'Terima kasih kerana mengesahkan. Domain kini berada dalam akaun anda.',
    'transfer.cancelled'      => 'Permohonan dibatalkan.',
    'transfer.accept_heading' => 'Terima pemindahan',
    'transfer.accept_text'    => 'Anda ialah penerima domain ini. Terima dahulu di panel DNSHE (kod pemindahan dihantar '
        . 'melalui e-mel), kemudian tekan butang di bawah: domain akan masuk ke akaun anda di sini.',
    'transfer.accept_submit'  => 'Saya sudah terima di DNSHE — pindahkan domain kepada saya',
    'transfer.locked'         => 'Domain ini sedang dalam proses pemindahan, jadi rekod tidak boleh diubah buat '
        . 'sementara.',
    'val.transfer_off'        => 'Pemindahan ditutup buat masa ini.',
    'val.transfer_suffix'     => 'Hanya domain di bawah akhiran yang diselesaikan DNSHE boleh dipindahkan.',
    'val.transfer_not_active' => 'Hanya domain aktif boleh dipindahkan.',
    'val.transfer_pending'    => 'Domain ini sudah dalam proses pemindahan.',
    'val.transfer_recipient'  => 'Sila masukkan nama penerima dan alamat e-mel yang sah.',
    'val.transfer_self'       => 'Anda tidak boleh memindahkan domain ke alamat e-mel anda sendiri.',
    'val.transfer_own'        => 'Tiket atau domain ini bukan milik akaun anda.',
    'val.transfer_not_recipient' => 'Pemindahan ini ditujukan kepada alamat e-mel lain. Sila log masuk dengan akaun '
        . 'bagi alamat e-mel yang tercatat dalam tiket.',
    'val.transfer_expired'    => '72 jam telah berlalu. Hubungi pendaftar untuk mengeluarkan semula kod pemindahan.',
    'val.transfer_empty'      => 'Sila masukkan kandungan dahulu.',
    'mail.transfer_new_subject' => 'Permohonan pemindahan baharu: %s',
    'mail.transfer_new_body'  => "Seorang pengguna menghantar permohonan pemindahan.\n\nDomain: %s\nPemilik semasa: %s\nPenerima: %s <%s>\n\nSila uruskan di halaman Tickets dalam pentadbir.",
    'mail.transfer_reply_subject' => 'Tiket pemindahan bagi %s',
    'mail.transfer_code_subject' => 'Kod pemindahan untuk %s',
    'mail.transfer_code_body' => "Anda akan menerima domain: %s\n\nKod pemindahan: %s\nSah sehingga: %s (72 jam)\nPihak yang menyerahkan: %s\n\nTebus kod ini dalam akaun DNSHE anda, kemudian kembali ke konsol dan sahkan pemindahan dengan akaun bagi alamat e-mel ini.",
    'mail.transfer_approved_subject' => 'Pemindahan %s telah diluluskan',
    'mail.transfer_approved_body' => "Pemindahan %s telah diluluskan. Kod pemindahan dihantar ke %s. Pemindahan selesai apabila pihak itu menerimanya sebelum %s.",
    'mail.transfer_rejected_subject' => 'Pemindahan %s tidak diluluskan',
    'mail.transfer_rejected_body' => "Pemindahan %s tidak diluluskan.\n\n%s",
    'mail.transfer_done_subject' => 'Pemindahan %s selesai',
    'mail.transfer_done_body' => "%s kini atas nama alamat e-mel anda dan boleh diuruskan dalam konsol.",
    'mail.transfer_done_holder_body' => "%s telah dipindahkan kepada %s. Domain ini tidak lagi dalam akaun anda.",
    'mail.transfer_footer'    => 'Tiket boleh dijejaki di halaman 「Pemindahan domain」 dalam konsol.',
    'market.heading'          => 'Domain yang dijual',
    'market.disclaimer_heading' => 'Baca dahulu',
    'market.disclaimer'       => 'Ruang ini hanya papan maklumat: pendaftar menyemak penyenaraian dan mempertemukan '
        . 'kedua pihak, tetapi **tidak terlibat dalam transaksi** — kami tidak memiliki domain, tidak menerima bayaran '
        . 'bagi pihak anda, tidak mengesahkan pemilik sebenar domain dan tidak memberikan sebarang jaminan atau '
        . 'eskrow. Harga, cara bayaran dan cara pemindahan hendaklah dipersetujui antara kedua pihak. Sahkan domain '
        . 'itu sendiri sebelum membayar (pemilik, tarikh luput, kunci pemindahan). Segala risiko dan pertikaian '
        . 'menjadi tanggungan penjual dan pembeli.',
    'market.intro'            => 'Di sini tersenarai domain yang dibeli di tempat lain dan tidak lagi diperlukan, '
        . 'disenaraikan oleh akaun konsol. Hubungan dibuat terus melalui e-mel antara penjual dan pembeli.',
    'market.off'              => 'Ruang jual beli ditutup buat masa ini.',
    'market.search'           => 'Cari (domain / keterangan / pendaftar)',
    'market.suffix'           => 'Akhiran (com, net…)',
    'market.max_price'        => 'Harga maksimum',
    'market.filter'           => 'Tapis',
    'market.reset'            => 'Set semula',
    'market.empty'            => 'Tiada penyenaraian sepadan.',
    'market.domain'           => 'Domain',
    'market.price'            => 'Harga jualan',
    'market.transfer_price'   => 'Harga pemindahan',
    'market.currency'         => 'Mata wang',
    'market.negotiable'       => 'Boleh runding',
    'market.registrar'        => 'Pendaftar',
    'market.registered_on'    => 'Tarikh pendaftaran',
    'market.expires_on'       => 'Tarikh luput',
    'market.status_heading'   => 'Status',
    'market.status_pending'   => 'Menunggu semakan',
    'market.status_active'    => 'Sedang dijual',
    'market.status_rejected'  => 'Ditolak',
    'market.status_sold'      => 'Telah dijual',
    'market.status_withdrawn' => 'Ditarik balik',
    'market.listed'           => 'Masa penyenaraian',
    'market.description'      => 'Butiran',
    'market.pinned'           => 'Dipinkan',
    'market.open'             => 'Lihat',
    'market.report_link'      => 'Laporkan penyenaraian ini',
    'market.back'             => 'Kembali ke ruang jual beli',
    'market.sell_hint'        => 'Ada domain untuk dijual? %s (perlu akaun konsol; setiap satu disemak oleh manusia).',
    'market.sell_link'        => 'Senaraikan di sini',

    /* ============ Kelompok 16: pengurusan penyenaraian, e-mel pasaran dan kedai kuota ============ */
    'market.buy_heading'      => 'Saya mahu beli',
    'market.buy_intro'        => 'Menghantar permohonan beli bermakna anda dan penjual menerima alamat e-mel satu sama '
        . 'lain dan seterusnya berurusan terus melalui e-mel. (%s)',
    'market.buy_intro_account' => 'Penjual menggunakan alamat e-mel akaun',
    'market.buy_intro_custom' => 'Penjual menggunakan alamat e-mel hubungan yang lain',
    'market.your_name'        => 'Nama anda',
    'market.your_email'       => 'Alamat e-mel',
    'market.email_hint'       => 'Alamat ini akan diberikan kepada penjual untuk dihubungi terus. Sila masukkan alamat '
        . 'yang benar-benar anda gunakan.',
    'market.message'          => 'Mesej kepada penjual',
    'market.send'             => 'Hantar permohonan beli',
    'market.login_first'      => 'Sila log masuk sebelum menghantar permohonan beli.',
    'market.login_first_help' => 'Permohonan memerlukan akaun konsol — %s atau %s kemudian kembali ke sini.',
    'market.sent'             => 'Permohonan dihantar: sila semak e-mel anda. Penjual juga menerima alamat e-mel anda.',
    'market.sold_note'        => 'Domain ini ditandakan sebagai telah dijual oleh penjual.',
    'market.own_offer'        => 'Ini penyenaraian anda sendiri. Permohonan pembeli akan muncul dalam konsol anda.',
    'market.mine_heading'     => 'Penyenaraian saya',
    'market.mine_intro'       => 'Apa yang anda senaraikan. Penyenaraian baharu atau perubahan domain dipaparkan '
        . 'selepas semakan manusia. Anda boleh menarik balik atau menandakannya sebagai terjual pada bila-bila masa.',
    'market.new_heading'      => 'Senaraikan',
    'market.new_intro'        => 'Hanya **domain peringkat pertama** yang dibeli di tempat lain boleh disenaraikan '
        . '(domain peringkat kedua yang dikeluarkan oleh laman ini tidak boleh). Pendaftar tidak dapat mengesahkan '
        . 'pemilikan, jadi sila isi dengan jujur — pembeli akan menggunakannya untuk menilai.',
    'market.domain_hint'      => 'Contoh: example.com. Domain di bawah akhiran laman ini sendiri tidak diterima.',
    'market.contact_email'    => 'Alamat e-mel hubungan untuk pembeli',
    'market.contact_hint'     => 'Ia dipaparkan kepada pembeli yang memohon. Boleh alamat e-mel akaun atau alamat lain.',
    'market.submit'           => 'Hantar untuk semakan',
    'market.save'             => 'Simpan perubahan',
    'market.created'          => 'Dihantar. Ia dipaparkan selepas semakan manusia dan kelulusan.',
    'market.updated'          => 'Disimpan.',
    'market.mark_sold'        => 'Tandakan sebagai terjual',
    'market.withdraw'         => 'Tarik balik',
    'market.marked_sold'      => 'Ditandakan sebagai terjual.',
    'market.withdrawn_ok'     => 'Ditarik balik.',
    'market.reject_reason'    => 'Sebab daripada pendaftar',
    'market.review_hint'      => 'Penyenaraian disemak satu demi satu oleh manusia, dan kedua-dua kelulusan dan '
        . 'penolakan dimaklumkan melalui e-mel.',
    'market.none'             => 'Anda belum menyenaraikan apa-apa.',
    'market.enquiries'        => 'Permohonan beli',
    'market.enquiries_count'  => 'Bilangan permohonan',
    'market.all_enquiries'    => 'Semua permohonan beli',
    'market.no_enquiries'     => 'Belum ada permohonan.',
    'market.when'             => 'Masa',
    'market.buyer'            => 'Pembeli',
    'market.view_public'      => 'Lihat di ruang jual beli',
    'market.back_mine'        => 'Kembali ke penyenaraian saya',
    'val.market_off'          => 'Ruang jual beli ditutup buat masa ini.',
    'val.market_domain'       => 'Sila masukkan domain peringkat pertama yang boleh didaftarkan (contoh: example.com).',
    'val.market_own_suffix'   => 'Domain di bawah akhiran laman ini sendiri tidak boleh disenaraikan (pendaftar yang '
        . 'mengeluarkannya).',
    'val.market_taken'        => 'Domain ini sudah disenaraikan.',
    'val.market_price'        => 'Sila masukkan harga jualan.',
    'val.market_own'          => 'Penyenaraian ini bukan milik akaun anda.',
    'val.market_closed'       => 'Penyenaraian ini telah tamat.',
    'val.market_self'         => 'Anda tidak boleh memohon beli penyenaraian anda sendiri.',
    'mail.market_new_subject' => 'Penyenaraian baharu di ruang jual beli: %s',
    'mail.market_new_body'    => "Seorang pengguna menghantar penyenaraian.\n\nDomain: %s\nPenjual: %s\nHarga jualan: %s\n\nSila semak di halaman Market dalam pentadbir.",
    'mail.market_approved_subject' => 'Penyenaraian %s telah diterbitkan',
    'mail.market_approved_body' => "Penyenaraian %s lulus semakan dan kini dipaparkan di ruang jual beli.\n\nPembeli akan menghubungi alamat e-mel yang anda isi. Setiap permohonan juga direkodkan dalam konsol anda.",
    'mail.market_rejected_subject' => 'Penyenaraian %s ditolak',
    'mail.market_rejected_body' => "Penyenaraian %s tidak lulus semakan.\n\nSebab: %s\n\nAnda boleh membetulkannya dalam konsol dan menghantar semula.",
    'mail.market_buyer_subject' => 'Permohonan beli untuk %s',
    'mail.market_buyer_body'  => "Anda memohon untuk membeli %s (%s).\n\nAlamat e-mel hubungan penjual: %s\nSila hubungi alamat itu terus untuk harga dan cara pemindahan. Pendaftar tidak terlibat dalam transaksi.",
    'mail.market_seller_subject' => 'Ada orang ingin membeli %s',
    'mail.market_seller_body' => "Ada pembeli berminat dengan %s.\n\nNama: %s\nAlamat e-mel: %s\nMesej: %s\n\nSila hubungi alamat itu terus. Pendaftar tidak terlibat dalam transaksi.",
    'shop.heading'      => 'Kedai kuota: tambah kuota pendaftaran',
    'shop.intro'        => 'Ada tiga cara untuk menambah kuota pendaftaran akaun anda dan semuanya percuma. Semua '
        . 'faedah ditambah pada kuota asal.',
    'shop.current'      => 'Kuota anda',
    'shop.names'        => 'Jumlah %s domain',
    'shop.unlimited'    => 'Tanpa had',
    'shop.used'         => 'Digunakan %s',
    'shop.earned'       => 'Antaranya %s daripada faedah kedai',
    'shop.bonus'        => 'Faedah',
    'shop.status'       => 'Status',
    'shop.when'         => 'Masa',
    'shop.status_pending'  => 'Menunggu semakan',
    'shop.status_approved' => 'Diluluskan',
    'shop.status_rejected' => 'Ditolak',
    'shop.no_table'     => 'Pangkalan data tiada jadual/item berkaitan kedai. Sila jalankan upgrade-shop.sql (halaman '
        . 'semakan kendiri akan menunjukkan SQL).',

    /* ============ Kelompok 17: faedah kedai dan sembang langsung ============ */
    'shop.item_star'    => 'Beri Star kepada projek di GitHub',
    'shop.star_intro'   => 'Beri Star kepada repositori GitHub kami dan akaun anda menerima %s kuota tambahan. Untuk '
        . 'mengesahkan Star itu milik anda, pautkan akaun GitHub dahulu.',
    'shop.star_off'     => 'Faedah Star tidak disediakan buat masa ini.',
    'shop.star_done'    => 'Sudah diterima (%s). Terima kasih atas sokongan!',
    'shop.star_need_link' => 'Pautkan akaun GitHub dahulu: %s.',
    'shop.star_submit'  => 'Saya sudah beri Star, semak sekarang',
    'shop.star_ok'      => 'Terima kasih atas sokongan! %s kuota domain telah ditambah ke akaun ini.',
    'shop.item_promo'   => 'Perkenalkan kami di platform lain',
    'shop.promo_intro'  => 'Anda telah memperkenalkan kami di blog, forum, kumpulan atau video? Hantar pautannya: '
        . 'setiap semakan yang lulus menambah %s kuota, terkumpul sehingga %s.',
    'shop.promo_left'   => 'Sudah menerima %s, boleh menerima %s lagi.',
    'shop.promo_full'   => 'Faedah perkongsian telah mencapai had. Terima kasih kerana menyebarkannya!',
    'shop.promo_url'    => 'Pautan perkongsian',
    'shop.promo_url_hint' => 'Halaman awam yang menyebut kami (https://…). Pentadbir akan membukanya semasa semakan.',
    'shop.promo_description' => 'Apa yang anda tulis dan di mana?',
    'shop.promo_submit' => 'Hantar untuk semakan',
    'shop.promo_sent'   => 'Dihantar. Pendaftar akan menyemak dan memaklumkan melalui e-mel.',
    'shop.item_invite'  => 'Kod jemputan khas',
    'shop.invite_intro' => 'Ini kod jemputan dan pautan jemputan khas anda. Orang yang mendaftar melalui pautan anda '
        . 'dikira dalam prestasi anda: kini %s orang, dengan %s domain aktif.',
    'shop.invite_code'  => 'Kod jemputan anda',
    'shop.invite_link'  => 'Pautan jemputan anda',
    'shop.invite_people' => 'Bilangan orang yang anda bawa',
    'shop.invite_active' => 'Bilangan domain aktif mereka',
    'shop.invite_pending' => '(%s lagi menunggu semakan)',
    'shop.invite_hint'  => 'Kongsi pautan itu di mana-mana. Pengguna yang mendaftar melaluinya akan direkodkan dalam '
        . 'prestasi anda apabila mereka masuk ke konsol buat kali pertama. Nombor di halaman Shop dalam pentadbir juga '
        . 'sepadan sepenuhnya, dan pentadbir memberikan faedah berdasarkan itu.',
    'shop.people_short' => 'orang',
    'shop.active_short' => 'domain aktif',
    'val.shop_off'      => 'Kedai kuota ditutup buat masa ini.',
    'val.shop_cap'      => 'Faedah perkongsian telah mencapai had (%s).',
    'val.shop_url'      => 'Sila masukkan pautan yang bermula dengan http:// atau https://.',
    'val.shop_description' => 'Sila terangkan secara ringkas apa yang anda terbitkan dan di mana.',
    'mail.shop_new_subject' => '%s menghantar perkongsian',
    'mail.shop_new_body' => "%s (%s) menghantar perkongsian.\n\nPautan: %s\nKeterangan: %s\n\nSila semak di halaman Shop dalam pentadbir.",
    'mail.shop_approved_subject' => 'Perkongsian anda telah diluluskan',
    'mail.shop_approved_body' => "Terima kasih atas sokongan! %s kuota telah ditambah ke akaun anda dan faedah perkongsian boleh menerima %s lagi.",
    'mail.shop_rejected_subject' => 'Perkongsian anda tidak diluluskan',
    'mail.shop_rejected_body' => "Perkongsian anda tidak lulus semakan.\n\nSebab: %s\n\nAnda boleh menghantar semula dengan pautan lain yang jelas menunjukkan perkongsian itu.",
    'chat.heading'       => 'Sembang langsung dengan pendaftar',
    'chat.intro'         => 'Tanya apa sahaja di sini. Kami membalas di halaman ini dan menghantar e-mel pada masa yang '
        . 'sama. Halaman dikemas kini secara automatik dan balasan muncul terus.',
    'chat.refresh_note'  => 'Dikemas kini secara automatik setiap %s saat — JavaScript tidak diperlukan.',
    'chat.leave_heading' => 'Mesej',
    'chat.leave_intro'   => 'Anda belum log masuk. Masukkan nama dan alamat e-mel anda; kami akan membalas melalui '
        . 'e-mel. Dengan akaun konsol (%s), perbualan disimpan dalam akaun anda.',
    'chat.your_name'     => 'Nama anda',
    'chat.your_email'    => 'Alamat e-mel',
    'chat.email_hint'    => 'Balasan akan dihantar ke alamat ini. Sila masukkan alamat yang anda pasti semak.',
    'chat.your_message'  => 'Mesej',
    'chat.send'          => 'Hantar',
    'chat.sent'          => 'Mesej diterima. Kami akan membalas di sini dan melalui e-mel.',
    'chat.need_name'     => 'Sila masukkan nama anda.',
    'chat.thread'        => 'Perbualan #%s',
    'chat.state'         => 'Status',
    'chat.status_open'   => 'Sedang berjalan',
    'chat.status_archived' => 'Diarkibkan (tiada mesej selama seminggu)',
    'chat.addressed'     => 'Ditujukan kepada',
    'chat.keep'          => 'Penyimpanan mesej',
    'chat.keep_value'    => 'Mesej tidak boleh dipadam dan boleh dieksport pada bila-bila masa',
    'chat.no_messages'   => 'Belum ada mesej.',
    'chat.write'         => 'Tulis mesej',
    'chat.from_staff'    => 'Pendaftar',
    'chat.from_me'       => 'Saya',
    'chat.from_user'     => 'Pengguna',
    'chat.from_guest'    => 'Tetamu',
    'chat.empty'         => 'Sila masukkan kandungan dahulu.',
    'chat.too_long'      => 'Mesej melebihi %s aksara.',
    'chat.too_fast'      => 'Beri jarak beberapa saat antara dua mesej.',
    'chat.rate_note'     => 'Jarak minimum %s saat antara mesej, dan satu mesej maksimum %s aksara.',
    'chat.archived_note' => 'Perbualan ini diarkibkan kerana tiada mesej selama seminggu. Menghantar satu mesej lagi '
        . 'akan memulakannya semula.',
    'chat.export'        => 'Eksport mesej saya',
    'chat.export_title'  => 'Perbualan #%s (dengan: %s)',
    'chat.refresh'       => 'Muat semula sekarang',
    'chat.refresh_manual_hint' => 'Halaman ini tidak dikemas kini secara automatik, jadi apa yang anda taip tidak akan '
        . 'hilang. Tekan butang di bawah untuk melihat balasan.',
    'chat.no_table'      => 'Pangkalan data tiada jadual berkaitan sembang. Sila jalankan upgrade-chat.sql (halaman '
        . 'semakan kendiri akan menunjukkan SQL).',
    'mail.chat_new_subject' => 'Mesej sembang baharu',
    'mail.chat_new_body' => "%s (%s) menulis:\n\n%s\n\nSila balas di halaman Chat dalam pentadbir.",
    'mail.chat_reply_subject' => 'Pendaftar telah membalas',
    'mail.chat_reply_body' => "Pendaftar membalas mesej anda:\n\n%s\n\nAnda boleh terus berbual di halaman sembang.",

    /* ============ Kelompok 18: e-mel pemberitahuan dan pemajuan e-mel ============ */
    'mail.request_subject'   => '[freedns] Permohonan baharu: %s',
    'mail.request_intro'     => 'Permohonan domain baharu diterima.',
    'mail.change_subject'    => '[freedns] Perubahan rekod: %s',
    'mail.reserved'          => 'Permohonan domain terpelihara: Ya (dihantar melalui laluan domain terpelihara)',
    'mail.change_decision_subject' => '[freedns] %s - perubahan rekod %s',
    'mail.approved_short'    => 'diluluskan',
    'mail.rejected_short'    => 'ditolak',
    'mail.change_intro'      => 'Terdapat perubahan rekod menunggu semakan.',
    'mail.decision_subject'  => '[freedns] %s - permohonan %s',
    'mail.dear'              => '%s yang dihormati',
    'mail.request_state'     => 'Status semasa domain %s yang dimohon ialah %s.',
    'mail.change_state'      => 'Perubahan rekod yang dihantar di %s (%s) %s',
    'mail.approved'          => 'diluluskan dan diterbitkan.',
    'mail.rejected'          => 'ditolak.',
    'mail.records'           => 'Rekod yang disimpan sekarang:',
    'mail.records_now'       => 'Rekod yang diterbitkan sekarang:',
    'mail.records_proposed'  => 'Rekod yang dicadangkan ketika itu:',
    'mail.note_registry'     => 'Nota daripada pendaftar:',
    'mail.note_applicant'    => 'Nota pemohon:',
    'mail.check_status'      => 'Status boleh disemak di sini pada bila-bila masa: %s',
    'mail.manage'            => 'Rekod domain aktif diuruskan di sini: %s',
    'mail.review_at'         => 'Skrin semakan: %s',
    'mail.domain'            => 'Domain    : %s',
    'mail.zone'              => 'Akhiran   : %s',
    'mail.code'              => 'Nombor permohonan: %s',
    'mail.applicant'         => 'Pemohon   : %s <%s>',
    'mail.organization'      => 'Organisasi: %s',
    'mail.country'           => 'Negara/wilayah: %s',
    'mail.records_label'     => 'Rekod:',
    'mail.purpose'           => 'Keterangan kegunaan:',
    'mail.submitted'         => 'Masa hantar: %s',
    'mail.alert_subject'     => 'Semakan penerbitan: %s masalah baharu',
    'mail.alert_intro'       => 'Rekod di peringkat atasan tidak sepadan dengan apa yang disimpan. E-mel ini dihantar '
        . 'hanya apabila masalah **baru muncul atau berubah**. Sistem tidak membaiki apa-apa secara automatik:',
    'mail.alert_footer'      => 'Anda boleh membaiki satu demi satu di halaman 「Published records」 dalam pentadbir, '
        . 'atau menjalankan reconcile penuh di sana.',
    'mail.srv_expire_subject' => '%s akan tamat tempoh tidak lama lagi',
    'mail.srv_expire_body' => "Pelayan awan %s akan tamat tempoh pada %s (%s hari lagi).\n\nPendaftar hanya memaklumkan; sila perbaharui dalam akaun RainYun anda sendiri. Kami tidak membayar atau menyimpannya untuk anda.",
    'mail.srv_expire_footer' => 'Di 「Pelayan awan saya」 dalam konsol anda boleh melihat pelayan yang dipautkan dan '
        . 'tarikh luputnya.',
    'mail.abuse_body' => "Kami menerima laporan penyalahgunaan.\n\nDomain: %s\nJenis: %s\nPelapor: %s\n\n%s\n\nSila uruskan di halaman Abuse dalam pentadbir.",
    'mail.heading'      => 'Pemajuan e-mel',
    'mail.intro'        => 'Terima e-mel pada domain anda dan paukannya ke alamat e-mel sebenar. Hanya tersedia bagi '
        . 'akhiran yang diselesaikan Cloudflare (pendaftar menghidupkan Email Routing dan mencipta peraturan pemajuan).',
    'mail.add_heading'  => 'Tambah alamat pemajuan',
    'mail.add_hint'     => 'Pilih domain, bahagian sebelum @, dan alamat e-mel sebenar yang hendak menerima.',
    'mail.name'         => 'Domain',
    'mail.local'        => 'Alamat (sebelum @)',
    'mail.forward_to'   => 'Majukan kepada',
    'mail.confirm_hint' => 'Pada kali pertama, Cloudflare menghantar pautan pengesahan ke alamat e-mel itu. Setelah '
        . 'disahkan, pemajuan dihidupkan.',
    'mail.add'          => 'Cipta pemajuan',
    'mail.mine'         => 'Alamat pemajuan saya',
    'mail.no_routes'    => 'Belum ada alamat pemajuan.',
    'mail.address'      => 'Alamat',
    'mail.status'       => 'Status',
    'mail.when'         => 'Masa dibuat',
    'mail.remove'       => 'Padam',
    'mail.added'        => 'Dicipta. Setelah anda mengesahkan e-mel pengesahan daripada Cloudflare, e-mel boleh diterima.',
    'mail.removed'      => 'Pemajuan dipadamkan.',
    'mail.none'         => 'Tiada domain aktif yang diselesaikan Cloudflare, jadi pemajuan e-mel tidak tersedia.',
    'mail.off'          => 'Pemajuan e-mel ditutup buat masa ini.',
    'mail.no_table'     => 'Pangkalan data tiada jadual yang diperlukan ciri ini. Sila jalankan upgrade-mailroute.sql '
        . '(boleh dijalankan dari halaman semakan kendiri).',
    'mail.rules'        => 'Hanya alamat yang anda masukkan akan dimajukan; yang lain ditolak terus oleh Cloudflare. '
        . 'Domain yang diwakilkan kepada pelayan nama sendiri tidak boleh menggunakan ciri ini (e-mel dikendalikan oleh '
        . 'DNS anda). Memadam pemajuan juga memadamkan peraturan di Cloudflare. Pendaftar tidak membaca atau menyimpan '
        . 'kandungan e-mel.',
    'mail.status_active'  => 'Sah',
    'mail.status_pending' => 'Menunggu',
    'mail.status_error'   => 'Ditolak oleh Cloudflare',

    /* ============ Kelompok 19 (terakhir): sisa permohonan, pengesahan dan pemadaman ============ */
    'request.email'          => 'Alamat e-mel *',
    'request.email_hint'     => 'Kami menggunakannya untuk pengesahan identiti, memaklumkan keputusan semakan dan '
        . 'mengurus rekod kemudian. Ia tidak dipaparkan dalam senarai awam.',
    'request.org'            => 'Organisasi (pilihan)',
    'request.country'        => 'Negara/wilayah (pilihan)',
    'request.country_hint'   => 'Isi bebas (contoh: <code>MY</code>, <code>SG</code>, <code>Malaysia</code>).',
    'request.records_hint'   => 'Sekurang-kurangnya satu rekod pada apex (<code>@</code>) diperlukan. Di sini '
        . '<code>A</code>, <code>AAAA</code> dan <code>CNAME</code> tersedia. <code>TXT</code> hanya boleh ditambah '
        . 'selepas domain diaktifkan. Jika anda menggunakan <code>CNAME</code>, tiada rekod lain boleh diletakkan pada '
        . 'nama yang sama.',
    'request.records_psl'    => 'Secara lalai pendaftar ini mengendalikan penyelesaian nama. Bagi akhiran yang '
        . 'dibenarkan pihak atasan (us.ci, de5.net dan lain-lain dalam <em>Public Suffix List</em>), selepas domain '
        . 'diaktifkan anda boleh bertukar kepada pelayan nama sendiri melalui 「Pelayan nama」 di konsol — berkuat '
        . 'kuasa serta-merta tanpa semakan, dan rekod yang dimasukkan di sini akan dipadamkan ketika itu.',
    'manage.col_change'      => 'Jenis',
    'manage.col_records'     => 'Rekod',
    'manage.rows_hint'       => 'Apex ialah <code>@</code>; subdomain ialah satu label (<code>www</code>, '
        . '<code>mail</code>, <code>shop</code> dan lain-lain). Tandakan <em>Padam</em> untuk membuang rekod itu. Apex '
        . 'memerlukan sekurang-kurangnya satu rekod <code>A</code>, <code>AAAA</code> atau <code>CNAME</code>.',
    'manage.name_placeholder'   => '@ atau www',
    'manage.value_placeholder'  => 'Alamat IPv4/IPv6, nama hos atau teks',
    'val.srv_need_key'       => 'Masukkan kedua-dua kunci API dan ID instans.',
    'val.srv_bad_id'         => 'ID instans kelihatan tidak sah (huruf, angka, sengkang dan garis bawah sahaja).',
    'val.srv_taken'          => 'Instans ini sudah didaftarkan.',
    'val.srv_need_approval'  => 'Pelayan perlu diluluskan dahulu sebelum didaftarkan. Sila mohon melalui borang di '
        . 'bawah.',
    'val.srv_key_refused'    => 'RainYun tidak menerima kunci API ini:',
    'val.abuse_domain'       => 'Masukkan domain penuh (contoh: something.us.ci).',
    'val.abuse_short'        => 'Terangkan masalah dalam beberapa ayat (sekurang-kurangnya 20 aksara).',
    'mail.abuse_subject'     => 'Laporan penyalahgunaan mengenai %s',
    'val.srv_off'            => 'Ciri pautan pelayan awan ditutup buat masa ini.',
    'val.srv_no_crypto'      => 'Persekitaran PHP semasa tidak boleh menyulitkan (tiada AES-256-GCM). Kunci tidak boleh '
        . 'disimpan dengan selamat, jadi pautan ditolak.',
    'val.srv_need'           => 'Masukkan kedua-dua kunci API dan ID instans.',
    'val.srv_no_key'         => 'Kunci yang disimpan tidak boleh dinyahsulitkan (mungkin disulitkan dengan kunci laman '
        . 'lain). Sila daftarkan semula instans ini.',
    'val.srv_power_off'      => 'Mula / henti / mulakan semula ditutup buat masa ini.',
    'val.srv_reinstall_off'  => 'Operasi ini ditutup: pemasangan semula, pemulihan sandaran dan pemadaman sandaran '
        . 'boleh menyebabkan kehilangan data, jadi pentadbir perlu menghidupkannya.',
    'val.srv_backup_off'     => 'Ciri sandaran ditutup buat masa ini.',
    'val.srv_fw_off'         => 'Perubahan tembok api ditutup buat masa ini.',
    'val.srv_vnc_off'        => 'Konsol VNC ditutup buat masa ini.',
    'val.srv_vnc_none'       => 'Pembekal perkhidmatan tidak mengembalikan alamat konsol.',
    'val.mail_off'           => 'Pemajuan e-mel ditutup buat masa ini.',
    'val.mail_zone'          => 'Pemajuan e-mel hanya tersedia bagi akhiran yang diselesaikan Cloudflare (DNSHE tiada '
        . 'ciri ini).',
    'val.mail_active'        => 'Hanya domain aktif boleh menerima e-mel.',
    'val.mail_delegated'     => 'Domain ini menggunakan pelayan nama sendiri, jadi e-melnya dikendalikan di sana.',
    'val.mail_local'         => 'Sebelum @ hanya huruf, angka, noktah, sengkang dan garis bawah dibenarkan.',
    'val.mail_loop'          => 'Tidak boleh memajukan e-mel kepada domain yang sama.',
    'val.mail_taken'         => 'Domain ini sudah mempunyai alamat "%s".',
    'val.mail_own'           => 'Domain atau pemajuan ini bukan milik akaun anda.',
    'account.register_intro' => 'Dengan mencipta akaun, semua domain anda berkumpul di satu tempat: semak status, ubah '
        . 'rekod dan mohon domain baharu tanpa perlu mencari nombor permohonan. Akaun dipautkan kepada domain melalui '
        . 'alamat e-mel pendaftaran, dan semua domain yang dimohon dengan alamat itu dipaparkan di sini.',
    'account.invite_code'    => 'Kod jemputan (pilihan)',
    'account.invite_code_hint' => 'Jika anda datang melalui jemputan rakan, masukkan kodnya di sini. Bilangan jemputan '
        . 'akan ditambah pada prestasi orang itu. Anda juga boleh mendaftar melalui pautan jemputan mereka tanpa '
        . 'mengisi kod.',
    'account.registered_title' => 'Akaun telah dicipta',
    'account.registered_body' => 'Pautan pengesahan dihantar ke %s. Buka pautan dalam e-mel untuk mengaktifkan akaun, '
        . 'kemudian anda boleh log masuk.',
    'account.registered_next' => 'Sudah aktif? Log masuk di sini: %s.',
    'account.registered_flash' => 'Akaun dicipta. Sila selesaikan pengesahan alamat e-mel dahulu.',
    'account.verify_mail_failed' => 'E-mel pengesahan gagal dihantar. Gunakan 「Hantar semula e-mel pengesahan」 di '
        . 'halaman log masuk atau hubungi pendaftar.',
    'account.email_change_warning' => 'Konsol kini memaparkan %d domain yang dimohon dengan %s. Menukar alamat e-mel '
        . 'akan membuat senarai mengikut alamat baharu. Domain yang dimohon dengan alamat lama masih boleh diurus '
        . 'dengan nombor permohonan.',
    'mail.user_verify_subject' => '[%s] Sila sahkan alamat e-mel anda',
    'mail.user_verify_intro'  => 'Selamat datang! Buka pautan berikut untuk mengesahkan alamat e-mel yang digunakan '
        . 'akaun 「%s」:',
    'mail.user_verify_expire' => 'Pautan ini sah selama 3 hari.',
    'mail.user_verify_ignore' => 'Jika anda tidak meminta ini, abaikan e-mel ini. Tiada kesan.',
    'mail.user_reset_subject' => '[%s] Tetapkan kata laluan baharu',
    'mail.user_reset_intro'   => 'Permintaan tetapan semula kata laluan dibuat untuk akaun anda. Buka pautan berikut '
        . 'untuk menetapkan kata laluan baharu:',
    'mail.user_reset_expire'  => 'Pautan ini sah selama 2 jam.',
    'mail.user_reset_ignore'  => 'Jika anda tidak meminta ini, abaikan e-mel ini. Kata laluan tidak berubah.',
    'close.heading'    => 'Data saya dan pemadaman akaun',
    'close.intro'      => 'Anda boleh membawa keluar semua data anda atau memadam akaun sepenuhnya. Tiada apa-apa '
        . 'dipadamkan melainkan anda melakukannya sendiri di sini.',
    'close.export_heading' => 'Eksport semua data',
    'close.export_hint' => 'Satu fail teks yang mengandungi akaun, kuota, setiap domain dan rekodnya, tiket, sembang '
        . 'serta item pasaran dan kedai. Simpan dengan baik — ia untuk dibaca, bukan format sandaran untuk pemulihan.',
    'close.export_link' => 'Muat turun data saya',
    'close.export_title' => '%s — data saya',
    'close.export_when' => 'Dieksport pada %s',
    'close.export_account' => 'Akaun',
    'close.export_domains' => 'Domain dan rekod',
    'close.export_tickets' => 'Tiket pemindahan',
    'close.export_chat' => 'Sembang',
    'close.export_market' => 'Penyenaraian',
    'close.export_shop' => 'Permohonan kedai',
    'close.close_heading' => 'Padam akaun',
    'close.warning'    => 'Tindakan ini tidak boleh dibatalkan. Anda mempunyai %s domain: ia akan digantung dan rekod '
        . 'yang diterbitkan akan dibuang dari perkhidmatan DNS, tetapi domain itu tidak akan berpindah kepada orang '
        . 'lain.',
    'close.what'       => 'Item',
    'close.effect'     => 'Kesannya',
    'close.account'    => 'Akaun',
    'close.effect_account' => 'Nama pengguna, alamat e-mel dan kata laluan dipadamkan. Sembang, penyenaraian, '
        . 'permohonan kedai, kunci API dan pemajuan e-mel juga dipadamkan.',
    'close.names'      => '%s domain anda',
    'close.effect_names' => 'Ia akan digantung: rekod dibuang dari peringkat atasan, domain tidak lagi diselesaikan, dan '
        . 'ia tidak akan dikeluarkan semula sepanjang tempoh simpanan.',
    'close.other'      => 'Apa yang tinggal',
    'close.effect_other' => 'Log audit menyimpan tindakan itu sendiri (tanpa kata laluan) — pendaftar mesti dapat '
        . 'menjelaskan apa yang berlaku.',
    'close.password'   => 'Kata laluan anda',
    'close.phrase_label' => 'Masukkan frasa pengesahan',
    'close.phrase_hint' => 'Taip dengan tepat seperti berikut: DELETE MY ACCOUNT',
    'close.submit'     => 'Sahkan pemadaman akaun',
    'close.bad_phrase' => 'Sila taip frasa pengesahan dengan tepat.',
    'close.bad_password' => 'Kata laluan tidak betul.',
    'close.bad_code'   => 'Kod aplikasi pengesahan tidak sah sekarang.',
    'close.done'       => 'Akaun dipadamkan dan domain anda digantung. Terima kasih atas segalanya.',
    'notify.heading'   => 'E-mel yang anda terima',
    'notify.intro'     => 'Semuanya dihidupkan secara lalai. Anda boleh mematikan yang tidak diperlukan. E-mel '
        . 'berkaitan 「log masuk semula akaun」 sentiasa dihantar.',
    'notify.kind'      => 'Jenis',
    'notify.examples'  => 'Contoh',
    'notify.switch_off' => 'Matikan',
    'notify.kind_chat' => 'Balasan sembang',
    'notify.example_chat' => 'Apabila pendaftar membalas dalam sembang langsung',
    'notify.kind_transfer' => 'Pemindahan',
    'notify.example_transfer' => 'Apabila kod pemindahan tiba atau pemindahan berkembang',
    'notify.kind_market' => 'Pasaran',
    'notify.example_market' => 'Apabila penyenaraian diluluskan atau ditolak, atau ada permohonan beli',
    'notify.kind_shop' => 'Kedai kuota',
    'notify.example_shop' => 'Apabila perkongsian diluluskan atau ditolak',
    'notify.kind_domain' => 'Berkaitan domain',
    'notify.example_domain' => 'Keputusan semakan permohonan atau perubahan yang anda hantar',
    'notify.kind_account' => 'Berkaitan akaun',
    'notify.example_account' => 'Tetapan semula kata laluan, pengesahan alamat e-mel',
    'notify.always'    => 'E-mel berkaitan akaun tidak boleh dimatikan: ia berkaitan sama ada anda boleh log masuk '
        . 'semula. E-mel pengesahan tindakan anda sendiri juga sentiasa dihantar — ia rekod tindakan itu.',
    'notify.save'      => 'Simpan tetapan',
    'notify.saved'     => 'Disimpan.',
    'notify.no_table'  => 'Pangkalan data tiada item yang diperlukan halaman ini. Sila jalankan upgrade-mailpref.sql '
        . '(boleh dijalankan dari halaman semakan kendiri).',
    'notify.scope'     => 'Tetapan ini hanya terpakai pada alamat e-mel akaun ini. Ia tidak menjejaskan e-mel kepada '
        . '「orang tanpa akaun」 (pemohon, tetamu).',
];

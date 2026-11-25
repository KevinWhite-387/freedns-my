<?php
/**
 * freedns.my - 한국어 용어집 (1차, 핵심 키만).
 *
 * 번역되지 않은 키는 자동으로 영어로 대체됩니다(자세한 내용은 多语言-扩展方案.md 참고).
 * 용어 기준: 도메인 / 메일 / 서버 / 등록기관 / 계정 / 지원.
 */

return [
    'nav.request'   => '도메인 신청',
    'nav.status'    => '신청 조회',
    'nav.domains'   => '등록된 도메인',
    'nav.doc'       => '문서',
    'nav.whois'     => 'WHOIS 조회',
    'nav.market'    => '도메인 거래',
    'nav.shop'      => '할당량 상점',
    'nav.chat'      => '실시간 상담',
    'nav.changelog' => '변경 이력',
    'nav.console'   => '콘솔',
    'index.heading' => '내 도메인을 무료로 받으세요',
    'index.announcement' => '공지',
    'index.zones'   => '신청 가능한 접미사',
    'index.group_heading' => '그룹 참여',
    'request.heading' => '도메인 신청',
    'request.submit'  => '신청 보내기',
    'request.name'    => '원하는 도메인',
    'request.policy_link' => '등록 정책',
    'status.heading' => '신청 상태 조회',
    'status.pending_text' => '신청이 사람의 검토를 기다리고 있습니다. 보통 며칠 안에 처리되며, 하실 일은 없습니다.',
    'status.rejected_text' => '신청이 승인되지 않았습니다. 수정해서 같은 도메인으로 다시 신청할 수 있습니다.',
    'nav.home'            => '홈',
    'nav.manage'          => '레코드 관리',
    'nav.admin'           => '관리자',
    'nav.lang_en'         => 'English',
    'nav.lang_zh'         => '中文',
    'nav.lang_title'      => '언어',
    'nav.signin'          => '로그인',
    'nav.register'        => '회원가입',
    'nav.signout'         => '로그아웃',
    'nav.theme_title'     => '테마',
    'nav.theme_light'     => '밝게',
    'nav.theme_dark'      => '어둡게',
    'nav.theme_auto'      => '시스템 설정',
    'footer.tagline'      => '%s &mdash; 무료 도메인 등록기관.',
    'footer.contact'      => '연락처: ',
    'footer.generated'    => '페이지 생성 시각 %s(%s).',
    'error.list_title'    => '다음 문제를 수정해 주세요:',
    'error.token_title'   => '양식이 만료되었습니다',
    'error.token_body'    => '양식이 만료되었거나 보안 토큰이 일치하지 않습니다. 뒤로 가서 페이지를 새로 고친 뒤 '
        . '다시 제출해 주세요.',
    'status.active'       => '활성',
    'status.pending'      => '심사 대기',
    'status.rejected'     => '거부됨',
    'status.suspended'    => '정지됨',
    'status.deleted'      => '삭제됨',
    'common.optional'     => '(선택)',
    'common.none'         => '-',
    'index.intro1'         => '<strong>%s</strong>에 오신 것을 환영합니다: <code>%s</code> 아래의 도메인을 무료로 '
        . '제공합니다. 등록은 전액 무료이며 갱신료도 없고, 페이지에 광고를 넣지도 않습니다.',
    'index.intro_records'  => '이 등록기관이 해당 도메인의 DNS를 직접 관리합니다: 승인된 도메인에는 저희가 보관하는 '
        . '<code>A</code>, <code>AAAA</code>, <code>CNAME</code>, <code>TXT</code> 레코드가 게시됩니다.',
    'index.intro_manage'   => '도메인이 활성화되면 %s 페이지에서 레코드를 수정하고 <code>www.myname</code> 같은 '
        . '서브도메인을 추가할 수 있습니다. 변경 사항은 관리자 승인 후 게시됩니다.',
    'index.intro_psl'      => '상위에서 허용하는 접미사(%s에 등재된 us.ci, de5.net 등)는 콘솔에서 자체 '
        . '네임서버를 지정할 수 있습니다 — 즉시 적용되며 심사가 필요 없습니다.',
    'index.group_text'     => '질문이나 의견, 다른 사용자와의 정보 교환은 Telegram 그룹 %s에서 환영합니다.',
    'index.no_zone'        => '아직 설정된 접미사가 없습니다. 관리자가 <em>관리자 → Zones</em>에서 먼저 추가해야 '
        . '합니다.',
    'index.col_zone'       => '접미사',
    'index.col_requests'   => '신청',
    'index.col_active'     => '활성',
    'index.col_notes'      => '설명',
    'index.open'           => '접수 중',
    'index.closed'         => '접수 중지',
    'index.status_h'       => '등록기관 상태',
    'index.form'           => '신청 양식',
    'index.form_open'      => '<span class="ok">접수 중</span> &mdash; 신청을 받고 있습니다',
    'index.form_closed'    => '<span class="err">접수 중지</span> &mdash; 현재 신청을 받지 않습니다',
    'index.active_names'   => '활성 도메인',
    'index.awaiting'       => '심사 대기 신청',
    'index.review_time'    => '심사 기간',
    'index.review_time_v'  => '보통 며칠 이내, 사람이 직접 심사',
    'index.price'          => '비용',
    'index.price_v'        => '무료, 갱신도 무료',
    'index.howto'          => '도메인 신청 방법',
    'index.step1'          => '어떤 접미사를 쓸지, 이 도메인을 어느 주소나 호스트 이름으로 연결할지 먼저 정합니다.',
    'index.step2'          => '%s를 작성합니다: 접미사를 고르고 도메인을 입력한 뒤, 최소 한 개의 레코드'
        . '(<code>A</code>, <code>AAAA</code>, <code>CNAME</code> 중 하나)를 적습니다.',
    'index.step3'          => '제출 후 표시되는 신청 번호를 기록해 두세요. 진행 조회와 이후 레코드 관리에 필요한 '
        . '유일한 열쇠입니다.',
    'index.step4'          => '사람이 직접 심사합니다. 승인되면 레코드가 해당 zone에 게시되고, 이후 서브도메인과 '
        . 'TXT 레코드도 추가할 수 있습니다.',
    'index.step5'          => '이후에는 %s에서 주소를 바꾸거나 서브도메인을 추가하고, %s에서 현재 상태를 확인할 수 '
        . '있습니다.',
    'index.documents'      => '문서',
    'index.documents_text' => '신청 전에 %s을(를) 먼저 읽어 주세요. 정책에 맞지 않는 신청은 거부됩니다.',
    'index.contact'        => '연락처',
    'index.contact_q'      => '특정 신청에 대한 질문: %s. 메일에는 신청 번호를 적어 주세요.',
    'index.contact_none'   => '신청 양식이나 조회 페이지를 이용해 주세요. 전화 지원은 제공하지 않습니다.',
    'index.small_print'    => '이 서비스는 자원봉사자가 가능한 범위에서 운영하며 서비스 수준을 보장하지 않습니다. '
        . '악용, 스팸, 피싱에 사용된 도메인은 회수됩니다.',
    'index.docs_link'      => '등록 정책 및 문서',
    'index.manage_link'    => '레코드 관리',
    'index.psl_link'       => 'Public Suffix List(공용 접미사 목록)',

    /* ============ 제2차: 신청 양식 ============ */
    'request.intro'          => '아래 양식으로 도메인을 신청합니다. <strong>*</strong>는 필수입니다. 사람이 직접 '
        . '심사하므로 먼저 %s을(를) 읽어 주세요.',
    'request.closed'         => '신청 양식이 잠시 닫혀 있습니다.',
    'request.no_zone'        => '설정된 접미사가 없습니다. 관리자가 <em>관리자 → Zones</em>에서 먼저 추가해야 '
        . '신청을 받을 수 있습니다.',
    'request.s1'             => '1. 접미사 선택',
    'request.s2'             => '2. 원하는 도메인',
    'request.s3'             => '3. 신청자 정보',
    'request.s4'             => '4. 레코드',
    'request.s5'             => '5. 용도',
    'request.s6'             => '6. 정책',
    'request.zone_closed'    => '(현재 신규 신청을 받지 않습니다)',
    'request.label'          => '도메인 *',
    'request.label_hint'     => '라벨 부분만 입력합니다(예: <code>myname</code>). 전체 도메인은 '
        . '<code>myname.%s</code>이(가) 됩니다. <code>www.myname</code> 같은 서브도메인은 도메인이 활성화된 뒤에 '
        . '추가할 수 있습니다.',
    'request.reserved_toggle' => '예약 도메인 신청입니다',
    'request.reserved_hint'  => '등록기관이 해당 도메인을 위해 예약해 둔 경우에만 선택하세요. 예약 도메인은 한 '
        . '글자도 가능하며, 등록기관의 예약 목록에 있어야 하고, 선택하면 5번 항목의 용도가 필수가 됩니다.',
    'request.proxied_toggle' => 'Cloudflare 프록시로 게시(주황색 구름)',
    'request.proxied_hint'   => '선택하지 않으면 DNS only(이름 해석만)이며 이것이 기본 방식입니다. 선택하면 A, '
        . 'AAAA, CNAME 트래픽이 Cloudflare를 거칩니다(TXT는 항상 비프록시). 실제 주소를 숨길 수 있지만 일부 '
        . '국가/지역에서는 느려질 수 있습니다. 제출 후에는 등록기관만 변경할 수 있습니다.',
    'request.check_heading'  => '이 도메인을 아직 신청할 수 있나요?',
    'request.check_hint'     => '등록기관은 신청을 받기 전에 %s의 DNS 영역에서 그 이름이 사용 중인지 확인합니다. '
        . '아래 버튼으로 결과를 바로 볼 수 있으며, 이 단계에서는 신청이 전송되지 않습니다.',
    'request.check_submit'   => '먼저 DNS 레코드 확인',
    'request.precheck_ok'    => 'DNS 서비스에 %s의 레코드가 아직 없습니다. 신청할 수 있습니다.',
    'request.name_hint'      => '<code>@</code>는 신청하는 도메인 자체를 뜻합니다. 여기서는 서브도메인을 신청할 '
        . '수 없습니다. 값은 IPv4 주소, IPv6 주소 또는 호스트 이름입니다.',
    'request.col_name'       => '이름',
    'request.col_type'       => '종류',
    'request.col_value'      => '값',
    'request.purpose'        => '용도 요약(선택)',
    'request.purpose_hint'   => '한두 문장이면 충분합니다. 심사 참고용이며 공개되지 않습니다.',
    'request.purpose_reserved_hint' => '예약 도메인 신청 시 필수입니다.',
    'request.policy_accept'  => '%s을(를) 읽고 동의합니다 *',
    'request.done_title'     => '신청이 접수되었습니다.',
    'request.done_keep'      => '아래 신청 번호를 기록해 두세요. 진행 조회와 이후 레코드 관리에 필요한 유일한 '
        . '열쇠입니다.',
    'request.done_code'      => '신청 번호:',
    'request.done_domain'    => '도메인:',
    'request.done_zone'      => '접미사:',
    'request.done_status'    => '상태:',
    'request.done_submitted' => '제출 시각:',
    'request.done_records'   => '이번 신청의 레코드 &mdash; 도메인은 다음과 같이 해석됩니다:',
    'request.done_review'    => '관리자가 사람이 직접 심사합니다. 결정되면 상태가 <code>ACTIVE</code>(활성) 또는 '
        . '<code>REJECTED</code>(거부)가 되고, 위 레코드가 <code>%s</code> zone에 게시됩니다.',
    'request.done_mail'      => '메일이 도달할 수 있다면 %s에도 알립니다.',
    'request.done_manage'    => '승인 후에는 이 신청 번호와 메일 주소로 %s 페이지에서 레코드 수정, 서브도메인 및 '
        . '<code>TXT</code> 레코드 추가를 할 수 있습니다.',
    'request.done_check'     => '언제든 %s에서 현재 상태를 확인할 수 있습니다.',
    'request.back_home'      => '홈으로 돌아가기',

    /* ============ 제3차: 신청 상태 조회 ============ */
    'status.intro'           => '제출 후 표시된 신청 번호와 그때 입력한 메일 주소를 입력해 주세요. 현재 상태와 '
        . '저장된 레코드를 보여 드립니다.',
    'status.code'            => '신청 번호 *',
    'status.code_hint'       => '예: <code>FD-25-1A2B3C4D</code>',
    'status.email'           => '신청에 사용한 메일 주소 *',
    'status.show'            => '상태 확인',
    'status.status'          => '상태',
    'status.zone'            => '접미사',
    'status.applicant'       => '신청자',
    'status.org'             => '조직',
    'status.submitted'       => '제출 시각',
    'status.updated'         => '최종 갱신',
    'status.decided'         => '심사 시각',
    'status.records'         => '레코드 수',
    'status.published'       => '이 도메인에는 다음 레코드가 게시되어 있습니다:',
    'status.kept'            => '이 신청에는 다음 레코드가 저장되어 있습니다:',
    'status.no_record'       => '; 레코드 없음',
    'status.change_title'    => '심사 대기 중인 변경이 있습니다',
    'status.change_text'     => '%s에서 레코드 변경을 제출했지만 아직 관리자 심사를 기다리고 있습니다. 승인될 '
        . '때까지 위 레코드가 유효합니다.',
    'status.proposed'        => '제안된 레코드:',
    'status.note'            => '등록기관 메모',
    'status.active_text'     => '위 레코드는 <code>%s</code> zone에서 유효합니다. 서비스를 제출한 주소나 호스트 '
        . '이름으로 연결해 주세요. %s에서 레코드를 수정하거나 서브도메인을 추가할 수 있습니다. 변경은 심사 후 '
        . '게시됩니다. 상위가 허용하는 접미사라면 콘솔의 「네임서버」에서 자체 네임서버로 바꿀 수 있습니다'
        . '(즉시 적용·심사 불필요).',

    /* ============ 제4차: 레코드 관리 ============ */
    'manage.heading'         => '레코드 관리',
    'manage.notice_heading'  => '중요 안내',
    'manage.closed_title'    => '이 페이지는 현재 닫혀 있습니다',
    'manage.closed_text'     => '현재 레코드 변경 제출은 로그인한 계정만 받습니다. 신청 시 사용한 메일 주소로'
        . '(또는 먼저 가입하여) 콘솔에 로그인해 주세요. 로그인 후에는 이 페이지를 평소처럼 사용할 수 있습니다.',
    'manage.closed_login'    => '콘솔 로그인',
    'manage.closed_register' => '계정 만들기',
    'manage.intro'           => '신청 번호와 신청 시 입력한 메일 주소를 넣으면 해당 도메인의 레코드를 수정하고 '
        . '<code>www.myname</code> 같은 서브도메인을 추가할 수 있습니다. 심사 대기 중인 신청은 변경이 곧바로 '
        . '저장되며(이 단계에서는 아직 아무것도 공개되지 않습니다), 도메인이 <strong>활성</strong>된 뒤에는 변경할 '
        . '때마다 심사 대기열에 들어가 관리자 승인 후 게시됩니다.',
    'manage.code'            => '신청 번호 *',
    'manage.code_hint'       => '도메인 신청 시 받은 번호입니다(예: <code>FD-25-1A2B3C4D</code>). 번호를 잊었다면 '
        . '바닥글의 메일 주소로 연락해 주세요.',
    'manage.email'           => '신청 시 사용한 메일 주소 *',
    'manage.open'            => '레코드 관리 열기',
    'manage.can_do'          => '여기서 할 수 있는 일',
    'manage.can_1'           => '심사 대기 중인 신청의 레코드를 수정(즉시 저장);',
    'manage.can_2'           => '활성 도메인의 레코드 변경(심사 대기열로 들어감);',
    'manage.can_3'           => '서브도메인 추가(예: <code>www.myname</code>, <code>mail.myname</code>, '
        . '<code>shop.myname</code>);',
    'manage.can_4'           => 'CNAME 및 TXT 레코드 추가;',
    'manage.can_5'           => '더 이상 필요 없는 레코드 삭제;',
    'manage.can_6'           => '심사 대기 중인 변경 취소.',
    'manage.hint_review'     => '활성 도메인의 변경은 사람의 심사 후 게시됩니다.',
    'manage.hint_disabled'   => ' 등록기관이 현재 직접 변경 기능을 닫아 두었습니다.',
    'manage.submitted_title' => '변경을 제출했습니다. 심사 대기 중입니다.',
    'manage.name_label'      => '도메인:',
    'manage.code_label'      => '신청 번호:',
    'manage.submitted_label' => '제출 시각:',
    'manage.proposed'        => '제안된 레코드:',
    'manage.what_changes'    => '변경 내용',
    'manage.added'           => '추가',
    'manage.removed'         => '삭제',
    'manage.kept'            => '변경 없음',
    'manage.kept_n'          => '%d개 레코드는 그대로',
    'manage.stay_live'       => '변경이 승인되기 전까지 현재 유효한 레코드는 그대로입니다. 기다리는 동안 직접 '
        . '취소할 수 있습니다:',
    'manage.withdraw'        => '이 변경 취소',
    'manage.withdraw_queued' => '대기열의 변경 취소',
    'manage.check_status'    => '상태 확인',
    'manage.manage_again'    => '레코드 다시 관리',
    'manage.queued_notice'   => '%s에서 제출한 변경이 아직 심사 대기 중입니다. 아래 양식은 그 제안을 출발점으로 '
        . '합니다. 다시 제출하면 대기열의 변경이 교체됩니다.',
    'manage.records_live'    => '현재 유효한 레코드',
    'manage.records_on_file' => '저장된 레코드',
    'manage.disabled'        => '등록기관이 직접 레코드 변경 기능을 닫았습니다. 바닥글의 메일 주소로 신청 번호와 '
        . '함께 연락해 주세요.',
    'manage.not_editable'    => '레코드를 변경할 수 있는 것은 심사 대기 중인 신청 또는 <code>ACTIVE</code>(활성) '
        . '도메인뿐입니다. 현재 상태: %s.',
    'manage.pending_notice'  => '신청이 아직 심사 대기 중이라 이 레코드들은 공개되지 않았습니다. 여기서 한 변경은 '
        . '곧바로 저장되어 심사 때 반영됩니다. 도메인이 활성화된 뒤에는 변경할 때마다 심사 대기열로 들어갑니다.',
    'manage.active_notice'   => '제출하면 레코드 전체가 심사 대기열로 들어갑니다. 관리자가 승인하기 전까지는 '
        . '아무것도 공개되지 않고, 현재 유효한 레코드는 그때까지 그대로입니다.',
    'manage.records_of'      => '%s의 레코드',
    'manage.col_name'        => '이름',
    'manage.col_type'        => '종류',
    'manage.col_value'       => '값',
    'manage.col_remove'      => '삭제',
    'manage.col_ttl'         => 'TTL',
    'manage.col_proxy'       => '프록시',
    'manage.apex'            => '@',
    'manage.add_records'     => '레코드 추가',
    'manage.limits'          => '사용 가능한 종류: <code>A</code>, <code>AAAA</code>(공인 IPv4/IPv6 주소, 같은 '
        . '이름에 최대 4개), <code>CNAME</code>(호스트 이름이며 그 이름에서 유일한 레코드여야 함), '
        . '<code>TXT</code>(최대 255자). 도메인당 최대 %d개.',
    'manage.note_label'      => '심사자에게 남기는 메모(선택)',
    'manage.submit'          => '심사에 제출',

    /* ============ 제6차: 레코드 검증 ============ */
    'rec.row_empty'        => '%d번째 줄: %s 레코드의 값이 비어 있습니다. 이 레코드를 삭제하려면 「삭제」에 '
        . '체크해 주세요.',
    'rec.new_row_empty'    => '%d번째 줄 추가: %s 레코드의 값을 입력해 주세요.',
    'rec.name_invalid'     => '이름 부분 "%s"이(가) 올바르지 않습니다: apex는 <code>@</code>, 서브도메인은 영문·'
        . '숫자·하이픈으로 된 라벨 하나를 입력해 주세요.',
    'rec.type_unsupported' => '레코드 종류 "%s"은(는) 지원하지 않습니다: A, AAAA, CNAME, TXT, MX, SRV, CAA, PTR을 '
        . '쓸 수 있습니다.',
    'rec.ttl_range'        => '%s번째 줄: TTL은 1(자동) 또는 60~86400초여야 합니다.',
    'rec.ttl_hint'         => '단위는 초, 60~86400. 1은 Cloudflare의 자동 TTL(Auto)입니다. 표시된 숫자를 그대로 두면 '
        . '등록기관 기본값을 사용합니다.',
    'rec.ttl_auto'         => '자동',
    'rec.type_not_here'    => '여기서는 %s 레코드를 신청할 수 없습니다. 신청 시 허용되는 종류: %s.',
    'rec.txt_later'        => '신청 시에는 TXT 레코드를 입력할 수 없습니다. 도메인이 활성화된 뒤에 추가할 수 '
        . '있습니다.',
    'rec.a_invalid'        => 'A 레코드의 값은 IPv4 주소여야 합니다(입력값 "%s").',
    'rec.aaaa_invalid'     => 'AAAA 레코드의 값은 IPv6 주소여야 합니다(입력값 "%s").',
    'rec.not_public'       => '주소 %s은(는) 공인 라우팅 가능 주소가 아닙니다.',
    'rec.cname_target'     => 'CNAME 대상은 호스트 이름이어야 합니다(예: example.net, 입력값 "%s").',
    'rec.cname_self'       => 'CNAME은 자기 자신을 가리킬 수 없습니다(%s).',
    'rec.txt_length'       => 'TXT 값은 최대 255자입니다.',
    'rec.txt_control'      => 'TXT 값에는 제어 문자나 줄바꿈을 넣을 수 없습니다.',
    'rec.duplicate'        => '레코드 %s %s %s이(가) 중복되었습니다.',
    'rec.need_apex_record' => 'apex에는 최소 한 개의 A, AAAA 또는 CNAME 레코드가 필요합니다.',

    /* ============ 제7차: 변경 결과와 계정 ============ */
    'change.withdrawn'        => '변경을 취소했습니다.',
    'change.nothing_pending'  => '현재 심사 대기 중인 변경이 없습니다.',
    'change.updated_pending'  => '레코드를 갱신했습니다. 신청은 아직 심사 대기 중입니다.',
    'change.disabled'         => '이 페이지의 레코드 변경 기능이 닫혀 있습니다. 바닥글의 메일 주소로 연락해 주세요.',
    'change.not_editable'     => '레코드를 변경할 수 있는 것은 심사 대기 중인 신청 또는 <code>ACTIVE</code>(활성) '
        . '도메인뿐입니다. 현재 상태: %s.',
    'change.save_error'       => '서버 오류로 변경을 저장하지 못했습니다. 몇 분 뒤 다시 시도해 주세요.',
    'account.login_heading'   => '로그인',
    'account.login_intro'     => '사용자 이름 또는 메일 주소로 로그인하면 소유한 모든 도메인을 한곳에서 관리할 수 '
        . '있습니다.',
    'account.login_id'        => '사용자 이름 또는 메일 주소',
    'account.login_id_hint'   => '둘 다 가능합니다.',
    'account.login_submit'    => '로그인',
    'account.welcome_back'    => '로그인했습니다.',
    'account.password'        => '비밀번호',
    'account.password_hint'   => '8자 이상, 사용자 이름이나 메일 주소와 같지 않게 해 주세요.',
    'account.password_repeat' => '비밀번호 확인',
    'account.username'        => '사용자 이름',
    'account.username_hint'   => '3~32자: 영문·숫자·밑줄·하이픈, 영문으로 시작해야 합니다. admin, www 같은 예약어는 '
        . '쓸 수 없습니다.',
    'account.email'           => '메일 주소',
    'account.email_hint'      => '이 메일 주소로 신청한 도메인이 콘솔에 표시되며, 확인 링크를 이 주소로 보냅니다.',
    'account.register_heading' => '계정 만들기',
    'account.register_submit' => '계정 만들기',
    'account.closed'          => '현재 신규 가입을 받지 않습니다.',
    'account.have_account'    => '이미 계정이 있습니다',
    'account.no_account'      => '새 계정 만들기',

    /* ============ 제8차: 메일 확인과 비밀번호 재설정 ============ */
    'account.verify_heading'  => '메일 주소 확인',
    'account.verify_ok'       => '메일 주소 확인 완료',
    'account.verify_ok_text'  => '이제 콘솔을 사용할 수 있습니다: %s. 아직 로그인하지 않았다면 먼저 %s해 주세요.',
    'account.verify_fail'     => '이 확인 링크는 유효하지 않습니다',
    'account.verify_fail_text' => '링크가 만료되었거나 이미 사용되었습니다. 로그인 후 확인 메일을 다시 보내거나 '
        . '「비밀번호 찾기」 페이지를 이용해 주세요.',
    'account.verify_retry'    => '이 링크를 다시 시도',
    'account.verify_resend'   => '확인 메일 다시 보내기',
    'account.verify_resent'   => '새 확인 메일을 보냈습니다.',
    'account.email_unverified' => '메일 주소 %s은(는) 아직 확인되지 않았습니다. 받은 편지함(스팸함 포함)을 확인하거나 '
        . '아래에서 확인 메일을 다시 보내 주세요.',
    'account.pending_notice'  => '계정 %s이(가) 아직 활성화되지 않았습니다. 먼저 메일 주소 확인을 완료해 주세요.',
    'account.resend_submit'   => '확인 메일 다시 보내기',
    'account.resend_done'     => '새 확인 메일을 %s로 보냈습니다.',
    'account.resend_failed'   => '해당 메일 주소에 해당하는 미확인 계정을 찾지 못했습니다.',
    'account.forgot_link'     => '비밀번호를 잊으셨나요?',
    'account.forgot_heading'  => '비밀번호 찾기',
    'account.forgot_intro'    => '계정의 메일 주소를 입력해 주세요. 새 비밀번호를 설정하는 메일을 보내 드립니다.',
    'account.forgot_submit'   => '재설정 링크 보내기',
    'account.forgot_sent_title' => '해당 메일 주소가 가입되어 있다면 메일을 보냈습니다',
    'account.forgot_sent_text' => '%s이(가) 여기에 가입되어 있다면 이미 메일을 보냈습니다. 링크는 2시간 동안 '
        . '유효합니다.',
    'account.reset_heading'   => '새 비밀번호 설정',
    'account.reset_intro'     => '계정 %s의 새 비밀번호를 설정합니다.',
    'account.reset_submit'    => '새 비밀번호 저장',
    'account.reset_done_title' => '비밀번호를 변경했습니다',
    'account.reset_done_text' => '새 비밀번호로 로그인할 수 있습니다: %s.',
    'account.reset_bad_title' => '이 재설정 링크는 유효하지 않습니다',
    'account.reset_bad_text'  => '링크가 만료되었거나 이미 사용되었습니다. 다시 신청해 주세요: %s.',

    /* ============ 제9차: 콘솔과 계정 정보 ============ */
    'account.console_heading' => '콘솔',
    'account.console_intro'   => '현재 로그인: %s. 아래는 모두 이 메일 주소로 신청한 도메인입니다.',
    'account.member_since'    => '가입일',
    'account.last_login'      => '마지막 로그인',
    'account.counts'          => '통계',
    'account.counts_value'    => '총 %d개 도메인: %d개 활성, %d개 심사 대기, %d개 레코드 변경 심사 대기',
    'account.my_domains'      => '내 도메인',
    'account.no_domains'      => '%s로 신청한 도메인이 아직 없습니다. 「도메인 신청」으로 하나 신청하면 여기에 '
        . '표시됩니다.',
    'account.col_change'      => '대기 중 변경',
    'account.col_actions'     => '작업',
    'account.queued'          => '심사 대기',
    'account.reserved_mark'   => '예약 도메인',
    'account.action_manage'   => '레코드 관리',
    'account.action_status'   => '상태 보기',
    'account.what_now'        => '다음에 할 수 있는 일',
    'account.link_new'        => '도메인 하나 더 신청: %s.',
    'account.link_profile'    => '프로필이나 비밀번호 변경: %s.',
    'account.link_export'     => '보유한 모든 도메인과 레코드 내보내기: %s.',
    'account.link_old'        => '계정 없이도: 신청 번호로 %s에서 조회하고, %s에서 개별 도메인을 관리할 수 있습니다.',
    'account.manage_heading'  => '레코드 관리',
    'account.manage_not_found' => '이 도메인은 귀하의 계정 것이 아닙니다.',
    'account.records_now'     => '현재 유효한 레코드',
    'account.profile_heading' => '계정 정보',
    'account.profile_intro'   => '현재 로그인: %s. 아래 변경에는 현재 비밀번호가 필요합니다.',
    'account.current_password' => '현재 비밀번호',
    'account.new_username'    => '새 사용자 이름',
    'account.change_password' => '비밀번호 변경',
    'account.new_password'    => '새 비밀번호',

    /* ============ 제10차: 계정 관리와 검증 ============ */
    'account.change_email'    => '메일 주소 변경',
    'account.new_email'       => '새 메일 주소',
    'account.email_changed'   => '메일 주소를 %s(으)로 변경했습니다. 확인을 완료해 주세요.',
    'account.profile_saved'   => '저장했습니다.',
    'account.password_changed' => '비밀번호를 변경했습니다.',
    'account.nothing_changed' => '변경 사항이 없습니다.',
    'account.save'            => '저장',
    'account.delete_heading'  => '계정 삭제',
    'account.delete_warning'  => '계정이 삭제됩니다. 아래 %d개 도메인은 그대로 남아 신청 번호로 계속 관리할 수 '
        . '있습니다. 이 작업은 되돌릴 수 없습니다.',
    'account.delete_confirm'  => '내 계정 삭제를 확인합니다',
    'account.delete_submit'   => '계정 삭제',
    'account.deleted'         => '계정을 삭제했습니다. 도메인은 그대로 남아 있습니다.',
    'account.signed_out'      => '콘솔에서 로그아웃했습니다.',
    'account.please_sign_in'  => '먼저 콘솔에 로그인해 주세요.',
    'account.export_heading'  => '내 데이터 내보내기',
    'account.old_channel_heading' => '단일 도메인만 관리한다면 가입이 필요 없습니다',
    'account.old_channel_text' => '콘솔은 편의 기능일 뿐 필수가 아닙니다: 신청 번호와 신청 시 메일 주소로 예전처럼 '
        . '%s에서 진행을 조회하고 %s에서 레코드를 수정할 수 있습니다.',
    'val.password_mismatch'   => '입력한 비밀번호가 일치하지 않습니다.',
    'val.username_short'      => '사용자 이름은 3자 이상이어야 합니다.',
    'val.username_long'       => '사용자 이름은 최대 32자입니다.',
    'val.username_chars'      => '사용자 이름은 영문으로 시작하고 영문·숫자·밑줄·하이픈만 쓸 수 있습니다.',
    'val.username_reserved'   => '이 사용자 이름은 예약어입니다. 다른 이름을 사용해 주세요.',
    'val.username_taken'      => '이 사용자 이름은 이미 사용 중입니다.',
    'val.user_email_taken'    => '이 메일 주소는 이미 가입되어 있습니다.',
    'val.password_short'      => '비밀번호는 8자 이상이어야 합니다.',
    'val.password_long'       => '비밀번호가 너무 깁니다(최대 200자).',
    'val.password_weak'       => '비밀번호는 사용자 이름이나 메일 주소와 같을 수 없습니다.',
    'val.user_current_password' => '현재 비밀번호가 올바르지 않습니다.',
    'val.user_credentials'    => '사용자 이름, 메일 주소 또는 비밀번호가 올바르지 않습니다.',
    'val.user_pending'        => '이 계정은 아직 활성화되지 않았습니다. 확인 메일의 링크를 먼저 열어 주세요.',
    'val.user_suspended'      => '이 계정은 정지되었습니다. 등록기관에 연락해 주세요.',
    'val.user_token'          => '링크가 만료되었거나 이미 사용되었습니다.',
    'val.user_policy'         => '먼저 등록 정책에 동의해 주세요.',
    'val.user_invite_code'    => '초대 코드가 없습니다. 없으면 비워 두세요.',
    'val.user_closed'         => '현재 신규 가입을 받지 않습니다.',
    'val.user_limit'          => '오늘 이 주소에서 가입한 계정이 너무 많습니다. 내일 다시 시도해 주세요.',
    'val.user_delete_confirm' => '먼저 확인란에 체크해 주세요.',

    /* ============ 제11차: 양식 검증 메시지 ============ */
    'val.zone_choose'      => '아래에 나열된 접미사 중 하나를 선택해 주세요.',
    'val.zone_closed'      => '접미사 %s은(는) 현재 새 신청을 받지 않습니다.',
    'val.no_zone'          => '설정된 접미사가 없어 신청을 받을 수 없습니다.',
    'val.form_closed'      => '신청 양식이 잠시 닫혀 있습니다. 나중에 다시 시도해 주세요.',
    'val.spam'             => '제출이 스팸 필터에 걸렸습니다.',
    'val.name_required'    => '등록할 도메인을 입력해 주세요.',
    'val.name_dots'        => '여기서는 라벨 하나만 등록할 수 있습니다. "%s"만 입력해 주세요. '
        . '<code>www.%s</code> 같은 서브도메인은 도메인이 활성화된 뒤에 추가할 수 있습니다.',
    'val.name_length'      => '도메인 길이는 2~63자여야 합니다.',
    'val.name_length_reserved' => '예약 도메인은 1~63자까지 가능합니다.',
    'val.reserved_not_listed' => '도메인 "%s"은(는) 등록기관의 예약 목록에 없어 「예약 도메인 신청」을 쓸 수 '
        . '없습니다. 체크를 해제해 주세요.',
    'val.reserved_use_channel' => '도메인 "%s"은(는) 등록기관이 예약한 이름입니다. 신청하려면 「예약 '
        . '도메인입니다」(2번 항목)를 체크하고 용도를 적어 주세요.',
    'val.reserved_purpose' => '예약 도메인 신청에는 설명이 필수입니다: 5번 항목에 용도를 적어 주세요.',
    'val.reserved_no_table' => '서버에 예약 도메인 목록이 아직 없어 이 옵션을 쓸 수 없습니다. 등록기관에 '
        . '연락해 주세요(데이터베이스에 upgrade-reserved.sql을 가져와야 합니다).',
    'val.cf_records_exist' => 'Cloudflare에 "%s"의 레코드가 이미 있습니다(총 %s개): %s. 이 도메인은 사용 중이니 '
        . '다른 이름으로 신청해 주세요. 문제가 있다고 보면 등록기관에 연락해 주세요.',
    'val.cf_check_failed'  => '지금은 Cloudflare에 이 도메인을 조회할 수 없어(%s) 이번 신청은 접수되지 않았습니다. '
        . '몇 분 뒤 다시 시도해 주세요.',
    'val.name_invalid'     => '도메인은 소문자 a-z, 숫자 0-9, 하이픈만 쓸 수 있고 하이픈으로 시작하거나 끝날 수 '
        . '없습니다.',
    'val.name_reserved'    => '도메인 "%s"은(는) 등록기관 예약이라 등록할 수 없습니다.',
    'val.applicant_required' => '이름을 입력해 주세요.',
    'val.applicant_long'   => '이름이 너무 깁니다.',
    'val.email_required'   => '메일 주소를 입력해 주세요.',
    'val.email_invalid'    => '메일 주소 형식이 올바르지 않습니다.',
    'val.org_long'         => '조직 이름이 너무 깁니다.',
    'val.country_long'     => '국가/지역 입력이 너무 깁니다.',
    'val.purpose_long'     => '용도 설명이 너무 깁니다(최대 2000자).',
    'val.policy_required'  => '등록 정책에 동의해야 합니다.',
    'val.dup_domain'       => '%s은(는) 이미 이 등록기관에서 「%s」 상태입니다(신청 번호 %s).',
    'val.limit_email'      => '이 메일 주소는 %s에서 이미 %d개 도메인(활성 또는 심사 대기)을 보유해 현재 상한에 '
        . '도달했습니다.',
    'val.quota_exceeded'   => '이 메일 주소는 모든 접미사 합계로 최대 %s개 도메인을 보유할 수 있습니다(현재 '
        . '%s개). 상한에 도달했습니다. 거부되거나 삭제된 도메인은 자리를 반환합니다. 더 필요하면 등록기관에 '
        . '연락해 주세요.',
    'val.name_taken'       => '도메인 "%s"은(는) 해당 접미사의 DNS 서비스에서 사용 중이라 발급할 수 없습니다. '
        . '다른 이름으로 시도해 주세요.',
    'val.limit_ip'         => '이 연결에서 오늘 신청이 너무 많습니다. 내일 다시 시도해 주세요.',
    'val.save_error'       => '서버 오류로 신청을 저장하지 못했습니다. 몇 분 뒤 다시 시도해 주세요.',
    'val.lookup_missing'   => '신청 번호와 메일 주소를 모두 입력해 주세요.',
    'val.lookup_none'      => '일치하는 신청이 없습니다. 신청 번호와 메일 주소를 확인해 주세요. 번호는 '
        . 'FD-25-1A2B3C4D 형식입니다.',
    'val.throttled'        => '시도가 너무 많습니다. 몇 분 뒤 다시 시도해 주세요.',

    /* ============ 제12차: 도메인 목록과 변경 내역 ============ */
    'list.heading'        => '도메인 목록',
    'list.count_one'      => '%s님이 도메인 1개를 등록했습니다%s.',
    'list.count_many'     => '%s님이 도메인 %s개를 등록했습니다%s.',
    'list.where_all'      => '이 등록기관',
    'list.where_zone'     => '<code>%s</code> 아래',
    'list.filtered'       => '(필터 적용됨)',
    'list.also'           => '심사 대기, 거부, 정지된 신청은 표시되지 않습니다.',
    'list.search'         => '이름으로 검색',
    'list.zone'           => '접미사',
    'list.all_zones'      => '모든 접미사',
    'list.search_btn'     => '검색',
    'list.reset'          => '초기화',
    'list.col_name'       => '도메인',
    'list.col_zone'       => '접미사',
    'list.col_records'    => '레코드',
    'list.col_org'        => '조직',
    'list.col_registered' => '등록 시각',
    'list.none'           => '조건에 맞는 도메인이 없습니다.',
    'list.more'           => '(그리고 %d개 더)',
    'changelog.heading'  => '변경 내용',
    'changelog.intro'    => '알아 둘 만한 변경을 여기에 기록합니다: 무엇을 고쳤고 무엇을 추가했는지. 최신 변경이 맨 위에 '
        . '있습니다.',
    'changelog.footer'   => '이해가 안 되는 부분이나 여기에 없는 문제가 있으면 「온라인 상담」에서 알려 주세요. '
        . '여기와 메일로 답변드립니다.',

    /* ============ 제13차: 악용 신고 · 약관 · 자체 점검 ============ */
    'abuse.heading'  => '악용 신고',
    'abuse.heading_short' => '악용 신고',
    'abuse.intro'    => '이 등록기관은 도메인을 무료로 발급하며 악용을 진지하게 다룹니다. 어느 접미사의 도메인이 '
        . '피싱, 스팸, 악성코드, 상표 침해 또는 기타 불법 용도로 쓰이고 있다면 여기에서 알려 주세요 — 계정 '
        . '가입은 필요 없습니다.',
    'abuse.domain'   => '신고할 도메인',
    'abuse.reporter' => '귀하의 메일 주소',
    'abuse.reporter_hint' => '세부 사항을 확인하기 위해 연락드릴 수 있습니다. 공개되지 않으며 도메인 소유자에게도 '
        . '알려지지 않습니다.',
    'abuse.kind'     => '어떤 종류인가요?',
    'abuse.kind_phishing'  => '피싱 / 사기',
    'abuse.kind_spam'      => '스팸',
    'abuse.kind_malware'   => '악성코드',
    'abuse.kind_trademark' => '상표 침해',
    'abuse.kind_illegal'   => '불법 콘텐츠',
    'abuse.kind_other'     => '기타',
    'abuse.message'  => '무엇을 보셨나요?',
    'abuse.message_hint' => '가능한 한 객관적으로, 전체 주소(구체적인 URL 등)를 함께 적어 주세요. 시각과 '
        . '스크린샷이 있으면 더 좋습니다. 신고 내용은 상위 서비스 제공자에게 제공될 수 있습니다.',
    'abuse.submit'   => '신고 보내기',
    'abuse.thanks'   => '신고해 주셔서 감사합니다. 접수했으며 사람이 확인합니다. 남겨 주신 메일 주소로 연락드릴 수 '
        . '있습니다.',
    'abuse.no_table' => '데이터베이스에 신고 테이블이 없습니다. upgrade-abuse.sql을 가져와 주세요(자체 점검 '
        . '페이지에서 실행할 수 있습니다).',
    'abuse.policy'   => '악용에 사용된 도메인은 정지되거나 삭제됩니다. 신고가 타당하지 않으면 이유를 밝혀 '
        . '거부하며, 두 결과 모두 기록으로 남습니다.',
    'legal.terms'    => '이용 약관',
    'legal.privacy'  => '개인정보 처리방침',
    'legal.intro'    => '이 등록기관의 규칙과 귀하의 데이터가 어떻게 처리되는지 쉬운 말로 설명합니다.',
    'legal.footer'   => '이 문구는 관리자에서 언제든 수정할 수 있으므로 항상 현재 운영 중인 버전의 서비스를 '
        . '설명합니다.',
    'health.footer'  => '처리할 항목이 있으면 자체 점검 페이지에서 데이터베이스 구조를 바로 복구할 수 있습니다.',

    /* ============ 제14차: WHOIS 조회와 접근 제한 ============ */
    'whois.heading'           => 'WHOIS 조회',
    'whois.intro'             => '도메인을 입력하세요: 이 등록기관이 발급한 도메인은 자체 등록 데이터베이스에서 바로 '
        . '답하고, 그 외 도메인은 공개 WHOIS 데이터를 조회합니다(RDAP 우선, 필요하면 전통적인 43번 포트 서비스로 '
        . '대체).',
    'whois.label'             => '도메인',
    'whois.submit'            => '조회',
    'whois.disabled'          => '현재 WHOIS 조회 기능이 닫혀 있습니다.',
    'whois.invalid'           => '"%s"은(는) 유효한 도메인이 아닙니다. myname.freedns.my 또는 example.com 같은 '
        . '형식으로 입력해 주세요.',
    'whois.not_registered'    => '찾을 수 없음: %s은(는) 이 등록기관에 등록되어 있지 않습니다(%s은(는) 이 '
        . '사이트의 접미사 중 하나입니다). 심사 대기와 거부된 신청은 표시되지 않습니다.',
    'whois.external_off'      => '이 등록기관은 자체 접미사 아래의 도메인에만 답합니다. 외부 도메인 조회는 닫혀 '
        . '있습니다.',
    'whois.limit'             => '이 주소에서는 한 시간에 %s건까지 외부 도메인을 조회할 수 있습니다. 모두 '
        . '사용했습니다. 잠시 후 다시 시도하거나 해당 도메인의 등록기관에서 직접 조회해 주세요.',
    'whois.cached'            => '아래는 캐시된 결과입니다(%s). 몇 분마다 자동으로 갱신됩니다.',
    'whois.lookup_failed'     => '%s 조회에 실패했습니다: %s',
    'whois.internal_note'     => '이 도메인은 이 사이트에 등록되어 있습니다. 여기서는 어떠한 개인정보도 공개하지 '
        . '않습니다: 등록기관은 신청자 정보를 보관하며 상태와 레코드만 표시합니다(IP는 공개 목록과 같은 방식으로 '
        . '마스킹합니다).',
    'whois.external_note'     => '이 결과는 해당 도메인 등록기관의 공개 WHOIS/RDAP 서비스에서 온 것이며 정확성은 '
        . '그 등록기관의 책임입니다.',
    'whois.help_heading'      => '설명',
    'whois.help_1'            => '이 사이트 접미사 아래의 도메인은 바로 답합니다(예: %s) — 데이터는 자체 등록 '
        . '데이터베이스에서 옵니다.',
    'whois.help_2'            => '그 외 도메인은 외부 조회로 처리합니다: 먼저 RDAP, 다음으로 전통적인 43번 포트 '
        . 'WHOIS. 둘 다 없는 접미사는 답할 수 없습니다.',
    'whois.help_3'            => '외부 조회는 주소당 한 시간 %s건으로 제한되며 몇 분간 캐시합니다. 따라서 이 '
        . '페이지를 무료 WHOIS 프록시로 쓸 수 없습니다.',
    'block.heading'           => '접근이 제한되었습니다',
    'block.message'           => '접근이 제한되었습니다: 이 사이트가 귀하의 이 주소에서 짧은 시간에 너무 많은 '
        . '요청을 받았습니다. 관리자에게 연락해 주세요.',
    'block.remaining'         => '제한은 약 %s분 후에 자동으로 해제됩니다.',
    'block.contact'           => '관리자 연락처: %s',
    'block.hint'              => '단순히 둘러보는 중이라면 몇 분 뒤 다시 시도해 주세요. 계정에는 아무런 변경이 '
        . '없습니다.',

    /* ============ 제15차: API 키 ============ */
    'api.heading'    => 'API 키',
    'api.intro'      => 'HTTP로 자신의 도메인을 관리합니다: 도메인 목록, 레코드 읽기, 레코드 추가·수정·삭제가 '
        . '가능합니다. 키는 귀하의 계정에 속하며, 소유한 도메인에만 접근할 수 있습니다.',
    'api.open'       => 'API 키 관리',
    'api.new'        => '키 만들기',
    'api.create'     => '키 만들기',
    'api.label'      => '용도 메모(선택)',
    'api.label_hint' => '예: 「내 스크립트」',
    'api.created'    => '키를 만들었습니다 — 지금 바로 복사하세요. 이번 한 번만 표시됩니다.',
    'api.token_once' => '이것이 새 키입니다. 지금 복사하세요: 이후에는 표시되지 않습니다.',
    'api.mine'       => '내 키',
    'api.none'       => '아직 키가 없습니다.',
    'api.hint'       => '끝자리',
    'api.calls'      => '호출 횟수',
    'api.last'       => '마지막 사용',
    'api.state'      => '상태',
    'api.active'     => '유효',
    'api.revoked_state' => '폐기됨(%s)',
    'api.revoke'     => '폐기',
    'api.revoked'    => '키를 폐기했습니다.',
    'api.not_yours'  => '이 키는 귀하의 계정 것이 아닙니다.',
    'api.off'        => '현재 API가 닫혀 있습니다.',
    'api.no_table'   => '데이터베이스에 API 관련 테이블이 없습니다. upgrade-api.sql을 가져와 주세요(자체 점검 '
        . '페이지에서 실행할 수 있습니다).',
    'api.docs'       => '사용법',
    'api.docs_body'  => "키는 Authorization 헤더에 넣습니다:\n\n  GET  %s?action=domains\n  GET  %s?action=records&domain=myname.us.ci\n  POST %s?action=record.add&domain=myname.us.ci&name=@&type=A&value=1.2.3.4\n  POST %s?action=record.update&domain=myname.us.ci&index=0&value=1.2.3.5\n  POST %s?action=record.delete&domain=myname.us.ci&index=1\n\n응답은 모두 JSON입니다: 성공은 \"ok\": true, 실패는 \"error\" 코드와 설명을 반환합니다.",
    'api.rules'      => '이 계정에 속하고 활성화된 도메인만 조작할 수 있습니다. 자체 NS로 위임된 도메인이나 '
        . '이전 중인 도메인은 변경이 거부됩니다(응답에 이유가 표시됩니다). 변경은 프런트와 같은 검증을 거치고 즉시 '
        . '게시되며 감사 로그에 기록됩니다.',
    'api.docs_auth'  => '호출마다 "Authorization: Bearer 귀하의키" 헤더가 필요합니다. curl 예: '
        . 'curl -H "Authorization: Bearer 귀하의키" "https://freedns.my/api.php?action=domains"; 쓰기 3종은 '
        . '-X POST를 추가합니다.',

    /* ============ 제16차: 동적 업데이트 ============ */
    'ddns.heading'      => '동적 업데이트(DDNS)',
    'ddns.intro'        => '도메인에 키가 포함된 업데이트 주소를 설정합니다: 공유기, NAS, 라즈베리 파이, 스크립트가 '
        . '자신의 주소를 알리면 레코드가 자동으로 따라갑니다.',
    'ddns.mine'         => '이 계정의 도메인',
    'ddns.name'         => '도메인',
    'ddns.state'        => '업데이트 주소',
    'ddns.state_on'     => '사용 중',
    'ddns.state_off'    => '미생성',
    'ddns.last'         => '마지막 업데이트',
    'ddns.open'         => '열기',
    'ddns.all'          => '모든 도메인',
    'ddns.token'        => '업데이트 키',
    'ddns.url'          => '업데이트 주소',
    'ddns.create'       => '업데이트 키 생성',
    'ddns.reset'        => '키 재생성',
    'ddns.revoke'       => '키 삭제',
    'ddns.created'      => '키를 생성했습니다. 업데이트 주소를 쓸 수 있습니다.',
    'ddns.reset_done'   => '새 키를 생성했습니다. 이전 주소는 즉시 무효가 됩니다.',
    'ddns.revoked_done' => '키를 삭제했습니다. 업데이트 주소는 더 이상 유효하지 않습니다.',
    'ddns.no_token'     => '이 도메인에는 아직 업데이트 키가 없습니다.',
    'ddns.none'         => '아직 활성 도메인이 없습니다.',
    'ddns.off'          => '동적 업데이트가 현재 닫혀 있습니다.',
    'ddns.no_table'     => '데이터베이스에 이 기능에 필요한 항목이 없습니다. upgrade-ddns.sql을 가져와 주세요'
        . '(자체 점검 페이지에서 실행할 수 있습니다).',
    'ddns.examples'     => '사용법',
    'ddns.example_curl' => 'curl "%s"',
    'ddns.example_router' => "공유기 / NAS(사용자 DDNS 설정 예):\n  호스트 이름 : 귀하의 도메인(예: myname.us.ci)\n  주소        : 위의 업데이트 주소(\"&ip=\"는 그대로 두세요. 클라이언트가 주소를 붙입니다)\n  계정/비밀번호 : 비워 두세요. 주소 안의 키가 자격 증명입니다",
    'ddns.rules'        => '도메인 자체(@)의 A와 AAAA 레코드만 갱신되므로 먼저 레코드 편집기에서 한 개를 추가해 '
        . '주세요. 두 번의 업데이트는 최소 10초 간격을 두세요. 주소가 바뀌지 않으면 즉시 응답합니다. 키를 잘 '
        . '보관하세요 — 아는 사람은 이 도메인의 주소를 바꿀 수 있습니다. 「재생성」을 누르면 이전 주소가 즉시 '
        . '무효가 됩니다.',
    'ddns.list_hint'    => '도메인을 열면 키, 바로 쓸 수 있는 업데이트 주소와 예시가 보입니다.',
    'ddns.status_good'  => '업데이트됨',
    'ddns.status_nochg' => '주소 변경 없음',
    'ddns.status_nohost' => '갱신할 레코드 없음',
    'ddns.status_badip' => '주소가 올바르지 않음',
    'ddns.status_badauth' => '키가 틀림',
    'ddns.status_911'   => '게시 실패',
    'val.ddns_own'      => '이 도메인은 귀하의 계정 것이 아닙니다.',

    /* ============ 제17차: 네임서버와 위임 검증 ============ */
    'ns.heading'         => '네임서버',
    'ns.intro'           => '일부 접미사는 도메인을 자신의 네임서버(예: Cloudflare NS)로 지정할 수 있습니다. '
        . '그렇게 하면 등록기관은 해당 도메인에 응답하지 않습니다: 상위에 위임 레코드만 게시하고 기존 레코드를 '
        . '내리며, 이름 해석은 귀하의 DNS 서비스가 담당합니다.',
    'ns.open'            => '특정 도메인의 네임서버 설정',
    'ns.name'            => '도메인',
    'ns.zone'            => '접미사',
    'ns.state'           => '상태',
    'ns.state_delegated' => '귀하의 네임서버로 위임됨',
    'ns.state_here'      => '이 등록기관이 응답',
    'ns.servers'         => '현재 네임서버',
    'ns.server'          => '네임서버 %s',
    'ns.glue_hint'       => 'Glue는 네임서버가 해당 도메인 안에 있을 때만 필요합니다(예: ns1.귀하의도메인.us.ci): '
        . '그 주소도 함께 입력하세요.',
    'ns.glue_host'       => 'Glue 호스트 이름',
    'ns.glue_value'      => 'Glue 주소(IPv4 또는 IPv6)',
    'ns.apply'           => '이 네임서버 사용',
    'ns.clear'           => '이 등록기관이 응답하도록 되돌리기',
    'ns.none'            => '사용자 NS를 설정할 수 있는 활성 도메인이 없습니다(해당 접미사에서 기능이 켜져 있어야 '
        . '합니다).',
    'ns.no_table'        => '데이터베이스에 위임 관련 항목이 없습니다. upgrade-ns.sql을 가져와 주세요(자체 점검 '
        . '페이지가 SQL을 보여 줍니다).',
    'ns.applied'         => '저장했습니다 — 이 도메인은 이제 귀하의 네임서버로 위임되었습니다.',
    'ns.cleared'         => '위임을 해제하고 이 등록기관이 응답하도록 되돌렸습니다. 레코드를 다시 입력해 주세요.',
    'ns.note'            => '위임은 즉시 적용되며 심사가 필요 없습니다. 위임 중에는 여기서 레코드를 수정할 수 없고, '
        . '귀하의 DNS 서비스가 정상 응답할 때만 도메인이 해석됩니다.',
    'ns.read_only'       => '이 도메인은 자체 네임서버를 사용하므로 여기서 레코드를 수정할 수 없습니다.',
    'val.ns_own'         => '이 도메인은 귀하의 계정 것이 아닙니다.',
    'val.ns_zone_off'    => '이 접미사는 사용자 네임서버를 지원하지 않습니다.',
    'val.ns_not_active'  => '활성 도메인만 위임할 수 있습니다.',
    'val.ns_publish'     => '이 접미사는 자동 게시가 설정되지 않아 위임을 상위로 보낼 수 없습니다. 등록기관에 '
        . '연락해 주세요.',
    'val.ns_host'        => '"%s"은(는) 유효한 네임서버 호스트 이름이 아닙니다.',
    'val.ns_ours'        => '등록기관 자신의 네임서버를 위임에 쓸 수 없습니다.',
    'val.ns_few'         => '네임서버를 최소 두 개 입력해 주세요.',
    'val.ns_many'        => '네임서버는 최대 네 개입니다.',
    'val.ns_glue_pair'   => 'Glue는 호스트 이름과 주소를 모두 입력해야 합니다.',
    'val.ns_glue_inside' => 'Glue는 %2$s 안에 있는 네임서버에만 쓸 수 있습니다("%1$s"은(는) 해당하지 않습니다).',
    'val.ns_glue_listed' => 'Glue 호스트 이름 "%s"이(가) 입력한 네임서버 목록에 없습니다.',
    'val.ns_glue_ip'     => '"%s"은(는) 유효한 IPv4도 IPv6도 아닙니다.',
    'val.ns_glue_needed' => '네임서버 %s이(가) 이 도메인 안에 있으므로 Glue 주소가 필요합니다.',

    /* ============ 제18차: 2단계 인증 ============ */
    'totp.heading'      => '2단계 인증',
    'totp.intro'        => '완전히 선택 사항이며 귀하의 계정에만 적용됩니다: 인증 앱(Google Authenticator, Aegis, '
        . '1Password 등)과 함께 쓰면 비밀번호가 유출되어도 콘솔에 들어갈 수 없습니다.',
    'totp.open'         => '2단계 인증 설정',
    'totp.state'        => '상태',
    'totp.state_on'     => '사용 중(%s부터)',
    'totp.state_off'    => '미사용(비밀번호만으로 로그인)',
    'totp.account'      => '계정',
    'totp.turn_on'      => '켜기',
    'totp.turn_on_hint' => '인증 앱으로 코드를 한 번 확인한 뒤에야 키가 저장됩니다 — 그래서 「설정하다가 스스로 '
        . '잠기는」 일이 없습니다.',
    'totp.begin'        => '키 생성',
    'totp.setup'        => '설정 완료',
    'totp.step_app'     => '인증 앱을 열고 「직접 입력」으로 계정을 추가합니다(여기서는 카메라가 필요 없습니다).',
    'totp.step_secret'  => '아래 키를 앱에 입력하세요(영문 A-Z와 숫자 2-7):',
    'totp.step_uri'     => '앱이 지원한다면 아래 주소를 그대로 붙여 넣어도 됩니다:',
    'totp.code'         => '앱에 표시되는 6자리 코드',
    'totp.confirm'      => '확인하고 켜기',
    'totp.cancel'       => '설정 취소',
    'totp.turn_off'     => '끄기',
    'totp.turn_off_hint' => '끄려면 현재 코드가 필요하므로, 훔친 비밀번호만으로는 해제할 수 없습니다.',
    'totp.disable'      => '2단계 인증 끄기',
    'totp.enabled'      => '2단계 인증을 켰습니다. 다음 로그인부터 코드가 필요합니다.',
    'totp.disabled'     => '2단계 인증을 껐습니다. 다시 비밀번호만으로 로그인합니다.',
    'totp.bad_code'     => '코드가 올바르지 않습니다. 휴대폰 시각이 정확한지 확인하고 다음 코드를 시도해 보세요.',
    'totp.no_setup'     => '설정이 만료되었습니다. 처음부터 다시 시작해 주세요.',
    'totp.not_on'       => '현재 2단계 인증이 켜져 있지 않습니다.',
    'totp.no_table'     => '데이터베이스에 이 기능에 필요한 항목이 없습니다. upgrade-totp.sql을 가져와 주세요'
        . '(자체 점검 페이지에서 실행할 수 있습니다).',
    'totp.recovery'     => '키를 별도로 안전하게 보관하세요: 키를 잃고 쓸 수 있는 코드도 없다면 등록기관 관리자의 '
        . '도움으로만 복구할 수 있습니다. 이 페이지에서 언제든 끌 수 있습니다.',
    'totp.ask'          => '2단계 인증',
    'totp.ask_intro'    => '이 계정은 인증 앱을 사용합니다. 로그인을 마치려면 현재 6자리 코드를 입력해 주세요.',
    'totp.ask_submit'   => '로그인 완료',
    'totp.ask_bad'      => '이 코드는 현재 유효하지 않습니다.',
    'val.totp_code'     => '앱에 표시되는 6자리 코드를 입력해 주세요.',

    /* ============ 제19차: 도메인 공유 ============ */
    'share.heading'    => '공유된 도메인',
    'share.intro'      => '자신의 도메인 하나를 다른 콘솔 계정에 열어 볼 수 있게 할 수 있고, 반대로 다른 사람이 '
        . '공유한 도메인도 볼 수 있습니다. 공유는 **읽기 전용**입니다: 상대는 도메인과 레코드를 볼 수 있을 뿐이며, '
        . '수정·이전·삭제는 여전히 소유자만 할 수 있습니다.',
    'share.with_me'    => '나에게 공유된 것',
    'share.with_me_none' => '아직 아무도 도메인을 공유하지 않았습니다.',
    'share.by_me'      => '내가 공유한 것',
    'share.by_me_hint' => '귀하의 도메인을 볼 수 있는 계정입니다. 그들은 아무것도 수정할 수 없습니다.',
    'share.by_me_none' => '아직 도메인을 공유한 적이 없습니다.',
    'share.name'       => '도메인',
    'share.zone'       => '접미사',
    'share.state'      => '상태',
    'share.by'         => '공유한 사람',
    'share.account'    => '계정',
    'share.when'       => '공유 시각',
    'share.look'       => '레코드 보기',
    'share.revoke'     => '취소',
    'share.invite'     => '내 도메인 하나 공유',
    'share.invite_email' => '상대 계정의 메일 주소',
    'share.invite_hint' => '그 메일 주소는 이 사이트의 콘솔 계정으로 가입되어 있어야 합니다. 상대에게 메일이 가고 '
        . '「나에게 공유된 것」에 이 도메인이 표시됩니다.',
    'share.invite_submit' => '공유(읽기 전용)',
    'share.added'      => '공유했습니다. 상대는 레코드를 볼 수만 있습니다.',
    'share.revoked'    => '공유를 취소했습니다.',
    'share.readonly_note' => '읽기 전용: 이 화면에서는 레코드를 수정할 수 없습니다. 편집·이전·삭제와 API는 여전히 '
        . '도메인 소유자만 할 수 있습니다.',
    'share.not_mine'   => '이 도메인은 귀하의 계정 것이 아닙니다.',
    'share.self'       => '이것은 귀하 자신의 도메인입니다.',
    'share.already'    => '계정 「%s」은(는) 이미 이 도메인을 볼 수 있습니다.',
    'share.no_account' => '메일 주소 %s을(를) 쓰는 콘솔 계정이 없습니다. 상대가 먼저 가입해야 합니다.',
    'share.no_table'   => '데이터베이스에 공유 관련 테이블이 없습니다. upgrade-sharing.sql을 가져와 주세요(자체 점검 '
        . '페이지에서 실행할 수 있습니다).',
    'share.rec_name'   => '이름',
    'share.rec_type'   => '종류',
    'share.rec_value'  => '값',
    'mail.share_subject' => '%s이(가) 귀하에게 공유했습니다',
    'mail.share_body'  => "%s이(가) %s에 의해 귀하에게 공유되었습니다(읽기 전용).\n\n콘솔의 「공유된 도메인」에서 이 도메인과 레코드를 볼 수 있지만 수정할 수는 없습니다: 편집·이전·삭제는 소유자만 할 수 있습니다.",
    'mail.share_footer' => '언제든지 관심을 끊을 수 있고, 소유자가 공유를 취소할 수도 있습니다.',

    /* ============ 제20차: GitHub 연동 ============ */
    'account.github_heading'        => 'GitHub',
    'account.github_login_hint'     => 'GitHub 계정으로도 로그인할 수 있습니다. 귀하의 GitHub 메일 주소를 쓰는 '
        . '계정이 이 사이트에 없으면 자동으로 만들어집니다.',
    'account.github_signin'         => 'GitHub로 로그인',
    'account.github_or'             => '또는',
    'account.github_signup'         => 'GitHub로 바로 가입',
    'account.github_signup_hint'    => '위 양식보다 간단합니다: GitHub 사용자 이름과 확인된 메일 주소를 그대로 쓰고 '
        . '확인 메일이 필요 없습니다. 나중에 프로필에서 비밀번호를 설정할 수 있습니다.',
    'account.github_signed_in'      => 'GitHub로 로그인했습니다: %s.',
    'account.github_welcome'        => '환영합니다, %s! GitHub를 통해 계정 「%s」을(를) 만들었습니다. 언제든 '
        . '프로필에서 비밀번호를 설정할 수 있습니다.',
    'account.github_linked'         => 'GitHub 계정 <strong>%s</strong>을(를) 연동했습니다(연동 시각: %s).',
    'account.github_linked_ok'      => 'GitHub를 연동했습니다(%s). 앞으로 GitHub로 한 번에 로그인할 수 있습니다.',
    'account.github_already_linked' => '이 계정은 이미 %s을(를) 연동했습니다.',
    'account.github_not_linked'     => '아직 GitHub를 연동하지 않았습니다. 연동하면 한 번에 로그인할 수 있어 '
        . '비밀번호를 잊었을 때 특히 편리합니다.',
    'account.github_link'           => 'GitHub 계정 연동',
    'account.github_unlink'         => '연동 해제',
    'account.github_unlinked'       => 'GitHub 연동을 해제했습니다.',
    'account.github_no_password_hint' => '이 계정에는 자체 비밀번호가 없습니다: 「비밀번호 변경」에서 먼저 설정한 뒤 '
        . 'GitHub 연동을 해제하세요. 그렇지 않으면 다시 로그인할 수 없습니다.',
    'val.github_off'                => '현재 GitHub 로그인을 제공하지 않습니다.',
    'val.github_no_table'           => '데이터베이스에 GitHub 관련 항목이 없습니다. upgrade-github.sql을 가져와 '
        . '주세요(자체 점검 페이지가 SQL을 보여 줍니다).',
    'val.github_signin_first'       => '먼저 로그인한 뒤 GitHub 계정을 연동해 주세요.',
    'val.github_bad_request'        => 'GitHub 요청이 올바르지 않습니다.',
    'val.github_state'              => 'GitHub 콜백을 이번 요청에 연결할 수 없습니다(보안 토큰이 일치하지 않거나 '
        . '만료되었습니다). 다시 시도해 주세요.',
    'val.github_cancelled'          => 'GitHub 인증이 취소되었습니다.',
    'val.github_token'              => 'GitHub에서 프로필을 가져오지 못했습니다: %s',
    'val.github_taken'              => '이 GitHub 계정은 이 사이트의 다른 계정에 이미 연동되어 있습니다.',
    'val.github_no_email'           => 'GitHub가 **확인된** 메일 주소를 반환하지 않아 계정을 만들거나 연결할 수 '
        . '없습니다. 먼저 GitHub에서 메일 주소를 추가·확인하거나 메일 주소로 이 사이트에 가입해 주세요.',
    'val.github_no_account'         => 'GitHub 계정과 일치하는 이 사이트 계정이 없고 「GitHub로 가입」이 현재 닫혀 '
        . '있습니다.',
    'val.github_not_linked'         => '이 계정은 GitHub를 연동하지 않았습니다.',
    'val.github_unlink_needs_password' => '먼저 이 계정의 비밀번호를 설정한 뒤 GitHub 연동을 해제하세요. 그렇지 '
        . '않으면 다시 로그인할 수 없습니다.',

    /* ============ 제21차: GitHub Star 혜택 ============ */
    'account.bonus_heading'   => '추가 할당량: GitHub에서 Star 누르기',
    'account.bonus_intro'     => 'GitHub에서 %s에 Star를 누르면 이 계정의 등록 할당량이 늘어납니다. 확인은 '
        . '연동된 GitHub 계정을 저장소의 Star 목록과 대조해 이루어지므로, 그 Star는 반드시 귀하 자신의 GitHub '
        . '계정에서 나온 것이어야 합니다.',
    'account.bonus_repo'      => '저장소',
    'account.bonus_amount'    => '계정당 혜택',
    'account.bonus_names'     => '개의 추가 도메인, 1회만 지급',
    'account.bonus_yours'     => '이미 %s개를 받았습니다',
    'account.bonus_status'    => '귀하의 혜택',
    'account.bonus_taken'     => '%s에 지급했습니다. 감사합니다!',
    'account.bonus_need_link' => '먼저 GitHub 계정을 연동해 주세요: %s. 혜택은 실제로 Star를 누른 GitHub 계정에만 '
        . '지급됩니다.',
    'account.bonus_ready'     => '확인할 수 있습니다 — Star를 누른 뒤 아래 버튼을 누르세요.',
    'account.bonus_step1'     => '%s을(를) 열고 오른쪽 위의 「Star」를 누릅니다.',
    'account.bonus_step2'     => '몇 초 기다립니다(GitHub의 Star 반영에는 약간의 지연이 있습니다).',
    'account.bonus_step3'     => '아래 버튼으로 확인하면 통과 시 할당량이 곧바로 계정에 추가됩니다.',
    'account.bonus_submit'    => 'Star를 눌렀습니다. 지금 확인',
    'account.bonus_ok'        => '감사합니다! 이 계정에 %s개의 도메인 할당량을 추가했습니다.',
    'account.bonus_off'       => '현재 Star 혜택을 제공하지 않습니다.',
    'account.bonus_count'     => '현재 %s개 계정이 이 저장소에 Star를 눌렀습니다(목록 수집: %s).',
    'account.bonus_truncated' => '목록이 이 사이트의 읽기 한도보다 깁니다. 찾을 수 없으면 관리자에게 연락해 주세요.',
    'account.bonus_note'      => '계정당 1회만 가능하며, 이후 Star를 취소해도 지급된 할당량은 회수되지 않습니다.',
    'account.back_to_console' => '콘솔로 돌아가기',
    'val.star_off'            => '현재 Star 혜택을 제공하지 않습니다.',
    'val.star_no_table'       => '데이터베이스에 Star 혜택 관련 항목이 없습니다. upgrade-star.sql을 가져와 '
        . '주세요(자체 점검 페이지가 SQL을 보여 줍니다).',
    'val.star_no_repo'        => '등록기관이 아직 GitHub 저장소를 설정하지 않았습니다.',
    'val.star_link_first'     => '먼저 프로필에서 GitHub 계정을 연동해 주세요 — 혜택은 Star를 누른 GitHub 계정에만 '
        . '지급됩니다.',
    'val.star_already'        => '이 계정은 이미 이 혜택을 받았습니다.',
    'val.star_lookup'         => 'GitHub에서 Star 목록을 읽지 못했습니다: %s',
    'val.star_not_found'      => '저장소의 Star 목록에서 귀하의 GitHub 계정을 찾지 못했습니다. 저장소 페이지에서 '
        . 'Star를 누르고 잠시 후 다시 시도해 주세요.',

    /* ============ 제22차: 도메인 이전 티켓 ============ */
    'transfer.heading'        => '도메인 이전',
    'transfer.intro'          => 'DNSHE가 이름 해석을 제공하는 접미사의 도메인은 DNSHE 쪽에서 삭제할 수 없고 이전으로만 '
        . '넘길 수 있습니다: 여기에서 신청하면 등록기관이 DNSHE에서 이전 코드를 준비하고, 받는 분이 DNSHE에서 '
        . '수락합니다. 등록기관은 아래 티켓에서 답변하며, 답변할 때마다 메일이 갑니다.',
    'transfer.ticket'         => '티켓 #%s',
    'transfer.status_heading' => '상태',
    'transfer.status_open'    => '등록기관 처리 대기',
    'transfer.status_approved'=> '이전 코드 발급됨, 받는 분 수락 대기',
    'transfer.status_accepted'=> '완료',
    'transfer.status_rejected'=> '거부됨',
    'transfer.status_expired' => '만료됨(시간 내 수락되지 않음)',
    'transfer.status_cancelled' => '취소됨',
    'transfer.hours_left'     => '앞으로 %s시간 동안 수락할 수 있습니다',
    'transfer.requester'      => '현재 소유자',
    'transfer.recipient'      => '받는 분',
    'transfer.recipient_name' => '받는 분 이름',
    'transfer.recipient_email'=> '받는 분 메일 주소',
    'transfer.opened'         => '시작 시각',
    'transfer.reason'         => '설명',
    'transfer.thread'         => '대화 기록',
    'transfer.no_messages'    => '아직 메시지가 없습니다.',
    'transfer.from_staff'     => '등록기관',
    'transfer.from_applicant' => '신청자(%s)',
    'transfer.write'          => '등록기관에 메시지',
    'transfer.message'        => '내용',
    'transfer.send'           => '보내기',
    'transfer.withdraw'       => '신청 취소',
    'transfer.mine'           => '내 이전 티켓',
    'transfer.none'           => '아직 이전 티켓이 없습니다.',
    'transfer.name'           => '도메인',
    'transfer.role'           => '나의 역할',
    'transfer.role_holder'    => '넘기는 쪽',
    'transfer.role_recipient' => '받는 쪽',
    'transfer.open'           => '열기',
    'transfer.back'           => '내 티켓으로 돌아가기',

    /* ============ 제23차: 이전 신청·수락·메일과 클라우드 서버 ============ */
    'transfer.new_heading'    => '이전 신청',
    'transfer.new_intro'      => '도메인 하나를 선택하고 받는 분을 입력하세요. 등록기관이 DNSHE에서 이전을 시작하고 '
        . '이전 코드를 받는 분의 메일로 보냅니다. 받는 분은 72시간 안에 수락해야 합니다.',
    'transfer.submit'         => '이전 신청 보내기',
    'transfer.off'            => '현재 이전을 받지 않습니다.',
    'transfer.nothing_to_transfer' => '이전할 수 있는 도메인이 없습니다(DNSHE 접미사 아래의 활성 도메인이어야 합니다).',
    'transfer.hint'           => '티켓이 처리 중이거나 수락 대기 중인 동안 그 도메인의 레코드는 잠겨 수정할 수 '
        . '없습니다. 받는 분은 여기에 입력한 메일 주소에 해당하는 계정으로 수락해야 합니다.',
    'transfer.created'        => '이전 신청을 보냈습니다. 등록기관이 여기와 메일로 답변합니다.',
    'transfer.message_saved'  => '메시지를 저장했습니다.',
    'transfer.confirmed'      => '확인해 주셔서 감사합니다. 도메인이 귀하의 계정으로 옮겨졌습니다.',
    'transfer.cancelled'      => '신청을 취소했습니다.',
    'transfer.accept_heading' => '이전 수락',
    'transfer.accept_text'    => '귀하는 이 도메인의 받는 분입니다. 먼저 DNSHE 패널에서 수락하고(이전 코드는 메일로 '
        . '받았습니다) 아래 버튼을 누르세요: 도메인이 여기의 귀하 계정으로 들어옵니다.',
    'transfer.accept_submit'  => 'DNSHE에서 수락했습니다 — 도메인을 제게 넘겨 주세요',
    'transfer.locked'         => '이 도메인은 이전 절차 중이라 잠시 레코드를 수정할 수 없습니다.',
    'val.transfer_off'        => '현재 이전을 받지 않습니다.',
    'val.transfer_suffix'     => 'DNSHE가 이름 해석을 제공하는 접미사 아래의 도메인만 이전할 수 있습니다.',
    'val.transfer_not_active' => '활성 도메인만 이전할 수 있습니다.',
    'val.transfer_pending'    => '이 도메인은 이미 이전 절차 중입니다.',
    'val.transfer_recipient'  => '받는 분 이름과 유효한 메일 주소를 입력해 주세요.',
    'val.transfer_self'       => '자신의 메일 주소로 도메인을 이전할 수 없습니다.',
    'val.transfer_own'        => '이 티켓 또는 도메인은 귀하의 계정 것이 아닙니다.',
    'val.transfer_not_recipient' => '이 이전은 다른 메일 주소 앞으로 되어 있습니다. 티켓에 적힌 메일 주소의 계정으로 '
        . '로그인해 주세요.',
    'val.transfer_expired'    => '72시간이 지났습니다. 등록기관에 연락해 이전 코드를 다시 발급받으세요.',
    'val.transfer_empty'      => '먼저 내용을 입력해 주세요.',
    'mail.transfer_new_subject' => '새 이전 신청: %s',
    'mail.transfer_new_body'  => "사용자가 이전 신청을 보냈습니다.\n\n도메인: %s\n현재 소유자: %s\n받는 분: %s <%s>\n\n관리자 Tickets 페이지에서 처리하세요.",
    'mail.transfer_reply_subject' => '%s 관련 이전 티켓',
    'mail.transfer_code_subject' => '%s의 이전 코드',
    'mail.transfer_code_body' => "도메인을 넘겨받습니다: %s\n\n이전 코드: %s\n유효 기한: %s(72시간)\n넘기는 쪽: %s\n\nDNSHE 계정에서 이 코드를 사용한 뒤, 콘솔로 돌아와 이 메일 주소의 계정으로 이전을 확정해 주세요.",
    'mail.transfer_approved_subject' => '%s의 이전을 승인했습니다',
    'mail.transfer_approved_body' => "%s의 이전을 승인했습니다. 이전 코드는 %s로 발송했습니다. 상대가 %s까지 수락하면 이전이 완료됩니다.",
    'mail.transfer_rejected_subject' => '%s의 이전이 승인되지 않았습니다',
    'mail.transfer_rejected_body' => "%s의 이전이 승인되지 않았습니다.\n\n%s",
    'mail.transfer_done_subject' => '%s의 이전이 완료되었습니다',
    'mail.transfer_done_body' => "%s은(는) 이제 귀하의 메일 주소 명의이며 콘솔에서 관리할 수 있습니다.",
    'mail.transfer_done_holder_body' => "%s을(를) %s에게 이전했습니다. 이 도메인은 더 이상 귀하의 계정에 없습니다.",
    'mail.transfer_footer'    => '티켓은 콘솔의 「도메인 이전」 페이지에서 추적할 수 있습니다.',
    'srv.heading' => '내 클라우드 서버',
    'srv.intro'   => 'RainYun에서 구매한 서버를 여기에 등록합니다: 상태와 만료 시각 확인, 만료 전 알림, 시작·중지·'
        . '재시작이 가능합니다. 서버는 항상 귀하의 계정에 있습니다 — 등록기관은 대신 결제하거나 보관하지 않습니다.',
    'srv.usage'   => '부하와 사용량',
    'srv.usage_hint' => '방금 서비스 제공자에서 읽었습니다. 값이 이상하면 표 아래에 원본 응답이 있습니다.',
    'srv.maintenance' => '점검',
    'srv.no_maintenance' => '점검 공지가 없습니다.',
    'srv.vnc'     => 'VNC 콘솔 열기',
    'srv.vnc_hint' => '일회용 티켓: 스스로 만료되며 저희는 저장하지 않습니다. 요청할 때마다 감사 로그에 기록됩니다.',
    'srv.raw'     => '원본 응답',

    /* ============ 제24차: 도메인 거래 ============ */
    'market.heading'          => '판매 중인 도메인',
    'market.disclaimer_heading' => '먼저 읽어 주세요',
    'market.disclaimer'       => '이 코너는 정보 게시판일 뿐입니다: 등록기관은 게시물을 심사하고 양측을 연결하지만 '
        . '**거래에 관여하지 않습니다** — 도메인을 보유하지 않고 대금을 대신 받지 않으며 도메인의 진정한 소유자를 '
        . '확인하지 않고 어떠한 보증이나 에스크로도 제공하지 않습니다. 가격·결제 방식·이전 방식은 당사자끼리 '
        . '정하세요. 결제 전에 도메인을 직접 확인하세요(소유자, 만료일, 이전 잠금). 발생한 모든 위험과 분쟁은 '
        . '사고파는 양측의 부담입니다.',
    'market.intro'            => '여기에는 콘솔 계정이 올린, 다른 곳에서 구매했다가 더 이상 필요 없어진 도메인이 '
        . '모입니다. 연락은 사고파는 양측이 메일로 직접 합니다.',
    'market.off'              => '거래 코너가 현재 닫혀 있습니다.',
    'market.search'           => '검색(도메인 / 설명 / 등록기관)',
    'market.suffix'           => '접미사(com, net…)',
    'market.max_price'        => '최고 가격',
    'market.filter'           => '필터',
    'market.reset'            => '초기화',
    'market.empty'            => '조건에 맞는 게시물이 없습니다.',
    'market.domain'           => '도메인',
    'market.price'            => '판매 가격',
    'market.transfer_price'   => '이전 가격',
    'market.currency'         => '통화',
    'market.negotiable'       => '협의 가능',
    'market.registrar'        => '등록기관',
    'market.registered_on'    => '등록일',
    'market.expires_on'       => '만료일',
    'market.status_heading'   => '상태',
    'market.status_pending'   => '심사 대기',
    'market.status_active'    => '판매 중',
    'market.status_rejected'  => '거부됨',
    'market.status_sold'      => '판매됨',
    'market.status_withdrawn' => '취소됨',
    'market.listed'           => '게시 시각',
    'market.description'      => '상세',
    'market.pinned'           => '고정',
    'market.open'             => '보기',
    'market.report_link'      => '이 게시물 신고',
    'market.back'             => '거래 코너로 돌아가기',
    'market.sell_hint'        => '팔고 싶은 도메인이 있나요? %s(콘솔 계정이 필요하며 한 건씩 사람이 심사합니다).',
    'market.sell_link'        => '여기에서 게시',
    'market.buy_heading'      => '구매하고 싶습니다',
    'market.buy_intro'        => '구매 신청을 보내면 귀하와 판매자가 서로의 메일 주소를 받고 이후에는 직접 메일로 '
        . '연락합니다.(%s)',
    'market.buy_intro_account' => '판매자가 계정 메일 주소를 사용',
    'market.buy_intro_custom' => '판매자가 별도 연락용 메일 주소를 사용',
    'market.your_name'        => '이름',
    'market.your_email'       => '메일 주소',
    'market.email_hint'       => '이 메일 주소는 판매자에게 전달되어 직접 연락할 수 있습니다. 실제로 사용하는 '
        . '주소를 입력해 주세요.',
    'market.message'          => '판매자에게 보낼 메시지',
    'market.send'             => '구매 신청 보내기',
    'market.login_first'      => '구매 신청 전에 로그인해 주세요.',
    'market.login_first_help' => '신청에는 콘솔 계정이 필요합니다 — %s 또는 %s 후에 여기로 돌아와 주세요.',
    'market.sent'             => '신청을 보냈습니다: 메일을 확인해 주세요. 판매자에게도 귀하의 메일 주소가 '
        . '전달되었습니다.',

    /* ============ 제25차: 거래 게시물 관리와 거래 메일 ============ */
    'market.sold_note'        => '이 도메인은 판매자가 판매됨으로 표시했습니다.',
    'market.own_offer'        => '이것은 귀하 자신의 게시물입니다. 구매자의 신청은 귀하의 콘솔에 표시됩니다.',
    'market.mine_heading'     => '내 게시물',
    'market.mine_intro'       => '귀하가 게시한 것. 새 게시물이나 도메인 변경은 사람의 심사 후에 표시됩니다. 언제든지 '
        . '취소하거나 판매됨으로 바꿀 수 있습니다.',
    'market.new_heading'      => '게시하기',
    'market.new_intro'        => '게시할 수 있는 것은 다른 곳에서 구매한 **1차 도메인**뿐입니다(이 사이트가 발급한 '
        . '2차 도메인은 게시할 수 없습니다). 등록기관은 소유권을 확인할 수 없으니 사실대로 적어 주세요 — 구매자가 '
        . '판단 근거로 삼습니다.',
    'market.domain_hint'      => '예: example.com. 이 사이트 자체 접미사 아래의 도메인은 받지 않습니다.',
    'market.contact_email'    => '구매자용 연락 메일 주소',
    'market.contact_hint'     => '신청한 구매자에게 표시됩니다. 계정 메일 주소여도 되고 다른 주소여도 됩니다.',
    'market.submit'           => '심사에 제출',
    'market.save'             => '변경 저장',
    'market.created'          => '제출했습니다. 사람의 심사 후 승인되면 표시됩니다.',
    'market.updated'          => '저장했습니다.',
    'market.mark_sold'        => '판매됨으로 표시',
    'market.withdraw'         => '취소',
    'market.marked_sold'      => '판매됨으로 표시했습니다.',
    'market.withdrawn_ok'     => '취소했습니다.',
    'market.reject_reason'    => '등록기관의 사유',
    'market.review_hint'      => '게시물은 한 건씩 사람이 심사하며 승인·거부 모두 메일로 알립니다.',
    'market.none'             => '아직 게시한 것이 없습니다.',
    'market.enquiries'        => '구매 신청',
    'market.enquiries_count'  => '신청 수',
    'market.all_enquiries'    => '모든 구매 신청',
    'market.no_enquiries'     => '아직 신청이 없습니다.',
    'market.when'             => '시각',
    'market.buyer'            => '구매자',
    'market.view_public'      => '거래 코너에서 보기',
    'market.back_mine'        => '내 게시물로 돌아가기',
    'val.market_off'          => '거래 코너가 현재 닫혀 있습니다.',
    'val.market_domain'       => '등록 가능한 1차 도메인을 입력해 주세요(예: example.com).',
    'val.market_own_suffix'   => '이 사이트 자체 접미사 아래의 도메인은 게시할 수 없습니다(등록기관이 발급합니다).',
    'val.market_taken'        => '이 도메인은 이미 게시되어 있습니다.',
    'val.market_price'        => '판매 가격을 입력해 주세요.',
    'val.market_own'          => '이 게시물은 귀하의 계정 것이 아닙니다.',
    'val.market_closed'       => '이 게시물은 종료되었습니다.',
    'val.market_self'         => '자신의 게시물에 구매 신청은 할 수 없습니다.',
    'mail.market_new_subject' => '거래 코너 새 게시물: %s',
    'mail.market_new_body'    => "사용자가 게시물을 제출했습니다.\n\n도메인: %s\n판매자: %s\n판매 가격: %s\n\n관리자 Market 페이지에서 심사하세요.",
    'mail.market_approved_subject' => '%s 게시물을 공개했습니다',
    'mail.market_approved_body' => "%s 게시물이 심사를 통과해 거래 코너에 표시됩니다.\n\n구매자는 귀하가 적은 연락용 메일 주소로 연락합니다. 신청은 한 건씩 콘솔에도 기록됩니다.",
    'mail.market_rejected_subject' => '%s 게시물이 거부되었습니다',
    'mail.market_rejected_body' => "%s 게시물이 심사를 통과하지 못했습니다.\n\n사유: %s\n\n콘솔에서 수정해 다시 제출할 수 있습니다.",
    'mail.market_buyer_subject' => '%s 구매 신청',
    'mail.market_buyer_body'  => "%s(%s)의 구매를 신청했습니다.\n\n판매자 연락용 메일 주소: %s\n가격과 이전 방식은 그 주소로 직접 연락해 정하세요. 등록기관은 거래에 관여하지 않습니다.",
    'mail.market_seller_subject' => '%s을(를) 사고 싶어 하는 분이 있습니다',
    'mail.market_seller_body' => "%s에 관심 있는 구매자가 있습니다.\n\n이름: %s\n메일 주소: %s\n메시지: %s\n\n그 주소로 직접 연락하세요. 등록기관은 거래에 관여하지 않습니다.",

    /* ============ 제26차: 할당량 상점 ============ */
    'shop.heading'      => '할당량 상점: 등록 할당량 늘리기',
    'shop.intro'        => '계정의 등록 할당량을 늘리는 방법이 세 가지 있으며 모두 무료입니다. 혜택은 모두 원래 '
        . '할당량에 더해집니다.',
    'shop.current'      => '귀하의 할당량',
    'shop.names'        => '총 %s개 도메인',
    'shop.unlimited'    => '무제한',
    'shop.used'         => '사용 중 %s개',
    'shop.earned'       => '그중 %s개는 상점 혜택',
    'shop.bonus'        => '혜택',
    'shop.status'       => '상태',
    'shop.when'         => '시각',
    'shop.status_pending'  => '심사 대기',
    'shop.status_approved' => '승인됨',
    'shop.status_rejected' => '거부됨',
    'shop.no_table'     => '데이터베이스에 상점 관련 테이블/항목이 없습니다. upgrade-shop.sql을 가져와 주세요(자체 '
        . '점검 페이지가 SQL을 보여 줍니다).',
    'shop.item_star'    => 'GitHub에서 프로젝트에 Star 누르기',
    'shop.star_intro'   => '저희 GitHub 저장소에 Star를 누르면 계정에 %s개의 추가 할당량이 지급됩니다. 그 Star가 '
        . '본인 것인지 확인하려면 먼저 GitHub 계정을 연동해야 합니다.',
    'shop.star_off'     => '현재 Star 혜택을 제공하지 않습니다.',
    'shop.star_done'    => '이미 받았습니다(%s). 후원해 주셔서 감사합니다!',
    'shop.star_need_link' => '먼저 GitHub 계정을 연동해 주세요: %s.',
    'shop.star_submit'  => 'Star를 눌렀습니다. 바로 확인',
    'shop.star_ok'      => '후원해 주셔서 감사합니다! 이 계정에 %s개의 도메인 할당량을 추가했습니다.',
    'shop.item_promo'   => '플랫폼에서 저희를 소개하기',
    'shop.promo_intro'  => '블로그, 포럼, 그룹, 영상에서 저희를 소개하셨나요? 링크를 보내 주세요: 심사를 통과할 '
        . '때마다 %s개의 할당량이 더해지며 최대 %s개까지 쌓입니다.',
    'shop.promo_left'   => '이미 %s개를 받았고 %s개를 더 받을 수 있습니다.',
    'shop.promo_full'   => '소개 혜택이 상한에 도달했습니다. 알려 주셔서 감사합니다!',
    'shop.promo_url'    => '소개 링크',
    'shop.promo_url_hint' => '저희를 언급한 공개 페이지(https://…). 심사할 때 관리자가 열어 확인합니다.',
    'shop.promo_description' => '무엇을 어디에 올렸나요?',
    'shop.promo_submit' => '심사에 제출',
    'shop.promo_sent'   => '제출했습니다. 등록기관이 심사하고 메일로 알립니다.',
    'shop.item_invite'  => '전용 초대 코드',
    'shop.invite_intro' => '이것이 귀하의 전용 초대 코드와 초대 링크입니다. 귀하의 링크로 가입한 사람은 귀하의 '
        . '실적으로 집계됩니다: 현재 %s명, 그중 활성 도메인 %s개.',
    'shop.invite_code'  => '귀하의 초대 코드',
    'shop.invite_link'  => '귀하의 초대 링크',
    'shop.invite_people' => '귀하가 데려온 사람 수',
    'shop.invite_active' => '그들의 활성 도메인 수',
    'shop.invite_pending' => '(그중 %s건 심사 대기)',
    'shop.invite_hint'  => '링크를 어디든 공유하세요. 링크로 가입한 사용자는 처음 콘솔에 들어올 때 귀하의 실적에 '
        . '기록됩니다. 관리자 Shop 페이지의 숫자도 이것과 정확히 일치하며, 관리자는 그 숫자를 근거로 혜택을 '
        . '지급합니다.',
    'shop.people_short' => '명',
    'shop.active_short' => '개의 활성 도메인',
    'val.shop_off'      => '현재 할당량 상점이 닫혀 있습니다.',
    'val.shop_cap'      => '소개 혜택이 상한(%s)에 도달했습니다.',
    'val.shop_url'      => 'http:// 또는 https://로 시작하는 링크를 입력해 주세요.',
    'val.shop_description' => '무엇을 어디에 공개했는지 간단히 설명해 주세요.',
    'mail.shop_new_subject' => '%s님이 소개를 보냈습니다',
    'mail.shop_new_body' => "%s(%s)님이 소개를 보냈습니다.\n\n링크: %s\n설명: %s\n\n관리자 Shop 페이지에서 심사하세요.",
    'mail.shop_approved_subject' => '귀하의 소개가 승인되었습니다',
    'mail.shop_approved_body' => "후원해 주셔서 감사합니다! 계정에 %s개의 할당량이 더해졌고 소개 혜택은 %s개를 더 받을 수 있습니다.",
    'mail.shop_rejected_subject' => '귀하의 소개가 승인되지 않았습니다',
    'mail.shop_rejected_body' => "귀하의 소개가 심사를 통과하지 못했습니다.\n\n사유: %s\n\n소개 내용을 분명히 확인할 수 있는 다른 링크로 다시 제출할 수 있습니다.",

    /* ============ 제27차: 온라인 상담 ============ */
    'chat.heading'       => '등록기관과 온라인 상담',
    'chat.intro'         => '무엇이든 여기에서 물어보세요. 이 페이지에서 답변하고 동시에 메일도 보냅니다. 페이지는 '
        . '자동으로 갱신되어 답변이 바로 나타납니다.',
    'chat.refresh_note'  => '%s초마다 자동 갱신합니다 — JavaScript는 필요 없습니다.',
    'chat.leave_heading' => '메시지',
    'chat.leave_intro'   => '아직 로그인하지 않았습니다. 이름과 메일 주소를 입력하세요. 메일로 답변합니다. 콘솔 '
        . '계정(%s)을 쓰면 대화가 계정에 저장됩니다.',
    'chat.your_name'     => '이름',
    'chat.your_email'    => '메일 주소',
    'chat.email_hint'    => '답변은 이 메일 주소로 갑니다. 확실히 확인하는 주소를 입력해 주세요.',
    'chat.your_message'  => '메시지',
    'chat.send'          => '보내기',
    'chat.sent'          => '메시지를 받았습니다. 여기와 메일로 답변합니다.',
    'chat.need_name'     => '이름을 입력해 주세요.',
    'chat.thread'        => '대화 #%s',
    'chat.state'         => '상태',
    'chat.status_open'   => '진행 중',
    'chat.status_archived' => '보관됨(1주일간 메시지 없음)',
    'chat.addressed'     => '답변 대상',
    'chat.keep'          => '메시지 보관',
    'chat.keep_value'    => '메시지는 삭제할 수 없으며 언제든 내보낼 수 있습니다',
    'chat.no_messages'   => '아직 메시지가 없습니다.',
    'chat.write'         => '메시지 쓰기',
    'chat.from_staff'    => '등록기관',
    'chat.from_me'       => '나',
    'chat.from_user'     => '사용자',
    'chat.from_guest'    => '게스트',
    'chat.empty'         => '먼저 내용을 입력해 주세요.',
    'chat.too_long'      => '메시지가 %s자를 넘었습니다.',
    'chat.too_fast'      => '두 통 사이에는 몇 초 간격을 두세요.',
    'chat.rate_note'     => '두 통 사이 최소 %s초, 한 통은 최대 %s자입니다.',
    'chat.archived_note' => '이 대화는 1주일간 메시지가 없어 보관되었습니다. 한 통 더 보내면 다시 시작됩니다.',
    'chat.export'        => '내 메시지 내보내기',
    'chat.export_title'  => '대화 #%s(상대: %s)',
    'chat.refresh'       => '지금 새로 고침',
    'chat.refresh_manual_hint' => '이 페이지는 자동 갱신하지 않으므로 입력 중인 내용이 사라지지 않습니다. 상대의 '
        . '답변을 확인하려면 아래 버튼을 누르세요.',
    'chat.no_table'      => '데이터베이스에 채팅 관련 테이블이 없습니다. upgrade-chat.sql을 가져와 주세요(자체 점검 '
        . '페이지가 SQL을 보여 줍니다).',
    'mail.chat_new_subject' => '새 채팅 메시지',
    'mail.chat_new_body' => "%s(%s)님이 썼습니다:\n\n%s\n\n관리자 Chat 페이지에서 답변하세요.",
    'mail.chat_reply_subject' => '등록기관이 답변했습니다',
    'mail.chat_reply_body' => "등록기관이 귀하의 메시지에 답변했습니다:\n\n%s\n\n채팅 페이지에서 계속 대화할 수 있습니다.",

    /* ============ 제28차: 알림 메일과 메일 전달 ============ */
    'mail.request_subject'   => '[freedns] 새 신청: %s',
    'mail.request_intro'     => '새 도메인 신청을 받았습니다.',
    'mail.change_subject'    => '[freedns] 레코드 변경: %s',
    'mail.reserved'          => '예약 도메인 신청: 예(예약 도메인 경로로 제출됨)',
    'mail.change_decision_subject' => '[freedns] %s - 레코드 변경%s',
    'mail.approved_short'    => '승인',
    'mail.rejected_short'    => '거부',
    'mail.change_intro'      => '심사 대기 중인 레코드 변경이 있습니다.',
    'mail.decision_subject'  => '[freedns] %s - 신청 %s',
    'mail.dear'              => '%s님',
    'mail.request_state'     => '신청한 도메인 %s의 현재 상태는 %s입니다.',
    'mail.change_state'      => '%s에서 제출한(%s) 레코드 변경이 %s',
    'mail.approved'          => '승인되어 게시되었습니다.',
    'mail.rejected'          => '거부되었습니다.',
    'mail.records'           => '현재 저장된 레코드:',
    'mail.records_now'       => '현재 게시된 레코드:',
    'mail.records_proposed'  => '그때 제안된 레코드:',
    'mail.note_registry'     => '등록기관 메모:',
    'mail.note_applicant'    => '신청자 메모:',
    'mail.check_status'      => '상태는 언제든 여기에서 확인할 수 있습니다: %s',
    'mail.manage'            => '활성 도메인의 레코드는 여기에서 관리합니다: %s',
    'mail.review_at'         => '심사 화면: %s',
    'mail.domain'            => '도메인  : %s',
    'mail.zone'              => '접미사: %s',
    'mail.code'              => '신청 번호: %s',
    'mail.applicant'         => '신청자  : %s <%s>',
    'mail.organization'      => '조직    : %s',
    'mail.country'           => '국가/지역: %s',
    'mail.records_label'     => '레코드:',
    'mail.purpose'           => '용도 설명:',
    'mail.submitted'         => '제출 시각: %s',
    'mail.alert_subject'     => '게시 점검: %s건의 새 문제',
    'mail.alert_intro'       => '도메인의 상위 레코드가 저장 내용과 일치하지 않습니다. 이 메일은 문제가 **새로 '
        . '생기거나 달라졌을 때만** 발송됩니다. 시스템은 아무것도 자동으로 고치지 않습니다:',
    'mail.alert_footer'      => '관리자 「Published records」 페이지에서 건별로 고치거나 거기에서 전체 reconcile을 '
        . '실행할 수 있습니다.',
    'mail.srv_expire_subject' => '%s의 만료가 다가옵니다',
    'mail.srv_expire_body' => "클라우드 서버 %s은(는) %s에 만료됩니다(남은 기간 %s일).\n\n등록기관은 알림만 하며, 귀하의 RainYun 계정에서 갱신해 주세요. 대신 결제하거나 보관하지 않습니다.",
    'mail.srv_expire_footer' => '콘솔의 「내 클라우드 서버」에서 연동된 서버와 만료 시각을 확인할 수 있습니다.',
    'mail.abuse_body' => "악용 신고를 받았습니다.\n\n도메인: %s\n종류: %s\n신고자: %s\n\n%s\n\n관리자 Abuse 페이지에서 처리하세요.",
    'mail.heading'      => '메일 전달',
    'mail.intro'        => '자신의 도메인으로 받은 메일을 실제 메일 주소로 전달합니다. Cloudflare가 이름 해석을 '
        . '제공하는 접미사에만 쓸 수 있습니다(등록기관이 Email Routing을 켜고 전달 규칙을 만듭니다).',
    'mail.add_heading'  => '전달 주소 추가',
    'mail.add_hint'     => '도메인, @ 앞부분, 실제로 받을 메일 주소를 고릅니다.',
    'mail.name'         => '도메인',
    'mail.local'        => '주소(@ 앞)',
    'mail.forward_to'   => '전달 대상',
    'mail.confirm_hint' => '처음에는 Cloudflare가 그 메일 주소로 확인 링크를 보냅니다. 확인하면 전달이 켜집니다.',
    'mail.add'          => '전달 만들기',
    'mail.mine'         => '내 전달 주소',
    'mail.no_routes'    => '아직 전달 주소가 없습니다.',
    'mail.address'      => '주소',
    'mail.status'       => '상태',
    'mail.when'         => '만든 시각',
    'mail.remove'       => '삭제',
    'mail.added'        => '만들었습니다. Cloudflare에서 온 확인 메일을 확인하면 수신할 수 있습니다.',
    'mail.removed'      => '전달을 삭제했습니다.',
    'mail.none'         => 'Cloudflare가 이름 해석을 제공하는 활성 도메인이 없어 메일 전달을 쓸 수 없습니다.',
    'mail.off'          => '메일 전달이 현재 닫혀 있습니다.',
    'mail.no_table'     => '데이터베이스에 이 기능에 필요한 테이블이 없습니다. upgrade-mailroute.sql을 가져와 '
        . '주세요(자체 점검 페이지에서 실행할 수 있습니다).',
    'mail.rules'        => '입력한 주소만 전달되며 나머지는 Cloudflare가 바로 거부합니다. 자체 NS로 위임한 도메인은 '
        . '이 기능을 쓸 수 없습니다(메일은 귀하의 DNS가 처리합니다). 전달을 삭제하면 Cloudflare의 규칙도 삭제됩니다. '
        . '등록기관은 메일 내용을 읽지 않고 저장하지도 않습니다.',
    'mail.status_active'  => '유효',
    'mail.status_pending' => '대기 중',
    'mail.status_error'   => 'Cloudflare가 거부했습니다',

    /* ============ 제29차: 콘솔 계정 메일 ============ */
    'mail.user_verify_subject' => '[%s] 메일 주소를 확인해 주세요',
    'mail.user_verify_intro'  => '환영합니다! 다음 링크를 열어 계정 「%s」이(가) 사용하는 메일 주소를 확인해 주세요:',
    'mail.user_verify_expire' => '이 링크는 3일 동안 유효합니다.',
    'mail.user_verify_ignore' => '기억나지 않는다면 이 메일을 무시하세요. 아무런 영향이 없습니다.',
    'mail.user_reset_subject' => '[%s] 새 비밀번호 설정',
    'mail.user_reset_intro'   => '귀하의 계정에서 비밀번호 재설정이 요청되었습니다. 다음 링크를 열어 새 비밀번호를 '
        . '설정해 주세요:',
    'mail.user_reset_expire'  => '이 링크는 2시간 동안 유효합니다.',
    'mail.user_reset_ignore'  => '기억나지 않는다면 이 메일을 무시하세요. 비밀번호는 바뀌지 않습니다.',

    /* ============ 제30차: 남은 신청·관리·검증 메시지 ============ */
    'request.email'          => '메일 주소 *',
    'request.email_hint'     => '본인 확인, 심사 결과 통보, 이후 레코드 관리에 사용합니다. 공개 목록에는 표시되지 '
        . '않습니다.',
    'request.org'            => '조직(선택)',
    'request.country'        => '국가/지역(선택)',
    'request.country_hint'   => '자유롭게 입력합니다(예: <code>CN</code>, <code>US</code>, <code>Singapore</code>).',
    'request.records_hint'   => '최소한 apex(<code>@</code>)에 레코드 한 개가 필요합니다. 여기서는 <code>A</code>, '
        . '<code>AAAA</code>, <code>CNAME</code>을 쓸 수 있습니다. <code>TXT</code>는 도메인이 활성화된 뒤에 '
        . '추가하세요. <code>CNAME</code>을 쓰면 같은 이름에 다른 레코드를 둘 수 없습니다.',
    'request.records_psl'    => '기본적으로 이 등록기관이 이름 해석을 담당합니다. 상위가 허용하는 접미사'
        . '(<em>Public Suffix List</em>에 등재된 us.ci, de5.net 등)는 도메인 활성화 후 콘솔의 「네임서버」에서 '
        . '자체 네임서버로 바꿀 수 있습니다 — 즉시 적용되고 심사가 필요 없으며, 그때 여기 입력한 레코드는 '
        . '삭제됩니다.',
    'request.name_placeholder'  => '@',
    'request.value_placeholder' => 'IPv4 주소, IPv6 주소 또는 호스트 이름',
    'manage.col_change'      => '종류',
    'manage.col_records'     => '레코드',
    'manage.rows_hint'       => 'apex는 <code>@</code>, 서브도메인은 라벨 하나(<code>www</code>, <code>mail</code>, '
        . '<code>shop</code> 등)를 입력합니다. <em>삭제</em>를 체크하면 그 레코드를 지울 수 있습니다. apex에는 최소 '
        . '한 개의 <code>A</code>, <code>AAAA</code>, <code>CNAME</code> 레코드가 필요합니다.',
    'manage.name_placeholder'   => '@ 또는 www',
    'manage.value_placeholder'  => 'IPv4/IPv6 주소, 호스트 이름 또는 텍스트',
    'rec.subnames_later'   => '신청 시에는 서브도메인 %s을(를) 입력할 수 없습니다. 도메인이 활성화된 뒤 관리 '
        . '페이지에서 추가하세요.',
    'rec.mx_format'   => 'MX 레코드 형식은 「10 mail.example.com」입니다: 우선순위 + 메일 서버 주소("%s").',
    'rec.mx_target'   => '"%s"은(는) 유효한 메일 서버 호스트 이름이 아닙니다.',
    'rec.mx_self'     => '메일 서버를 도메인 자신으로 지정할 수 없습니다("%s").',
    'rec.srv_format'  => 'SRV 레코드 형식은 「10 5 443 target.example.com」입니다: 우선순위, 가중치, 포트, '
        . '대상("%s").',
    'rec.srv_numbers' => 'SRV 레코드의 처음 세 값은 0~65535 사이 숫자여야 합니다.',
    'rec.srv_target'  => '"%s"은(는) 유효한 대상 호스트 이름이 아닙니다.',
    'rec.srv_self'    => 'SRV 대상을 도메인 자신으로 지정할 수 없습니다("%s").',
    'rec.caa_format'  => 'CAA 레코드 형식은 「0 issue "letsencrypt.org"」입니다: flag, 태그(issue / issuewild / '
        . 'iodef), 값("%s").',
    'rec.caa_flags'   => 'CAA 레코드의 flag는 0~255여야 합니다.',
    'rec.caa_target'  => '"%s"은(는) 유효한 인증기관 도메인이 아닙니다.',
    'rec.caa_iodef'   => 'iodef 값은 http(s) 주소 또는 mailto: 주소여야 합니다("%s").',
    'rec.ptr_bad'     => '"%s"은(는) 유효한 호스트 이름이 아닙니다.',
    'rec.ptr_same'    => 'PTR 레코드를 도메인 자신으로 지정할 수 없습니다("%s").',
    'rec.cname_one_only'   => '이름 %s에 CNAME 레코드가 %d개가 됩니다. 하나만 허용됩니다.',
    'rec.cname_exclusive'  => '이름 %s에는 이미 CNAME 레코드가 있어 다른 종류를 둘 수 없습니다.',
    'rec.need_apex_cname'  => 'apex에는 CNAME 레코드가 필요합니다.',
    'rec.subnames_zone_off' => '접미사 %s에서는 서브도메인을 제공하지 않습니다. %s 자체의 레코드만 쓸 수 있습니다.',
    'rec.too_many'         => '도메인당 최대 %d개 레코드입니다. 현재 %d개입니다.',
    'val.abuse_domain'     => '전체 도메인을 입력해 주세요(예: something.us.ci).',
    'val.abuse_short'      => '문제를 몇 문장으로 설명해 주세요(20자 이상).',
    'mail.abuse_subject'   => '%s 관련 악용 신고',
    'val.srv_off'          => '클라우드 서버 연동 기능이 현재 닫혀 있습니다.',
    'val.srv_no_crypto'    => '현재 PHP 환경에서는 암호화할 수 없습니다(AES-256-GCM이 없습니다). 키를 안전하게 '
        . '저장할 수 없어 연동을 거부합니다.',
    'val.srv_need'         => 'API 키와 인스턴스 ID를 모두 입력해 주세요.',
    'val.srv_need_key'     => 'API 키와 인스턴스 ID를 모두 입력해 주세요.',
    'val.srv_bad_id'       => '인스턴스 ID 형식이 올바르지 않은 것 같습니다(영문·숫자·하이픈·밑줄만).',
    'val.srv_taken'        => '이 인스턴스는 이미 등록되어 있습니다.',
    'val.srv_need_approval' => '서버를 등록하려면 먼저 승인이 필요합니다. 아래 양식에서 신청해 주세요.',
    'val.srv_key_refused'  => 'RainYun이 이 API 키를 받아들이지 않았습니다:',
    'val.srv_no_key'       => '저장된 키를 복호화할 수 없습니다(다른 사이트 키로 암호화되었을 수 있습니다). 이 '
        . '인스턴스를 다시 등록해 주세요.',
    'val.srv_power_off'    => '시작 / 중지 / 재시작이 현재 닫혀 있습니다.',
    'val.srv_reinstall_off' => '이 작업은 닫혀 있습니다: 재설치, 백업 복원, 백업 삭제는 데이터를 잃을 수 있으므로 '
        . '관리자가 켜야 합니다.',
    'val.srv_backup_off'   => '백업 기능이 현재 닫혀 있습니다.',
    'val.srv_fw_off'       => '방화벽 변경이 현재 닫혀 있습니다.',
    'val.srv_vnc_off'      => 'VNC 콘솔이 현재 닫혀 있습니다.',
    'val.srv_vnc_none'     => '서비스 제공자가 콘솔 주소를 반환하지 않았습니다.',
    'val.mail_off'         => '메일 전달이 현재 닫혀 있습니다.',
    'val.mail_zone'        => '메일 전달은 Cloudflare가 이름 해석을 제공하는 접미사에만 쓸 수 있습니다(DNSHE에는 이 '
        . '기능이 없습니다).',
    'val.mail_active'      => '메일을 받을 수 있는 것은 활성 도메인뿐입니다.',
    'val.mail_delegated'   => '이 도메인은 자체 네임서버를 쓰므로 메일은 그쪽에서 처리됩니다.',
    'val.mail_local'       => '@ 앞에는 영문·숫자·마침표·하이픈·밑줄만 쓸 수 있습니다.',
    'val.mail_loop'        => '같은 도메인으로 메일을 전달할 수 없습니다.',
    'val.mail_taken'       => '이 도메인에는 이미 주소 "%s"이(가) 있습니다.',
    'val.mail_own'         => '이 도메인 또는 전달은 귀하의 계정 것이 아닙니다.',
    'val.user_no_table'    => '데이터베이스에 사용자 콘솔용 테이블이 없습니다. 등록기관이 upgrade-users.sql을 '
        . '가져와야 합니다.',

    /* ============ 제31차: 정책 문서 · 데이터 삭제 · 알림 설정 · 가입 안내 ============ */
    'doc.heading'          => '정책 및 문서',
    'doc.intro'            => '아래 내용은 등록기관이 관리하며 모두 일반 텍스트입니다. 이 페이지, 첫 페이지, 도메인 '
        . '목록은 쿠키를 전혀 쓰지 않습니다. 쿠키를 쓰는 곳은 신청 양식, 조회 페이지, 관리 페이지와 관리자뿐이며 '
        . '목적은 크로스 사이트 요청 위조 방지 하나입니다.',
    'doc.policy'           => '등록 정책',
    'doc.documentation'    => '문서',
    'doc.quickref'         => '요약표',
    'doc.zones_offered'    => '신청 가능한 접미사',
    'doc.none_configured'  => '미설정',
    'doc.closed'           => '(접수 중지)',
    'doc.zone'             => '접미사',
    'doc.records_required' => '레코드 요건',
    'doc.records_value'    => 'apex에 최소 한 개의 <code>A</code>, <code>AAAA</code>, <code>CNAME</code> 레코드. 같은 '
        . '이름에 최대 4개 주소',
    'doc.record_types'     => '관리 가능한 레코드 종류',
    'doc.record_types_v'   => '<code>A</code>, <code>AAAA</code>, <code>CNAME</code>, <code>TXT</code>'
        . ' &mdash; 도메인당 최대 %d개',
    'doc.subnames'         => '서브도메인',
    'doc.subnames_v'       => '도메인 앞에 라벨 하나를 붙입니다(예: <code>www.myname.%s</code>). 도메인이 '
        . '활성화되면 추가할 수 있습니다',
    'doc.changes'          => '레코드 변경',
    'doc.changes_v'        => '신청자가 %s 페이지에서 제출하며 관리자 승인 후 반영됩니다',
    'doc.nameservers'      => '네임서버',
    'doc.nameservers_v'    => '이름 해석은 등록기관이 담당합니다. 허용된 접미사(Public Suffix List에 등재된 us.ci, '
        . 'de5.net 등)에서는 콘솔에서 자체 네임서버를 지정할 수 있습니다 &mdash; 즉시 적용되고 심사가 필요 없습니다',
    'doc.cost'             => '비용',
    'doc.cost_v'           => '무료, 갱신도 무료',
    'doc.example'          => '레코드 예시',
    'doc.example_intro'    => '예를 들어 도메인 <code>example.%s</code>: 자기 주소 하나, <code>www</code>는 다른 '
        . '서버, 메일은 메일 사업자에 맡긴다면 등록기관은 다음을 게시합니다:',
    'doc.docrange'         => '<code>203.0.113.x</code>와 <code>2001:db8::/32</code>는 문서용 예시 대역이며 여기서는 '
        . '표기 설명에만 씁니다. 양식은 이를 거부합니다. 실제로 서비스가 쓰는 주소와 호스트 이름을 입력해 주세요.',
    'doc.tell'             => '내용이 바뀌면 등록기관에 알려 주세요. 관리 페이지에서 직접 바꿀 수도 있습니다. 레코드 '
        . '변경은 사람이 심사하며 보통 며칠 안에 처리됩니다.',
    'doc.why'              => '언제 자체 네임서버를 쓸 수 있나요',
    'doc.why_1'            => '서브도메인을 나눠 주는 일반적인 방법은 <code>NS</code> 레코드로 위임하고 존을 직접 '
        . '관리해 임의의 레코드 종류(MX, TXT, SRV 등)를 설정하는 것입니다. 그러려면 그 접미사가 <em>Public Suffix '
        . 'List</em>(PSL)에 등재되어 있어야 합니다: 그렇지 않으면 브라우저, 인증서 발급기관, 리졸버가 이 '
        . '서브도메인을 독립된 공용 접미사로 취급하지 않아 위임한 이름에서 예기치 않은 동작이 생깁니다.',
    'doc.why_2'            => '접미사가 PSL에 등재되기 전까지는 등록기관이 승인된 각 도메인의 이름 해석을 담당하고 '
        . '저장된 레코드를 게시합니다. 그래서 <code>A</code>, <code>AAAA</code>, <code>CNAME</code>, '
        . '<code>TXT</code> 외의 종류(<code>MX</code>, <code>SRV</code> 등)는 현재 직접 설정할 수 없습니다. '
        . '필요하면 바닥글의 메일 주소로 신청 번호와 함께 연락해 주세요.',
    'close.heading'    => '내 데이터와 계정 삭제',
    'close.intro'      => '모든 데이터를 가져갈 수도 있고 계정을 완전히 삭제할 수도 있습니다. 여기에서 직접 하지 '
        . '않으면 아무것도 삭제되지 않습니다.',
    'close.export_heading' => '모든 데이터 내보내기',
    'close.export_hint' => '텍스트 파일 하나로 계정, 할당량, 각 도메인과 레코드, 티켓, 채팅, 거래와 상점 항목을 '
        . '담습니다. 잘 보관하세요 — 읽기 위한 것이며 복원용 백업 형식이 아닙니다.',
    'close.export_link' => '내 데이터 내려받기',
    'close.export_title' => '%s — 내 데이터',
    'close.export_when' => '내보낸 시각 %s',
    'close.export_account' => '계정',
    'close.export_domains' => '도메인과 레코드',
    'close.export_tickets' => '이전 티켓',
    'close.export_chat' => '채팅',
    'close.export_market' => '게시물',
    'close.export_shop' => '상점 신청',
    'close.close_heading' => '계정 삭제',
    'close.warning'    => '되돌릴 수 없습니다. 소유한 %s개 도메인이 있습니다: 정지되고 게시된 레코드는 DNS '
        . '서비스에서 제거되지만 도메인이 다른 사람에게 넘어가지는 않습니다.',
    'close.what'       => '항목',
    'close.effect'     => '결과',
    'close.account'    => '계정',
    'close.effect_account' => '사용자 이름, 메일 주소, 비밀번호가 삭제됩니다. 채팅, 게시물, 상점 신청, API 키와 '
        . '메일 전달도 함께 삭제됩니다.',
    'close.names'      => '귀하의 %s개 도메인',
    'close.effect_names' => '정지됩니다: 레코드가 상위에서 제거되고 도메인이 해석되지 않으며 보존 기간 동안 '
        . '재발급되지 않습니다.',
    'close.other'      => '남는 것',
    'close.effect_other' => '감사 로그에는 그 행위 자체가 남습니다(비밀번호는 포함되지 않습니다) — 등록기관은 무슨 '
        . '일이 있었는지 설명할 수 있어야 합니다.',
    'close.password'   => '귀하의 비밀번호',
    'close.phrase_label' => '확인 문구 입력',
    'close.phrase_hint' => '다음과 같이 정확히 입력하세요: DELETE MY ACCOUNT',
    'close.submit'     => '계정 삭제 확정',
    'close.bad_phrase' => '확인 문구를 정확히 입력해 주세요.',
    'close.bad_password' => '비밀번호가 올바르지 않습니다.',
    'close.bad_code'   => '인증 앱 코드가 현재 유효하지 않습니다.',
    'close.done'       => '계정을 삭제하고 소유한 도메인을 정지했습니다. 그동안 감사했습니다.',
    'notify.heading'   => '받을 메일',
    'notify.intro'     => '기본값은 모두 켜짐입니다. 필요 없는 것은 끌 수 있습니다. 「계정 재로그인」 관련 메일은 '
        . '항상 발송됩니다.',
    'notify.kind'      => '종류',
    'notify.examples'  => '예',
    'notify.switch_off' => '끄기',
    'notify.kind_chat' => '채팅 답변',
    'notify.example_chat' => '등록기관이 온라인 상담에서 답변했을 때',
    'notify.kind_transfer' => '이전',
    'notify.example_transfer' => '이전 코드가 왔을 때 또는 이전이 진행되었을 때',
    'notify.kind_market' => '거래',
    'notify.example_market' => '게시물이 승인·거부되었을 때, 구매 신청이 왔을 때',
    'notify.kind_shop' => '할당량 상점',
    'notify.example_shop' => '소개가 승인·거부되었을 때',
    'notify.kind_domain' => '도메인 관련',
    'notify.example_domain' => '신청 심사 결과 또는 제출한 변경의 결과',
    'notify.kind_account' => '계정 관련',
    'notify.example_account' => '비밀번호 재설정, 메일 주소 확인',
    'notify.always'    => '계정 관련 메일은 끌 수 없습니다: 다시 로그인할 수 있는지에 관한 것이기 때문입니다. 직접 '
        . '한 조작의 확인 메일도 항상 발송됩니다 — 그 조작의 기록이기 때문입니다.',
    'notify.save'      => '설정 저장',
    'notify.saved'     => '저장했습니다.',
    'notify.no_table'  => '데이터베이스에 이 페이지에 필요한 항목이 없습니다. upgrade-mailpref.sql을 가져와 주세요'
        . '(자체 점검 페이지에서 실행할 수 있습니다).',
    'notify.scope'     => '이 설정은 이 계정의 메일 주소에만 적용됩니다. 「계정이 없는 사람」(신청자, 게스트)에게 '
        . '가는 메일에는 영향을 주지 않습니다.',
    'account.register_intro' => '계정을 만들면 소유한 모든 도메인이 한곳에 모입니다: 상태 확인, 레코드 수정, 새 '
        . '도메인 신청이 가능하고 신청 번호를 찾아다닐 필요가 없습니다. 계정은 가입 메일 주소로 도메인과 연결되며, '
        . '같은 메일 주소로 신청한 도메인은 모두 여기에 표시됩니다.',
    'account.invite_code'   => '초대 코드(선택)',
    'account.invite_code_hint' => '친구의 초대로 왔다면 그 초대 코드를 여기에 입력할 수 있습니다. 초대 인원은 그 '
        . '사람의 실적에 더해집니다. 입력하지 않고 그 사람의 초대 링크로 가입해도 됩니다.',
    'account.registered_title' => '계정을 만들었습니다',
    'account.registered_body' => '확인 링크를 %s로 보냈습니다. 메일의 링크를 열어 계정을 활성화하면 로그인할 수 '
        . '있습니다.',
    'account.registered_next' => '활성화하셨나요? 여기에서 로그인: %s.',
    'account.registered_flash' => '계정을 만들었습니다. 먼저 메일 주소 확인을 끝내 주세요.',
    'account.verify_mail_failed' => '확인 메일을 보내지 못했습니다. 로그인 페이지의 「확인 메일 다시 보내기」를 쓰거나 '
        . '등록기관에 연락해 주세요.',
    'account.email_change_warning' => '콘솔에는 현재 %s로 신청한 %d개 도메인이 표시됩니다. 메일 주소를 바꾸면 목록도 '
        . '새 주소를 따릅니다. 이전 주소로 신청한 도메인은 신청 번호로 관리할 수 있습니다.',
];

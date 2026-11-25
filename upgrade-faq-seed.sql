-- freedns.my - ten starter questions, English and Chinese.
-- Safe to run twice: an entry whose English question already exists is skipped.

INSERT INTO faq_items (category_id, question_en, question_zh, answer_en, answer_zh, sort_order, is_public, created_at)
SELECT c.id, v.qen, v.qzh, v.aen, v.azh, v.so, 1, NOW()
FROM (
  SELECT 'start' AS slug, 'How do I request a name?' AS qen, '怎么申请一个域名？' AS qzh,
         'Pick a suffix on the request page, type one label (without dots), choose your records and submit. You get a request code you can use to follow it.' AS aen,
         '在申请页选一个后缀，填一个标签（不要带点），填好记录后提交。提交后会给一个申请编号，可以用它查询进度。' AS azh, 10 AS so
  UNION ALL SELECT 'start', 'How long does approval take?', '审核要多久？',
         'Usually within a day. Requests that point at a working website are approved faster; ones with no reachable record may be asked to fix it first.',
         '一般一天内。指向可用网站的申请会更快；如果记录指不到任何东西，可能会先让你改。', 20
  UNION ALL SELECT 'start', 'Is it really free?', '真的免费吗？',
         'Yes. There is no fee, no card and no trial period. The suffixes are given away for free, one name per person per suffix unless an operator grants more.',
         '真的。没有费用、不用信用卡、也没有试用期。后缀免费提供，每个后缀默认每人一个，操作员可以额外批准。', 30
  UNION ALL SELECT 'dns', 'How do I add or change records?', '怎么添加或修改解析？',
         'Open the manage page for your name and edit the records there. At least one A, AAAA or CNAME is needed for the name itself (@).',
         '打开该域名的管理页，在那里改记录。域名本身（@）至少要有一条 A、AAAA 或 CNAME。', 10
  UNION ALL SELECT 'dns', 'How long do changes take to work?', '改了要多久生效？',
         'Changes are published to the DNS service within a minute. Caches elsewhere may hold the old answer until its TTL runs out.',
         '一分钟内会推送到 DNS 服务商。别处的缓存可能还留着旧值，直到 TTL 过期。', 20
  UNION ALL SELECT 'dns', 'Why does my name not resolve?', '为什么不解析？',
         'Check that the name is approved (status active) and that it has at least one A, AAAA or CNAME on @, and that the target host is reachable.',
         '确认域名已审核通过（状态 active）、@ 上至少有一条 A/AAAA/CNAME，并且目标主机是通的。', 30
  UNION ALL SELECT 'account', 'I lost the e-mail I registered with.', '我注册用的邮箱没了。',
         'Write to us from any address and include the request code. We can move the name to a new address once we are satisfied it is yours.',
         '用任意邮箱写信给我们，带上申请编号。确认归属后可以把域名转到新地址。', 10
  UNION ALL SELECT 'account', 'How do I delete a name?', '怎么删除域名？',
         'Open the manage page and choose delete. The records are removed from the DNS service; the free subdomain itself cannot be released once registered.',
         '打开管理页选择删除。记录会从 DNS 服务商移除；免费子域名一旦注册就无法释放。', 20
  UNION ALL SELECT 'mail', 'Can I receive mail on my name?', '能用我的域名收信吗？',
         'Mail forwarding exists for suffixes that run on Cloudflare e-mail routing. It forwards only; it does not send.',
         '只有走 Cloudflare 邮件路由的后缀支持转发。只能收信转发，不能发信。', 10
  UNION ALL SELECT 'mail', 'Why was my forwarding refused?', '为什么我的转发被拒了？',
         'Free e-mail routing needs the destination address confirmed by its provider, and only addresses on your own name can be forwarded.',
         '免费邮件路由需要目标邮箱在服务商那边点确认；而且只能转发你自己域名下的地址。', 20
) AS v
JOIN faq_categories c ON c.slug = v.slug
WHERE NOT EXISTS (SELECT 1 FROM faq_items x WHERE x.question_en = v.qen);
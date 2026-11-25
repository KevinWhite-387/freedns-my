<?php
declare(strict_types=1);

/**
 * freedns.my - open platform: the documentation page (English only, on purpose).
 *
 * The audience here is a developer with an editor open, not a visitor: every claim on this page is meant to be
 * copy-pasteable and to be true of the code that answers the request. The Chinese console pages link here.
 */

require __DIR__ . '/inc/bootstrap.php';

page_header('Open platform for developers');
?>
<p>
  Third parties can sign a user in with <strong>Sign in with freedns.my</strong> and then read - or, with the write
  scope, change - what that user explicitly allowed. It is plain OAuth 2.0 (authorisation code), so any OAuth
  client library works. Start by submitting an application on
  <a href="<?= h(url('account/apps.php')) ?>">/account/apps.php</a>: a human approves it, and then you get a
  <code>client_id</code> (and, unless you registered a public client, a <code>client_secret</code> shown once).
</p>

<h2>Endpoints</h2>
<table>
  <tr><th>Endpoint</th><th>What it does</th></tr>
  <tr><td><code>GET  /oauth/authorize.php</code></td><td>The consent screen. Send the user here.</td></tr>
  <tr><td><code>POST /oauth/token.php</code></td><td>Exchange a code for tokens; refresh an expired access token.</td></tr>
  <tr><td><code>GET  /oauth/userinfo.php</code></td><td>Who the token belongs to. Scope: <code>profile</code>.</td></tr>
  <tr><td><code>GET  /open/v1/domains.php</code></td><td>The names this account holds. Scope: <code>domains</code>.</td></tr>
  <tr><td><code>GET  /open/v1/records.php?name=…</code></td><td>The DNS records of one name. Scope: <code>records</code>.</td></tr>
  <tr><td><code>POST /open/v1/records.php?name=…</code></td><td>Add a record. Scope: <code>records.write</code>.<br>
      <code>PUT</code> changes one by <code>index=</code>, <code>DELETE</code> removes one.</td></tr>
  <tr><td><code>GET  /open/v1/mailboxes.php</code></td><td>Mailboxes and forwarding of this account. Scope: <code>mailboxes</code>.</td></tr>
  <tr><td><code>GET  /open/v1/applications.php</code></td><td>Requests, quota and counts. Scope: <code>applications</code>.</td></tr>
</table>
<p class="hint">Every answer is JSON. Errors follow RFC 6749: <code>{"error":"…","error_description":"…"}</code>.
  A request with a missing, expired or revoked token is <code>401</code>; a token without the scope the call needs
  is <code>403 insufficient_scope</code>; a record the registry refuses is <code>422</code> with the same sentence
  the web console would have shown.</p>

<h2>Scopes</h2>
<table>
  <tr><th>Scope</th><th>Grants</th></tr>
  <?php foreach (op_scopes() as $key => $label): ?>
  <tr><td><code><?= h($key) ?></code></td><td><?= h($label[0]) ?></td></tr>
  <?php endforeach; ?>
</table>
<p class="hint">The user sees these sentences on the consent screen and may refuse any application. An application
can only ask for scopes the operator approved for it; anything else is refused with <code>invalid_scope</code>.</p>

<h2>Quick start (curl)</h2>
<pre style="white-space:pre-wrap"># 0) PKCE pair. Keep the verifier; send the challenge. (openssl, or any library)
VERIFIER=$(openssl rand -hex 32)
CHALLENGE=$(printf '%s' "$VERIFIER" | openssl dgst -binary -sha256 | openssl base64 | tr '+/' '-_' | tr -d '=')

# 1) Send the user to the consent screen (in a browser, while they are signed in):
#      <?= h(url('oauth/authorize.php')) ?>?response_type=code
#        &client_id=YOUR_CLIENT_ID
#        &redirect_uri=https%3A%2F%2Fyour.app%2Fcallback
#        &scope=profile%20domains%20records
#        &state=RANDOM
#        &code_challenge=$CHALLENGE&code_challenge_method=S256
#
#    They press Allow, and your callback receives: ?code=…&state=RANDOM

# 2) Exchange the code for tokens (10 minutes, one use only):
curl -s -X POST <?= h(url('oauth/token.php')) ?> \
  -d grant_type=authorization_code \
  -d client_id=YOUR_CLIENT_ID -d client_secret=YOUR_SECRET \
  -d redirect_uri=https://your.app/callback \
  -d code=THE_CODE -d code_verifier="$VERIFIER"
# => {"access_token":"…","token_type":"Bearer","expires_in":3600,"refresh_token":"…","scope":"profile domains records"}

# 3) Use it:
curl -s -H "Authorization: Bearer $ACCESS" <?= h(url('oauth/userinfo.php')) ?>
curl -s -H "Authorization: Bearer $ACCESS" <?= h(url('open/v1/domains.php')) ?>
curl -s -H "Authorization: Bearer $ACCESS" "<?= h(url('open/v1/records.php')) ?>?name=abc.us.ci"

# 4) Change a record (needs records.write):
curl -s -X POST -H "Authorization: Bearer $ACCESS" \
  -d "sub=www" -d "type=A" -d "value=1.2.3.4" \
  "<?= h(url('open/v1/records.php')) ?>?name=abc.us.ci"

# 5) When the access token expires, refresh it. The old refresh token dies at this moment:
curl -s -X POST <?= h(url('oauth/token.php')) ?> \
  -d grant_type=refresh_token -d client_id=YOUR_CLIENT_ID -d client_secret=YOUR_SECRET \
  -d refresh_token=$REFRESH</pre>

<h2>PHP, without a library</h2>
<pre style="white-space:pre-wrap">$ch = curl_init('<?= h(url('open/v1/domains.php')) ?>');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_HTTPHEADER     =&gt; ['Authorization: Bearer ' . $accessToken],
]);
$answer = json_decode((string)curl_exec($ch), true);
if (($answer['ok'] ?? false) !== true) {
    // $answer['error'] is machine readable, $answer['message'] or ['error_description'] is for a human
}</pre>

<h2>Rules that will not change</h2>
<ul>
  <li>A redirect address is matched <strong>character for character</strong>; there are no wildcards, and a
      mismatch is answered on this site instead of redirecting (that is deliberate: it stops a stolen client_id
      from becoming an open redirect).</li>
  <li>An authorisation code is bound to one application, one redirect address and one PKCE challenge, and it dies
      on first use.</li>
  <li>Access tokens live one hour, refresh tokens thirty days and <strong>rotate</strong>: refreshing revokes the
      refresh token you sent and returns a new pair.</li>
  <li>Writing goes through the same validation a human would meet in the console - record types, values, the
      one-CNAME-per-name rule, the per-name ceiling, and publishing. There is no side door.</li>
  <li>A revoked application, a suspended account or a disconnected user stops answering immediately, even with a
      token that has not expired yet.</li>
  <li>Only the hash of a secret or a token is stored. Losing one means regenerating it, not reading it back.</li>
</ul>

<h2>Everything else</h2>
<p>
  Submit and manage applications on <a href="<?= h(url('account/apps.php')) ?>">/account/apps.php</a> (holders see
  who is connected to them there, and can disconnect). The API for a single key that acts as the account itself
  lives on <a href="<?= h(url('account/api.php')) ?>">/account/api.php</a>. The open platform is switched off as a
  whole while the tables are missing; a call then answers <code>503 temporarily_unavailable</code>.
</p>
<?php
page_footer();
